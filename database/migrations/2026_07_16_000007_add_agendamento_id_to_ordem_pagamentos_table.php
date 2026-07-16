<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo ordem de pagamento ↔ agendamento (booking público). Antes a ordem era só
 * {user_id, valor, descricao}; agora pode referenciar o agendamento que a originou,
 * permitindo ao webhook de pagamento avançar o status do agendamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordem_pagamentos', function (Blueprint $table) {
            $table->foreignId('agendamento_id')
                ->nullable()
                ->after('user_id')
                ->constrained('agendamentos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ordem_pagamentos', function (Blueprint $table) {
            $table->dropForeign(['agendamento_id']);
            $table->dropColumn('agendamento_id');
        });
    }
};
