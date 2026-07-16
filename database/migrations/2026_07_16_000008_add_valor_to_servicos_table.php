<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preço do serviço — necessário para o booking público (cliente paga ao reservar).
 * Nullable: serviços antigos/sem preço online ficam NULL; o /agendar só oferece
 * serviços com valor > 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->decimal('valor', 10, 2)->nullable()->after('duracao');
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn('valor');
        });
    }
};
