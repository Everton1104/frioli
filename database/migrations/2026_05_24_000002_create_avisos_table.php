<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['cancelamento', 'reagendamento']);
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('servico_id')->constrained('servicos');
            $table->dateTime('data_antiga');
            $table->dateTime('data_nova')->nullable();
            $table->timestamp('dispensado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos');
    }
};
