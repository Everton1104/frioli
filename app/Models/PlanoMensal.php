<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/*
 * Pacote mensal fixo (serviço "Mensal" / recorrente=1). O cliente compra um slot fixo
 * semanal (barbeiro + dia da semana + horário) para um mês-alvo.
 *
 * Ciclo de vida:
 *  - aguardando_pagamento: criado na compra, ligado a uma OrdemPagamento.
 *  - ativo: pagamento aprovado → materializa pré-reservas (status pago_aguardando) nas
 *    ocorrências cujo slot do barbeiro já esteja aberto; bloqueia avulsos.
 *  - A cada confirmação do funcionário num atendimento do plano, desconta 1 unidade.
 *  - consumido: unidades_usadas >= unidades_total.
 *  - expirado: fim do mês-alvo com unidades não usadas → entram em "Negociar".
 *  - cancelado.
 */
class PlanoMensal extends Model
{
    protected $table = 'planos_mensais';

    public const STATUS_AGUARDANDO_PAGAMENTO = 'aguardando_pagamento';
    public const STATUS_ATIVO                = 'ativo';
    public const STATUS_CONSUMIDO            = 'consumido';
    public const STATUS_EXPIRADO             = 'expirado';
    public const STATUS_CANCELADO            = 'cancelado';

    protected $fillable = [
        'user_id', 'servico_id', 'funcionario_id', 'dia_semana', 'hora', 'mes',
        'unidades_total', 'unidades_usadas', 'valor_total', 'status', 'ordem_pagamento_id',
        'recorrente', 'assinatura_id', 'data_inicio', 'data_fim', 'dia_renovacao',
        'distribuicao', 'valores_extra',
    ];

    protected $casts = [
        'mes'             => 'date',
        'data_inicio'     => 'date',
        'data_fim'        => 'date',
        'valor_total'     => 'decimal:2',
        'unidades_total'  => 'integer',
        'unidades_usadas' => 'integer',
        'dia_renovacao'   => 'integer',
        'recorrente'      => 'boolean',
        'distribuicao'    => 'array',
        'valores_extra'   => 'array',
    ];

    /** True se o ciclo tem distribuição semanal definida (modo agenda por visita). */
    public function temDistribuicao(): bool
    {
        return filled($this->distribuicao) && count($this->distribuicao) > 0;
    }

    /**
     * Descrição da próxima visita (não confirmada) conforme a distribuição.
     * Ex.: "corte + barba" (próxima = visita de ordem unidades_usadas+1).
     * Retorna '' se não houver distribuição ou se o ciclo já estiver consumido.
     */
    public function proximaVisitaDesc(): string
    {
        if (!$this->temDistribuicao()) {
            return '';
        }
        $ordem = (int) $this->unidades_usadas; // 0-based: próxima visita = índice unidades_usadas
        $dist = array_values($this->distribuicao);
        if (!isset($dist[$ordem])) {
            return '';
        }
        $nomes = [];
        foreach ($dist[$ordem] as $sid) {
            $s = ServicosModel::find($sid);
            if ($s) {
                $nomes[] = strtolower($s->descricao);
            }
        }
        return $nomes ? implode(' + ', $nomes) : '';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function servico()
    {
        return $this->belongsTo(ServicosModel::class);
    }

    public function funcionario()
    {
        return $this->belongsTo(User::class, 'funcionario_id');
    }

    public function ordemPagamento()
    {
        return $this->belongsTo(OrdemPagamento::class, 'ordem_pagamento_id');
    }

    public function agendamentos()
    {
        return $this->hasMany(AgendamentoModel::class, 'plano_mensal_id');
    }

    public function assinatura()
    {
        return $this->belongsTo(AssinaturaMensal::class, 'assinatura_id');
    }

    /** Serviços inclusos neste ciclo (multi-serviço). */
    public function itens()
    {
        return $this->hasMany(PlanoMensalServico::class, 'plano_mensal_id');
    }

    public function scopeAtivo($query)
    {
        return $query->where('status', self::STATUS_ATIVO);
    }

    public function restantes(): int
    {
        return max(0, (int) $this->unidades_total - (int) $this->unidades_usadas);
    }

    /** Unidades ainda não confirmadas mas com pré-reserva criada (pendentes). */
    public function pendentes(): int
    {
        return (int) $this->agendamentos()
            ->where('status', AgendamentoModel::STATUS_PAGO_AGUARDANDO)
            ->count();
    }

    /** True quando o plano ainda pode gerar novas pré-reservas (não consumido/expirado). */
    public function negociar(): bool
    {
        return $this->status === self::STATUS_EXPIRADO && $this->restantes() > 0;
    }

    /**
     * Nº de vezes que o dia da semana cai no mês (4 ou 5).
     * dia_semana: 0=Dom .. 6=Sáb (igual ao PHP date('w')).
     */
    public static function unidadesNoMes(Carbon $mes, int $diaSemana): int
    {
        $cursor = $mes->copy()->startOfMonth();
        $fim    = $mes->copy()->endOfMonth();
        $count  = 0;
        while ($cursor <= $fim) {
            if ((int) $cursor->format('w') === (int) $diaSemana) {
                $count++;
            }
            $cursor->addDay();
        }
        return $count;
    }

    /** [unidades, valor_total, valor_unitario] — autoritativo (server-side). */
    public static function calcular(ServicosModel $servico, Carbon $mes, int $diaSemana): array
    {
        $unidades = self::unidadesNoMes($mes, $diaSemana);
        $unitario = (float) $servico->valor;
        return [
            'unidades'      => $unidades,
            'valor_unitario' => $unitario,
            'valor_total'   => round($unidades * $unitario, 2),
        ];
    }

    /**
     * Datas (Carbon) de cada ocorrência do dia_semana na janela do ciclo
     * (data_inicio..data_fim). Fallback: mês-calendário (planos legados). Limita ao
     * nº de visitas do ciclo (unidades_total) — suporta "pagar 4 em mês de 5".
     */
    public function datasOcorrencias()
    {
        $inicio = $this->data_inicio
            ? Carbon::parse($this->data_inicio)->startOfDay()
            : $this->mes->copy()->startOfMonth();
        $fim = $this->data_fim
            ? Carbon::parse($this->data_fim)->endOfDay()
            : $this->mes->copy()->endOfMonth();

        $out = collect();
        $cursor = $inicio->copy();
        while ($cursor <= $fim) {
            if ((int) $cursor->format('w') === (int) $this->dia_semana) {
                $out->push($cursor->copy());
            }
            $cursor->addDay();
        }
        return $out->take(max(1, (int) $this->unidades_total));
    }

    /** Coleção de Carbon com todas as ocorrências do dia_semana no mês-calendário. */
    public static function ocorrenciasNoMes(Carbon $mes, int $diaSemana): Collection
    {
        $out = collect();
        $cursor = $mes->copy()->startOfMonth();
        $fim    = $mes->copy()->endOfMonth();
        while ($cursor <= $fim) {
            if ((int) $cursor->format('w') === $diaSemana) {
                $out->push($cursor->copy());
            }
            $cursor->addDay();
        }
        return $out;
    }

    /**
     * Cálculo de um ciclo multi-serviço ancorado num mês-calendário.
     *
     * @param Collection $itens  [[servico_id, quantidade], ...] ou models com ->servico_id/->quantidade
     * @param Carbon     $mes    mês-alvo (1º do mês)
     * @param int        $diaSemana
     * @param int|null   $cap    limite de visitas (opção "4 em mês de 5"); null = todas
     * @return array{unidades:int,unidades_max:int,valor_total:float,itens:array,data_inicio:Carbon|null,data_fim:Carbon|null}
     */
    public static function calcularCombo(Collection $itens, Carbon $mes, int $diaSemana, ?int $cap = null): array
    {
        $ocorrencias = self::ocorrenciasNoMes($mes, $diaSemana);
        $unidadesMax = $ocorrencias->count();
        $unidades    = $cap ? min((int) $cap, $unidadesMax) : $unidadesMax;
        $janela      = $ocorrencias->take($unidades);

        $itensOut = [];
        $valor    = 0.0;
        foreach ($itens as $item) {
            $servicoId = is_object($item)
                ? ($item->servico_id ?? null)
                : ($item['servico_id'] ?? $item[0] ?? null);
            $qtd = is_object($item)
                ? ($item->quantidade ?? 0)
                : ($item['quantidade'] ?? $item[1] ?? 0);
            $servico = $servicoId ? ServicosModel::find($servicoId) : null;
            $unit    = (float) ($servico->valor ?? 0);
            $valor  += (int) $qtd * $unit;
            $itensOut[] = [
                'servico_id'    => (int) $servicoId,
                'quantidade'    => (int) $qtd,
                'valor_unitario' => $unit,
                'descricao'     => $servico->descricao ?? null,
            ];
        }

        return [
            'unidades'    => $unidades,
            'unidades_max' => $unidadesMax,
            'valor_total' => round($valor, 2),
            'itens'       => $itensOut,
            'data_inicio' => $janela->first(),
            'data_fim'    => $janela->last(),
        ];
    }

    /**
     * Próximo ciclo de uma assinatura, ancorado no dia_renovacao preferido do cliente.
     *
     * data_inicio = próxima ocorrência do dia_semana a partir de $aPartirDe;
     * data_fim    = a ocorrência mais próxima do dia_renovacao (~1 mês à frente);
     * unidades    = ocorrências em [data_inicio, data_fim];
     * valor_total = Σ quantidade × valor_unitario dos itens.
     *
     * O cliente escolhe o dia_renovacao (dia do mês em que costuma renovar); o ciclo
     * cobre as visitas semanais até a ocorrência mais próxima desse dia no mês seguinte.
     */
    public static function calcularProximoCiclo(int $diaSemana, Collection $itens, Carbon $aPartirDe, int $diaRenovacao, ?int $max = null): array
    {
        // data_inicio = próxima ocorrência do dia_semana a partir de $aPartirDe.
        $inicio = $aPartirDe->copy()->startOfDay();
        while ((int) $inicio->format('w') !== $diaSemana) {
            $inicio->addDay();
        }

        // Alvo: ~1 mês à frente, no dia_renovacao (clampado ao fim do mês).
        $alvo = $aPartirDe->copy()->startOfMonth()->addMonth();
        $alvo->day = min(max(1, $diaRenovacao), (int) $alvo->daysInMonth);

        // Coleta ocorrências semanais a partir de início, estendendo além do alvo.
        $oc  = collect();
        $cur = $inicio->copy();
        for ($i = 0; $i < 12; $i++) {
            $oc->push($cur->copy());
            if ($cur->copy()->endOfDay()->gt($alvo) && $oc->count() >= 2) {
                break;
            }
            $cur->addWeek();
        }
        // data_fim = ocorrência (semana inteira) mais próxima do alvo.
        $fim = $oc->sortBy(fn ($d) => abs((int) $d->getTimestamp() - (int) $alvo->getTimestamp()))->first();

        $valor    = 0.0;
        $itensOut = [];
        foreach ($itens as $item) {
            $sid = is_object($item) ? ($item->servico_id ?? null) : ($item['servico_id'] ?? $item[0] ?? null);
            $qtd = is_object($item) ? ($item->quantidade ?? 0) : ($item['quantidade'] ?? $item[1] ?? 0);
            $s   = $sid ? ServicosModel::find($sid) : null;
            $unit = (float) ($s->valor ?? 0);
            $valor += (int) $qtd * $unit;
            $itensOut[] = [
                'servico_id'    => (int) $sid,
                'quantidade'    => (int) $qtd,
                'valor_unitario' => $unit,
                'descricao'     => $s->descricao ?? null,
            ];
        }

        $unidades = $oc->filter(fn ($d) => $d->betweenIncluded($inicio, $fim))->count();

        // Cap (ex.: assinatura sem aceita_5 → limitar a 4 visitas em mês de 5 semanas).
        if ($max !== null && $unidades > $max) {
            $ocJanela = $oc->filter(fn ($d) => $d->betweenIncluded($inicio, $fim))->sort()->values();
            $fim      = $ocJanela->get($max - 1) ?? $fim;
            $unidades = $max;
        }

        return [
            'data_inicio' => $inicio,
            'data_fim'    => $fim,
            'unidades'    => $unidades,
            'valor_total' => round($valor, 2),
            'itens'       => $itensOut,
            'alvo'        => $alvo,
        ];
    }

    /**
     * Núcleo do cálculo de um ciclo COM distribuição semanal.
     *
     * @param Collection $distribuicao  [[servico_id,...], ...] — 1 entrada por visita, ordenada.
     * @param Collection $ocorrencias   datas Carbon disponíveis (do mês ou da janela).
     * @param array      $valoresExtra  { servico_id => preço } da visita extra (5ª semana).
     * @return array{unidades:int,valor_total:float,itens:array,data_inicio:Carbon|null,data_fim:Carbon|null,tem_extra:bool}
     */
    public static function calcularComDistribuicao(Collection $distribuicao, Collection $ocorrencias, array $valoresExtra = []): array
    {
        $visitas  = $distribuicao->count();
        $janela   = $ocorrencias->take($visitas);
        $temExtra = $ocorrencias->count() > $visitas;

        $valor = 0.0;
        $porServico = []; // servico_id => ['qtd'=>int,'unit'=>float,'desc'=>string|null]
        foreach ($distribuicao as $pos) {
            foreach ((array) $pos as $sid) {
                $sid  = (int) $sid;
                $serv = ServicosModel::find($sid);
                $unit = (float) ($serv->valor ?? 0);
                $valor += $unit;
                if (!isset($porServico[$sid])) {
                    $porServico[$sid] = ['qtd' => 0, 'unit' => $unit, 'desc' => $serv->descricao ?? null];
                }
                $porServico[$sid]['qtd']++;
            }
        }

        $itensOut = [];
        foreach ($porServico as $sid => $info) {
            $itensOut[] = [
                'servico_id'     => $sid,
                'quantidade'     => $info['qtd'],
                'valor_unitario' => $info['unit'],
                'descricao'      => $info['desc'],
            ];
        }

        return [
            'unidades'    => $visitas,
            'valor_total' => round($valor, 2),
            'itens'       => $itensOut,
            'data_inicio' => $janela->first(),
            'data_fim'    => $janela->last(),
            'tem_extra'   => $temExtra,
        ];
    }

    /** Criação staff (mês-calendário) com distribuição: primeiras N ocorrências do mês. */
    public static function calcularComboDistribuido(Collection $distribuicao, Carbon $mes, int $diaSemana, array $valoresExtra = []): array
    {
        return self::calcularComDistribuicao($distribuicao, self::ocorrenciasNoMes($mes, $diaSemana), $valoresExtra);
    }

    /**
     * Renovação com distribuição: primeiras N ocorrências do dia_semana a partir de
     * $aPartirDe. O tamanho do ciclo = nº de visitas da distribuição (o dia_renovacao
     * deixa de definir o tamanho — a renovação dispara pelo fim do ciclo).
     */
    public static function calcularProximoCicloDistribuido(int $diaSemana, Collection $distribuicao, Carbon $aPartirDe, array $valoresExtra = []): array
    {
        $visitas = $distribuicao->count();

        $inicio = $aPartirDe->copy()->startOfDay();
        while ((int) $inicio->format('w') !== $diaSemana) {
            $inicio->addDay();
        }

        // Coleta N+2 ocorrências (N do ciclo + folga para sinalizar a visita extra).
        $oc  = collect();
        $cur = $inicio->copy();
        for ($i = 0; $i < $visitas + 2; $i++) {
            $oc->push($cur->copy());
            $cur->addWeek();
        }

        return self::calcularComDistribuicao($distribuicao, $oc, $valoresExtra);
    }

    /**
     * Núcleo do cálculo de um ciclo LENDO o combo master (Fase 5): composição (preço
     * com desconto por serviço) + distribuição (4 visitas base). A 5ª visita (em mês
     * de 5 semanas) é AUTOMÁTICA — repete a 1ª visita da distribuição (o padrão do
     * pacote recomeça). Sem escolha de serviço extra pelo staff.
     *
     * @param ServicosModel $base       combo master (com composicao+distribuicao)
     * @param Collection    $ocorrencias datas disponíveis (do mês ou da janela)
     */
    public static function calcularDeCombo(ServicosModel $base, Collection $ocorrencias): array
    {
        $distribuicao = collect($base->distribuicao ?: []);
        $composicao   = $base->composicao ?: [];
        $baseVisitas  = $distribuicao->count();
        $ocCount      = $ocorrencias->count();
        // Vinculação parcial: limita às ocorrências disponíveis (início no meio do mês).
        $visitas      = min($baseVisitas, $ocCount);
        $temExtra     = $ocCount > $baseVisitas; // mês com 5ª semana
        $janela       = $ocorrencias->take($visitas + ($temExtra ? 1 : 0));

        // 5ª visita (extra) = repete a 1ª visita da distribuição.
        $distFinal = [];
        foreach ($distribuicao->take($visitas) as $pos) {
            $distFinal[] = array_map('intval', (array) $pos);
        }
        if ($temExtra) {
            $distFinal[] = array_map('intval', (array) ($distribuicao[0] ?? []));
        }

        $valor = 0.0;
        $porServico = []; // servico_id => qtd
        foreach ($distFinal as $ids) {
            foreach ($ids as $sid) {
                $valor += (float) ($composicao[$sid] ?? 0);
                $porServico[$sid] = ($porServico[$sid] ?? 0) + 1;
            }
        }

        $itens = [];
        foreach ($porServico as $sid => $qtd) {
            $itens[] = [
                'servico_id'     => $sid,
                'quantidade'     => $qtd,
                'valor_unitario' => (float) ($composicao[$sid] ?? 0),
            ];
        }

        return [
            'unidades'    => count($distFinal),
            'valor_total' => round($valor, 2),
            'itens'       => $itens,
            'data_inicio' => $janela->first(),
            'data_fim'    => $janela->last(),
            'distribuicao'=> $distFinal,
            'tem_extra'   => $temExtra,
        ];
    }

    /** Criação (mês-calendário) lendo o combo master. */
    public static function calcularComboDeCombo(ServicosModel $base, Carbon $mes, int $diaSemana): array
    {
        return self::calcularDeCombo($base, self::ocorrenciasNoMes($mes, $diaSemana));
    }

    /**
     * Vinculação a partir de $aPartirDe (hoje): ocorrências do dia_semana a partir de
     * $aPartirDe (inclusive, a próxima), limitadas ao mês corrente. Suporta início no
     * meio do mês — só as semanas restantes (ciclo parcial), valor proporcional.
     */
    public static function calcularComboDeComboAPartirDe(ServicosModel $base, int $diaSemana, Carbon $aPartirDe): array
    {
        $fim = $aPartirDe->copy()->endOfMonth();
        $cur = $aPartirDe->copy()->startOfDay();
        while ((int) $cur->format('w') !== $diaSemana) {
            $cur->addDay();
        }
        $oc = collect();
        while ($cur->copy()->startOfDay()->lte($fim)) {
            $oc->push($cur->copy());
            $cur->addWeek();
        }
        return self::calcularDeCombo($base, $oc);
    }

    /** Renovação (janela a partir de $aPartirDe) lendo o combo master. */
    public static function calcularProximoCicloDeCombo(ServicosModel $base, int $diaSemana, Carbon $aPartirDe): array
    {
        $visitas = count($base->distribuicao ?: []);
        $inicio = $aPartirDe->copy()->startOfDay();
        while ((int) $inicio->format('w') !== $diaSemana) {
            $inicio->addDay();
        }
        $oc  = collect();
        $cur = $inicio->copy();
        for ($i = 0; $i < $visitas + 2; $i++) {
            $oc->push($cur->copy());
            $cur->addWeek();
        }
        return self::calcularDeCombo($base, $oc);
    }

    /**
     * Desconta 1 visita do ciclo: +1 em unidades_usadas e +1 em cada item com saldo
     * (usados < quantidade). Marca consumido quando as visitas esgotam. Retorna os
     * IDs dos itens incrementados — registrados no agendamento (consumo_plano) para
     * permitir reverter EXATAMENTE esses itens ao excluir/cancelar a visita.
     */
    public function descontarVisita(?int $ordem = null): array
    {
        $incrementados = [];

        if ($this->status !== self::STATUS_ATIVO) {
            return $incrementados;
        }

        // Serviços a abater nesta visita. Com distribuição: apenas os da posição
        // $ordem (1-based). Sem distribuição (ou ordem null): todos com saldo
        // (modo uniforme legado — 1 de cada serviço a cada visita).
        $servicosDaVisita = null;
        if ($ordem !== null && $this->temDistribuicao()) {
            $dist = array_values($this->distribuicao);
            $pos  = $dist[$ordem - 1] ?? [];
            $servicosDaVisita = array_map('intval', $pos);
        }

        DB::transaction(function () use (&$incrementados, $servicosDaVisita) {
            $this->unidades_usadas = (int) $this->unidades_usadas + 1;
            $this->save();

            foreach ($this->itens as $item) {
                if ((int) $item->usados >= (int) $item->quantidade) {
                    continue;
                }
                if ($servicosDaVisita !== null && !in_array((int) $item->servico_id, $servicosDaVisita, true)) {
                    continue;
                }
                $item->usados = (int) $item->usados + 1;
                $item->save();
                $incrementados[] = (int) $item->id;
            }

            if ((int) $this->unidades_usadas >= (int) $this->unidades_total) {
                $this->status = self::STATUS_CONSUMIDO;
                $this->save();
            }
        });

        return $incrementados;
    }

    /**
     * Reverte 1 visita: -1 em unidades_usadas e -1 em cada item informado (com
     * usados > 0). Volta de consumido para ativo se aplicável. $itemIds vem do
     * consumo_plano registrado no agendamento ao confirmar.
     */
    public function restaurarVisita(array $itemIds): void
    {
        DB::transaction(function () use ($itemIds) {
            $ids = array_map('intval', $itemIds);

            if ($this->status === self::STATUS_CONSUMIDO) {
                $this->status = self::STATUS_ATIVO;
            }
            $this->unidades_usadas = max(0, (int) $this->unidades_usadas - 1);
            $this->save();

            foreach ($this->itens as $item) {
                if (in_array((int) $item->id, $ids, true) && (int) $item->usados > 0) {
                    $item->usados = (int) $item->usados - 1;
                    $item->save();
                }
            }
        });
    }

    /** Texto "corte 2/4, barba 4/4" para o saldo por serviço. */
    public function restantesPorServico(): string
    {
        if ($this->itens->isEmpty()) {
            return (string) $this->restantes();
        }
        return $this->itens->map(function ($i) {
            $nome = strtolower($i->servico->descricao ?? 'serviço');
            return "{$nome} {$i->usados}/{$i->quantidade}";
        })->implode(', ');
    }

    /** Texto "4× Corte + 4× Barba" descrevendo os itens inclusos. */
    public function descricaoItens(): string
    {
        if ($this->itens->isEmpty()) {
            return $this->servico->descricao ?? 'Plano mensal';
        }
        return $this->itens->map(function ($i) {
            return "{$i->quantidade}× " . ($i->servico->descricao ?? 'serviço');
        })->implode(' + ');
    }
}
