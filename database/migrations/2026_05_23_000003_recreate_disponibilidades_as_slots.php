<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Recria a tabela disponibilidades com estrutura por slot (data + hora)
// substituindo o modelo anterior de intervalo (hora_inicio / hora_fim).
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('disponibilidades');

        Schema::create('disponibilidades', function (Blueprint $table) {
            $table->id();
            $table->date('data');
            $table->time('hora');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['data', 'hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilidades');
    }
};
