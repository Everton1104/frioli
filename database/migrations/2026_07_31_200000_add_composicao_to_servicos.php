<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Pacote mensal — Fase 5: composição do combo master definida no cadastro do serviço.
 *
 * Para serviços mensais (recorrente=1), a composição do pacote passa a viver aqui
 * (antes era montada a cada criação de plano). Ao vincular o combo a um cliente, o
 * plano herda tudo.
 *
 *  - servicos.composicao: JSON {servico_id: valor_com_desconto} — ex.: {"1": 60, "2": 20}.
 *  - servicos.distribuicao: JSON array de 4 visitas base, cada uma lista de servico_ids
 *    — ex.: [[1,2],[1],[1,2],[1]].
 *
 * Compatível sqlite/mysql. Guards Schema::hasColumn (idempotente). Nullable: combos
 * antigos / serviços comuns seguem sem composição (path legado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            if (!Schema::hasColumn('servicos', 'composicao')) {
                $table->json('composicao')->nullable()->after('valor');
            }
            if (!Schema::hasColumn('servicos', 'distribuicao')) {
                $table->json('distribuicao')->nullable()->after('composicao');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            if (Schema::hasColumn('servicos', 'distribuicao')) {
                $table->dropColumn('distribuicao');
            }
            if (Schema::hasColumn('servicos', 'composicao')) {
                $table->dropColumn('composicao');
            }
        });
    }
};
