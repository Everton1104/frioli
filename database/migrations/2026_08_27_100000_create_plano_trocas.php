<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Solicitações de troca de dia/horário do plano mensal (slot fixo) feitas pelo
 * próprio cliente. A troca NÃO é aplicada na hora: fica "pendente" até o staff
 * aprovar (aplicar) ou recusar — o slot do plano afeta a agenda do barbeiro e
 * os agendamentos futuros do ciclo, então a palavra final é da barbearia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plano_trocas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assinatura_id')->constrained('assinaturas_mensais')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 0=Dom .. 6=Sáb
            $table->time('hora');
            $table->string('status', 20)->default('pendente'); // pendente|aprovada|recusada
            $table->timestamp('resolvido_em')->nullable();
            $table->foreignId('resolvido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plano_trocas');
    }
};
