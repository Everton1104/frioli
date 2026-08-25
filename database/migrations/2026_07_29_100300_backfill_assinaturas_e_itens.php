<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Pacote mensal multi-serviço (Fase 1) — BACKFILL de planos legados.
 *
 * Cada planos_mensais legado (servico_id único, mês-calendário) é envolvido numa
 * assinaturas_mensais (1 por slot fixo: user+func+dia+hora, mesmo com vários meses)
 * e ganha 1 item em plano_mensal_servicos com a quantidade/usados que já tinha.
 *
 * Idempotente: só processa whereNull('assinatura_id'). Via DB::table() direto
 * (sem eventos de model). Não resolve colisões pré-existentes de slot — rodar o
 * relatório de duplicados (func,dia,hora,mes ativos) antes, se houver.
 */
return new class extends Migration
{
    public function up(): void
    {
        $map = []; // "user|func|dia|hora" => assinatura_id
        $now = Carbon::now();

        DB::table('planos_mensais')->whereNull('assinatura_id')->orderBy('id')
            ->chunkById(200, function ($planos) use (&$map, $now) {
                DB::transaction(function () use ($planos, &$map, $now) {
                    foreach ($planos as $plano) {
                        $key = $plano->user_id . '|' . $plano->funcionario_id . '|' . $plano->dia_semana . '|' . $plano->hora;

                        if (!isset($map[$key])) {
                            $ex = DB::table('assinaturas_mensais')
                                ->where('user_id', $plano->user_id)
                                ->where('funcionario_id', $plano->funcionario_id)
                                ->where('dia_semana', $plano->dia_semana)
                                ->where('hora', $plano->hora)
                                ->orderByDesc('id')->first();

                            $map[$key] = $ex?->id ?: DB::table('assinaturas_mensais')->insertGetId([
                                'user_id'         => $plano->user_id,
                                'funcionario_id'  => $plano->funcionario_id,
                                'dia_semana'       => $plano->dia_semana,
                                'hora'            => $plano->hora,
                                'dia_renovacao'   => null,
                                'servico_base_id' => $plano->servico_id,
                                'status'          => 'ativo',
                                'legado'          => true,
                                'created_at'      => $now,
                                'updated_at'      => $now,
                            ]);
                        }
                        $assinaturaId = $map[$key];

                        [$inicio, $fim] = $this->limitesMes($plano->mes, (int) $plano->dia_semana);

                        DB::table('planos_mensais')->where('id', $plano->id)->update([
                            'assinatura_id' => $assinaturaId,
                            'data_inicio'   => $inicio,
                            'data_fim'      => $fim,
                            'dia_renovacao' => null,
                        ]);

                        $valor = DB::table('servicos')->where('id', $plano->servico_id)->value('valor');

                        DB::table('plano_mensal_servicos')->insert([
                            'plano_mensal_id' => $plano->id,
                            'servico_id'      => $plano->servico_id,
                            'quantidade'      => $plano->unidades_total,
                            'usados'          => min((int) $plano->unidades_usadas, (int) $plano->unidades_total),
                            'valor_unitario'  => $valor ?? 0,
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ]);
                    }
                });
            });
    }

    public function down(): void
    {
        // Reversível em colunas de planos_mensais; não apaga assinaturas/itens (histórico).
        DB::table('planos_mensais')->whereNotNull('assinatura_id')->update([
            'assinatura_id' => null,
            'data_inicio'   => null,
            'data_fim'      => null,
            'dia_renovacao' => null,
        ]);
        DB::table('plano_mensal_servicos')->truncate();
        DB::table('assinaturas_mensais')->truncate();
    }

    /** [primeiraData, últimaData] do dia_semana no mês-calendário dado (1º do mês). */
    private function limitesMes(string $mes, int $diaSemana): array
    {
        $start = Carbon::parse($mes)->startOfMonth();
        $end   = $start->copy()->endOfMonth();
        $first = null;
        $last  = null;
        $cur   = $start->copy();
        while ($cur <= $end) {
            if ((int) $cur->format('w') === $diaSemana) {
                $first = $first ?? $cur->copy();
                $last  = $cur->copy();
            }
            $cur->addDay();
        }
        return [$first?->toDateString(), $last?->toDateString()];
    }
};
