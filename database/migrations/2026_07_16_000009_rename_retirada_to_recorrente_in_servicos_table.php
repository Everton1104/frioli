<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renomeia o flag "retirada" (port da clínica anavertuan — não se aplica à barbearia)
 * para "recorrente" (gancho do futuro recurso de assinatura/pagamento recorrente).
 *
 * renameColumn é nativo no Laravel 11+ (MySQL/MariaDB), sem doctrine/dbal.
 * O reset para false descarta o backfill antigo (serviços com "retirada" no nome);
 * nenhum serviço deve nascer recorrente hoje (o recurso de assinatura é futuro).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->renameColumn('retirada', 'recorrente');
        });

        DB::table('servicos')->update(['recorrente' => false]);
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->renameColumn('recorrente', 'retirada');
        });
    }
};
