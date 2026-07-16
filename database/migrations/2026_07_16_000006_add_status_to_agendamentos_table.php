<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ciclo de vida do agendamento self-service (booking público).
 * Valores: confirmado (default/padrão do staff) | aguardando_pagamento |
 * pago_aguardando | recusado | cancelado. A checagem de conflito (horarios/store)
 * trata como ocupante do slot qualquer um com status em
 * (confirmado, aguardando_pagamento, pago_aguardando).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->string('status')->default('confirmado')->after('confirmado_em');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
