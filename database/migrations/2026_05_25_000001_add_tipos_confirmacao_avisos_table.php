<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE avisos MODIFY COLUMN tipo ENUM('cancelamento','reagendamento','confirmacao','reagendamento_solicitado') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE avisos MODIFY COLUMN tipo ENUM('cancelamento','reagendamento') NOT NULL");
    }
};
