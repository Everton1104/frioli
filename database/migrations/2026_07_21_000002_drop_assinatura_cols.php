<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Descarta as colinas da assinatura InfinitePay:
 * - servicos.link_inscricao
 * - planos_mensais.assinatura_id
 *
 * Motivo: a InfinitePay NÃO tem webhook de assinatura (só link avulso), então o
 * modelo voltou a ser link mensal avulso com renovação pelo sistema (command).
 * Mantém planos_mensais.recorrente (flag de renovação automática mensal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            if (Schema::hasColumn('servicos', 'link_inscricao')) {
                $table->dropColumn('link_inscricao');
            }
        });
        Schema::table('planos_mensais', function (Blueprint $table) {
            if (Schema::hasColumn('planos_mensais', 'assinatura_id')) {
                $table->dropColumn('assinatura_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->string('link_inscricao')->nullable()->after('valor');
        });
        Schema::table('planos_mensais', function (Blueprint $table) {
            $table->string('assinatura_id')->nullable()->after('recorrente');
        });
    }
};
