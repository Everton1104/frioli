<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Um pacote de unidades de um serviço contratado pelo cliente. O mesmo cliente
// pode ter mais de um pacote do mesmo serviço (cada linha é um pacote).
class CreditoServico extends Model
{
    protected $table = 'creditos_servico';

    protected $fillable = ['user_id', 'servico_id', 'quantidade'];

    protected $casts = ['quantidade' => 'integer'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function servico()
    {
        return $this->belongsTo(ServicosModel::class, 'servico_id');
    }

    // Agendamentos vivos que consomem deste pacote (inclui encaixes de outros serviços).
    public function agendamentos()
    {
        return $this->hasMany(AgendamentoModel::class, 'credito_servico_id');
    }

    public function usadas(): int
    {
        // Usa o withCount quando carregado em lote, para evitar 1 consulta por pacote.
        return $this->agendamentos_count ?? $this->agendamentos()->count();
    }

    public function restantes(): int
    {
        return max(0, $this->quantidade - $this->usadas());
    }

    // Posição (1-based) de um agendamento dentro do pacote: a 1ª consulta é 1,
    // a 2ª é 2, ... — usado para exibir "Serviço X (2/5)". Reusa a relação já
    // carregada (evita N+1) e cai para consulta avulsa quando não há eager load.
    public function ordinalDe(AgendamentoModel $agendamento): int
    {
        $ags = $this->relationLoaded('agendamentos')
            ? $this->agendamentos
            : $this->agendamentos()->get(['id', 'credito_servico_id']);

        return $ags->where('id', '<=', $agendamento->id)->count();
    }
}
