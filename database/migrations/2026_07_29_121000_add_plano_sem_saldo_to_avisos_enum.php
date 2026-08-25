<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Fase 2 — aviso "cliente sem saldo no plano" (slot reservado aguardando renovação).
 * Adiciona o valor 'plano_sem_saldo' ao ENUM avisos.tipo (MySQL/MariaDB). Não dispara
 * WhatsApp (AvisoObserver só notifica tipos reagendamento*); aparece no painel do staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE avisos MODIFY COLUMN tipo ENUM('cancelamento','reagendamento','confirmacao','reagendamento_solicitado','plano_sem_saldo') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE avisos MODIFY COLUMN tipo ENUM('cancelamento','reagendamento','confirmacao','reagendamento_solicitado') NOT NULL");
    }
};
