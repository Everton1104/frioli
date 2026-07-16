<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            // Pré-confirmação: cliente confirmou pelo lembrete da véspera.
            $table->timestamp('pre_confirmado_em')->nullable()->after('confirmado');
            // Confirmação oficial: cliente confirmou pelo lembrete de 2h (ou staff confirmou manualmente).
            $table->timestamp('confirmado_em')->nullable()->after('pre_confirmado_em');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn(['pre_confirmado_em', 'confirmado_em']);
        });
    }
};
