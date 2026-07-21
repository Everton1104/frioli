<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Cada agendamento passa a ter um barbeiro (funcionário) responsável. Coluna nullable:
 * agendamentos legados ficam NULL (órfãos) — só o admin os vê e pode reatribuir. A partir
 * daqui, todo agendamento criado (booking público ou staff) vem com funcionario_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->unsignedBigInteger('funcionario_id')->nullable()->after('servico_id');
            $table->foreign('funcionario_id')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->index('funcionario_id');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropForeign(['funcionario_id']);
            $table->dropIndex(['funcionario_id']);
            $table->dropColumn('funcionario_id');
        });
    }
};
