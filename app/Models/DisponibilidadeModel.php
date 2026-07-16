<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisponibilidadeModel extends Model
{
    protected $table = 'disponibilidades';
    protected $fillable = ['data', 'hora', 'created_by'];
}
