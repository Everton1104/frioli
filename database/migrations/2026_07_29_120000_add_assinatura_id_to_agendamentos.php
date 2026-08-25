<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fase 2 — slot fixo reservado mesmo sem saldo (aguardando renovação).
 *
 * agendamentos.assinatura_id permite criar agendamentos de RESERVA (status
 * reserva_renovacao) ligados diretamente à assinatura (não a um ciclo consumido):
 * ocupam o slot (bloqueiam outros clientes) e sinalizam ao staff que o cliente
 * está sem saldo e precisa renovar. Também é setado nos agendamentos pagos dos
 * planos, para a checagem "assinatura já tem visita esta semana?" ser uniforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            if (!Schema::hasColumn('agendamentos', 'assinatura_id')) {
                $table->unsignedBigInteger('assinatura_id')->nullable()->after('plano_mensal_id');
                $table->foreign('assinatura_id')->references('id')->on('assinaturas_mensais')->nullOnDelete();
                $table->index('assinatura_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            if (Schema::hasColumn('agendamentos', 'assinatura_id')) {
                $table->dropForeign(['assinatura_id']);
                $table->dropIndex(['assinatura_id']);
                $table->dropColumn('assinatura_id');
            }
        });
    }
};
