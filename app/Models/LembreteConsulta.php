<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LembreteConsulta extends Model
{
    protected $table = 'lembrete_consulta';

    protected $fillable = ['agendamento_id', 'tipo', 'status', 'erro_msg'];

    public function agendamento()
    {
        return $this->belongsTo(AgendamentoModel::class, 'agendamento_id');
    }
}
