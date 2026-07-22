<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Ordem de pagamento criada pelo staff e quitável pelo paciente. Hoje via
// InfinitePay (Link de Pagamento / redirect); ordens antigas via Mercado Pago
// (transparente) permanecem com gateway='mercadopago'. O status segue os
// estados do pagamento (approved, pending, rejected...) mais os internos
// "aberta" (aguardando o paciente pagar) e "cancelled".
class OrdemPagamento extends Model
{
    protected $table = 'ordem_pagamentos';

    // Teto de parcelas oferecidas (o cliente escolhe 1 a 12x no checkout).
    public const MAX_PARCELAS = 1; // Pagamento à vista (cartão ou Pix) — sem parcelamento.

    // Parcelas sem juros (taxa paga pelo estabelecimento); da 7ª à 12ª o juros
    // é pago pelo cliente. A regra efetiva é configurada na conta InfinitePay.
    public const MAX_SEM_JUROS = 6;

    protected $fillable = [
        'user_id',
        'criado_por',
        'agendamento_id',
        'plano_mensal_id',
        'valor',
        'taxa_mp',
        'valor_liquido',
        'descricao',
        'max_parcelas',
        'status',
        'external_reference',
        'infinitepay_slug',
        'infinitepay_url',
        'gateway',
        'payment_id_mp',
        'payment_method_id',
        'installments',
        'status_detail',
        'pago_em',
    ];

    protected $casts = [
        'valor'         => 'float',
        'taxa_mp'       => 'float',
        'valor_liquido' => 'float',
        'max_parcelas'  => 'integer',
        'installments'  => 'integer',
        'pago_em'       => 'datetime',
    ];

    // Paciente que deve pagar a ordem.
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Staff (adm/func) que criou a ordem.
    public function criador()
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    // Agendamento que originou esta ordem (booking público). NULL = ordem avulsa.
    public function agendamento()
    {
        return $this->belongsTo(AgendamentoModel::class, 'agendamento_id');
    }

    public function eventos()
    {
        return $this->hasMany(OrdemPagamentoEvent::class, 'ordem_id');
    }

    // Ordens que o paciente ainda pode tentar pagar no checkout.
    public function pagavel(): bool
    {
        return in_array($this->status, ['aberta', 'rejected', 'pending'], true);
    }

    // Rótulo legível + classe Bootstrap do status para a tabela/cards.
    public function statusBadge(): array
    {
        return match ($this->status) {
            'approved'   => ['Aprovado', 'bg-success'],
            'pending'    => ['Em análise', 'bg-warning text-dark'],
            'aberta'     => ['Aguardando pagamento', 'bg-info text-dark'],
            'rejected'   => ['Recusado', 'bg-danger'],
            'cancelled'  => ['Cancelada', 'bg-secondary'],
            'refunded'   => ['Estornada', 'bg-dark'],
            default      => [ucfirst($this->status), 'bg-secondary'],
        };
    }

    // Líquido recebido pelo estabelecimento. Se o webhook registrou o
    // valor_liquido real (congelado no pagamento — só acontece para ordens
    // antigas do MP), usa-o. Senão cai para a estimativa com a taxa do gateway
    // da própria ordem (MP pior caso 6x, ou InfinitePay). A estimativa do
    // InfinitePay é marcada com ~ no dashboard (a API não devolve o líquido real).
    public function valorLiquido(): ?float
    {
        if (!is_null($this->valor_liquido)) {
            return (float) $this->valor_liquido;
        }
        $taxa = $this->gateway === 'infinitepay'
            ? (float) config('services.infinitepay.taxa_credito', 4.99)
            : (float) config('services.mercadopago.taxa_credito_6x', 14.94);
        return round((float) $this->valor * (1 - $taxa / 100), 2);
    }

    // True quando o líquido exibido é estimativa (não veio do MP). Usado para
    // marcar a coluna "Valor recebido" com ~.
    public function liquidoEstimado(): bool
    {
        return is_null($this->valor_liquido);
    }
}
