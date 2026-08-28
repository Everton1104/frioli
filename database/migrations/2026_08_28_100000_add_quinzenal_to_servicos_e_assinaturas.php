<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Plano QUINZENAL: cliente com slot fixo que vem a cada 15 dias (semana sim,
 * semana não). As semanas vazias ficam LIVRES na agenda — outro cliente
 * (avulso ou um 2º quinzenal "complementar") pode usar o mesmo dia/horário.
 *
 * - servicos.quinzenal: o combo mensal é quinzenal (define o comportamento do
 *   ciclo: visitas só nas semanas alternadas).
 * - assinaturas_mensais.quinzenal_fase: paridade A/B (0/1) das semanas do
 *   cliente, ancorada num domingo fixo (PlanoMensal::faseSemana). NULL =
 *   assinatura semanal (todo semana). Dois quinzenais dividem o mesmo slot
 *   quando as fases são OPOSTAS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            if (!Schema::hasColumn('servicos', 'quinzenal')) {
                $table->boolean('quinzenal')->default(false)->after('recorrente');
            }
        });
        Schema::table('assinaturas_mensais', function (Blueprint $table) {
            if (!Schema::hasColumn('assinaturas_mensais', 'quinzenal_fase')) {
                $table->unsignedTinyInteger('quinzenal_fase')->nullable()->after('hora');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn('quinzenal');
        });
        Schema::table('assinaturas_mensais', function (Blueprint $table) {
            $table->dropColumn('quinzenal_fase');
        });
    }
};
