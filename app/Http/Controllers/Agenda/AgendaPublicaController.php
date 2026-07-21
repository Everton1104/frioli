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

        return view('agendar.index', [
            'servicos'         => $servicos,
            'barbeiros'        => $barbeiros,
            'penalizado'       => (bool) ($user?->isPenalizado() ?? false),
            'whatsappAdmin'    => PageContent::get('contato', 'whatsapp_numero', '5511988245815'),
            'recaptchaSiteKey' => app(RecaptchaService::class)->siteKey(),
        ]);
    }

    public function reservar(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Cliente penalizado (no-show anterior) não pode fazer novos agendamentos.
        if ($user->isPenalizado()) {
            return back()->withErrors(['penalizado' => 'Você está com uma pendência. Contate a barbearia para liberar novos agendamentos.'])->withInput();
        }

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
