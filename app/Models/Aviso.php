<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aviso extends Model
{
    protected $table = 'avisos';
    protected $fillable = ['tipo', 'user_id', 'servico_id', 'especial', 'data_antiga', 'data_nova', 'dispensado_at'];

    protected $casts = [
        'especial'      => 'boolean',
        'data_antiga'   => 'datetime',
        'data_nova'     => 'datetime',
        'dispensado_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function servico()
    {
        return $this->belongsTo(ServicosModel::class);
    }
}
