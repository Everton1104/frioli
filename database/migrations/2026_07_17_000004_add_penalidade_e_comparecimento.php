<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Frente D — pagar no local + penalidade de no-show:
 *
 * - users.penalizado (bool) + penalizado_em: cliente marcado como penalizado pelo barbeiro
 *   (botão "Não compareceu"). Penalizado não consegue novos agendamentos nem comprar
 *   pacotes até adm/func remover a penalidade.
 * - agendamentos.compareceu (nullable bool): NULL=pendente, 1=compareceu, 0=não compareceu.
 *   Marcado pelo barbeiro na própria agenda.
 * - agendamentos.pagar_no_local (bool): agendamento sem pagamento online (cliente paga na
 *   barbearia). Não gera OrdemPagamento; no recusar, não há reembolso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('penalizado')->default(false)->after('excluido');
            $table->datetime('penalizado_em')->nullable()->after('penalizado');
        });

        Schema::table('agendamentos', function (Blueprint $table) {
            $table->boolean('compareceu')->nullable()->after('status'); // null/1/0
            $table->boolean('pagar_no_local')->default(false)->after('compareceu');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn(['compareceu', 'pagar_no_local']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['penalizado', 'penalizado_em']);
        });
    }
};
