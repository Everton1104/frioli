<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
 * Assinatura de plano mensal — o SLOT FIXO PERSISTENTE.
 *
 * Representa o dia/horário fixo semanal reservado para um cliente com um barbeiro
 * (funcionario + dia_semana + hora). Sobrevive ao fim do saldo de cada ciclo:
 * enquanto ativa, o slot segue reservado para o cliente (mesmo sem saldo — Fase 2).
 *
 * Cada mês/renovação comprado é um CICLO (PlanoMensal) ligado a esta assinatura; os
 * serviços inclusos de cada ciclo vivem em PlanoMensalServico. A composição do combo
 * (quais serviços e quantidades) é definida pelo staff na criação do 1º ciclo e
 * replicada a cada renovação.
 *
 * Status: ativo / pausado / cancelado (aguardando_renovacao chega na Fase 2).
 */
class AssinaturaMensal extends Model
{
    protected $table = 'assinaturas_mensais';

    public const STATUS_ATIVO     = 'ativo';
    public const STATUS_PAUSADO   = 'pausado';
    public const STATUS_CANCELADO = 'cancelado';

    protected $fillable = [
        'user_id', 'funcionario_id', 'dia_semana', 'hora', 'dia_renovacao',
        'servico_base_id', 'status', 'cancelado_em', 'observacao', 'legado',
        'aceita_5', 'servico_extra_id', 'validade_dias',
    ];

    protected $casts = [
        'dia_renovacao'   => 'integer',
        'cancelado_em'    => 'datetime',
        'legado'          => 'boolean',
        'aceita_5'        => 'boolean',
        'servico_extra_id' => 'integer',
        'validade_dias'   => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function funcionario()
    {
        return $this->belongsTo(User::class, 'funcionario_id');
    }

    public function servicoBase()
    {
        return $this->belongsTo(ServicosModel::class, 'servico_base_id');
    }

    public function planos()
    {
        return $this->hasMany(PlanoMensal::class, 'assinatura_id');
    }

    public function scopeAtivo($query)
    {
        return $query->where('status', self::STATUS_ATIVO);
    }

    /**
     * Assinatura ATIVA de OUTRO cliente no mesmo slot fixo (barbeiro + dia + hora),
     * se houver. O slot fixo é um compromisso SEMANAL recorrente: mesmo que numa
     * semana específica não exista agendamento (o dono remarcou pontualmente), o
     * slot segue reservado — outro plano fixo NÃO pode assumi-lo, só avulsos e
     * remarcações pontuais podem usar a semana vaga.
     *
     * $ignorarUserId exclui o próprio cliente (renovação/troca do mesmo dono).
     */
    public static function slotFixoOcupado(int $funcionarioId, int $diaSemana, string $hora, ?int $ignorarUserId = null): ?self
    {
        $hora = substr($hora, 0, 5); // aceita "HH:MM" ou "HH:MM:SS"
        return static::query()
            ->where('status', self::STATUS_ATIVO)
            ->where('funcionario_id', $funcionarioId)
            ->where('dia_semana', $diaSemana)
            ->where('hora', 'like', $hora . '%')
            ->when($ignorarUserId, fn($q) => $q->where('user_id', '!=', $ignorarUserId))
            ->with('user:id,name')
            ->first();
    }

    /** Ciclo mais recente da assinatura (fonte da composição do combo em renovações). */
    public function cicloAtual()
    {
        return $this->planos->sortByDesc('id')->first();
    }

    /** Itens inclusos do ciclo atual (composição do combo). */
    public function itensAtuais()
    {
        $ciclo = $this->cicloAtual();
        return $ciclo ? $ciclo->itens : collect();
    }
}
