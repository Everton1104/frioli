<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percentual de repasse ao funcionário (barbeiro) sobre o valor do serviço.
 * Ex.: corte R$100 com repasse_percent = 50 → o barbeiro recebe R$50.
 * Nullable: serviços sem repasse configurado ficam NULL (repasse 0 no financeiro).
 * Vale para serviços avulsos e para os combos mensais (recorrente).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('servicos', 'repasse_percent')) {
            Schema::table('servicos', function (Blueprint $table) {
                $table->decimal('repasse_percent', 5, 2)->nullable()->after('duracao');
            });
        }
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn('repasse_percent');
        });
    }
};
