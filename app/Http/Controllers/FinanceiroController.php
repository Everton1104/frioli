<?php

namespace App\Http\Controllers;

use App\Models\AgendamentoModel;
use App\Models\OrdemPagamento;
use App\Models\PlanoMensalServico;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Financeiro de repasses semanais aos barbeiros (somente admin, somente leitura).
 *
 * Mostra, por barbeiro, os cortes da semana que ocorreram (status confirmado ou
 * pago_aguardando, horário já passado, excluindo faltas marcadas) e calcula o
 * repasse = valor do atendimento × % de repasse do serviço.
 *
 * Visita de plano mensal: usa o preço com desconto do pacote (Σ valor_unitario
 * dos itens consumidos na visita). Demais atendimentos: preço de tabela do
 * serviço (servicos.valor).
 */
class FinanceiroController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->adm, 403);

        // Domingo da semana solicitada (default: semana atual).
        $inicio = $this->resolverInicio($request->input('inicio'));
        $fim    = $inicio->copy()->addDays(6)->endOfDay();

        $semanaAnterior = $inicio->copy()->subDays(7)->format('Y-m-d');
        $semanaProxima  = $inicio->copy()->addDays(7)->format('Y-m-d');
        $hoje           = Carbon::now()->startOfDay();

        $barbeiros = User::barbeiros()->get();
        $totalGeralFaturado = 0.0;
        $totalGeralRepasse  = 0.0;
        $algumSemPercentual = false;

        foreach ($barbeiros as $barbeiro) {
            $atendimentos = AgendamentoModel::where('funcionario_id', $barbeiro->id)
                ->whereBetween('data_inicio', [$inicio, $fim])
                ->where('data_inicio', '<=', now())
                ->whereIn('status', [AgendamentoModel::STATUS_CONFIRMADO, AgendamentoModel::STATUS_PAGO_AGUARDANDO])
                ->where(function ($q) {
                    // Exclui faltas marcadas (compareceu=0); NULL conta como ocorrido.
                    $q->where('compareceu', 1)->orWhereNull('compareceu');
                })
                ->with(['servico', 'user', 'planoMensal.itens'])
                ->orderBy('data_inicio')
                ->get();

            $linhas = [];
            $totalFaturado = 0.0;
            $totalRepasse  = 0.0;

            foreach ($atendimentos as $ag) {
                [$base, $percentual, $repasse, $indeterminado] = $this->calcularRepasse($ag);

                if ($percentual <= 0) {
                    $algumSemPercentual = true;
                }

                $linhas[] = [
                    'id'           => $ag->id,
                    'data'         => Carbon::parse($ag->data_inicio),
                    'cliente'      => $ag->user?->name ?? '—',
                    'servico'      => $ag->servico?->descricao ?? '—',
                    'e_plano'      => (bool) $ag->plano_mensal_id,
                    // compareceu: null=pendente (entra como ocorrido), true=confirmado.
                    // (false/não-compareceu é excluído pela query e não aparece aqui.)
                    'compareceu'   => $ag->compareceu,
                    'base'         => $base,
                    'percentual'   => $percentual,
                    'repasse'      => $repasse,
                    'indeterminado'=> $indeterminado,
                ];
                $totalFaturado += $base;
                $totalRepasse  += $repasse;
            }

            $barbeiro->financas = [
                'linhas'        => $linhas,
                'totalFaturado' => round($totalFaturado, 2),
                'totalRepasse'  => round($totalRepasse, 2),
                'quantidade'    => count($linhas),
            ];

            $totalGeralFaturado += $totalFaturado;
            $totalGeralRepasse  += $totalRepasse;
        }

        // Ordens de pagamento (todas — avulsas e de plano mensal) e clientes para o
        // modal "Nova ordem". Migrou do dashboard para não poluir o painel inicial.
        $ordensPagamento = OrdemPagamento::with(['user', 'criador'])->latest()->limit(50)->get();
        $clientes = User::where([['excluido', 0], ['func', 0], ['adm', 0]])->orderBy('name')->get();

        return view('financeiro.index', [
            'barbeiros'           => $barbeiros,
            'inicio'              => $inicio,
            'fim'                 => $fim,
            'semanaAnterior'      => $semanaAnterior,
            'semanaProxima'       => $semanaProxima,
            'eSemanaAtual'        => $inicio->isSameDay(Carbon::now()->startOfDay()->subDays(Carbon::now()->dayOfWeek)),
            'totalGeralFaturado'  => round($totalGeralFaturado, 2),
            'totalGeralRepasse'   => round($totalGeralRepasse, 2),
            'algumSemPercentual'  => $algumSemPercentual,
            'ordensPagamento'     => $ordensPagamento,
            'clientes'            => $clientes,
        ]);
    }

    /**
     * Resolve o domingo da semana a exibir a partir do parâmetro ?inicio=YYYY-MM-DD.
     * Se a data informada não for um domingo, recua até o domingo daquela semana.
     */
    private function resolverInicio(?string $inicio): Carbon
    {
        $hoje = Carbon::now();
        $data = $inicio ? Carbon::createFromFormat('Y-m-d', $inicio, $hoje->timezone) : null;

        // Data inválida → volta para a semana atual.
        if (!$data || $data->format('Y-m-d') !== $inicio) {
            return $hoje->copy()->subDays($hoje->dayOfWeek)->startOfDay();
        }

        return $data->startOfDay();
    }

    /**
     * Calcula [base, percentual, repasse, indeterminado] para um atendimento.
     * - Avulso: base = servico.valor; % = servico.repasse_percent.
     * - Plano mensal: base = Σ valor_unitario dos itens consumidos (consumo_plano);
     *   fallback pela distribuição da visita; % = servico.repasse_percent (o combo).
     */
    private function calcularRepasse(AgendamentoModel $ag): array
    {
        $percentual = $ag->servico?->repassePercent() ?? 0.0;

        // Atendimento de plano mensal — valor com desconto da visita consumida.
        if ($ag->plano_mensal_id && $ag->planoMensal) {
            $itens = $ag->planoMensal->itens;
            $base = 0.0;
            $indeterminado = false;

            $consumidos = is_array($ag->consumo_plano) ? $ag->consumo_plano : [];

            if (!empty($consumidos)) {
                // Itens efetivamente consumidos nesta visita (IDs em consumo_plano).
                $base = $itens->filter(fn (PlanoMensalServico $i) => in_array($i->id, $consumidos))
                    ->sum(fn (PlanoMensalServico $i) => (float) $i->valor_unitario);
            } elseif (!empty($ag->planoMensal->distribuicao) && $ag->plano_ordem) {
                // Fallback: serviço da visita pela posição na distribuição.
                $ordem = $ag->plano_ordem - 1;
                $distribuicao = $ag->planoMensal->distribuicao;
                $servicosDaVisita = $distribuicao[$ordem] ?? null;
                if (is_array($servicosDaVisita)) {
                    $base = $itens->filter(fn (PlanoMensalServico $i) => in_array((int) $i->servico_id, array_map('intval', $servicosDaVisita)))
                        ->sum(fn (PlanoMensalServico $i) => (float) $i->valor_unitario);
                }
            }

            // Sem consumo e sem distribuição → não foi possível determinar.
            if ($base == 0.0 && empty($consumidos) && empty($ag->planoMensal->distribuicao)) {
                $indeterminado = true;
            }

            $repasse = round($base * ($percentual / 100), 2);
            return [$base, $percentual, $repasse, $indeterminado];
        }

        // Avulso (online, pagar-no-local ou pacote pré-pago): preço de tabela.
        $base = (float) ($ag->servico?->valor ?? 0);
        $repasse = round($base * ($percentual / 100), 2);
        return [$base, $percentual, $repasse, false];
    }
}
