<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avisos', function (Blueprint $table) {
            // Marca se o aviso se refere a um agendamento especial (encaixe), para
            // exibir o alerta de "horário especial" sem depender do serviço.
            $table->boolean('especial')->default(0)->after('servico_id');
        });
    }

    public function down(): void
    {
        Schema::table('avisos', function (Blueprint $table) {
            $table->dropColumn('especial');
        });
    }
};
