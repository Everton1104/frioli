<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicosModel extends Model
{
    protected $table = 'servicos';
    protected $fillable = ['descricao', 'duracao', 'status', 'excluido', 'visivel_cliente', 'recorrente', 'valor', 'repasse_percent', 'user_id', 'composicao', 'distribuicao', 'quinzenal'];

    protected $casts = ['recorrente' => 'boolean', 'quinzenal' => 'boolean', 'valor' => 'float', 'repasse_percent' => 'float', 'composicao' => 'array', 'distribuicao' => 'array'];

    /** Percentual (0–100) de repasse ao funcionário sobre o valor do serviço (0 se não definido). */
    public function repassePercent(): float
    {
        return (float) ($this->repasse_percent ?? 0);
    }

    public function agendamentos()
    {
        return $this->hasMany(AgendamentoModel::class);
    }

    /** Combo master mensal com composição/preços definidos (Fase 5). */
    public function temComposicao(): bool
    {
        return $this->recorrente && filled($this->composicao) && filled($this->distribuicao);
    }

    /** Preço com desconto de um serviço incluso no combo (0 se não definido). */
    public function precoExtra(int $servicoId): float
    {
        return (float) ($this->composicao[$servicoId] ?? 0);
    }

    /** Texto "2× Corte + 4× Barba" do combo, contando os serviços da distribuição. */
    public function descricaoItens(): string
    {
        if (!$this->temComposicao()) {
            return (string) $this->descricao;
        }
        $contagem = [];
        foreach ((array) $this->distribuicao as $visita) {
            foreach ((array) $visita as $sid) {
                $sid = (int) $sid;
                $contagem[$sid] = ($contagem[$sid] ?? 0) + 1;
            }
        }
        if (!$contagem) {
            return (string) $this->descricao;
        }
        $nomes = static::whereIn('id', array_keys($contagem))->pluck('descricao', 'id');
        return collect($contagem)
            ->map(fn($qtd, $sid) => "{$qtd}× " . ($nomes[$sid] ?? 'serviço'))
            ->implode(' + ');
    }

    /**
     * Total do pacote (4 visitas base) = Σ do preço (com desconto) de cada serviço de
     * cada visita da distribuição. Usado como referência no cadastro/preview.
     */
    public function precoTotalBase(): float
    {
        if (!$this->temComposicao()) {
            return 0.0;
        }
        $total = 0.0;
        foreach ((array) $this->distribuicao as $visita) {
            foreach ((array) $visita as $sid) {
                $total += $this->precoExtra((int) $sid);
            }
        }
        return round($total, 2);
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