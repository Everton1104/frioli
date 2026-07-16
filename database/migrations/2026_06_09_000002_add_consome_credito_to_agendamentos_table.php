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
            $table->boolean('consome_credito')->default(false)->after('confirmado');
        });

        // Agendamentos já existentes contam como 1 unidade consumida.
        DB::table('agendamentos')->update(['consome_credito' => true]);
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn('consome_credito');
        });
    }
};
