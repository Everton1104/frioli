<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Guarda a URL completa do link InfinitePay gerado para a ordem. Necessário para
// REUSAR o mesmo link a cada clique (uma ordem = um link) — o link da InfinitePay
// só é pagável uma vez, então reusar previne pagamento em duplicidade. A URL não é
// reconstruível a partir do slug (token `lenc` não-derivável), por isso persistimos.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordem_pagamentos', function (Blueprint $table) {
            $table->string('infinitepay_url', 500)->nullable()->after('infinitepay_slug');
        });
    }

    public function down(): void
    {
        Schema::table('ordem_pagamentos', function (Blueprint $table) {
            $table->dropColumn('infinitepay_url');
        });
    }
};
