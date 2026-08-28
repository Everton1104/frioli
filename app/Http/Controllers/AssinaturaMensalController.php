<?php

namespace App\Http\Controllers;

use App\Models\AgendamentoModel;
use App\Models\AssinaturaMensal;
use App\Models\Aviso;
use Illuminate\Http\Request;

/*
 * Cancelamento de assinatura de plano mensal (slot fixo) pelo staff ou pelo
 * próprio cliente (dono da assinatura, direto do painel dele).
 *
 * Cancelar a assinatura = o cliente não quer mais o plano recorrente. Isso:
 *  - interrompe as renovações automáticas (RenovarPlanosMensais só processa
 *    assinaturas ativas);
 *  - dispensa o aviso "plano_sem_saldo" em aberto;
 *  - libera os slots de reserva sem saldo futuros desse slot.
 *
 * O ciclo pago em vigor é MANTIDO (o cliente já pagou — as visitas restantes
 * seguem valendo até o fim do ciclo). Quando o dono cancela pelo site, ele é
 * lembrado de que o acerto das visitas restantes já pagas é combinado
 * diretamente com a barbearia via WhatsApp.
 */
class AssinaturaMensalController extends Controller
{
    public function destroy(Request $request, AssinaturaMensal $assinatura)
    {
        $user = $request->user();
        $ehStaff  = (bool) ($user->adm || $user->func);
        $ehDono   = (int) $assinatura->user_id === (int) $user->id;
        abort_unless($ehStaff || $ehDono, 403);

        if ($assinatura->status !== AssinaturaMensal::STATUS_ATIVO) {
            return redirect()->back()->with('msg', 'Este plano já não está ativo.');
        }

        $assinatura->status        = AssinaturaMensal::STATUS_CANCELADO;
        $assinatura->cancelado_em  = now();
        $assinatura->save();

        // Dispensa avisos "sem saldo" em aberto do cliente (não vai mais renovar).
        Aviso::where('tipo', 'plano_sem_saldo')
            ->where('user_id', $assinatura->user_id)
            ->whereNull('dispensado_at')
            ->update(['dispensado_at' => now()]);

        // Libera os slots de reserva sem saldo futuros dessa assinatura.
        AgendamentoModel::where('assinatura_id', $assinatura->id)
            ->where('status', AgendamentoModel::STATUS_RESERVA_RENOVACAO)
            ->delete();

        return redirect()->back()->with(
            'msg',
            $ehDono && !$ehStaff
                ? 'Plano cancelado. As renovações automáticas param e o ciclo já pago segue valendo até o fim. Acerte os valores das visitas restantes já pagas diretamente com a barbearia pelo WhatsApp.'
                : 'Plano cancelado. As renovações automáticas foram interrompidas; o ciclo já pago segue valendo até o fim.'
        );
    }
}
