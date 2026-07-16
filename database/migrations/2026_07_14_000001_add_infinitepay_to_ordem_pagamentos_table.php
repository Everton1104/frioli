<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adiciona suporte ao gateway InfinitePay (Link de Pagamento / redirect):
//  - infinitepay_slug: identificador do link gerado (ancora para o payment_check).
//  - gateway: qual gateway originou a ordem ('mercadopago' histórico | 'infinitepay').
// O payment_id_mp é reaproveitado para guardar o transaction_nsu do InfinitePay
// (ver OrdemPagamento::$fillable). Mantém histórico do MP intacto.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordem_pagamentos', function (Blueprint $table) {
            $table->string('infinitepay_slug', 100)->nullable()->after('external_reference');
            $table->string('gateway', 20)->default('mercadopago')->after('infinitepay_slug');

            $table->index('infinitepay_slug');
        });
    }

    public function down(): void
    {
        Schema::table('ordem_pagamentos', function (Blueprint $table) {
            $table->dropIndex(['infinitepay_slug']);
            $table->dropColumn(['infinitepay_slug', 'gateway']);
        });
    }
};
