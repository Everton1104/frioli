<?php

namespace App\Console\Commands;

use App\Http\Controllers\WhatsappController;
use App\Models\AgendamentoModel;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TestarLembrete extends Command
{
    protected $signature   = 'teste:lembrete {numero : Número WhatsApp de destino (com DDI, ex: 5511999999999)} {--agendamento= : ID do agendamento; se omitido, usa o próximo do sistema}';
    protected $description = 'Envia o template confirmar_reagendar_consulta para um número de teste';

    public function handle(): int
    {
        $phoneId  = env('PHONE_NUMBER_ID');
        $template = env('WHATSAPP_TEMPLATE_LEMBRETE', 'confirmar_reagendar_consulta');
        $numero   = preg_replace('/\D/', '', $this->argument('numero'));

        if (!$phoneId) {
            $this->error('PHONE_NUMBER_ID não configurado no .env');
            return 1;
        }

        if (strlen($numero) <= 11) {
            $numero = '55' . $numero;
        }

        $agendamentoId = $this->option('agendamento');

        if ($agendamentoId) {
            $agendamento = AgendamentoModel::with(['user', 'servico'])->find($agendamentoId);
            if (!$agendamento) {
                $this->error("Agendamento #{$agendamentoId} não encontrado.");
                return 1;
            }
        } else {
            $agendamento = AgendamentoModel::with(['user', 'servico'])
                ->where('data_inicio', '>', now())
                ->orderBy('data_inicio')
                ->first();

            if (!$agendamento) {
                $this->error('Nenhum agendamento futuro encontrado. Use --agendamento=ID para forçar um.');
                return 1;
            }
        }

        $nome          = ucfirst($agendamento->user->name ?? 'Cliente Teste');
        $nomeComercial = env('WHATSAPP_NOME_COMERCIAL', config('app.name'));
        $data          = Carbon::parse($agendamento->data_inicio)
                             ->locale('pt_BR')
                             ->translatedFormat('d \d\e F \d\e Y');
        $hora          = Carbon::parse($agendamento->data_inicio)->format('H:i');

        $this->line('');
        $this->info("Template : {$template}");
        $this->line("Destino  : {$numero}");
        $this->line("Agenda   : #{$agendamento->id} — {$nome} em {$data} às {$hora}");
        $this->line("Botões   : confirmar_{$agendamento->id} / reagendar_{$agendamento->id}");
        $this->line('');

        $resultado = WhatsappController::enviarModelo($phoneId, $numero, $template, [
            ['type' => 'text', 'text' => $nome],
            ['type' => 'text', 'text' => $nomeComercial],
            ['type' => 'text', 'text' => $data],
            ['type' => 'text', 'text' => $hora],
        ], 'pt_BR', [
            'confirmar_' . $agendamento->id,
            'reagendar_' . $agendamento->id,
        ]);

        if (isset($resultado['erro'])) {
            $this->error('Falha: ' . $resultado['msg']);
            return 1;
        }

        $this->info('Mensagem enviada com sucesso!');
        return 0;
    }
}
