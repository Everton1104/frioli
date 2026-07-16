<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Eventos de uma ordem (criação, tentativas de pagamento, webhooks do MP).
// Servem de auditoria e garantem idempotência no webhook.
class OrdemPagamentoEvent extends Model
{
    protected $table = 'ordem_pagamentos_events';

    protected $fillable = [
        'ordem_id',
        'payment_id_mp',
        'status',
        'origem',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function ordem()
    {
        return $this->belongsTo(OrdemPagamento::class, 'ordem_id');
    }
}
