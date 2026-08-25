<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horário comercial POR BARBEIRO (janela que abre ao liberar uma semana).
 * Cada barbeiro pode ter a sua janela (ex.: func1 07:00–18:00, func2 09:00–20:00).
 * Se NULL, cai no padrão global (agenda.comercial_inicio/fim do PageContent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('horario_inicio')->nullable()->after('indicado_em');
            $table->time('horario_fim')->nullable()->after('horario_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['horario_inicio', 'horario_fim']);
        });
    }
};
