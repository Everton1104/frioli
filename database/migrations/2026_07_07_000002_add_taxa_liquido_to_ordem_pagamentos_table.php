<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordem_pagamentos', function (Blueprint $table) {
            // Custo real do Mercado Pago (comissão + retenção de IR) e líquido
            // recebido, congelados no momento da aprovação do pagamento. Vêm do
            // webhook (fee_details/taxes do MP). Null enquanto não houve pagamento.
            $table->decimal('taxa_mp', 12, 2)->nullable()->after('valor');
            $table->decimal('valor_liquido', 12, 2)->nullable()->after('taxa_mp');
        });
    }

    public function down(): void
    {
        Schema::table('ordem_pagamentos', function (Blueprint $table) {
            $table->dropColumn(['taxa_mp', 'valor_liquido']);
        });
    }
};
