<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ordens de pagamento criadas pelo staff (adm/func) e pagas pelo paciente via
// Mercado Pago (Checkout Transparente). O valor e o limite de parcelas são
// definidos aqui, no cadastro — nunca confiados ao front no momento do pagamento.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ordem_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // paciente
            $table->foreignId('criado_por')->constrained('users');                 // adm/func que criou
            $table->decimal('valor', 10, 2);
            $table->string('descricao');
            $table->unsignedTinyInteger('max_parcelas')->default(1);               // ex.: 6x sem juros
            // aberta|processando|approved|pending|rejected|cancelled|refunded
            $table->string('status')->default('aberta');
            $table->string('external_reference')->unique();                        // = (string) id
            $table->string('payment_id_mp')->nullable()->index();                  // id retornado pelo MP
            $table->string('payment_method_id')->nullable();                       // visa, master...
            $table->unsignedTinyInteger('installments')->nullable();
            $table->string('status_detail')->nullable();                           // motivo retornado pelo MP
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordem_pagamentos');
    }
};
