<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creditos_servico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();
            $table->unsignedInteger('quantidade')->default(0); // total de unidades contratadas
            $table->timestamps();

            $table->unique(['user_id', 'servico_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creditos_servico');
    }
};
