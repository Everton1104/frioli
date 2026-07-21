<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
    ];

    protected $casts = [
        'mes'             => 'date',
        'valor_total'     => 'decimal:2',
        'unidades_total'  => 'integer',
        'unidades_usadas' => 'integer',
    ];

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

    /** Datas (Carbon) de cada ocorrência do dia_semana no mês do plano. */
    public function datasOcorrencias()
    {
        $out = collect();
        $cursor = $this->mes->copy()->startOfMonth();
        $fim    = $this->mes->copy()->endOfMonth();
        while ($cursor <= $fim) {
            if ((int) $cursor->format('w') === (int) $this->dia_semana) {
                $out->push($cursor->copy());
            }
            $cursor->addDay();
        }
        return $out;
    }
}
