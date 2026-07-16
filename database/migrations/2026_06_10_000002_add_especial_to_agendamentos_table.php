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
            // "Especial" (encaixe) deixa de ser um serviço dedicado (visivel_cliente=0)
            // e passa a ser uma propriedade do agendamento: pode ser aplicado a qualquer
            // serviço, pode ser colocado sobre outros agendamentos (não conflita) e o
            // cliente não pode reagendá-lo.
            $table->boolean('especial')->default(0)->after('consome_credito');
        });

        // Migra os agendamentos antigos: os que usavam um serviço de staff
        // (visivel_cliente=0) passam a ser marcados como especiais.
        DB::table('agendamentos')
            ->whereIn('servico_id', function ($q) {
                $q->select('id')->from('servicos')->where('visivel_cliente', 0);
            })
            ->update(['especial' => 1]);
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn('especial');
        });
    }
};
