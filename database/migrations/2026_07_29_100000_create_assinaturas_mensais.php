<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Pacote mensal multi-serviço (Fase 1) — o SLOT FIXO PERSISTENTE.
 *
 * Uma assinatura representa o dia/horário fixo semanal reservado para um cliente
 * com um barbeiro (barbeiro + dia da semana + horário). Ela sobrevive ao fim do
 * saldo de cada ciclo: enquanto ativa, o slot segue reservado para o cliente.
 *
 * Cada renovação/mês comprado é um CICLO (linha em planos_mensais) ligado a esta
 * assinatura, e os serviços inclusos do ciclo vivem em plano_mensal_servicos.
 *
 * Status: ativo / pausado / cancelado (aguardando_renovacao chega na Fase 2).
 * dia_renovacao (1-31) é o dia preferido de renovar — usado a partir da Fase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assinaturas_mensais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('funcionario_id')->nullable();
            $table->unsignedTinyInteger('dia_semana')->comment('0=Dom .. 6=Sa');
            $table->time('hora');
            $table->unsignedTinyInteger('dia_renovacao')->nullable()->comment('1-31, dia preferido de renovacao (Fase 2)');
            $table->unsignedBigInteger('servico_base_id')->nullable()->comment('combo master (nome/duracao do slot)');
            $table->string('status', 40)->default('ativo');
            $table->datetime('cancelado_em')->nullable();
            $table->string('observacao')->nullable();
            $table->boolean('legado')->default(false)->comment('true se veio do backfill de planos legados');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('funcionario_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('servico_base_id')->references('id')->on('servicos')->nullOnDelete();

            // Conflito (1 cliente por slot semanal de cada barbeiro) e validado em query
            // sobre status ativos — NAO e unique, para permitir re-reserva apos cancelamento.
            $table->index(['funcionario_id', 'dia_semana', 'hora'], 'assinatura_slot_idx');
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assinaturas_mensais');
    }
};
