<?php

namespace App\Console\Commands;

use App\Models\PlanoMensal;
use Illuminate\Console\Command;

/*
 * Ao virar o mês-alvo, planos mensais ativos com unidades não usadas ficam "expirados"
 * (entram em Negociar — o cliente renegocia os cortes restantes via WhatsApp).
 */
class ExpirarPlanosMensais extends Command
{
    protected $signature = 'planos:expirar';
    protected $description = 'Expira planos mensais cujo mês passou com unidades não usadas.';

    public function handle(): int
    {
        $inicioMesAtual = now()->copy()->startOfMonth();

        $qtd = PlanoMensal::where('status', PlanoMensal::STATUS_ATIVO)
            ->where('mes', '<', $inicioMesAtual->toDateString())
            ->whereColumn('unidades_usadas', '<', 'unidades_total')
            ->update(['status' => PlanoMensal::STATUS_EXPIRADO]);

        if ($qtd) {
            $this->info("Planos mensais expirados: {$qtd}");
        }

        return self::SUCCESS;
    }
}
