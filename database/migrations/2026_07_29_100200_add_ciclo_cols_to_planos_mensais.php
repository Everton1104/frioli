<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Pacote mensal multi-serviço (Fase 1) — planos_mensais vira um CICLO ligado à assinatura.
 *
 * Colunas adicionadas (todas nullable para preservar planos legados até o backfill):
 *  - assinatura_id: FK para assinaturas_mensais (o slot fixo persistente).
 *  - data_inicio / data_fim: janela do ciclo (1ª/última ocorrência do dia_semana).
 *    Quando preenchidas, datasOcorrencias() usa a janela em vez do mês-calendário.
 *  - dia_renovacao: snapshot do dia preferido (cópia da assinatura; ajustável).
 *
 * ⚠️ assinatura_id JÁ EXISTIU como string (ID da assinatura InfinitePay) e foi dropado
 *    pela migration 2026_07_21_000002_drop_assinatura_cols.php. Guardamos com
 *    Schema::hasColumn caso algum ambiente não tenha rodado o drop.
 *
 * Mantemos mes / servico_id / unidades_total / unidades_usadas para back-compat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos_mensais', function (Blueprint $table) {
            if (!Schema::hasColumn('planos_mensais', 'assinatura_id')) {
                $table->unsignedBigInteger('assinatura_id')->nullable()->after('ordem_pagamento_id');
                $table->foreign('assinatura_id')->references('id')->on('assinaturas_mensais')->nullOnDelete();
            }
            if (!Schema::hasColumn('planos_mensais', 'data_inicio')) {
                $table->date('data_inicio')->nullable()->after('mes');
            }
            if (!Schema::hasColumn('planos_mensais', 'data_fim')) {
                $table->date('data_fim')->nullable()->after('data_inicio');
            }
            if (!Schema::hasColumn('planos_mensais', 'dia_renovacao')) {
                $table->unsignedTinyInteger('dia_renovacao')->nullable()->after('data_fim');
            }
            $table->index('assinatura_id');
            $table->index('data_fim');
        });
    }

    public function down(): void
    {
        Schema::table('planos_mensais', function (Blueprint $table) {
            $table->dropIndex(['assinatura_id']);
            $table->dropIndex(['data_fim']);
            if (Schema::hasColumn('planos_mensais', 'dia_renovacao')) {
                $table->dropColumn('dia_renovacao');
            }
            if (Schema::hasColumn('planos_mensais', 'data_fim')) {
                $table->dropColumn('data_fim');
            }
            if (Schema::hasColumn('planos_mensais', 'data_inicio')) {
                $table->dropColumn('data_inicio');
            }
            if (Schema::hasColumn('planos_mensais', 'assinatura_id')) {
                $table->dropForeign(['assinatura_id']);
                $table->dropColumn('assinatura_id');
            }
        });
    }
};
