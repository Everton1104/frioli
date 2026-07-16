<?php

namespace App\Console\Commands;

use App\Models\AgendamentoModel;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Libera slots de agendamentos públicos que ficaram presos em
 * "aguardando_pagamento" (cliente reservou mas não pagou). Cancela os que
 * ultrapassaram o prazo, devolvendo o horário à disponibilidade. Agendado em
 * routes/console.php (a cada 5 min).
 */
class ExpirarAgendamentosPendentes extends Command
{
    protected $signature = 'agendamentos:expirar-pendentes
                            {--minutos=15 : minutos de tolerância antes de expirar}';

    protected $description = 'Cancela agendamentos públicos "aguardando_pagamento" parados há mais de N minutos (libera o slot).';

    public function handle(): int
    {
        $minutos  = max(1, (int) $this->option('minutos'));
        $limite   = Carbon::now()->subMinutes($minutos);

        $pendentes = AgendamentoModel::where('status', AgendamentoModel::STATUS_AGUARDANDO_PAGAMENTO)
            ->where('created_at', '<', $limite)
            ->get();

        if ($pendentes->isEmpty()) {
            $this->info('Nenhum agendamento pendente para expirar.');
            return self::SUCCESS;
        }

        foreach ($pendentes as $ag) {
            $ag->status = AgendamentoModel::STATUS_CANCELADO;
            $ag->save();
        }

        $this->info('Expirados/cancelados: ' . $pendentes->count());
        return self::SUCCESS;
    }
}
