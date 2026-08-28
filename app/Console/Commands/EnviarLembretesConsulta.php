<?php

namespace App\Console\Commands;

use App\Http\Controllers\WhatsappController;
use App\Models\AgendamentoModel;
use App\Models\LembreteConsulta;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class EnviarLembretesConsulta extends Command
{
    protected $signature   = 'lembretes:enviar';
    protected $description = 'Envia o lembrete da véspera das consultas via WhatsApp (confirmação oficial)';

    public function handle(): void
    {
        $phoneId = env('PHONE_NUMBER_ID');
        $template = env('WHATSAPP_TEMPLATE_LEMBRETE');

        if (!$phoneId) {
            $this->error('PHONE_NUMBER_ID não configurado no .env');
            return;
        }

        if (!$template) {
            $this->error('WHATSAPP_TEMPLATE_LEMBRETE não configurado no .env');
            return;
        }

        // Lembrete da véspera: roda uma vez ao dia (17h) e cobre TODAS as consultas do dia seguinte.
        $amanha = now()->addDay();
        $this->enviarLembretes($amanha->copy()->startOfDay(), $amanha->copy()->endOfDay(), $phoneId, $template);
    }

    private function enviarLembretes(Carbon $de, Carbon $ate, string $phoneId, string $template): void
    {
        $agendamentos = AgendamentoModel::with([
            'user', 'servico',
            'planoMensal.itens.servico', 'creditoServico.servico',
        ])
            ->whereBetween('data_inicio', [$de, $ate])
            // Só visitas reais/confirmáveis recebem lembrete. A reserva "sem saldo"
            // (reserva_renovacao), as intenções e os cancelados/recusados ficam de fora.
            ->whereIn('status', [
                AgendamentoModel::STATUS_CONFIRMADO,
                AgendamentoModel::STATUS_PAGO_AGUARDANDO,
                AgendamentoModel::STATUS_AGUARDANDO_PAGAMENTO,
            ])
            ->whereDoesntHave('lembretes', fn($q) => $q->where('tipo', '24h')->where('status', 'enviado'))
            ->get();

        foreach ($agendamentos as $agendamento) {
            $user = $agendamento->user;

            if (!$user || !$user->whatsapp) {
                continue;
            }

            $nome          = ucfirst($user->name);
            $nomeComercial = env('WHATSAPP_NOME_COMERCIAL', config('app.name'));
            $data          = Carbon::parse($agendamento->data_inicio)
                                ->locale('pt_BR')
                                ->translatedFormat('d \d\e F \d\e Y');
            $hora          = Carbon::parse($agendamento->data_inicio)->format('H:i');

            // Saldo do pacote/plano concatenado no parâmetro da hora (sem mexer no
            // template da Meta): ex.: "14:00 · corte 1/4, barba 1/4". Vazio → só a hora.
            $saldo   = $agendamento->saldoPacoteTexto();
            $horaMsg = $saldo !== '' ? "{$hora} · {$saldo}" : $hora;

            // Lembrete único da véspera já vale como confirmação oficial (botão
            // confirmar_). O payload leva o SLUG do sistema ("fr_confirmar_42"):
            // o gateway evtu roteia o clique de volta para este sistema pelo slug.
            $slug  = (string) env('WHATSAPP_SYSTEM_SLUG', '');
            $pref  = $slug !== '' ? $slug . '_' : '';

            $resultado = WhatsappController::enviarModelo($phoneId, $user->whatsapp, $template, [
                ['type' => 'text', 'text' => $nome],
                ['type' => 'text', 'text' => $nomeComercial],
                ['type' => 'text', 'text' => $data],
                ['type' => 'text', 'text' => $horaMsg],
            ], 'pt_BR', [
                $pref . 'confirmar_' . $agendamento->id,
                $pref . 'reagendar_' . $agendamento->id,
            ]);

            $erro    = isset($resultado['erro']);
            $erroMsg = $erro ? ($resultado['msg'] ?? 'Erro desconhecido') : null;

            // updateOrCreate respeita o unique(agendamento_id, tipo): em um retry de erro
            // a mesma linha é atualizada (de 'erro' para 'enviado') em vez de violar a chave.
            LembreteConsulta::updateOrCreate(
                ['agendamento_id' => $agendamento->id, 'tipo' => '24h'],
                ['status' => $erro ? 'erro' : 'enviado', 'erro_msg' => $erroMsg],
            );

            $this->line($erro
                ? "  [ERRO] véspera → {$user->whatsapp}: {$erroMsg}"
                : "  [OK]   véspera → {$user->whatsapp} ({$nome} — {$data} às {$hora})"
            );
        }

        $this->info("Lembretes da véspera: {$agendamentos->count()} processado(s).");
    }
}
