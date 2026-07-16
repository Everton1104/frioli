<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Serviço de "retirada de medicamentos": não pede confirmação de consulta.
        // Em vez do lembrete com botões, o staff dispara manualmente o aviso de
        // "pedido pronto para retirada" (template UTILITY).
        Schema::table('servicos', function (Blueprint $table) {
            $table->boolean('retirada')->default(false)->after('visivel_cliente');
        });

        // Marca o serviço já existente para o novo comportamento valer de imediato.
        DB::table('servicos')
            ->whereRaw('LOWER(descricao) LIKE ?', ['%retirada%'])
            ->update(['retirada' => true]);
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn('retirada');
        });
    }
};
