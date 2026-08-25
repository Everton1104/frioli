<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
 * Item incluso num ciclo de plano mensal (planos_mensais). Um ciclo pode ter vários
 * itens (ex.: combo master = 4 cortes + 4 barbas; ou 2 cortes + 4 barbas).
 *
 * A cada visita confirmada, desconta 1 de cada item que ainda tiver saldo (usados <
 * quantidade). valor_unitario é snapshot de servicos.valor na compra.
 */
class PlanoMensalServico extends Model
{
    protected $table = 'plano_mensal_servicos';

    protected $fillable = [
        'plano_mensal_id', 'servico_id', 'quantidade', 'usados', 'valor_unitario',
    ];

    protected $casts = [
        'quantidade'     => 'integer',
        'usados'         => 'integer',
        'valor_unitario' => 'decimal:2',
    ];

    public function plano()
    {
        return $this->belongsTo(PlanoMensal::class, 'plano_mensal_id');
    }

    public function servico()
    {
        return $this->belongsTo(ServicosModel::class, 'servico_id');
    }

    public function restantes(): int
    {
        return max(0, (int) $this->quantidade - (int) $this->usados);
    }

    public function esgotado(): bool
    {
        return $this->restantes() <= 0;
    }
}
