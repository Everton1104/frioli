<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Frente C (Fase 2) — Pacote mensal fixo.
 *
 * Um plano mensal é um slot fixo semanal (barbeiro + dia da semana + horário) que o
 * cliente compra para um mês-alvo. Quando o barbeiro/adm abre a semana (salvarSemana),
 * os slots fixos dos planos ativos viram agendamentos confirmados automaticamente,
 * consumindo 1 unidade por ocorrência até esgotar o pacote.
 *
 * - planos_mensais: o plano em si (cliente, serviço, barbeiro, dia/semana, hora, mês,
 *   unidades total/usadas, valor, status e a ordem de pagamento).
 * - agendamentos.plano_mensal_id: vincula o agendamento auto-reservado ao plano.
 * - ordem_pagamentos.plano_mensal_id: vincula a ordem de compra ao plano (ativação no webhook).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos_mensais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('servico_id');
            $table->unsignedBigInteger('funcionario_id')->nullable();
            $table->unsignedTinyInteger('dia_semana')->comment('0=Dom .. 6=Sáb');
            $table->time('hora');
            $table->date('mes')->comment('1º dia do mês-alvo');
            $table->unsignedSmallInteger('unidades_total');
            $table->unsignedSmallInteger('unidades_usadas')->default(0);
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->string('status', 40)->default('aguardando_pagamento');
            $table->unsignedBigInteger('ordem_pagamento_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('servico_id')->references('id')->on('servicos')->cascadeOnDelete();
            $table->foreign('funcionario_id')->references('id')->on('users')->nullOnDelete();
            // Conflito (1 cliente por slot semanal de cada barbeiro no mês) é validado em query.
            $table->index(['funcionario_id', 'dia_semana', 'hora', 'mes'], 'planos_mensais_slot_idx');
            $table->index('status');
        });

        Schema::table('agendamentos', function (Blueprint $table) {
            $table->unsignedBigInteger('plano_mensal_id')->nullable()->after('credito_servico_id');
            $table->foreign('plano_mensal_id')->references('id')->on('planos_mensais')->nullOnDelete();
            $table->index('plano_mensal_id');
        });

        Schema::table('ordem_pagamentos', function (Blueprint $table) {
            $table->unsignedBigInteger('plano_mensal_id')->nullable()->after('agendamento_id');
            $table->foreign('plano_mensal_id')->references('id')->on('planos_mensais')->nullOnDelete();
            $table->index('plano_mensal_id');
        });
    }

    public function down(): void
    {
        Schema::table('ordem_pagamentos', function (Blueprint $table) {
            $table->dropForeign(['plano_mensal_id']);
            $table->dropIndex(['plano_mensal_id']);
            $table->dropColumn('plano_mensal_id');
        });

        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropForeign(['plano_mensal_id']);
            $table->dropIndex(['plano_mensal_id']);
            $table->dropColumn('plano_mensal_id');
        });

        Schema::dropIfExists('planos_mensais');
    }
};
