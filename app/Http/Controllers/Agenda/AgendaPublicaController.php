<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Models\AgendamentoModel;
use App\Models\AssinaturaMensal;
use App\Models\CreditoServico;
use App\Models\DisponibilidadeModel;
use App\Models\OrdemPagamento;
use App\Models\PageContent;
use App\Models\PlanoMensal;
use App\Models\ServicosModel;
use App\Models\User;
use App\Services\PlanoMensalService;
use App\Services\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Agendamento público (booking self-service). O cliente autenticado (cadastro por
 * OTP) escolhe serviço + dia + horário aberto e reserva: cria um agendamento
 * "aguardando_pagamento" (LOCK do slot) vinculado a uma ordem de pagamento, e segue
 * para o checkout. A validação de slot/conflito espelha AgendaController@store (para
 * cliente, não-staff). Em caso de erro volta com mensagem.
 */
class AgendaPublicaController extends Controller
{
    public function index(): View
    {
        $user = request()->user();

        $servicos = ServicosModel::where('excluido', 0)
            ->where('status', 1)
            ->where('visivel_cliente', 1)
            ->where('recorrente', 0)
            ->whereNotNull('valor')
            ->where('valor', '>', 0)
            ->orderBy('descricao')
            ->get();

        $barbeiros = User::barbeiros()->get();

        // Modo pacote avulso (?pacote=ID do CreditoServico): veio do botão
        // "Agendar" do painel — o serviço é o do pacote (já pago), sem cobrança.
        $pacote = null;
        if ($user && request('pacote')) {
            $pacote = CreditoServico::with('servico')
                ->where('user_id', $user->id)
                ->find(request('pacote'));
            // Só vale com saldo e não expirado; senão cai no fluxo normal.
            if ($pacote && ($pacote->restantes() <= 0 || $pacote->expirado())) {
                $pacote = null;
            }
        }

        return view('agendar.index', [
            'servicos'         => $servicos,
            'barbeiros'        => $barbeiros,
            'pacote'           => $pacote,
            'penalizado'       => (bool) ($user?->isPenalizado() ?? false),
            'whatsappAdmin'    => PageContent::get('contato', 'whatsapp_numero', '5511988245815'),
            'recaptchaSiteKey' => app(RecaptchaService::class)->siteKey(),
        ]);
    }

    public function reservar(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Cliente penalizado (no-show anterior): o pagamento ONLINE segue normal
        // (abaixo); o pagamento NO LOCAL vira "intenção de agendamento" pendente de
        // aprovação do barbeiro (não bloqueia totalmente o cliente).

        $request->validate(
            [
                'servico_id'     => ['required', 'integer'],
                'funcionario_id' => ['required', 'integer'],
                'data_inicio'    => ['required', 'date', 'after:now'],
            ],
            [
                'servico_id.required'     => 'Selecione um serviço.',
                'funcionario_id.required' => 'Selecione um barbeiro.',
                'data_inicio.required'    => 'Selecione um dia e horário.',
                'data_inicio.after'       => 'O horário precisa ser futuro.',
            ]
        );

        // Anti-bot: reCAPTCHA (exige só se configurado no .env).
        if (!app(RecaptchaService::class)->verificado($request->input('g-recaptcha-response'))) {
            return back()->withErrors(['recaptcha' => 'Confirme que você não é um robô.'])->withInput();
        }

        // Resolução do serviço: fluxo normal exige serviço visível com valor online;
        // no modo pacote avulso vale o serviço do PRÓPRIO pacote (já pago) — pode não
        // ter valor online (vendido só em pacote), então o filtro é relaxado.
        $credito = null;
        if ($request->filled('pacote_id')) {
            $credito = CreditoServico::with('servico')
                ->where('user_id', $user->id)
                ->find($request->pacote_id);
            $servico = ($credito && (int) $credito->servico_id === (int) $request->servico_id
                && !$credito->expirado() && $credito->restantes() > 0
                && $credito->servico && $credito->servico->status == 1 && $credito->servico->excluido == 0)
                ? $credito->servico
                : null;
        } else {
            $servico = $this->servicoDisponivel($request->servico_id);
        }

        if (!$servico) {
            $erro = $request->filled('pacote_id')
                ? ['pacote' => 'Este pacote não está mais disponível para agendamento.']
                : ['servico_id' => 'Serviço indisponível.'];
            return back()->withErrors($erro)->withInput();
        }

        // Barbeiro precisa ser agendável (func=1, ativo).
        $funcionario = User::barbeiros()->find($request->funcionario_id);
        if (!$funcionario) {
            return back()->withErrors(['funcionario_id' => 'Barbeiro inválido.'])->withInput();
        }

        $inicio    = Carbon::parse($request->data_inicio);
        $duracao   = $this->timeToMinutes($servico->duracao);
        $intervalo = ServicosModel::intervaloMinimoCliente(); // acompanha o menor serviço ativo
        $fim       = $inicio->copy()->addMinutes($duracao);

        if ($erro = $this->validarSlotCliente($servico, $inicio, $fim, $duracao, $intervalo, $funcionario->id)) {
            return back()->withErrors(['data_inicio' => $erro])->withInput();
        }

        // Pacote avulso: o serviço já está pago (unidade do pacote). Ocupa o slot
        // como visita pré-paga (mesmo status de plano mensal) e DESCONTA a unidade
        // via credito_servico_id — sem ordem de pagamento.
        if ($credito) {
            AgendamentoModel::create([
                'user_id'            => $user->id,
                'servico_id'         => $servico->id,
                'funcionario_id'     => $funcionario->id,
                'data_inicio'        => $inicio,
                'data_fim'           => $fim,
                'status'             => AgendamentoModel::STATUS_PAGO_AGUARDANDO,
                'credito_servico_id' => $credito->id,
                'consome_credito'    => true,
            ]);

            return redirect()->route('dashboard')->with(
                'msg',
                'Agendamento feito usando seu pacote (' . $servico->descricao . ')! Resta(m) ' . $credito->restantes() . ' unidade(s).'
            );
        }

        // Pagar no local: sem ordem de pagamento. Ocupa o slot e fica aguardando o staff
        // confirmar (status pago_aguardando + flag pagar_no_local).
        if ($request->boolean('pagar_no_local')) {
            // Cliente penalizado: o pedido vira "intenção de agendamento" — NÃO ocupa
            // slot e fica pendente de aprovação do barbeiro escolhido. Ao aprovar, a
            // penalidade é removida. Pagamento online (pré-pago) segue normal abaixo.
            if ($user->isPenalizado()) {
                $intencao = AgendamentoModel::create([
                    'user_id'        => $user->id,
                    'servico_id'     => $servico->id,
                    'funcionario_id' => $funcionario->id,
                    'data_inicio'    => $inicio,
                    'data_fim'       => $fim,
                    'status'         => AgendamentoModel::STATUS_INTENCAO,
                    'pagar_no_local' => true,
                ]);
                $this->avisarIntencao($intencao, $funcionario);

                return redirect()->route('dashboard')->with('msg', 'Seu pedido foi enviado e está aguardando aprovação do barbeiro. Você será avisado quando ele responder.');
            }

            AgendamentoModel::create([
                'user_id'        => $user->id,
                'servico_id'     => $servico->id,
                'funcionario_id' => $funcionario->id,
                'data_inicio'    => $inicio,
                'data_fim'       => $fim,
                'status'         => AgendamentoModel::STATUS_PAGO_AGUARDANDO,
                'pagar_no_local' => true,
            ]);

            return redirect()->route('dashboard')->with('msg', 'Agendamento realizado! O pagamento é no local, no dia e horário marcados.');
        }

        // Fluxo padrão: cria agendamento pendente (LOCK do slot) + ordem de pagamento vinculada.
        $ordem = DB::transaction(function () use ($user, $servico, $funcionario, $inicio, $fim) {
            $ag = AgendamentoModel::create([
                'user_id'        => $user->id,
                'servico_id'     => $servico->id,
                'funcionario_id' => $funcionario->id,
                'data_inicio'    => $inicio,
                'data_fim'       => $fim,
                'status'         => AgendamentoModel::STATUS_AGUARDANDO_PAGAMENTO,
            ]);

            $o = OrdemPagamento::create([
                'user_id'            => $user->id,
                'criado_por'         => $user->id,
                'agendamento_id'     => $ag->id,
                'valor'              => $servico->valor,
                'descricao'          => $servico->descricao,
                'max_parcelas'       => OrdemPagamento::MAX_PARCELAS,
                'status'             => 'aberta',
                'external_reference' => (string) Str::uuid(),
            ]);
            $o->eventos()->create(['status' => 'aberta', 'origem' => 'checkout']);
            return $o;
        });

        return redirect()->route('pagamentos.pagar', $ordem);
    }

    /**
     * Avisa o número central de atendimento sobre uma nova intenção de agendamento de
     * cliente penalizado (o barbeiro vê e decide no painel). Best-effort: falha não
     * impede o registro da intenção.
     */
    private function avisarIntencao(AgendamentoModel $ag, User $funcionario): void
    {
        $numero  = env('WHATSAPP_ATENDIMENTO_NUMBER', env('WHATSAPP_ADMIN_NUMBER'));
        $phoneId = env('PHONE_NUMBER_ID');
        if (!$numero || !$phoneId || !$ag->user) {
            return;
        }
        $data = $ag->data_inicio->format('d/m/Y H:i');
        $msg  = "Nova intenção de agendamento (cliente penalizado): {$ag->user->name} solicitou "
              . ($ag->servico->descricao ?? 'um serviço') . " com {$funcionario->name} em {$data} (pagar no local). Analise no painel.";
        try {
            \App\Http\Controllers\WhatsappController::enviarMsg($phoneId, $numero, $msg);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('single')->warning('[BOOKING] falha ao avisar intenção de agendamento', ['msg' => $e->getMessage()]);
        }
    }

    /** Cálculo autoritativo de um combo multi-serviço (unidades + valor + itens). */
    public function mensalCalcular(Request $request)
    {
        $dados = $request->validate([
            'dia_semana'        => ['required', 'integer', 'between:0,6'],
            'mes'               => ['required', 'date'],
            'cap'               => ['nullable', 'integer', 'between:1,6'],
            'itens_servico'     => ['nullable', 'array'],
            'itens_servico.*'   => ['integer'],
            'itens_qtd'         => ['nullable', 'array'],
            'itens_qtd.*'       => ['nullable', 'integer', 'min:0'],
            'distribuicao'      => ['nullable', 'array'],
            'distribuicao.*'    => ['array'],
            'distribuicao.*.*'  => ['integer'],
            'valores_extra'     => ['nullable', 'array'],
            'valores_extra.*'   => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $mes = Carbon::parse($dados['mes'])->startOfMonth();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Mês inválido'], 422);
        }

        $distribuicao = !empty($dados['distribuicao']) ? collect($dados['distribuicao']) : null;
        $valoresExtra = $dados['valores_extra'] ?? [];

        if ($distribuicao && $distribuicao->isNotEmpty()) {
            $calc = PlanoMensal::calcularComboDistribuido($distribuicao, $mes, (int) $dados['dia_semana'], $valoresExtra);
        } else {
            $itens = collect();
            foreach (($dados['itens_servico'] ?? []) as $i => $sid) {
                $qtd = (int) ($dados['itens_qtd'][$i] ?? 0);
                if ($sid && $qtd > 0) {
                    $itens->push(['servico_id' => (int) $sid, 'quantidade' => $qtd]);
                }
            }
            if ($itens->isEmpty()) {
                return response()->json(['error' => 'Inclua ao menos 1 serviço no combo.'], 422);
            }
            $calc = PlanoMensal::calcularCombo($itens, $mes, (int) $dados['dia_semana'], isset($dados['cap']) ? (int) $dados['cap'] : null);
        }

        $calc['data_inicio'] = $calc['data_inicio'] ? $calc['data_inicio']->format('d/m/Y') : null;
        $calc['data_fim']    = $calc['data_fim'] ? $calc['data_fim']->format('d/m/Y') : null;

        return response()->json($calc);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function servicoDisponivel(int $id): ?ServicosModel
    {
        return ServicosModel::where('excluido', 0)
            ->where('status', 1)
            ->where('visivel_cliente', 1)
            ->where('recorrente', 0)
            ->where('valor', '>', 0)
            ->find($id);
    }

    private function servicoMensalDisponivel(int $id): ?ServicosModel
    {
        return ServicosModel::where('excluido', 0)
            ->where('status', 1)
            ->where('visivel_cliente', 1)
            ->where('recorrente', 1)
            ->where('valor', '>', 0)
            ->find($id);
    }

    /**
     * Validação de slot para cliente (espelha AgendaController@store). Retorna uma
     * mensagem de erro ou null se ok. Conflito e disponibilidade são POR BARBEIRO.
     */
    private function validarSlotCliente(ServicosModel $servico, Carbon $inicio, Carbon $fim, int $duracao, int $intervalo, ?int $funcionarioId = null): ?string
    {
        if ($inicio < Carbon::now()->addHours(2)) {
            return 'Só é possível agendar com no mínimo 2h de antecedência.';
        }

        $slots = DisponibilidadeModel::where('data', $inicio->toDateString())
            ->when($funcionarioId, fn($q) => $q->where('funcionario_id', $funcionarioId))
            ->pluck('hora')
            ->map(fn($h) => substr($h, 0, 5))
            ->toArray();

        // Cliente só enxerga slots no intervalo do menor serviço ativo (múltiplos dele).
        $slots = array_values(array_filter(
            $slots,
            fn($h) => (((int) substr($h, 0, 2)) * 60 + (int) substr($h, 3, 2)) % $intervalo === 0
        ));

        if (!in_array($inicio->format('H:i'), $slots)) {
            return 'Este horário não está disponível.';
        }

        $slotsNecessarios = (int) ceil($duracao / $intervalo);
        for ($i = 1; $i < $slotsNecessarios; $i++) {
            $check = $inicio->copy()->addMinutes($intervalo * $i)->format('H:i');
            if (!in_array($check, $slots)) {
                return 'O serviço ultrapassa os horários disponíveis.';
            }
        }

        // Conflito é por barbeiro: só agendamentos do mesmo barbeiro ocupam o slot.
        $conflito = AgendamentoModel::where('data_inicio', '<', $fim)
            ->where('data_fim', '>', $inicio)
            ->when($funcionarioId, fn($q) => $q->where('funcionario_id', $funcionarioId))
            ->whereIn('status', AgendamentoModel::OCUPANTES)
            ->exists();

        if ($conflito) {
            return 'Alguém acabou de reservar esse horário. Tente outro.';
        }

        return null;
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');
        return ((int) $h) * 60 + (int) $m;
    }

}
