<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicosModel extends Model
{
    protected $table = 'servicos';
    protected $fillable = ['descricao', 'duracao', 'status', 'excluido', 'visivel_cliente', 'recorrente', 'valor', 'user_id'];

    protected $casts = ['recorrente' => 'boolean', 'valor' => 'float'];

    public function agendamentos()
    {
        return $this->hasMany(AgendamentoModel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Serviço personalizado de um cliente (user_id definido). NULL se não houver.
    public static function doCliente(int $userId): ?self
    {
        return static::where('user_id', $userId)->where('excluido', 0)->first();
    }

    /**
     * Intervalo (em minutos) dos horários que o cliente vê no agendamento online.
     * = menor duração entre os serviços ativos/agendáveis, limitado a [15, 60].
     * Ex.: se o serviço mais curto é 60min, o cliente só vê horários cheios (:00);
     * se é 30min, vê :00/:30; se é 15min, vê de 15 em 15. Fallback 30min.
     */
    public static function intervaloMinimoCliente(): int
    {
        $min = static::where('excluido', 0)
            ->where('status', 1)
            ->where('visivel_cliente', 1)
            ->where('recorrente', 0)
            ->where('valor', '>', 0)
            ->get(['duracao'])
            ->map(function ($s) {
                $d = (string) $s->duracao;
                return ((int) substr($d, 0, 2)) * 60 + ((int) substr($d, 3, 2));
            })
            ->min();

        if (!$min) {
            return 30;
        }

        return (int) min(60, max(15, $min));
    }
}