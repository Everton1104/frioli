<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Devolução de saldo ao excluir/cancelar uma visita de plano mensal.
 *
 * consumo_plano guarda (em JSON) quais itens de plano_mensal_servicos foram
 * consumidos NAQUELE agendamento ao ser confirmado (ex.: [12, 13]). Assim, ao
 * excluir a visita, o sistema reverte EXATAMENTE esses itens — inclusive no caso
 * de contagens desiguais (ex.: 3ª visita de um 2+4 consumiu só barba).
 *
 * Agendamentos legados (confirmados antes desta migration) ficam com NULL: ao
 * excluir, só revertemos unidades_usadas (melhor esforço, sem saber os itens).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            if (!Schema::hasColumn('agendamentos', 'consumo_plano')) {
                $table->json('consumo_plano')->nullable()->after('plano_mensal_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            if (Schema::hasColumn('agendamentos', 'consumo_plano')) {
                $table->dropColumn('consumo_plano');
            }
        });
    }
};
