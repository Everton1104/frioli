<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Agenda\AgendaController;
use App\Models\AgendamentoModel;
use App\Models\AssinaturaMensal;
use App\Models\PlanoMensal;
use App\Models\PlanoTroca;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Troca de dia/horário do plano mensal (slot fixo) a pedido do cliente.
 *
 * O cliente pede a troca no painel dele; ela fica PENDENTE (o plano segue no
 * slot antigo) até o staff decidir:
 *  - aprovar: aplica o novo dia/hora na assinatura e nos ciclos em vigor, remove
 *    os agendamentos futuros do slot antigo (devolvendo visitas já descontadas)
 *    e re-materializa as pré-reservas no novo slot (materializarPlano é
 *    idempotente e pula ocorrências com conflito na agenda do barbeiro);
 *  - recusar: só marca como recusada — o cliente vê o resultado no painel.
 */
class PlanoTrocaController extends Controller
{
    public function store(Request $request, AssinaturaMensal $assinatura)
    {
        $user    = $request->user();
        $ehStaff = (bool) ($user->adm || $user->func);
        abort_unless($ehStaff || (int) $assinatura->user_id === (int) $user->id, 403);

        $validated = $request->validate([
            'dia_semana' => 'required|integer|between:0,6',
            'hora'       => ['required', 'date_format:H:i', 'in:' . implode(',', $this->horariosValidos())],
        ], [
            'dia_semana.between' => 'Escolha o dia da semana.',
            'hora.date_format'   => 'Escolha um horário válido.',
            'hora.in'            => 'Escolha um horário válido (de 15 em 15 minutos).',
        ]);

        if ($assinatura->status !== AssinaturaMensal::STATUS_ATIVO) {
            return redirect()->back()->with('msgErro', 'Este plano não está ativo.');
        }

        if ((int) $validated['dia_semana'] === (int) $assinatura->dia_semana
            && $validated['hora'] === substr((string) $assinatura->hora, 0, 5)) {
            return redirect()->back()->with('msgErro', 'Escolha um dia ou horário diferente do atual.');
        }

        // O novo dia/horário não pode ser o slot fixo de OUTRO mensalista — mesmo
        // que uma semana pareça livre (o dono pode ter remarcado pontualmente), o
        // slot é semanal e volta a ser usado por ele. (Não nomeamos o outro cliente
        // na frente do cliente.)
        if (AssinaturaMensal::slotFixoOcupado((int) $assinatura->funcionario_id, (int) $validated['dia_semana'], $validated['hora'], (int) $assinatura->user_id)
            || PlanoMensal::slotOcupadoPorCiclo((int) $assinatura->funcionario_id, (int) $validated['dia_semana'], $validated['hora'], (int) $assinatura->user_id)) {
            return redirect()->back()->with('msgErro', 'Este dia/horário não está disponível para plano fixo. Escolha outro.');
        }

        $jaPendente = PlanoTroca::where('assinatura_id', $assinatura->id)
            ->where('status', PlanoTroca::STATUS_PENDENTE)
            ->exists();
        if ($jaPendente) {
            return redirect()->back()->with('msgErro', 'Já existe uma troca aguardando aprovação para este plano.');
        }

        $troca = PlanoTroca::create([
            'assinatura_id' => $assinatura->id,
            'user_id'       => $assinatura->user_id,
            'dia_semana'    => (int) $validated['dia_semana'],
            'hora'          => $validated['hora'] . ':00',
            'status'        => PlanoTroca::STATUS_PENDENTE,
        ]);

        \App\Http\Controllers\WhatsappController::avisarStaffTrocaPlano($troca);

        return redirect()->back()->with(
            'msg',
            'Troca solicitada! Seu plano continua no horário atual até a barbearia confirmar a mudança.'
        );
    }

    public function aprovar(Request $request, PlanoTroca $troca)
    {
        $user = $request->user();
        abort_unless($user->adm || $user->func, 403);

        if ($troca->status !== PlanoTroca::STATUS_PENDENTE) {
            return redirect()->back()->with('msgErro', 'Esta solicitação já foi resolvida.');
        }

        $assinatura = $troca->assinatura;
        if (!$assinatura || $assinatura->status !== AssinaturaMensal::STATUS_ATIVO) {
            $troca->update(['status' => PlanoTroca::STATUS_RECUSADA, 'resolvido_em' => now(), 'resolvido_por' => $user->id]);
            return redirect()->back()->with('msgErro', 'A assinatura não está mais ativa; a troca foi descartada.');
        }

        // Revalida o slot na aprovação: pode ter sido ocupado por outro mensalista
        // entre o pedido e agora. Mantém a troca PENDENTE (o staff combina outro
        // dia com o cliente ou recusa).
        $donoSlot = AssinaturaMensal::slotFixoOcupado((int) $assinatura->funcionario_id, (int) $troca->dia_semana, (string) $troca->hora, (int) $assinatura->user_id)
            ?? PlanoMensal::slotOcupadoPorCiclo((int) $assinatura->funcionario_id, (int) $troca->dia_semana, (string) $troca->hora, (int) $assinatura->user_id);
        if ($donoSlot) {
            return redirect()->back()->with(
                'msgErro',
                'Não foi possível aprovar: ' . $troca->slotDesc() . ' é o slot fixo de ' . ($donoSlot->user->name ?? 'outro cliente') . ' neste período. Combine outro dia com o cliente ou recuse o pedido.'
            );
        }

        $aplicadas = DB::transaction(function () use ($troca, $assinatura, $user) {
            // 1. Novo slot na assinatura (fonte da verdade p/ renovações futuras) e
            //    nos ciclos em vigor.
            $assinatura->update([
                'dia_semana' => $troca->dia_semana,
                'hora'       => $troca->hora,
            ]);
            $ciclos = PlanoMensal::where('assinatura_id', $assinatura->id)
                ->whereIn('status', [PlanoMensal::STATUS_ATIVO, PlanoMensal::STATUS_AGUARDANDO_PAGAMENTO])
                ->get();

            // Reancora a JANELA de cada ciclo no novo dia: data_inicio/data_fim
            // passam a ser a 1ª e a última das `restantes` próximas ocorrências do
            // novo dia em semanas LIVRES (1 visita/semana — semanas que já tiveram
            // visita passada da assinatura são puladas, igual ao materializarPlano).
            // Assim datasOcorrencias()->take(unidades_total) devolve exatamente as
            // visitas ainda devidas, sem cortar a última nem criar fora do pago.
            $ultimaSemanaOcupada = AgendamentoModel::where('assinatura_id', $assinatura->id)
                ->whereIn('status', AgendamentoModel::OCUPANTES)
                ->where('data_inicio', '<=', now())
                ->orderByDesc('data_inicio')
                ->first()
                ?->data_inicio->copy()->startOfWeek(Carbon::SUNDAY);

            foreach ($ciclos as $ciclo) {
                $restantes = max(0, (int) $ciclo->unidades_total - (int) $ciclo->unidades_usadas);
                $base = $ciclo->data_inicio
                    ? Carbon::parse($ciclo->data_inicio)->startOfDay()
                    : ($ciclo->mes ? Carbon::parse($ciclo->mes)->startOfMonth() : now()->startOfDay());

                $futuras = collect();
                $cursor = max($base, now()->startOfDay())->copy();
                $limite = $cursor->copy()->addMonths(3); // salvaguarda do loop
                while ($cursor->lte($limite) && $futuras->count() < $restantes) {
                    $semana = $cursor->copy()->startOfWeek(Carbon::SUNDAY);
                    if ((int) $cursor->format('w') === (int) $troca->dia_semana
                        && (!$ultimaSemanaOcupada || $semana->gt($ultimaSemanaOcupada))) {
                        $futuras->push($cursor->copy());
                    }
                    $cursor->addDay();
                }

                // Sem futuras devidas mantém a janela original (só troca o slot).
                $dados = ['dia_semana' => (int) $troca->dia_semana, 'hora' => $troca->hora];
                if ($futuras->isNotEmpty()) {
                    $dados['data_inicio'] = $futuras->first();
                    $dados['data_fim']    = $futuras->last()->endOfDay();
                }
                $ciclo->update($dados);
            }

            // 2. Remove os agendamentos FUTUROS do slot antigo (visitas do plano e
            //    reservas de renovação). Visitas já descontadas (consumo_plano) têm a
            //    unidade devolvida — a re-materialização recria a pré-reserva no novo
            //    slot sem descontar (mesma regra do reagendamento/exclusão).
            $futuros = AgendamentoModel::where('assinatura_id', $assinatura->id)
                ->whereIn('status', AgendamentoModel::OCUPANTES)
                ->where('data_inicio', '>', now())
                ->get();
            foreach ($futuros as $ag) {
                if ($ag->plano_mensal_id && !empty($ag->consumo_plano)) {
                    $plano = PlanoMensal::with('itens')->find($ag->plano_mensal_id);
                    $plano?->restaurarVisita($ag->consumo_plano);
                }
                $ag->delete();
            }

            // 3. Re-materializa as pré-reservas no novo slot (idempotente; pula
            //    ocorrências em conflito na agenda do barbeiro). A janela reancorada
            //    garante datasOcorrencias()->take(unidades_total) = exatamente as
            //    visitas pagas; as passadas são puladas e as futuras criadas.
            $agenda = app(AgendaController::class);
            $aplicadas = 0;
            foreach ($ciclos->where('status', PlanoMensal::STATUS_ATIVO) as $ciclo) {
                $aplicadas += $agenda->materializarPlano($ciclo->fresh());
            }

            $troca->update([
                'status'       => PlanoTroca::STATUS_APROVADA,
                'resolvido_em' => now(),
                'resolvido_por' => $user->id,
            ]);

            return $aplicadas;
        });

        return redirect()->back()->with(
            'msg',
            "Troca aprovada. Plano movido para {$troca->slotDesc()}"
            . ($aplicadas > 0 ? " ({$aplicadas} visita(s) remarcada(s))." : ' — as visitas surgem na agenda conforme o novo dia.')
        );
    }

    public function recusar(Request $request, PlanoTroca $troca)
    {
        $user = $request->user();
        abort_unless($user->adm || $user->func, 403);

        if ($troca->status !== PlanoTroca::STATUS_PENDENTE) {
            return redirect()->back()->with('msgErro', 'Esta solicitação já foi resolvida.');
        }

        $troca->update([
            'status'        => PlanoTroca::STATUS_RECUSADA,
            'resolvido_em'  => now(),
            'resolvido_por' => $user->id,
        ]);

        return redirect()->back()->with('msg', 'Troca recusada. O cliente verá o resultado no painel dele.');
    }

    /** Horários de 15 em 15 minutos (mesma grade da agenda). */
    private function horariosValidos(): array
    {
        $out = [];
        for ($m = 7 * 60; $m <= 21 * 60; $m += 15) {
            $out[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }
        return $out;
    }
}
