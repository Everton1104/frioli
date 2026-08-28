<?php

namespace App\Http\Controllers;

use App\Models\AgendamentoModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/*
 * Histórico de atendimentos realizados (tela dedicada ao staff).
 *
 * Tiramos da tela principal os pacotes/planos já consumidos — aqui o funcionário
 * vê os cortes que ACONTECERAM (agendamentos passados, não cancelados/recusados),
 * no mesmo formato de cards da agenda, com filtros: cliente, barbeiro (admin),
 * período (últimos 30 dias / semana de uma data / dia específico / tudo) e tipo
 * (plano mensal, pacote avulso, sem pacote).
 *
 * Funcionário vê só os próprios atendimentos; admin vê de todos (+ filtro).
 */
class HistoricoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->adm || $user->func, 403);

        $periodo = $request->input('periodo', '30d');
        $data    = $request->input('data'); // usada nos modos semana/dia

        $query = AgendamentoModel::with([
                'user:id,name',
                'funcionario:id,name',
                'servico:id,descricao,duracao',
                'creditoServico.servico:id,descricao',
                'planoMensal:id,status',
            ])
            ->where('data_inicio', '<', now())
            ->whereNotIn('status', [
                AgendamentoModel::STATUS_RECUSADO,
                AgendamentoModel::STATUS_CANCELADO,
                AgendamentoModel::STATUS_INTENCAO,
            ])
            // Barbeiro vê só a própria agenda; admin pode filtrar por qualquer um.
            ->when($user->func, fn($q) => $q->where('funcionario_id', $user->id))
            ->when($user->adm && $request->filled('barbeiro'), fn($q) => $q->where('funcionario_id', $request->barbeiro))
            ->when($request->filled('cliente'), fn($q) => $q->whereHas('user', fn($u) => $u->where('name', 'like', '%' . $request->cliente . '%')))
            ->when($request->input('tipo') === 'mensal', fn($q) => $q->whereNotNull('plano_mensal_id'))
            ->when($request->input('tipo') === 'avulso', fn($q) => $q->whereNull('plano_mensal_id')->whereNotNull('credito_servico_id'))
            ->when($request->input('tipo') === 'sem', fn($q) => $q->whereNull('plano_mensal_id')->whereNull('credito_servico_id'));

        switch ($periodo) {
            case 'semana':
                if ($data) {
                    $domingo = Carbon::parse($data)->startOfWeek(Carbon::SUNDAY)->startOfDay();
                    $query->whereBetween('data_inicio', [$domingo, $domingo->copy()->addDays(6)->endOfDay()]);
                }
                break;
            case 'dia':
                if ($data) {
                    $query->whereDate('data_inicio', $data);
                }
                break;
            case 'todos':
                break; // sem janela
            default: // '30d'
                $query->where('data_inicio', '>=', now()->subDays(30));
                break;
        }

        $atendimentos = $query->orderByDesc('data_inicio')
            ->paginate(60)
            ->withQueryString();

        $barbeiros = $user->adm ? User::barbeiros()->get() : collect();

        return view('historico.index', [
            'atendimentos' => $atendimentos,
            'barbeiros'    => $barbeiros,
            'filtros'      => [
                'periodo'  => $periodo,
                'data'     => $data,
                'cliente'  => $request->input('cliente'),
                'barbeiro' => $request->input('barbeiro'),
                'tipo'     => $request->input('tipo', 'todos'),
            ],
        ]);
    }
}
