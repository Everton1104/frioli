<?php

namespace App\Console\Commands;

use App\Models\AgendamentoModel;
use App\Models\PlanoMensal;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/*
 * Desconto automático de visitas de plano mensal — por data, sem confirmação.
 *
 * O cliente não precisa confirmar (nem o botão do WhatsApp, nem o barbeiro no painel)
 * para o saldo baixar: ao passar o dia/hora da visita, a unidade é descontada. Se ele
 * FALTAR, perde a unidade (trava que combina com a validade de 45 dias contra quem
 * remarca demais). Se o funcionário excluir ou remarcar a visita, a exclusão devolve
 * a unidade (restaurarVisita via consumo_plano).
 *
 * Idempotente: só processa agendamentos com consumo_plano NULL. Ao descontar, grava
 * consumo_plano (ids dos itens) — quando o funcionário exclui, restaurarVisita usa
 * esses ids para devolver exatamente o que foi abatido.
 */
class DescontarVisitasPlano extends Command
{
    protected $signature   = 'planos:descontar-visitas';
    protected $description = 'Desconta as visitas de plano mensal cuja data já passou (sem confirmação).';

    public function handle(): int
    {
        // Delega para o service — a mesma lógica roda automaticamente ao abrir a semana
        // (salvarSemana). Este command fica para uso manual/backstop.
        $n = \App\Services\PlanoMensalService::descontarVisitasVencidas();

        if ($n) {
            $this->info("Visitas de plano mensal descontadas: {$n}");
        }

        return self::SUCCESS;
    }
}
