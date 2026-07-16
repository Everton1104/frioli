<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            // Serviço cujo saldo este agendamento consome (pode diferir do servico_id,
            // ex.: um "Encaixe" que desconta de um pacote do cliente). NULL = não desconta.
            $table->unsignedBigInteger('credito_servico_id')->nullable()->after('consome_credito');
            $table->index('credito_servico_id');
        });

        // Agendamentos já contabilizados consomem o próprio serviço.
        DB::table('agendamentos')
            ->where('consome_credito', 1)
            ->update(['credito_servico_id' => DB::raw('servico_id')]);
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropIndex(['credito_servico_id']);
            $table->dropColumn('credito_servico_id');
        });
    }
};
