<?php

namespace App\Http\Controllers;

use App\Models\AgendamentoModel;
use App\Models\DisponibilidadeModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/*
 * Calendário da equipe (rota /calendario1 — nome histórico do protótipo 1, que
 * foi o formato adotado em 2026-08-28).
 *
 * Visão do DIA com blocos posicionados na linha do tempo (estilo Google Agenda):
 * funcionário vê só a própria agenda; admin pode filtrar por barbeiro. Lê dados
 * reais (agendamentos OCUPANTES + disponibilidades como faixas abertas) e
 * permite marcar comparecimento direto nos blocos (mesma rota do dashboard).
 * Os protótipos 2 (dia × barbeiros) e 3 (tabela compacta) foram descartados.
 */
class CalendarioTesteController extends Controller
{
    /** Janela de exibição da grade (07:00–22:00). */
    private const HORA_INI = 7;
    private const HORA_FIM = 22;

    public function n1(Request $request)
    {
        $user = $request->user();
        abort_unless($user->adm || $user->func, 403);

        $barbeiros = $user->func
            ? User::barbeiros()->where('id', $user->id)->get()
            : User::barbeiros()->get();
        $barbeiroFiltro = (!$user->func && $request->filled('barbeiro'))
            ? (int) $request->barbeiro
            : null;

        $diaRef = $request->filled('data') ? Carbon::parse($request->data)->startOfDay() : now()->startOfDay();

        $agendamentos = AgendamentoModel::with(['user:id,name', 'funcionario:id,name', 'servico:id,descricao'])
            ->whereIn('status', AgendamentoModel::OCUPANTES)
            ->whereDate('data_inicio', $diaRef)
            ->when($user->func, fn($q) => $q->where('funcionario_id', $user->id))
            ->when($barbeiroFiltro, fn($q) => $q->where('funcionario_id', $barbeiroFiltro))
            ->orderBy('data_inicio')
            ->get();

        $disponibilidades = DisponibilidadeModel::whereIn('funcionario_id', $barbeiros->pluck('id'))
            ->where('data', $diaRef->toDateString())
            ->get(['funcionario_id', 'data', 'hora']);

        // Eventos prontos para a view: classe css + badges + comparecimento + geometria.
        $eventos = $agendamentos->map(function ($a) {
            $ini = $a->data_inicio;
            $fim = $a->data_fim;
            $classe = 'ev-confirmado';
            if ($a->status === AgendamentoModel::STATUS_RESERVA_RENOVACAO) $classe = 'ev-reserva';
            elseif ($a->status === AgendamentoModel::STATUS_PAGO_AGUARDANDO) $classe = 'ev-pago';
            elseif ($a->especial) $classe = 'ev-especial';
            if ($a->plano_mensal_id) $classe .= ' ev-mensal';

            $badges = [];
            if ($a->confirmado) $badges[] = '✓';
            if ($a->plano_mensal_id) $badges[] = '📅';
            if ($a->credito_servico_id) $badges[] = '🧾';
            if ($a->pagar_no_local) $badges[] = '💵';
            if ($a->especial) $badges[] = '⚡';

            return [
                'id'         => $a->id,
                'data'       => $ini->format('Y-m-d'),
                'iniMin'     => (int) $ini->format('H') * 60 + (int) $ini->format('i'),
                'fimMin'     => (int) $fim->format('H') * 60 + (int) $fim->format('i'),
                'horaStr'    => $ini->format('H:i') . '–' . $fim->format('H:i'),
                'cliente'    => $a->user->name ?? '—',
                'barbeiro'   => $a->funcionario->name ?? '—',
                'barbeiroId' => (int) $a->funcionario_id,
                'servico'    => $a->servico_display,
                'classe'     => $classe,
                'badges'     => implode(' ', $badges),
                'compareceu' => $a->compareceu === null ? null : (bool) $a->compareceu,
                'podeMarcar' => $a->status !== AgendamentoModel::STATUS_RESERVA_RENOVACAO && $a->compareceu === null,
            ];
        });

        // Faixas de horário aberto do dia (união dos barbeiros visíveis) para o fundo.
        $abertos = [];
        foreach ($disponibilidades->groupBy('data') as $data => $horas) {
            $mins = $horas->map(fn($d) => (int) substr($d->hora, 0, 2) * 60 + (int) substr($d->hora, 3, 2))->sort()->values();
            foreach ($this->faixasContiguas($mins) as $fx) {
                $abertos[] = ['data' => $data, 'iniMin' => $fx[0], 'fimMin' => $fx[1] + 15];
            }
        }

        return view('calendario.teste1', [
            'barbeiros'      => $barbeiros,
            'barbeiroFiltro' => $barbeiroFiltro,
            'diaRef'         => $diaRef,
            'eventos'        => $eventos,
            'abertos'        => collect($abertos),
            'horaIni'        => self::HORA_INI,
            'horaFim'        => self::HORA_FIM,
            'ehFunc'         => (bool) $user->func,
        ]);
    }

    /** Agrupa minutos 15-em-15 contíguos em faixas [ini, fim]. */
    private function faixasContiguas($mins): array
    {
        $out = [];
        foreach ($mins as $m) {
            if ($out && $m - end($out)[1] <= 15) {
                $out[count($out) - 1][1] = $m;
            } else {
                $out[] = [$m, $m];
            }
        }
        return $out;
    }
}
