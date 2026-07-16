<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Para cada slot existente em HH:00:00, insere HH:15:00.
 * Para cada slot em HH:30:00, insere HH:45:00.
 * Garante compatibilidade dos dias já configurados com o novo check de 15 min.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            INSERT INTO disponibilidades (data, hora, created_by, created_at, updated_at)
            SELECT
                d.data,
                CASE
                    WHEN d.hora LIKE '%:00:00' THEN REPLACE(d.hora, ':00:00', ':15:00')
                    WHEN d.hora LIKE '%:30:00' THEN REPLACE(d.hora, ':30:00', ':45:00')
                END AS hora_nova,
                d.created_by,
                NOW(),
                NOW()
            FROM disponibilidades d
            WHERE (d.hora LIKE '%:00:00' OR d.hora LIKE '%:30:00')
              AND NOT EXISTS (
                  SELECT 1 FROM disponibilidades d2
                  WHERE d2.data = d.data
                    AND d2.hora = CASE
                        WHEN d.hora LIKE '%:00:00' THEN REPLACE(d.hora, ':00:00', ':15:00')
                        WHEN d.hora LIKE '%:30:00' THEN REPLACE(d.hora, ':30:00', ':45:00')
                    END
              )
        ");
    }

    public function down(): void
    {
        // Remove slots em :15 e :45 que foram inseridos por esta migration
        DB::statement("
            DELETE FROM disponibilidades
            WHERE hora LIKE '%:15:00' OR hora LIKE '%:45:00'
        ");
    }
};
