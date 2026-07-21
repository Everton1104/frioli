<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Pacotes passam a ter validade: 30 dias a partir da compra (criação/atribuição).
 * `expira_em` = created_at + 30 dias. Pacotes expirados com saldo restante não somem —
 * ficam marcados como "Negociar" (a app deriva o estado). Backfill em loop PHP para valer
 * em SQLite e MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos_servico', function (Blueprint $table) {
            $table->datetime('expira_em')->nullable()->after('quantidade');
            $table->index('expira_em');
        });

        // Backfill: pacotes existentes vencem 30 dias após a criação.
        foreach (DB::table('creditos_servico')->whereNull('expira_em')->lazyById() as $pacote) {
            DB::table('creditos_servico')
                ->where('id', $pacote->id)
                ->update(['expira_em' => \Illuminate\Support\Carbon::parse($pacote->created_at)->addDays(30)]);
        }
    }

    public function down(): void
    {
        Schema::table('creditos_servico', function (Blueprint $table) {
            $table->dropIndex(['expira_em']);
            $table->dropColumn('expira_em');
        });
    }
};
