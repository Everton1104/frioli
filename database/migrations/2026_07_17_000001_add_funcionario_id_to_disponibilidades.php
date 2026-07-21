<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Disponibilidade deixa de ser da barbearia toda e passa a ser POR BARBEIRO.
 *
 * - adiciona `funcionario_id` (FK→users, nullOnDelete);
 * - troca a unique (data,hora) por (funcionario_id,data,hora) — agora cada barbeiro
 *   tem a própria grade independente;
 * - MIGRA os slots globais existentes: duplica as linhas atuais para cada barbeiro
 *   (func=1, excluido=0), e (se houver ≥1 barbeiro) remove as linhas originais sem dono.
 *   Assim cada barbeiro herda o horário hoje configurado e nada quebra. Backfill em loop
 *   PHP p/ ser agnóstico a SQLite/MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disponibilidades', function (Blueprint $table) {
            $table->unsignedBigInteger('funcionario_id')->nullable()->after('created_by');
            $table->foreign('funcionario_id')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->index('funcionario_id');
        });

        // Troca a unique: (data,hora) -> (funcionario_id,data,hora)
        Schema::table('disponibilidades', function (Blueprint $table) {
            $table->dropUnique('disponibilidades_data_hora_unique');
        });

        $this->migrarSlotsPorBarbeiro();

        Schema::table('disponibilidades', function (Blueprint $table) {
            $table->unique(['funcionario_id', 'data', 'hora'], 'disponibilidades_func_data_hora_unique');
        });
    }

    public function down(): void
    {
        Schema::table('disponibilidades', function (Blueprint $table) {
            $table->dropForeign(['funcionario_id']);
            $table->dropIndex('disponibilidades_func_data_hora_unique'); // unique sai como index
        });

        // Volta a unique original (data,hora). Atenção: pode falhar se existirem duplicatas
        // (slots idênticos para >1 barbeiro) — neste caso limpe manualmente antes do rollback.
        try {
            Schema::table('disponibilidades', function (Blueprint $table) {
                $table->unique(['data', 'hora'], 'disponibilidades_data_hora_unique');
            });
        } catch (\Throwable $e) {
            // ignora: mantém sem unique original em rollback de emergência
        }

        Schema::table('disponibilidades', function (Blueprint $table) {
            $table->dropColumn('funcionario_id');
        });
    }

    /*
     * Duplica os slots globais (funcionario_id NULL) para cada barbeiro. Se houver ≥1
     * barbeiro, remove os originais sem dono (redundantes). Se não houver barbeiro algum,
     * mantém os originais como "template" (a app ignora funcionario_id NULL).
     */
    private function migrarSlotsPorBarbeiro(): void
    {
        $barbeiros = DB::table('users')
            ->where('func', 1)
            ->where('excluido', 0)
            ->pluck('id');

        if ($barbeiros->isEmpty()) {
            return; // sem barbeiros: mantém slots globais como template
        }

        $slots = DB::table('disponibilidades')
            ->whereNull('funcionario_id')
            ->get(['data', 'hora', 'created_by']);

        $now = now();
        foreach ($barbeiros as $fid) {
            foreach ($slots as $s) {
                // unique (funcionario_id,data,hora) ainda não existe neste ponto
                DB::table('disponibilidades')->insert([
                    'funcionario_id' => $fid,
                    'data'           => $s->data,
                    'hora'           => $s->hora,
                    'created_by'     => $s->created_by,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }

        DB::table('disponibilidades')->whereNull('funcionario_id')->delete();
    }
};
