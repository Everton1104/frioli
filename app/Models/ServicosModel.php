<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicosModel extends Model
{
    protected $table = 'servicos';
    protected $fillable = ['descricao', 'duracao', 'status', 'excluido', 'visivel_cliente', 'retirada', 'valor'];

    protected $casts = ['retirada' => 'boolean', 'valor' => 'float'];

    public function agendamentos()
    {
        return $this->hasMany(AgendamentoModel::class);
    }
}