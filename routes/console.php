<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Envia lembretes de consulta via WhatsApp.
// Garanta que o cron do servidor esteja configurado:
//   * * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1

// Lembrete da véspera: todo dia às 17h, para todas as consultas do dia seguinte.
// (Unificado: um único lembrete que já vale como confirmação oficial.)
Schedule::command('lembretes:enviar')
    ->dailyAt('17:00')
    ->withoutOverlapping()
    ->runInBackground();

// Reconciliação InfinitePay: backstop para webhook perdido. A cada 5 min consulta
// (payment_check) as ordens pagáveis com link gerado e aplica o status real.
Schedule::command('infinitepay:reconciliar')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Booking público: libera slots de agendamentos "aguardando_pagamento" não pagos
// em 15 min (cliente reservou e abandonou).
Schedule::command('agendamentos:expirar-pendentes')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Fim do mês: planos mensais com unidades não usadas → expiram (Negociar).
Schedule::command('planos:expirar')->dailyAt('03:17')->withoutOverlapping();

// Renovação mensal: nos últimos 10 dias do mês gera o próximo mês dos planos
// recorrentes ativos (link avulso) e avisa o cliente pagar. O próprio command
// checa a janela e é idempotente.
Schedule::command('planos:renovar')->dailyAt('04:00')->withoutOverlapping();

// Desconto de visitas: ao abrir a semana (salvarSemana) E backstop diário às 23:30
// (caso o staff abra tudo e não interaja mais). Ambos chamam o mesmo método idempotente.
Schedule::command('planos:descontar-visitas')->dailyAt('23:30')->withoutOverlapping();
