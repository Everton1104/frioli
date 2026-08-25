<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Pacote mensal multi-serviço (Fase 1) — ITENS INCLUSOS de cada CICLO.
 *
 * Um ciclo (planos_mensais) agora pode incluir vários serviços atômicos, cada um
 * com sua própria quantidade (ex.: combo master = 4 cortes + 4 barbas; ou 2 cortes
 * + 4 barbas). A cada visita confirmada, desconta 1 de cada item com saldo restante.
 *
 * valor_unitario é um snapshot de servicos.valor no momento da compra (proteção
 * contra repreço). plano_mensais.unidades_total/unidades_usadas seguem contando
 * o nº de VISITAS do ciclo (1 visita = 1 de cada serviço incluso que tenha saldo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plano_mensal_servicos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plano_mensal_id');
            $table->unsignedBigInteger('servico_id');
            $table->unsignedSmallInteger('quantidade');
            $table->unsignedSmallInteger('usados')->default(0);
            $table->decimal('valor_unitario', 10, 2)->default(0);
            $table->timestamps();

            $table->foreign('plano_mensal_id')->references('id')->on('planos_mensais')->cascadeOnDelete();
            $table->foreign('servico_id')->references('id')->on('servicos')->cascadeOnDelete();

            // Não repetir o mesmo serviço atômico duas vezes no mesmo ciclo.
            $table->unique(['plano_mensal_id', 'servico_id'], 'plano_item_unique');
            $table->index('servico_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plano_mensal_servicos');
    }
};
