<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Cliente "indicado": marcado manualmente pelo staff (adm/func) na ficha do cliente.
 * Um cliente indicado pode comprar/renovar plano mensal pelo site (auto-atendimento),
 * em vez de só pedir via WhatsApp. Não-indicado continua vendo apenas serviços avulsos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('indicado')->default(false)->after('penalizado_em');
            $table->datetime('indicado_em')->nullable()->after('indicado');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['indicado', 'indicado_em']);
        });
    }
};
