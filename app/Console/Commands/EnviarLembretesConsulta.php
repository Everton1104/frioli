<?php

namespace App\Console\Commands;

use App\Http\Controllers\WhatsappController;
use App\Models\AgendamentoModel;
use App\Models\LembreteConsulta;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class EnviarLembretesConsulta extends Command
{
    protected $signature   = 'lembretes:enviar {modo=todos : Quais lembretes enviar: diario, 2h ou todos}';
    protected $description = 'Envia lembretes de consulta via WhatsApp (diário do dia seguinte e 2h antes)';

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

        $modo = $this->argument('modo');

        // Lembrete diário: roda uma vez ao dia (17h) e cobre TODAS as consultas do dia seguinte.
        if ($modo === 'diario' || $modo === 'todos') {
            $amanha = now()->addDay();
            $this->enviarLembretes('24h', $amanha->copy()->startOfDay(), $amanha->copy()->endOfDay(), $phoneId, $template);
        }

        // Lembrete de 2h antes: roda a cada 15 min; janela de 15 min para disparar ~2h antes
        // (cobre também horários com intervalos de 15 minutos).
        if ($modo === '2h' || $modo === 'todos') {
            $this->enviarLembretes('2h', now()->addHours(2)->subMinutes(15), now()->addHours(2), $phoneId, $template);
        }
    }

    private function enviarLembretes(string $tipo, Carbon $de, Carbon $ate, string $phoneId, string $template): void
    {
        $agendamentos = AgendamentoModel::with(['user', 'servico'])
            ->whereBetween('data_inicio', [$de, $ate])
            // Serviços de retirada não pedem confirmação: o aviso é manual ("pedido pronto").
            ->whereDoesntHave('servico', fn($q) => $q->where('retirada', true))
            ->whereDoesntHave('lembretes', fn($q) => $q->where('tipo', $tipo)->where('status', 'enviado'))
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

            // Véspera ('24h') gera pré-confirmação; 2h antes gera a confirmação oficial.
            $btnConfirmar = $tipo === '2h'
                ? 'confirmar_' . $agendamento->id
                : 'confirmar_pre_' . $agendamento->id;

            $resultado = WhatsappController::enviarModelo($phoneId, $user->whatsapp, $template, [
                ['type' => 'text', 'text' => $nome],
                ['type' => 'text', 'text' => $nomeComercial],
                ['type' => 'text', 'text' => $data],
                ['type' => 'text', 'text' => $hora],
            ], 'pt_BR', [
                $btnConfirmar,
                'reagendar_' . $agendamento->id,
            ]);

            $erro    = isset($resultado['erro']);
            $erroMsg = $erro ? ($resultado['msg'] ?? 'Erro desconhecido') : null;

            // updateOrCreate respeita o unique(agendamento_id, tipo): em um retry de erro
            // a mesma linha é atualizada (de 'erro' para 'enviado') em vez de violar a chave.
            LembreteConsulta::updateOrCreate(
                ['agendamento_id' => $agendamento->id, 'tipo' => $tipo],
                ['status' => $erro ? 'erro' : 'enviado', 'erro_msg' => $erroMsg],
            );

            $this->line($erro
                ? "  [ERRO] {$tipo} → {$user->whatsapp}: {$erroMsg}"
                : "  [OK]   {$tipo} → {$user->whatsapp} ({$nome} — {$data} às {$hora})"
            );
        }

        $this->info("Lembretes {$tipo}: {$agendamentos->count()} processado(s).");
    }
}
