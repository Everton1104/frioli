<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Pacote mensal com opção de recorrência (cartão salvo via assinatura InfinitePay):
 * - servicos.link_inscricao: URL do link de inscrição gerada no painel da InfinitePay
 *   (serviços mensais/recorrentes). O sistema só guarda e envia ao cliente; a cobrança
 *   recorrente roda na InfinitePay.
 * - planos_mensais.recorrente: true quando é assinatura (cartão salvo) em vez de pacote
 *   avulso pré-pago.
 * - planos_mensais.assinatura_id: id da assinatura na InfinitePay (preenchido pelo
 *   webhook quando chegar — formato do payload ainda a confirmar empiricamente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->string('link_inscricao')->nullable()->after('valor');
        });

        Schema::table('planos_mensais', function (Blueprint $table) {
            $table->boolean('recorrente')->default(false)->after('status');
            $table->string('assinatura_id')->nullable()->after('recorrente');
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn('link_inscricao');
        });
        Schema::table('planos_mensais', function (Blueprint $table) {
            $table->dropColumn(['recorrente', 'assinatura_id']);
        });
    }
};
