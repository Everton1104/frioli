<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Models\AgendamentoModel;
use App\Models\DisponibilidadeModel;
use App\Models\OrdemPagamento;
use App\Models\ServicosModel;
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
        $servicos = ServicosModel::where('excluido', 0)
            ->where('status', 1)
            ->where('visivel_cliente', 1)
            ->where('recorrente', 0)
            ->whereNotNull('valor')
            ->where('valor', '>', 0)
            ->orderBy('descricao')
            ->get();

        return view('agendar.index', compact('servicos'));
    }

    public function reservar(Request $request): RedirectResponse
    {
        $request->validate(
            [
                'servico_id'  => ['required', 'integer'],
                'data_inicio' => ['required', 'date', 'after:now'],
            ],
            [
                'servico_id.required'  => 'Selecione um serviço.',
                'data_inicio.required' => 'Selecione um dia e horário.',
                'data_inicio.after'    => 'O horário precisa ser futuro.',
            ]
        );

        $user    = $request->user();
        $servico = $this->servicoDisponivel($request->servico_id);

        if (!$servico) {
            return back()->withErrors(['servico_id' => 'Serviço indisponível.'])->withInput();
        }

        $inicio   = Carbon::parse($request->data_inicio);
        $duracao  = $this->timeToMinutes($servico->duracao);
        $intervalo = 30; // clientes
        $fim      = $inicio->copy()->addMinutes($duracao);

        if ($erro = $this->validarSlotCliente($servico, $inicio, $fim, $duracao, $intervalo)) {
            return back()->withErrors(['data_inicio' => $erro])->withInput();
        }

        // Cria agendamento pendente (LOCK do slot) + ordem de pagamento vinculada.
        $ordem = DB::transaction(function () use ($user, $servico, $inicio, $fim) {
            $ag = AgendamentoModel::create([
                'user_id'     => $user->id,
                'servico_id'  => $servico->id,
                'data_inicio' => $inicio,
                'data_fim'    => $fim,
                'status'      => AgendamentoModel::STATUS_AGUARDANDO_PAGAMENTO,
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

    /**
     * Validação de slot para cliente (espelha AgendaController@store). Retorna uma
     * mensagem de erro ou null se ok.
     */
    private function validarSlotCliente(ServicosModel $servico, Carbon $inicio, Carbon $fim, int $duracao, int $intervalo): ?string
    {
        if ($inicio < Carbon::now()->addHours(2)) {
            return 'Só é possível agendar com no mínimo 2h de antecedência.';
        }

        $slots = DisponibilidadeModel::where('data', $inicio->toDateString())
            ->pluck('hora')
            ->map(fn($h) => substr($h, 0, 5))
            ->toArray();

        // Clientes só enxergam :00 e :30
        $slots = array_values(array_filter(
            $slots,
            fn($h) => in_array(substr($h, 3, 2), ['00', '30'])
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

        $conflito = AgendamentoModel::where('data_inicio', '<', $fim)
            ->where('data_fim', '>', $inicio)
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
