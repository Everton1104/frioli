<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Passa o controle de créditos a ser por PACOTE (linha de creditos_servico),
// permitindo que o mesmo cliente tenha mais de um pacote do mesmo serviço.
// agendamentos.credito_servico_id deixa de guardar o id do serviço e passa a
// guardar o id da linha de creditos_servico.
return new class extends Migration
{
    public function up(): void
    {
        // O índice normal precisa existir ANTES de remover o unique: a FK de
        // user_id se apoia no unique e o MariaDB recusa o drop sem substituto.
        Schema::table('creditos_servico', function (Blueprint $table) {
            $table->index(['user_id', 'servico_id']);
        });
        Schema::table('creditos_servico', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'servico_id']);
        });

        // Converte os agendamentos existentes: credito_servico_id (id do serviço)
        // → id do pacote correspondente do cliente. Sem pacote: deixa de consumir.
        $agendamentos = DB::table('agendamentos')->whereNotNull('credito_servico_id')->get();
        foreach ($agendamentos as $a) {
            $credito = DB::table('creditos_servico')
                ->where('user_id', $a->user_id)
                ->where('servico_id', $a->credito_servico_id)
                ->orderBy('id')
                ->first();

            DB::table('agendamentos')->where('id', $a->id)->update([
                'credito_servico_id' => $credito->id ?? null,
                'consome_credito'    => $credito ? 1 : 0,
            ]);
        }
    }

    public function down(): void
    {
        // Volta a apontar para o id do serviço do pacote.
        $agendamentos = DB::table('agendamentos')->whereNotNull('credito_servico_id')->get();
        foreach ($agendamentos as $a) {
            $credito = DB::table('creditos_servico')->where('id', $a->credito_servico_id)->first();
            DB::table('agendamentos')->where('id', $a->id)->update([
                'credito_servico_id' => $credito->servico_id ?? null,
            ]);
        }

        Schema::table('creditos_servico', function (Blueprint $table) {
            $table->unique(['user_id', 'servico_id']);
        });
        Schema::table('creditos_servico', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'servico_id']);
        });
    }
};
