<?php

namespace App\Models;

use App\Models\LembreteConsulta;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AgendamentoModel extends Model
{
    protected $table = 'agendamentos';
    protected $fillable = [
        'user_id',
        'servico_id',
        'funcionario_id',
        'data_inicio',
        'data_fim',
        'confirmado',
        'consome_credito',
        'especial',
        'credito_servico_id',
        'pre_confirmado_em',
        'confirmado_em',
        'status',
        'compareceu',
        'pagar_no_local',
        'plano_mensal_id',
        'consumo_plano',
        'assinatura_id',
        'plano_ordem',
    ];

    // Ciclo de vida do booking público. Default "confirmado" (staff cria direto).
    public const STATUS_CONFIRMADO           = 'confirmado';
    public const STATUS_AGUARDANDO_PAGAMENTO = 'aguardando_pagamento';
    public const STATUS_PAGO_AGUARDANDO      = 'pago_aguardando';
    public const STATUS_RECUSADO             = 'recusado';
    public const STATUS_CANCELADO            = 'cancelado';
    // Cliente penalizado pedindo agendamento no local: fica pendente de aprovação
    // do barbeiro escolhido. NÃO ocupa slot (não bloqueia outros clientes).
    public const STATUS_INTENCAO             = 'intencao_agendamento';
    // Slot fixo de plano mensal reservado sem saldo (assinatura ativa, aguardando
    // renovação): OCUPA o slot (bloqueia outros clientes) e sinaliza ao staff.
    public const STATUS_RESERVA_RENOVACAO    = 'reserva_renovacao';

    // Statuses que OCUPAM o slot (bloqueiam sobreposição no horarios/store).
    public const OCUPANTES = [
        self::STATUS_CONFIRMADO,
        self::STATUS_AGUARDANDO_PAGAMENTO,
        self::STATUS_PAGO_AGUARDANDO,
        self::STATUS_RESERVA_RENOVACAO,
    ];

    protected $casts = [
        'data_inicio'        => 'datetime',
        'data_fim'           => 'datetime',
        'confirmado'         => 'boolean',
        'consome_credito'    => 'boolean',
        'especial'           => 'boolean',
        'pre_confirmado_em'  => 'datetime',
        'confirmado_em'      => 'datetime',
        'compareceu'         => 'boolean',
        'pagar_no_local'     => 'boolean',
        'consumo_plano'      => 'array',
        'plano_ordem'        => 'integer',
    ];

    // Serializa as datas no horário local (sem conversão p/ UTC) e com espaço,
    // no formato que o front-end consome (ex.: editarConsulta faz split(' ')).
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    // Expõe o status do lembrete da véspera e o nome de exibição do serviço no JSON
    // (consumidos pelos cards do dashboard).
    protected $appends = ['lembrete_24h', 'servico_display'];

    // 'enviado' | 'erro' | null (ainda não disparado)
    public function getLembrete24hAttribute(): ?string
    {
        return $this->lembretes->firstWhere('tipo', '24h')?->status;
    }

    public function servico()
    {
        return $this->belongsTo(ServicosModel::class);
    }

    // Pacote (creditos_servico) de onde este agendamento desconta 1 unidade.
    // Pode ser de outro serviço (ex.: especial que abate de um pacote). NULL = livre.
    public function creditoServico()
    {
        return $this->belongsTo(CreditoServico::class, 'credito_servico_id');
    }

    // Nome de exibição do serviço com a posição da consulta no pacote: "Serviço X (2/5)"
    // = 2ª consulta de 5. Especial que desconta de outro serviço: "especial - Serviço X (2/5)".
    public function getServicoDisplayAttribute(): string
    {
        $nome    = $this->servico->descricao ?? '';
        $credito = $this->creditoServico;

        if ($credito) {
            if ($credito->servico_id != $this->servico_id) {
                $nome .= ' - ' . ($credito->servico->descricao ?? '');
            }
            // Pacote de unidade única (1/1): mostra só o nome, sem o "(1/1)" redundante.
            if ($credito->quantidade > 1) {
                $nome .= ' (' . $credito->ordinalDe($this) . '/' . $credito->quantidade . ')';
            }
        }

        return $nome;
    }

    /**
     * Saldo do pacote/plano para o lembrete da véspera (texto cru, sem prefixo).
     * Reutilizado pelo lembrete automático (lembretes:enviar) e pelo reenvio manual,
     * para os dois caminhos não divergirem.
     *  - Plano mensal com itens: "corte 1/4, barba 1/4" (restantesPorServico).
     *  - Pacote avulso (crédito) qtd>1: "corte 2/5".
     *  - Avulso sem pacote/plano ou unidade única: "" (vazio → lembrete só com a hora).
     */
    public function saldoPacoteTexto(): string
    {
        if ($this->plano_mensal_id && $this->planoMensal) {
            $p = $this->planoMensal;
            if ($p->itens->isNotEmpty()) {
                // "1/4 usadas · corte 1/4, barba 1/2" — total de visitas + por serviço.
                return $p->unidades_usadas . '/' . $p->unidades_total . ' usadas'
                    . ' · ' . $p->restantesPorServico();
            }
            return '';
        }

        if ($this->credito_servico_id && $this->creditoServico) {
            $credito = $this->creditoServico;
            if ($credito->quantidade > 1) {
                $nome = strtolower($credito->servico->descricao ?? 'serviço');
                return "{$nome} {$credito->ordinalDe($this)}/{$credito->quantidade}";
            }
        }

        return '';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Barbeiro (funcionário) responsável pelo atendimento. NULL = legado/órfão.
    public function funcionario()
    {
        return $this->belongsTo(User::class, 'funcionario_id');
    }

    // Plano mensal fixo que originou este agendamento (auto-reservado). NULL = avulso.
    public function planoMensal()
    {
        return $this->belongsTo(PlanoMensal::class);
    }

    public function lembretes()
    {
        return $this->hasMany(LembreteConsulta::class, 'agendamento_id');
    }
}