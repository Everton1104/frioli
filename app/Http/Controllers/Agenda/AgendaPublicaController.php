<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Models\AgendamentoModel;
use App\Models\DisponibilidadeModel;
use App\Models\OrdemPagamento;
use App\Models\PageContent;
use App\Models\PlanoMensal;
use App\Models\ServicosModel;
use App\Models\User;
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
        $user     = request()->user();
        $indicado = (bool) ($user?->isIndicado() ?? false);

        $servicos = ServicosModel::where('excluido', 0)
            ->where('status', 1)
            ->where('visivel_cliente', 1)
            ->where('recorrente', 0)
            ->whereNotNull('valor')
            ->where('valor', '>', 0)
            ->orderBy('descricao')
            ->get();

        // Planos mensais (horário fixo) só aparecem para clientes "indicados" pelo
        // staff — liberados a comprar/renovar pelo site.
        $servicosMensais = $indicado
            ? ServicosModel::where('excluido', 0)
                ->where('status', 1)
                ->where('visivel_cliente', 1)
                ->where('recorrente', 1)
                ->where('valor', '>', 0)
                ->orderBy('descricao')
                ->get()
            : collect();

        $barbeiros = User::barbeiros()->get();

        return view('agendar.index', [
            'servicos'         => $servicos,
            'servicosMensais'  => $servicosMensais,
            'barbeiros'        => $barbeiros,
            'penalizado'       => (bool) ($user?->isPenalizado() ?? false),
            'indicado'         => $indicado,
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

        $servico = $this->servicoDisponivel($request->servico_id);

        if (!$servico) {
            return back()->withErrors(['servico_id' => 'Serviço indisponível.'])->withInput();
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

    /** Cálculo autoritativo: unidades (4 ou 5) + valor total do plano mensal. */
    public function mensalCalcular(Request $request)
    {
        $servico = $this->servicoMensalDisponivel($request->servico_id);
        if (!$servico) {
            return response()->json(['error' => 'Serviço inválido'], 422);
        }
        $dia = (int) $request->dia_semana;
        if ($dia < 0 || $dia > 6) {
            return response()->json(['error' => 'Dia inválido'], 422);
        }
        try {
            $mes = Carbon::parse($request->mes)->startOfMonth();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Mês inválido'], 422);
        }

        return response()->json(PlanoMensal::calcular($servico, $mes, $dia));
    }

    /**
     * Compra/renovação de plano mensal pelo site (auto-atendimento). Disponível só
     * para clientes "indicados" pelo staff. Espelha OrdemPagamentoController::
     * mensalStore, porém o cliente é o próprio usuário autenticado; ao pagar, o
     * webhook ativa o plano e materializa as pré-reservas (fluxo já existente).
     */
    public function mensalComprar(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isIndicado(), 403, 'Compra de plano mensal não liberada.');

        $dados = $request->validate([
            'servico_id'     => ['required', 'integer'],
            'funcionario_id' => ['required', 'integer'],
            'dia_semana'     => ['required', 'integer', 'between:0,6'],
            'hora'           => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'mes'            => ['required', 'date'],
            'recorrente'     => ['nullable', 'boolean'],
        ], [
            'servico_id.required'     => 'Selecione o serviço.',
            'funcionario_id.required' => 'Selecione o barbeiro.',
            'dia_semana.required'     => 'Selecione o dia da semana.',
            'hora.required'           => 'Selecione o horário.',
            'mes.required'            => 'Selecione o mês.',
        ]);

        $servico = $this->servicoMensalDisponivel($dados['servico_id']);
        if (!$servico) {
            return back()->withErrors(['servico_id' => 'Serviço mensal indisponível.'])->withInput();
        }

        $funcionario = User::barbeiros()->find($dados['funcionario_id']);
        if (!$funcionario) {
            return back()->withErrors(['funcionario_id' => 'Barbeiro inválido.'])->withInput();
        }

        $mes  = Carbon::parse($dados['mes'])->startOfMonth();
        if ($mes->lt(Carbon::now()->startOfMonth())) {
            return back()->withErrors(['mes' => 'Selecione um mês atual ou futuro.'])->withInput();
        }
        $hora = $dados['hora'] . ':00';
        $calc = PlanoMensal::calcular($servico, $mes, (int) $dados['dia_semana']);

        if ($calc['unidades'] < 2) {
            return back()->withErrors(['dia_semana' => 'Não há ocorrências suficientes neste mês.'])->withInput();
        }

        // 1 cliente por slot semanal de cada barbeiro no mês.
        $existe = PlanoMensal::whereIn('status', [PlanoMensal::STATUS_ATIVO, PlanoMensal::STATUS_AGUARDANDO_PAGAMENTO])
            ->where('funcionario_id', $funcionario->id)
            ->where('dia_semana', (int) $dados['dia_semana'])
            ->where('hora', $hora)
            ->where('mes', $mes->toDateString())
            ->exists();
        if ($existe) {
            return back()->withErrors(['funcionario_id' => 'Esse horário fixo já foi reservado para este barbeiro neste mês. Escolha outro horário.'])->withInput();
        }

        $ordem = DB::transaction(function () use ($user, $servico, $funcionario, $dados, $mes, $hora, $calc, $request) {
            $plano = PlanoMensal::create([
                'user_id'         => $user->id,
                'servico_id'      => $servico->id,
                'funcionario_id'  => $funcionario->id,
                'dia_semana'      => (int) $dados['dia_semana'],
                'hora'            => $hora,
                'mes'             => $mes,
                'unidades_total'  => $calc['unidades'],
                'unidades_usadas' => 0,
                'valor_total'     => $calc['valor_total'],
                'status'          => PlanoMensal::STATUS_AGUARDANDO_PAGAMENTO,
                'recorrente'      => $request->boolean('recorrente'),
            ]);

            $o = OrdemPagamento::create([
                'user_id'            => $user->id,
                'criado_por'         => $user->id,
                'plano_mensal_id'    => $plano->id,
                'valor'              => $calc['valor_total'],
                'descricao'          => $servico->descricao . ' (mensal ' . $calc['unidades'] . 'x)',
                'max_parcelas'       => OrdemPagamento::MAX_PARCELAS,
                'status'             => 'aberta',
                'external_reference' => (string) Str::uuid(),
            ]);
            $o->eventos()->create(['status' => 'aberta', 'origem' => 'checkout']);

            $plano->ordem_pagamento_id = $o->id;
            $plano->save();

            return $o;
        });

        return redirect()->route('pagamentos.pagar', $ordem);
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
