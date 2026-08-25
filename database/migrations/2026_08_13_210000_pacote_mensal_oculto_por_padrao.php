<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Pacote mensal (serviço recorrente) some da visão do cliente POR PADRÃO. O adm
// habilita a exibição (valores + itens) por serviço, no editor do combo, via
// checkbox "mostrar_clientes" → coluna `visivel_cliente` (reutilizada: para
// avulsos continua sempre 1). Esta migration oculta os mensais já existentes.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('servicos')->where('recorrente', 1)->update(['visivel_cliente' => 0]);
    }

    public function down(): void
    {
        DB::table('servicos')->where('recorrente', 1)->update(['visivel_cliente' => 1]);
    }
};
