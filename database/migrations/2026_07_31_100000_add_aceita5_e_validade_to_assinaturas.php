<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Pacote mensal — Fase 4: preferência 4-vs-5 semanas + validade de consumo.
 *
 *  - assinaturas_mensais.aceita_5: bool — em meses de 5 semanas, o cliente aceita
 *    a 5ª unidade (extra, com desconto). Se false, cap em 4 (default).
 *  - assinaturas_mensais.servico_extra_id: qual serviço da 5ª unidade default
 *    (nullable; fallback = último serviço da distribuição).
 *  - assinaturas_mensais.validade_dias: dias para consumir as unidades (default 45).
 *
 * Compatível sqlite/mysql. Guards Schema::hasColumn (idempotente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assinaturas_mensais', function (Blueprint $table) {
            if (!Schema::hasColumn('assinaturas_mensais', 'aceita_5')) {
                $table->boolean('aceita_5')->default(false)->after('dia_renovacao');
            }
            if (!Schema::hasColumn('assinaturas_mensais', 'servico_extra_id')) {
                $table->unsignedBigInteger('servico_extra_id')->nullable()->after('aceita_5');
                $table->foreign('servico_extra_id')->references('id')->on('servicos')->nullOnDelete();
            }
            if (!Schema::hasColumn('assinaturas_mensais', 'validade_dias')) {
                $table->unsignedSmallInteger('validade_dias')->default(45)->after('servico_extra_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assinaturas_mensais', function (Blueprint $table) {
            if (Schema::hasColumn('assinaturas_mensais', 'validade_dias')) {
                $table->dropColumn('validade_dias');
            }
            if (Schema::hasColumn('assinaturas_mensais', 'servico_extra_id')) {
                $table->dropForeign(['servico_extra_id']);
                $table->dropColumn('servico_extra_id');
            }
            if (Schema::hasColumn('assinaturas_mensais', 'aceita_5')) {
                $table->dropColumn('aceita_5');
            }
        });
    }
};
