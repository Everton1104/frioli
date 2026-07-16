<?php

namespace App\Console\Commands;

use App\Http\Controllers\InfinitePayWebhookController;
use App\Models\OrdemPagamento;
use Illuminate\Console\Command;

// Backstop para webhook perdido: periodicamente consulta (payment_check) as
// ordens InfinitePay que ainda estão pagáveis e têm um link gerado, e aplica o
// status real. A entrega do webhook é best-effort — este command garante que
// nenhuma ordem fique presa caso o webhook não chegue.
class ReconciliarOrdensInfinitePay extends Command
{
    protected $signature = 'infinitepay:reconciliar';

    protected $description = 'Reconcilia ordens InfinitePay via payment_check (backstop de webhook)';

    public function handle(): int
    {
        // Ignora ordens atualizadas há poucos minutos (dá tempo do webhook chegar).
        $corte = now()->subMinutes(3);

        $ordens = OrdemPagamento::where('gateway', 'infinitepay')
            ->whereIn('status', ['aberta', 'pending', 'rejected'])
            ->whereNotNull('infinitepay_slug')
            ->where('updated_at', '<', $corte)
            ->limit(100)
            ->get();

        if ($ordens->isEmpty()) {
            return self::SUCCESS;
        }

        $sync = app(InfinitePayWebhookController::class);
        $aprovadas = 0;

        foreach ($ordens as $ordem) {
            try {
                $antes = $ordem->status;
                $sync->sincronizarOrdem($ordem);
                $ordem->refresh();
                if ($ordem->status === 'approved' && $antes !== 'approved') {
                    $aprovadas++;
                }
            } catch (\Throwable $e) {
                $this->error("Ordem {$ordem->id}: {$e->getMessage()}");
            }
        }

        $this->info("InfinitePay: {$aprovadas} aprovada(s) de {$ordens->count()} verificada(s).");
        return self::SUCCESS;
    }
}
