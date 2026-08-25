<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Pacote mensal — Fase 3: distribuição semanal + visita extra (5ª semana).
 *
 *  - planos_mensais.distribuicao: JSON nullable — agenda ordenada de visitas do
 *    ciclo. Cada elemento é a lista de servico_ids daquela semana.
 *    Ex.: [[1,2],[1],[1,2],[1]]  =>  sem1 corte+barba, sem2 corte, sem3 corte+barba, sem4 corte.
 *    NULL = ciclo legado (abate uniforme: 1 de cada serviço por visita).
 *
 *  - planos_mensais.valores_extra: JSON nullable — preço avulso por serviço para a
 *    visita extra da 5ª semana. Ex.: {"1": 40, "2": 30} (corte=40, barba=30).
 *    NULL/vazio = sem oferta de extra (caí no aviso "sem saldo" mudo atual).
 *
 *  - agendamentos.plano_ordem: unsignedTinyInteger nullable — posição (1-based) da
 *    visita na distribuição, gravada ao materializar. Permite descontar os serviços
 *    EXATOS daquela visita (e não deslocar a composição ao cancelar uma do meio).
 *    NULL = avulso ou legado.
 *
 * Compatível com SQLite (dev) e MySQL (prod): json() vira TEXT/JSON; after() é
 * no-op em SQLite. Guards com Schema::hasColumn (idempotente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos_mensais', function (Blueprint $table) {
            if (!Schema::hasColumn('planos_mensais', 'distribuicao')) {
                $table->json('distribuicao')->nullable()->after('dia_renovacao');
            }
            if (!Schema::hasColumn('planos_mensais', 'valores_extra')) {
                $table->json('valores_extra')->nullable()->after('distribuicao');
            }
        });

        Schema::table('agendamentos', function (Blueprint $table) {
            if (!Schema::hasColumn('agendamentos', 'plano_ordem')) {
                $table->unsignedTinyInteger('plano_ordem')->nullable()->after('plano_mensal_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            if (Schema::hasColumn('agendamentos', 'plano_ordem')) {
                $table->dropColumn('plano_ordem');
            }
        });

        Schema::table('planos_mensais', function (Blueprint $table) {
            if (Schema::hasColumn('planos_mensais', 'valores_extra')) {
                $table->dropColumn('valores_extra');
            }
            if (Schema::hasColumn('planos_mensais', 'distribuicao')) {
                $table->dropColumn('distribuicao');
            }
        });
    }
};
