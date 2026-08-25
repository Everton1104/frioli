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
        $hoje = now();

        // Expira ciclos ativos com saldo não usado cuja validade (data_inicio +
        // validade_dias da assinatura, default 45 dias) já passou. Legados sem
        // data_inicio usam o mês-calendário. Não toca na assinatura — o slot fixo
        // segue reservado até o cliente renovar. Filtrado em PHP (compatível sqlite/mysql).
        $candidatos = PlanoMensal::with('assinatura')
            ->where('status', PlanoMensal::STATUS_ATIVO)
            ->whereColumn('unidades_usadas', '<', 'unidades_total')
            ->get();

        $expirar = [];
        foreach ($candidatos as $p) {
            $inicio = $p->data_inicio
                ? \Illuminate\Support\Carbon::parse($p->data_inicio)
                : ($p->mes ? \Illuminate\Support\Carbon::parse($p->mes)->startOfMonth() : null);
            if (!$inicio) {
                continue;
            }
            $validade = (int) ($p->assinatura?->validade_dias ?: 45);
            if ($inicio->copy()->addDays($validade)->lt($hoje)) {
                $expirar[] = $p->id;
            }
        }

        $qtd = 0;
        if ($expirar) {
            $qtd = PlanoMensal::whereIn('id', $expirar)->update(['status' => PlanoMensal::STATUS_EXPIRADO]);
        }

        if ($qtd) {
            $this->info("Planos mensais expirados: {$qtd}");
        }

        return self::SUCCESS;
    }
}
