<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/*
 * Pedido de troca de dia/horário do plano mensal (slot fixo) feito pelo cliente.
 *
 * Fluxo: cliente solicita → status "pendente" (o plano segue no slot antigo) →
 * staff aprova (AssinaturaMensalController aplica: assinatura + ciclos mudam de
 * dia/hora, agendamentos futuros são remarcados) ou recusa. Uma assinatura só
 * pode ter UMA troca pendente por vez.
 */
class PlanoTroca extends Model
{
    protected $table = 'plano_trocas';

    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_APROVADA = 'aprovada';
    public const STATUS_RECUSADA = 'recusada';

    protected $fillable = [
        'assinatura_id', 'user_id', 'dia_semana', 'hora',
        'status', 'resolvido_em', 'resolvido_por',
    ];

    protected $casts = [
        'resolvido_em' => 'datetime',
    ];

    public function assinatura()
    {
        return $this->belongsTo(AssinaturaMensal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedor()
    {
        return $this->belongsTo(User::class, 'resolvido_por');
    }

    /** "Quarta-feira às 14:00". */
    public function slotDesc(): string
    {
        $dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        return ($dias[$this->dia_semana] ?? '') . ' às ' . Carbon::parse($this->hora)->format('H:i');
    }
}
