<?php

namespace App\Observers;

use App\Http\Controllers\WhatsappController;
use App\Models\Aviso;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AvisoObserver
{
    // Sempre que um aviso de REAGENDAMENTO é criado, avisa o número de atendimento
    // no WhatsApp (além de o aviso aparecer no card do sistema). Cancelamento já é
    // notificado por AgendaController::notificarStaffCancelamento, então é ignorado
    // aqui para não duplicar.
    public function created(Aviso $aviso): void
    {
        if (!in_array($aviso->tipo, ['reagendamento_solicitado', 'reagendamento'], true)) {
            return;
        }

        $numero  = env('WHATSAPP_ATENDIMENTO_NUMBER', env('WHATSAPP_ADMIN_NUMBER'));
        $phoneId = env('PHONE_NUMBER_ID');
        if (!$numero || !$phoneId) {
            return;
        }

        $aviso->loadMissing('user');
        $nome          = ucfirst($aviso->user->name ?? 'Um paciente');
        $nomeComercial = env('WHATSAPP_NOME_COMERCIAL', config('app.name'));

        $antiga = $aviso->data_antiga ? Carbon::parse($aviso->data_antiga)->format('d/m \à\s H:i') : null;
        $nova   = $aviso->data_nova   ? Carbon::parse($aviso->data_nova)->format('d/m \à\s H:i')   : null;

        $resumo = match ($aviso->tipo) {
            'reagendamento_solicitado' => "{$nome} solicitou reagendamento da consulta de {$antiga}.",
            'reagendamento'            => "{$nome} reagendou a consulta de {$antiga} para {$nova}.",
            default                    => "Novo aviso de {$nome}.",
        };

        try {
            $template = env('WHATSAPP_TEMPLATE_AVISO');
            if ($template) {
                // Template aprovado na Meta: {{1}} = nome comercial, {{2}} = resumo.
                WhatsappController::enviarModelo($phoneId, $numero, $template, [
                    ['type' => 'text', 'text' => $nomeComercial],
                    ['type' => 'text', 'text' => $resumo],
                ]);
            } else {
                // Sem template configurado: texto livre (só chega se o número estiver
                // na janela de 24h da Meta). Fallback até o template ser aprovado.
                WhatsappController::enviarMsg($phoneId, $numero,
                    "🔔 Novo aviso no sistema da {$nomeComercial}.\n\n{$resumo}\n\nAcesse o painel para ver os detalhes e responder.");
            }
        } catch (\Throwable $e) {
            // A notificação nunca pode derrubar a criação do aviso.
            Log::channel('single')->warning('[AVISO-NOTIF] falha ao notificar atendimento: ' . $e->getMessage());
        }
    }
}
