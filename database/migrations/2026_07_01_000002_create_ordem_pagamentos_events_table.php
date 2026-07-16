<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Histórico de eventos por ordem (criação, tentativas de pagamento, webhooks).
// Garante idempotência: um mesmo payment_id_mp+status reprocessado não dobra nada.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ordem_pagamentos_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_id')->constrained('ordem_pagamentos')->cascadeOnDelete();
            $table->string('payment_id_mp')->nullable()->index();
            $table->string('status')->nullable();
            $table->string('origem')->default('manual'); // manual|webhook|checkout
            $table->json('payload')->nullable();
            $table->timestamps();
            // Evita registrar o mesmo evento (mesma ordem + pagamento + status) duas vezes.
            $table->unique(['ordem_id', 'payment_id_mp', 'status'], 'upe_ordem_pagamento_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordem_pagamentos_events');
    }
};
