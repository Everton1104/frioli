<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lembrete_consulta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agendamento_id')->constrained('agendamentos')->cascadeOnDelete();
            $table->enum('tipo', ['24h', '2h']);
            $table->enum('status', ['enviado', 'erro'])->default('enviado');
            $table->text('erro_msg')->nullable();
            $table->timestamps();

            $table->unique(['agendamento_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lembrete_consulta');
    }
};
