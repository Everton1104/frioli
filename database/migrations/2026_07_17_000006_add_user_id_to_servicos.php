<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Serviço por cliente (Fase 3). `servicos.user_id` NULL = catálogo global (avulso/mensal);
 * definido = serviço personalizado daquele cliente (1 por cliente), com nome+valor+duração
 * configurados pelo admin id=1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('excluido');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
