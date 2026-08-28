<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsappController;
use App\Models\AgendamentoModel;
use App\Models\Aviso;
use App\Models\CreditoServico;
use App\Models\DisponibilidadeModel;
use App\Models\LembreteConsulta;
use App\Models\OrdemPagamento;
use App\Models\PlanoMensal;
use App\Models\ServicosModel;
use App\Models\User;
use App\Services\PlanoMensalService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AgendaController extends Controller
{
    public function timeToMinutes($time)
    {
        $parts = explode(':', $time);
        return ($parts[0] * 60) + $parts[1];
    }

    // ── API: dias com pelo menos 1 slot disponível no mês (do barbeiro) ──────
    public function diasDisponiveis($ano, $mes)
    {
        $fid  = request('funcionario_id');
        $dias = DisponibilidadeModel::whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->when($fid, fn($q) => $q->where('funcionario_id', $fid))
            ->selectRaw('DAY(data) as dia')
            ->distinct()
            ->pluck('dia')
            ->values();

        return response()->json($dias);
    }

    // ── API: dias NÃO liberados (sem slot) mas com agendamentos fixos (ex.:
    // pré-reservas/visitas de plano mensal). Avisa a equipe que há atendimento
    // marcado num dia que ainda não foi aberto na grade. = agendados − liberados.
    public function diasFixos($ano, $mes)
    {
        $fid = request('funcionario_id');

        $diasAgendados = AgendamentoModel::whereYear('data_inicio', $ano)
            ->whereMonth('data_inicio', $mes)
            ->whereIn('status', AgendamentoModel::OCUPANTES)
            ->when($fid, fn($q) => $q->where('funcionario_id', $fid))
            ->selectRaw('DAY(data_inicio) as dia')
            ->distinct()
            ->pluck('dia');

        $diasLiberados = DisponibilidadeModel::whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->when($fid, fn($q) => $q->where('funcionario_id', $fid))
            ->selectRaw('DAY(data) as dia')
            ->distinct()
            ->pluck('dia');

        return response()->json($diasAgendados->diff($diasLiberados)->values());
    }

    // ── API: todos os slots do dia com status e quem está agendado ───────────
    public function getSlotsDia($data)
    {
        $user = auth()->user();
        if (!$user || (!$user->adm && !$user->func)) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        // Barbeiro cuja grade está sendo exibida: func vê só a própria; adm escolhe.
        $fid = $user->func ? $user->id : request('funcionario_id');

        $slotsDisponiveis = DisponibilidadeModel::where('data', $data)
            ->when($fid, fn($q) => $q->where('funcionario_id', $fid))
            ->pluck('hora')
            ->map(fn($h) => substr($h, 0, 5))
            ->toArray();

        $consultas = AgendamentoModel::with([
                'user',
                'servico',
                'creditoServico.servico',
                'creditoServico.agendamentos:id,credito_servico_id',
            ])
            ->whereDate('data_inicio', $data)
            ->when($fid, fn($q) => $q->where('funcionario_id', $fid))
            // Estados "mortos" (slot já liberado) não são desenhados na agenda.
            ->whereNotIn('status', [AgendamentoModel::STATUS_RECUSADO, AgendamentoModel::STATUS_CANCELADO])
            ->orderBy('data_inicio')
            ->get();

        $slots = [];

        // Exibir das 00:00 às 23:45 em intervalos de 15 min (96 slots — dia inteiro)
        $cursor = Carbon::parse($data . ' 00:00');
        $fim    = Carbon::parse($data . ' 23:59');

        while ($cursor < $fim) {
            $hora         = $cursor->format('H:i');
            $disponivel   = in_array($hora, $slotsDisponiveis);
            $agendamentos = [];

            foreach ($consultas as $c) {
                $inicioC = Carbon::parse($c->data_inicio);
                $fimC    = Carbon::parse($c->data_fim);
                if ($cursor >= $inicioC && $cursor < $fimC) {
                    $agendamentos[] = [
                        'paciente'  => $c->user->name,
                        'servico'   => $c->servico_display,
                        'inicio'    => $inicioC->format('H:i'),
                        'fim'       => $fimC->format('H:i'),
                        // Agendamentos especiais são "transparentes": não bloqueiam o slot.
                        'is_staff'  => (bool) $c->especial,
                    ];
                }
            }

            $slots[] = [
                'hora'         => $hora,
                'disponivel'   => $disponivel,
                'agendamentos' => $agendamentos,
            ];

            $cursor->addMinutes(15);
        }

        return response()->json($slots);
    }

    // ── API: salvar o horário comercial padrão da casa (início/fim) — adm only
    public function salvarHorarioComercial(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->adm && !$user->func)) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $data = $request->validate([
            'inicio' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'fim'    => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'funcionario_id' => ['nullable', 'integer'],
        ]);

        // Alvo da janela: func → si mesmo; adm → o barbeiro informado; sem alvo →
        // padrão global (fallback de quem ainda não definiu a sua).
        if ($user->func) {
            $target = $user;
        } elseif ($request->filled('funcionario_id')) {
            $target = User::where('id', $request->input('funcionario_id'))->where('func', 1)->first();
            if (!$target) {
                return response()->json(['error' => 'Barbeiro não encontrado.'], 404);
            }
        } else {
            $target = null;
        }

        if ($target) {
            $target->update(['horario_inicio' => $data['inicio'], 'horario_fim' => $data['fim']]);
        } else {
            foreach (['inicio' => 'comercial_inicio', 'fim' => 'comercial_fim'] as $campo => $key) {
                \App\Models\PageContent::updateOrCreate(
                    ['section' => 'agenda', 'key' => $key],
                    ['value' => $data[$campo], 'type' => 'text', 'label' => "Agenda — horário comercial ($campo)"]
                );
            }
        }

        return response()->json([
            'ok'     => true,
            'inicio' => $data['inicio'],
            'fim'    => $data['fim'],
            'alvo'   => $target ? (int) $target->id : 'global',
        ]);
    }

    // ── API: aplicar o horário comercial a uma semana inteira (dom→sáb) ───────
    // Pula dias que já têm qualquer slot configurado. Agendamentos são outra tabela
    // e nunca são tocados. Aditivo (não apaga) — respeita o unique(data,hora).
    public function salvarSemana(Request $request, $domingo)
    {
        $user = auth()->user();
        if (!$user || (!$user->adm && !$user->func)) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        try {
            $base = Carbon::parse($domingo);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Data inválida.'], 422);
        }
        if (!$base->isSunday()) {
            return response()->json(['error' => 'A data base precisa ser um domingo.'], 422);
        }

        // Padrão global (fallback para o barbeiro que ainda não definiu a sua janela).
        $globalIni = \App\Models\PageContent::get('agenda', 'comercial_inicio', '08:00');
        $globalFim = \App\Models\PageContent::get('agenda', 'comercial_fim', '17:45');

        // Barbeiros-alvo: func → só si; adm → 'all' (todos os barbeiros) ou um específico.
        if ($user->func) {
            $alvo = [$user->id];
        } else {
            $fidParam = $request->input('funcionario_id', 'all');
            $alvo = ($fidParam === 'all' || !$fidParam)
                ? User::barbeiros()->pluck('id')->all()
                : [(int) $fidParam];
        }

        // Janela POR BARBEIRO: cada um pode ter um horário diferente (ex.: func1
        // 07:00–18:00, func2 09:00–20:00). A semana abre com a janela de cada um;
        // se o barbeiro não definiu, usa o padrão global.
        $barbeirosAlvo = User::whereIn('id', $alvo)->get()->keyBy('id');
        $slotsPorBarbeiro = [];
        foreach ($alvo as $fid) {
            $b    = $barbeirosAlvo->get($fid);
            $hIni = ($b && $b->horario_inicio) ? $b->horario_inicio : $globalIni;
            $hFim = ($b && $b->horario_fim) ? $b->horario_fim : $globalFim;
            $slotsPorBarbeiro[$fid] = $this->gerarSlotsDia($hIni, $hFim);
        }

        $preenchidos = [];
        $ignorados   = [];
        for ($i = 0; $i < 7; $i++) {
            $dia     = $base->copy()->addDays($i)->toDateString();
            $ignorado = false;
            foreach ($alvo as $fid) {
                // Aditivo: pula o barbeiro que já tem qualquer slot no dia.
                if (DisponibilidadeModel::where('data', $dia)->where('funcionario_id', $fid)->exists()) {
                    $ignorado = true;
                    continue;
                }
                foreach ($slotsPorBarbeiro[$fid] ?? [] as $hora) {
                    DisponibilidadeModel::firstOrCreate(
                        ['funcionario_id' => $fid, 'data' => $dia, 'hora' => $hora],
                        ['created_by' => $user->id]
                    );
                }
            }
            if ($ignorado) {
                $ignorados[] = $dia;
            } else {
                $preenchidos[] = $dia;
            }
        }

        // Semana aberta: pré-reserva os slots fixos de clientes mensais (planos ativos).
        $this->materializarPlanosSemana($alvo, $base);
        // ...e reserva os slots de assinaturas ativas sem ciclo pago nesta semana
        // (cliente sem saldo / aguardando renovação) — mantém o horário reservado.
        $this->materializarReservasSemana($alvo, $base);

        // Desconta visitas vencidas (data passou) — o staff vê o saldo atualizado ao
        // abrir a agenda. Substitui o antigo command diário às 23:30.
        PlanoMensalService::descontarVisitasVencidas();

        // Janela exibida no alerta: a do primeiro barbeiro-alvo (representativa).
        $primB  = $alvo ? $barbeirosAlvo->get($alvo[0]) : null;
        $respI  = ($primB && $primB->horario_inicio) ? $primB->horario_inicio : $globalIni;
        $respF  = ($primB && $primB->horario_fim) ? $primB->horario_fim : $globalFim;

        return response()->json([
            'ok'          => true,
            'preenchidos' => $preenchidos,
            'ignorados'   => $ignorados,
            'inicio'      => $respI,
            'fim'         => $respF,
        ]);
    }

    /**
     * Gera a lista de slots de 15 em 15 minutos (H:i:s) dentro da janela dada.
     * Usado ao abrir a semana — cada barbeiro usa a sua própria janela.
     */
    private function gerarSlotsDia(string $horaIni, string $horaFim): array
    {
        [$hI, $mI] = array_pad(explode(':', $horaIni), 2, '0');
        [$hF, $mF] = array_pad(explode(':', $horaFim), 2, '0');
        $cursor = Carbon::createFromTime((int) $hI, (int) $mI, 0);
        $fim    = Carbon::createFromTime((int) $hF, (int) $mF, 0);
        $slots  = [];
        while ($cursor <= $fim) {
            $slots[] = $cursor->format('H:i:s');
            $cursor->addMinutes(15);
        }
        return $slots;
    }

    // ── API: salvar/substituir todos os slots de um dia ──────────────────────
    public function salvarSlots(Request $request, $data)
    {
        $user = auth()->user();
        if (!$user || (!$user->adm && !$user->func)) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        // Barbeiro cuja grade é editada: func → só si; adm → o selecionado.
        $fid = $user->func ? $user->id : $request->input('funcionario_id');
        if (!$fid) {
            return response()->json(['error' => 'Selecione um barbeiro.'], 422);
        }

        $slots = $request->slots ?? [];

        // Remove os slots do dia DESTE barbeiro e recria com os selecionados.
        DisponibilidadeModel::where('data', $data)->where('funcionario_id', $fid)->delete();

        foreach ($slots as $hora) {
            if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
                DisponibilidadeModel::firstOrCreate(
                    ['funcionario_id' => $fid, 'data' => $data, 'hora' => $hora . ':00'],
                    ['created_by' => $user->id]
                );
            }
        }

        return response()->json(['ok' => true]);
    }

    // ── API: horários disponíveis para agendamento (para o calendário) ───────
    public function horarios($data)
    {
        $authUser = auth()->user();
        $isStaff  = $authUser && ($authUser->adm || $authUser->func);
        $fid      = request('funcionario_id'); // barbeiro escolhido (booking) ou em edição
        // Intervalo dos horários: staff usa a grade de 15min; cliente usa o menor serviço
        // ativo (ex.: tudo ≥1h → só :00; 30min → :00/:30; 15min → de 15 em 15).
        $intervalo = $isStaff ? 15 : ServicosModel::intervaloMinimoCliente();

        $slotsDisponiveis = DisponibilidadeModel::where('data', $data)
            ->when($fid, fn($q) => $q->where('funcionario_id', $fid))
            ->orderBy('hora')
            ->pluck('hora')
            ->map(fn($h) => substr($h, 0, 5))
            ->toArray();

        if (empty($slotsDisponiveis)) {
            return response()->json([]);
        }

        $servico = ServicosModel::find(request('servico_id'));

        if (!$servico) {
            return response()->json(['error' => 'Serviço não encontrado'], 422);
        }

        // Clientes não podem agendar serviços restritos ao staff
        if (!$isStaff && !$servico->visivel_cliente) {
            return response()->json(['error' => 'Serviço não disponível'], 403);
        }

        // Agendamento especial (encaixe) pode ser colocado sobre outros horários sem
        // conflitar com eles. É uma propriedade do agendamento (não do serviço) e só o
        // staff pode marcá-lo, vindo do checkbox no formulário.
        $especialAgendamento = $isStaff && request()->boolean('especial');

        // Cliente só enxerga slots no intervalo do menor serviço ativo (:00, :00/:30,
        // ou de 15 em 15). O start em minutos deve ser múltiplo do intervalo.
        if (!$isStaff) {
            $slotsDisponiveis = array_values(array_filter(
                $slotsDisponiveis,
                fn($h) => (((int) substr($h, 0, 2)) * 60 + (int) substr($h, 3, 2)) % $intervalo === 0
            ));
        }

        $ignoreId  = request('ignore_id');
        // Conflito é POR BARBEIRO: só agendamentos do barbeiro selecionado ocupam o slot.
        // (Órfãos legados com funcionario_id NULL não bloqueiam nenhum barbeiro.)
        $consultas = AgendamentoModel::with('servico')
            ->whereDate('data_inicio', $data)
            ->when($fid, fn($q) => $q->where('funcionario_id', $fid))
            ->whereIn('status', AgendamentoModel::OCUPANTES)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->orderBy('data_inicio')
            ->get();

        // Especial pode ter duração personalizada; vazio = duração do serviço.
        $duracaoMin = $this->timeToMinutes($servico->duracao);
        if ($especialAgendamento && request()->filled('duracao_min')) {
            $duracaoMin = max(1, (int) request('duracao_min'));
        }
        $slotsNecessarios = (int) ceil($duracaoMin / $intervalo);

        $agora = Carbon::now();

        $horarios = [];

        foreach ($slotsDisponiveis as $hora) {
            $inicio = Carbon::parse($data . ' ' . $hora);

            if ($inicio <= $agora) {
                $horarios[] = [
                    'hora'    => $hora,
                    'ocupado' => true,
                    'motivo'  => 'Horário já passou',
                ];
                continue;
            }

            // Clientes só podem agendar com no mínimo 2h de antecedência (tempo de deslocamento da nutri).
            if (!$isStaff && $inicio < $agora->copy()->addHours(2)) {
                $horarios[] = [
                    'hora'    => $hora,
                    'ocupado' => true,
                    'motivo'  => 'Antecedência mínima de 2h',
                ];
                continue;
            }

            $fimTeorico = $inicio->copy()->addMinutes($duracaoMin);
            $ocupado    = false;
            $motivo     = null;

            // 1. Slot diretamente ocupado por um agendamento
            // Especiais não conflitam: nem bloqueiam nem são bloqueados por outros agendamentos.
            if (!$especialAgendamento) {
                foreach ($consultas as $c) {
                    if ($c->especial) continue; // agendamento especial não bloqueia
                    $inicioC = Carbon::parse($c->data_inicio);
                    $fimC    = Carbon::parse($c->data_fim);
                    if ($inicio >= $inicioC && $inicio < $fimC) {
                        $ocupado = true;
                        $motivo  = 'Horário já reservado';
                        break;
                    }
                }
            }

            // 2. Slots consecutivos necessários estão todos disponíveis?
            if (!$ocupado && $slotsNecessarios > 1) {
                for ($i = 1; $i < $slotsNecessarios; $i++) {
                    $slotCheck = $inicio->copy()->addMinutes($intervalo * $i)->format('H:i');
                    if (!in_array($slotCheck, $slotsDisponiveis)) {
                        $ocupado = true;
                        $motivo  = 'Não comporta a duração do serviço';
                        break;
                    }
                }
            }

            // 3. Duração conflita com outro agendamento?
            if (!$ocupado && !$especialAgendamento) {
                foreach ($consultas as $c) {
                    if ($c->especial) continue; // agendamento especial não bloqueia
                    $inicioC = Carbon::parse($c->data_inicio);
                    $fimC    = Carbon::parse($c->data_fim);
                    if ($inicio < $fimC && $fimTeorico > $inicioC) {
                        $ocupado = true;
                        $motivo  = 'A duração conflita com outro agendamento';
                        break;
                    }
                }
            }

            $horarios[] = [
                'hora'    => $hora,
                'ocupado' => $ocupado,
                'motivo'  => $motivo,
            ];
        }

        return response()->json($horarios);
    }

    // ── Criar / editar agendamento ────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate(
            [
                'user_id'     => ['required'],
                'servico_id'  => ['required', \Illuminate\Validation\Rule::exists('servicos', 'id')->where('excluido', 0)],
                'data_inicio' => ['required', 'date', 'after:now'],
            ],
            [
                'user_id.required'     => 'Selecione o cliente.',
                'servico_id.required'  => 'Selecione o serviço.',
                'servico_id.exists'    => 'Serviço inválido.',
                'data_inicio.required' => 'Selecione um dia e horário.',
                'data_inicio.date'     => 'Data inválida.',
                'data_inicio.after'    => 'O agendamento deve ser em uma data futura.',
            ]
        );

        $authUser = auth()->user();
        $isStaff  = $authUser->adm || $authUser->func;
        $ignoreId = $request->agendamento_id;

        $servico = ServicosModel::where('excluido', 0)->find($request->servico_id);
        if (!$servico) {
            return back()->withErrors(['servico_id' => 'Serviço inválido.'])->withInput();
        }

        // Clientes: buscar agendamento original uma única vez (reutilizado nas validações abaixo)
        $agendamentoOriginal = null;
        if (!$isStaff) {
            $request->merge(['user_id' => $authUser->id]);

            if (!$ignoreId) {
                abort(403); // Clientes só podem reagendar
            }

            $agendamentoOriginal = AgendamentoModel::where('id', $ignoreId)
                ->where('user_id', $authUser->id)
                ->firstOrFail();

            // Serviço não pode ser alterado no reagendamento
            if ((int) $request->servico_id !== (int) $agendamentoOriginal->servico_id) {
                return back()
                    ->withErrors(['data_inicio' => 'Não é possível alterar o serviço no reagendamento.'])
                    ->withInput();
            }
        }

        // Clientes só podem usar serviços visíveis; exceção: reagendar com o serviço original
        if (!$isStaff && !$servico->visivel_cliente) {
            if (!$agendamentoOriginal || (int) $agendamentoOriginal->servico_id !== (int) $servico->id) {
                abort(403);
            }
        }

        // Barbeiro responsável: func → só si; adm → do formulário; cliente reagendando
        // mantém o barbeiro original (não troca de barbeiro ao reagendar).
        if ($isStaff) {
            $funcionarioId = $authUser->func ? $authUser->id : $request->funcionario_id;
            if (!$funcionarioId) {
                return back()->withErrors(['funcionario_id' => 'Selecione o barbeiro.'])->withInput();
            }
        } else {
            $funcionarioId = $agendamentoOriginal?->funcionario_id;
        }

        // Agendamento especial (encaixe): só o staff pode marcar. Pode sobrepor outros
        // agendamentos e ter duração personalizada (vazio = duração do serviço).
        $especial = $isStaff && $request->boolean('especial');

        $duracao = $this->timeToMinutes($servico->duracao);
        if ($especial && $request->filled('duracao_min')) {
            $duracao = max(1, (int) $request->duracao_min);
        }
        $intervalo = $isStaff ? 15 : 30;
        $inicio    = Carbon::parse($request->data_inicio);
        $fim       = $inicio->copy()->addMinutes($duracao);

        // Clientes só podem agendar com no mínimo 2h de antecedência (tempo de deslocamento da nutri).
        if (!$isStaff && $inicio < Carbon::now()->addHours(2)) {
            return back()
                ->withErrors(['data_inicio' => 'Só é possível agendar com no mínimo 2 horas de antecedência.'])
                ->withInput();
        }

        // Slots abertos do dia PARA O BARBEIRO selecionado (grade própria).
        $slotsDisponiveis = DisponibilidadeModel::where('data', $inicio->toDateString())
            ->when($funcionarioId, fn($q) => $q->where('funcionario_id', $funcionarioId))
            ->pluck('hora')
            ->map(fn($h) => substr($h, 0, 5))
            ->toArray();

        // Clientes só podem usar slots em :00 e :30
        if (!$isStaff) {
            $slotsDisponiveis = array_values(array_filter(
                $slotsDisponiveis,
                fn($h) => in_array(substr($h, 3, 2), ['00', '30'])
            ));
        }

        // Verificar se o slot inicial está disponível
        if (!in_array($inicio->format('H:i'), $slotsDisponiveis)) {
            return back()->withErrors(['data_inicio' => 'Este horário não está disponível'])->withInput();
        }

        // Verificar se todos os slots necessários estão disponíveis
        $slotsNecessarios = (int) ceil($duracao / $intervalo);
        for ($i = 1; $i < $slotsNecessarios; $i++) {
            $slotCheck = $inicio->copy()->addMinutes($intervalo * $i)->format('H:i');
            if (!in_array($slotCheck, $slotsDisponiveis)) {
                return back()->withErrors(['data_inicio' => 'O serviço ultrapassa os horários disponíveis'])->withInput();
            }
        }

        // Agendamentos especiais (encaixe) podem ser colocados sobre outros horários sem
        // conflitar. Os demais só conflitam com agendamentos não-especiais (os especiais
        // são "transparentes").
        $existeConflito = !$especial && AgendamentoModel::where(function ($q) use ($inicio, $fim) {
                $q->where('data_inicio', '<', $fim)
                  ->where('data_fim', '>', $inicio);
            })
            ->where('especial', 0)
            ->when($funcionarioId, fn($q) => $q->where('funcionario_id', $funcionarioId))
            ->whereIn('status', AgendamentoModel::OCUPANTES)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($existeConflito) {
            return back()
                ->withErrors(['data_inicio' => 'A duração deste serviço conflita com outro agendamento'])
                ->withInput();
        }

        $dataAntiga = null;
        if ($ignoreId) {
            $agendamento = $agendamentoOriginal ?? AgendamentoModel::findOrFail($ignoreId);
            $dataAntiga  = $agendamento->data_inicio;
        } else {
            $agendamento = new AgendamentoModel();
        }

        $isEdicao = !empty($ignoreId);

        // Créditos de serviço: consome 1 unidade do pacote (credito_id = linha de
        // creditos_servico; o cliente pode ter vários pacotes do mesmo serviço).
        // Cancelar (destroy) devolve automaticamente (a linha some da contagem).
        // - Serviços visíveis ao cliente EXIGEM um pacote com saldo (do próprio serviço).
        // - Serviços de staff (ex.: especial) podem descontar de qualquer pacote do
        //   cliente ou ser livres (sem desconto).
        // Na edição, só o staff pode trocar o pacote; mantido o mesmo, não revalida
        // (o pacote pode estar zerado justamente por este agendamento). Cliente
        // reagendando não envia credito_id e não altera o desconto.
        $creditoAtualId = $isEdicao ? $agendamento->credito_servico_id : null;
        $creditoNovoId  = $request->credito_id ?: null;
        $alterarCredito = !$isEdicao
            || ($isStaff && (string) ($creditoNovoId ?? '') !== (string) ($creditoAtualId ?? ''));

        if ($alterarCredito) {
            $credito = null;

            if ($creditoNovoId) {
                $credito = CreditoServico::where('user_id', $request->user_id)->find($creditoNovoId);

                if (!$credito || $credito->restantes() <= 0) {
                    return back()
                        ->withErrors(['servico_id' => 'O pacote escolhido para desconto não possui saldo.'])
                        ->withInput();
                }
                if ($credito->expirado()) {
                    return back()
                        ->withErrors(['servico_id' => 'O pacote escolhido está expirado (em negociação). Selecione um pacote vigente.'])
                        ->withInput();
                }
            }

            if (!$especial) {
                // Agendamento comum: precisa de pacote VIGENTE do próprio serviço.
                // Sem pacote explícito, FIFO — pega o vigente que vence primeiro com saldo.
                if (!$credito) {
                    $credito = CreditoServico::where('user_id', $request->user_id)
                        ->where('servico_id', $servico->id)
                        ->vigente()
                        ->get()
                        ->filter(fn($c) => $c->restantes() > 0)
                        ->sortBy('expira_em')
                        ->first();
                }
                if (!$credito || (int) $credito->servico_id !== (int) $servico->id) {
                    return back()
                        ->withErrors(['servico_id' => 'O cliente não possui saldo vigente para este serviço.'])
                        ->withInput();
                }
            }

            $agendamento->credito_servico_id = $credito?->id;
            $agendamento->consome_credito    = (bool) $credito;
        }

        // Reagendamento (edição com data diferente): a véspera/2h e a confirmação
        // precisam valer para a NOVA data. Sem isso, o comando diário pula a consulta
        // (já existe um lembrete 'enviado' + unique agendamento_id/tipo) e o cliente
        // nunca recebe a véspera da data nova.
        $dataMudou = $isEdicao && $dataAntiga && !$dataAntiga->equalTo($inicio);

        $agendamento->user_id        = $request->user_id;
        $agendamento->servico_id     = $servico->id;
        $agendamento->funcionario_id = $funcionarioId;
        $agendamento->data_inicio    = $inicio;
        $agendamento->data_fim       = $fim;
        $agendamento->especial       = $especial;
        $agendamento->confirmado     = 0;
        if ($dataMudou) {
            $agendamento->pre_confirmado_em = null;
            $agendamento->confirmado_em     = null;

            // Remarcação de visita de plano já descontada (o desconto é por data):
            // devolve a unidade e limpa consumo_plano p/ o command descontar na nova data.
            if ($agendamento->plano_mensal_id && !empty($agendamento->consumo_plano)) {
                $plano = PlanoMensal::with('itens')->find($agendamento->plano_mensal_id);
                if ($plano) {
                    $plano->restaurarVisita($agendamento->consumo_plano);
                }
                $agendamento->consumo_plano = null;
            }
        }
        $agendamento->save();

        // Remove os lembretes já enviados para que a véspera/2h disparem de novo na data nova.
        if ($dataMudou) {
            $agendamento->lembretes()->delete();
        }

        if ($isStaff) {
            $agendamento->load(['user', 'servico']);
            $this->notificarWhatsApp($agendamento, $isEdicao);
        } elseif ($isEdicao && $dataAntiga) {
            Aviso::create([
                'tipo'        => 'reagendamento',
                'user_id'     => $authUser->id,
                'servico_id'  => $servico->id,
                'especial'    => $especial,
                'data_antiga' => $dataAntiga,
                'data_nova'   => $inicio,
            ]);
        }

        return redirect()->back()->with('msg', 'Agendamento salvo com sucesso');
    }

    private function notificarWhatsApp(AgendamentoModel $agendamento, bool $isEdicao): void
    {
        $user    = $agendamento->user;
        $phoneId = env('PHONE_NUMBER_ID');

        if (!$user || !$user->whatsapp || !$phoneId) return;

        $nome    = ucfirst($user->name);
        $data    = Carbon::parse($agendamento->data_inicio)
                       ->locale('pt_BR')
                       ->translatedFormat('d \d\e F \d\e Y');
        $hora    = Carbon::parse($agendamento->data_inicio)->format('H:i');
        $servico = $agendamento->servico->descricao ?? '';

        // Pacote descontado: mostra o saldo restante "Serviço X (2/5)". Especial que
        // descontou de outro serviço mostra o nome do serviço descontado. Cortesia
        // (sem desconto) mantém o nome original sem contador.
        $credito = $agendamento->creditoServico;
        if ($credito) {
            if ($credito->servico_id != $agendamento->servico_id) {
                $servico = $credito->servico->descricao ?? $servico;
            }
            // Pacote de unidade única (1/1): mostra só o nome, sem o "(1/1)" redundante
            // (mesma regra de AgendamentoModel::getServicoDisplayAttribute).
            if ($credito->quantidade > 1) {
                $servico .= ' (' . $credito->ordinalDe($agendamento) . '/' . $credito->quantidade . ')';
            }
        }

        if ($isEdicao) {
            // confirmacao_reagendamento_fr: nome, nome_comercial, data, hora
            // (o "confirmacao_reagendamento" sem sufixo é o template da clínica no
            // WABA compartilhado — falava "consulta" para cliente da barbearia)
            WhatsappController::enviarModelo($phoneId, $user->whatsapp, env('WHATSAPP_TEMPLATE_CONFIRMACAO_REAGENDAMENTO', 'confirmacao_reagendamento_fr'), [
                ['type' => 'text', 'text' => $nome],
                ['type' => 'text', 'text' => env('WHATSAPP_NOME_COMERCIAL', config('app.name'))],
                ['type' => 'text', 'text' => $data],
                ['type' => 'text', 'text' => $hora],
            ]);
        } else {
            // confirmacao_agendamento_fr: nome, data+hora, servico, numero de confirmacao
            // (template próprio do frioli — o "confirmacao_agendamento" do WABA é da clínica)
            WhatsappController::enviarModelo($phoneId, $user->whatsapp, env('WHATSAPP_TEMPLATE_CONFIRMACAO_AGENDAMENTO', 'confirmacao_agendamento_fr'), [
                ['type' => 'text', 'text' => $nome],
                ['type' => 'text', 'text' => $data . ' às ' . $hora],
                ['type' => 'text', 'text' => $servico],
                ['type' => 'text', 'text' => (string) $agendamento->id],
            ]);
        }
    }

    // Reenvia manualmente (pela equipe) o lembrete com os botões confirmar/reagendar.
    public function reenviarLembrete($id)
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $agendamento = AgendamentoModel::with([
            'user', 'servico', 'planoMensal.itens.servico', 'creditoServico.servico',
        ])->findOrFail($id);
        if ($user->func && (int) $agendamento->funcionario_id !== (int) $user->id) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }
        $cliente     = $agendamento->user;
        $phoneId     = env('PHONE_NUMBER_ID');
        $template    = env('WHATSAPP_TEMPLATE_LEMBRETE');

        if (!$cliente || !$cliente->whatsapp) {
            return response()->json(['error' => 'Cliente sem WhatsApp cadastrado.'], 422);
        }
        if (!$phoneId || !$template) {
            return response()->json(['error' => 'WhatsApp não configurado.'], 422);
        }

        $nome          = ucfirst($cliente->name);
        $nomeComercial = env('WHATSAPP_NOME_COMERCIAL', config('app.name'));
        $data          = Carbon::parse($agendamento->data_inicio)
                             ->locale('pt_BR')
                             ->translatedFormat('d \d\e F \d\e Y');
        $hora          = Carbon::parse($agendamento->data_inicio)->format('H:i');

        // Saldo do pacote/plano concatenado no parâmetro da hora (mesmo formato do
        // lembrete automático): ex.: "14:00 · corte 1/4, barba 1/4".
        $saldo   = $agendamento->saldoPacoteTexto();
        $horaMsg = $saldo !== '' ? "{$hora} · {$saldo}" : $hora;

        $resultado = WhatsappController::enviarModelo($phoneId, $cliente->whatsapp, $template, [
            ['type' => 'text', 'text' => $nome],
            ['type' => 'text', 'text' => $nomeComercial],
            ['type' => 'text', 'text' => $data],
            ['type' => 'text', 'text' => $horaMsg],
        ], 'pt_BR', [
            'confirmar_' . $agendamento->id,
            'reagendar_' . $agendamento->id,
        ]);

        if (isset($resultado['erro'])) {
            return response()->json(['error' => $resultado['msg'] ?? 'Falha ao enviar lembrete.'], 422);
        }

        // Envio manual substitui o lembrete da véspera: marca '24h' como enviado para o
        // cron diário não reenviar (a véspera é agora o único lembrete).
        LembreteConsulta::updateOrCreate(
            ['agendamento_id' => $agendamento->id, 'tipo' => '24h'],
            ['status' => 'enviado', 'erro_msg' => null],
        );

        return response()->json(['ok' => true]);
    }

    public function confirmar($id)
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $agendamento = AgendamentoModel::with(['user', 'servico', 'planoMensal.itens.servico'])->findOrFail($id);
        if ($user->func && (int) $agendamento->funcionario_id !== (int) $user->id) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }
        // Reserva "sem saldo" (aguardando renovação): não pode ser confirmada/atendida
        // enquanto o cliente não renovar o plano.
        if ($agendamento->status === AgendamentoModel::STATUS_RESERVA_RENOVACAO) {
            return response()->json(['error' => 'Cliente sem saldo neste plano — renove o plano para atender.'], 422);
        }
        $eraConfirmado = (bool) $agendamento->confirmado;
        $agendamento->confirmado    = 1;
        $agendamento->confirmado_em = now();
        $agendamento->status        = AgendamentoModel::STATUS_CONFIRMADO;
        // Confirmação manual vale como pré-confirmação também (estado consistente).
        if (!$agendamento->pre_confirmado_em) {
            $agendamento->pre_confirmado_em = now();
        }
        $agendamento->save();

        // O desconto da visita do plano mensal é por data (command planos:descontar-visitas),
        // não na confirmação. Aqui só marcamos a presença (confirmado/status).

        $this->notificarWhatsApp($agendamento, false);

        return response()->json(['ok' => true, 'confirmado_em' => $agendamento->confirmado_em->format('d/m H:i')]);
    }

    /**
     * Recusa um agendamento do booking público (pago ou pendente): marca
     * STATUS_RECUSADO (libera o slot e some da agenda) e, quando houve pagamento
     * online aprovado, credita 1 unidade do serviço ao cliente (pacote avulso,
     * sem validade) para remarcar. O dinheiro NÃO é reembolso por aqui — a
     * devolução, se pedida, é combinada diretamente com o barbeiro via WhatsApp.
     */
    public function recusar($id)
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $agendamento = AgendamentoModel::with(['user', 'servico'])->findOrFail($id);

        if ($user->func && (int) $agendamento->funcionario_id !== (int) $user->id) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        if (!in_array($agendamento->status, [
            AgendamentoModel::STATUS_PAGO_AGUARDANDO,
            AgendamentoModel::STATUS_AGUARDANDO_PAGAMENTO,
            AgendamentoModel::STATUS_CONFIRMADO,
        ])) {
            return response()->json(['error' => 'Este agendamento não pode ser recusado.'], 422);
        }

        $agendamento->status = AgendamentoModel::STATUS_RECUSADO;
        $agendamento->save();

        // Pagamento online aprovado → o valor vira 1 unidade de crédito. A ordem
        // PERMANECE 'approved' (o registro do evento documenta a conversão).
        // "Pagar no local" e pendentes sem ordem não geram crédito (nada foi pago).
        $credito = false;
        $motivo  = 'Sem pagamento online';

        if (!$agendamento->pagar_no_local) {
            $ordem = OrdemPagamento::where('agendamento_id', $agendamento->id)
                ->whereIn('status', ['approved'])
                ->latest('id')
                ->first();

            if ($ordem) {
                CreditoServico::create([
                    'user_id'    => $agendamento->user_id,
                    'servico_id' => $agendamento->servico_id,
                    'quantidade' => 1,
                    'expira_em'  => null, // pacote avulso: sem validade
                ]);
                $ordem->eventos()->create([
                    'status'  => 'recusado_credito',
                    'origem'  => 'manual',
                    'payload' => [
                        'agendamento_id' => $agendamento->id,
                        'credito'        => '1 unidade (sem validade)',
                    ],
                ]);
                $credito = true;
                $motivo  = 'Valor convertido em 1 unidade de crédito';
            }
        }

        $this->notificarClienteRecusa($agendamento, $credito);

        return response()->json([
            'ok'      => true,
            'credito' => $credito,
            'motivo'  => $motivo,
        ]);
    }

    private function notificarClienteRecusa(AgendamentoModel $agendamento, bool $credito): void
    {
        $user     = $agendamento->user;
        $phoneId  = env('PHONE_NUMBER_ID');
        if (!$user || !$user->whatsapp || !$phoneId) {
            return;
        }

        $servico = $agendamento->servico->descricao ?? 'serviço';

        $msg = "Olá, " . ucfirst($user->name) . "! Não conseguimos confirmar seu horário na data escolhida. ";
        $msg .= $credito
            ? "Você ganhou 1 crédito de {$servico} (sem validade) para agendar quando quiser pelo site. "
              . "Se preferir a devolução do valor, é só combinar com a gente por aqui no WhatsApp."
            : "Você pode reagendar pelo site ou combinar outro horário com a gente por aqui no WhatsApp.";

        try {
            WhatsappController::enviarMsg($phoneId, $user->whatsapp, $msg);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('single')->warning('[BOOKING] falha ao avisar cliente da recusa', ['msg' => $e->getMessage()]);
        }
    }

    /**
     * Aprova uma "intenção de agendamento" de cliente penalizado (pagar no local):
     * confirma o horário (se ainda livre) e REMOVE a penalidade do cliente, que volta
     * a poder agendar normalmente. Func só aprova da própria agenda.
     */
    public function aprovarIntencao($id)
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $ag = AgendamentoModel::with(['user', 'servico'])->findOrFail($id);
        if ($user->func && (int) $ag->funcionario_id !== (int) $user->id) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }
        if ($ag->status !== AgendamentoModel::STATUS_INTENCAO) {
            return response()->json(['error' => 'Esta intenção já foi processada.'], 422);
        }

        // A intenção não trava slot — revalida disponibilidade antes de confirmar.
        $conflito = AgendamentoModel::where('id', '!=', $ag->id)
            ->where('data_inicio', '<', $ag->data_fim)
            ->where('data_fim', '>', $ag->data_inicio)
            ->where('funcionario_id', $ag->funcionario_id)
            ->whereIn('status', AgendamentoModel::OCUPANTES)
            ->exists();
        if ($conflito) {
            return response()->json(['error' => 'Esse horário não está mais disponível.'], 422);
        }

        $ag->confirmado        = 1;
        $ag->confirmado_em     = now();
        $ag->status            = AgendamentoModel::STATUS_CONFIRMADO;
        $ag->pre_confirmado_em = $ag->pre_confirmado_em ?: now();
        $ag->save();

        // Aprovação libera o cliente: a penalidade é removida.
        if ($ag->user && $ag->user->isPenalizado()) {
            $ag->user->penalizado    = false;
            $ag->user->penalizado_em = null;
            $ag->user->save();
        }

        $this->notificarWhatsApp($ag, false);

        return response()->json(['ok' => true, 'confirmado_em' => $ag->confirmado_em->format('d/m H:i')]);
    }

    /**
     * Recusa uma "intenção de agendamento": o status vira recusado e a penalidade do
     * cliente PERMANECE (só a aprovação a remove). Func só recusa da própria agenda.
     */
    public function recusarIntencao($id)
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $ag = AgendamentoModel::with(['user', 'servico'])->findOrFail($id);
        if ($user->func && (int) $ag->funcionario_id !== (int) $user->id) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }
        if ($ag->status !== AgendamentoModel::STATUS_INTENCAO) {
            return response()->json(['error' => 'Esta intenção já foi processada.'], 422);
        }

        $ag->status = AgendamentoModel::STATUS_RECUSADO;
        $ag->save();

        $this->notificarClienteIntencaoRecusada($ag);

        return response()->json(['ok' => true]);
    }

    private function notificarClienteIntencaoRecusada(AgendamentoModel $agendamento): void
    {
        $user    = $agendamento->user;
        $phoneId = env('PHONE_NUMBER_ID');
        if (!$user || !$user->whatsapp || !$phoneId) {
            return;
        }
        $msg = 'Olá, ' . ucfirst($user->name) . '! Seu pedido de agendamento no local não pôde ser aprovado pelo barbeiro. '
             . 'Você segue podendo agendar pelo site com pagamento online, ou pode falar com a barbearia.';
        try {
            WhatsappController::enviarMsg($phoneId, $user->whatsapp, $msg);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('single')->warning('[BOOKING] falha ao avisar cliente da recusa de intenção', ['msg' => $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $user        = auth()->user();
        $agendamento = AgendamentoModel::with('creditoServico.agendamentos:id,credito_servico_id')->findOrFail($id);

        if (!$user->adm && !$user->func && $agendamento->user_id !== $user->id) {
            abort(403);
        }
        // Funcionário só edita atendimentos da própria agenda.
        if ($user->func && (int) $agendamento->funcionario_id !== (int) $user->id) {
            abort(403);
        }

        // Inclui o ordinal desta consulta no pacote, para o select reabrir com o
        // número correto mesmo quando o pacote já está esgotado por ela.
        $data = $agendamento->toArray();
        $data['credito_ordinal'] = $agendamento->creditoServico
            ? $agendamento->creditoServico->ordinalDe($agendamento)
            : null;

        return $data;
    }

    public function destroy($id)
    {
        $user        = auth()->user();
        $agendamento = AgendamentoModel::with(['user', 'servico'])->findOrFail($id);

        // Funcionário só remove atendimentos da própria agenda.
        if ($user->func && (int) $agendamento->funcionario_id !== (int) $user->id) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        if (!$user->adm && !$user->func) {
            if ($agendamento->user_id !== $user->id) {
                abort(403);
            }
            if (!$agendamento->data_inicio->isFuture()) {
                return response()->json(['error' => 'Não é possível cancelar agendamentos passados.'], 422);
            }
            Aviso::create([
                'tipo'        => 'cancelamento',
                'user_id'     => $user->id,
                'servico_id'  => $agendamento->servico_id,
                'especial'    => $agendamento->especial,
                'data_antiga' => $agendamento->data_inicio,
            ]);
            $this->notificarStaffCancelamento($agendamento);
        }

        // Devolve o saldo do plano mensal se a visita foi descontada (desconto agora é
        // por data via command planos:descontar-visitas, marcado em consumo_plano — não
        // mais na confirmação). Reverte exatamente os itens consumidos naquela visita.
        if ($agendamento->plano_mensal_id && !empty($agendamento->consumo_plano)) {
            $plano = PlanoMensal::with('itens')->find($agendamento->plano_mensal_id);
            if ($plano) {
                $plano->restaurarVisita($agendamento->consumo_plano);
            }
        }

        $agendamento->delete();
        return response()->json(['ok' => true]);
    }

    // Retorna apenas o HTML da lista de avisos (para atualização parcial via axios).
    public function avisosParcial()
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $avisos = Aviso::with(['user', 'servico'])
            ->whereNull('dispensado_at')
            ->latest()
            ->get();

        return response()->json([
            'count' => $avisos->count(),
            'html'  => view('partials.avisos', compact('avisos'))->render(),
        ]);
    }

    public function dispensarAviso($id)
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }
        Aviso::findOrFail($id)->update(['dispensado_at' => now()]);
        return response()->json(['ok' => true]);
    }

    /**
     * Marca comparecimento (true) ou no-show (false). No-show penaliza o cliente
     * (bloqueia novos agendamentos até adm/func remover a penalidade).
     */
    public function comparecimento(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $agendamento = AgendamentoModel::with('user')->findOrFail($id);
        if ($user->func && (int) $agendamento->funcionario_id !== (int) $user->id) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $compareceu = (bool) $request->input('compareceu', false);
        $agendamento->compareceu = $compareceu;
        $agendamento->save();

        if (!$compareceu && $agendamento->user && !$agendamento->user->isPenalizado()) {
            $agendamento->user->penalizado    = true;
            $agendamento->user->penalizado_em = now();
            $agendamento->user->save();
        }

        return response()->json(['ok' => true, 'compareceu' => $compareceu]);
    }

    /**
     * Remove a penalidade de um cliente (adm ou func).
     */
    public function removerPenalidade($userId)
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $alvo = User::findOrFail($userId);
        $alvo->penalizado    = false;
        $alvo->penalizado_em = null;
        $alvo->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Pré-reserva as ocorrências do plano mensal (status pago_aguardando) cujo slot do
     * barbeiro já esteja aberto — bloqueia clientes avulsos. NÃO desconta unidade (o
     * desconto é só na confirmação do funcionário). Idempotente (pula conflito/já-existente).
     * $inicio/$fim limitam a janela de datas (usado pelo hook de salvarSemana).
     * Retorna o nº de pré-reservas criadas.
     */
    public function materializarPlano(PlanoMensal $plano, ?Carbon $inicio = null, ?Carbon $fim = null): int
    {
        if ($plano->status !== PlanoMensal::STATUS_ATIVO) {
            return 0;
        }
        if (!$plano->funcionario_id || !$plano->servico) {
            return 0;
        }

        $duracaoMin = $this->timeToMinutes((string) $plano->servico->duracao);
        $horaStr    = (string) $plano->hora; // "HH:MM:SS"
        $hhmm       = substr($horaStr, 0, 5);
        $agora      = Carbon::now();
        $criados    = 0;

        foreach ($plano->datasOcorrencias() as $idx => $data) {
            if ($inicio && $data->lt($inicio->copy()->startOfDay())) {
                continue;
            }
            if ($fim && $data->gt($fim->copy()->endOfDay())) {
                continue;
            }

            $inicioAg = $data->copy()->setTimeFromTimeString($hhmm);
            if ($inicioAg <= $agora) {
                continue; // só datas futuras
            }
            $fimAg = $inicioAg->copy()->addMinutes($duracaoMin);

            // Ao renovar (novo ciclo ativo cobrindo uma semana antes reservada "sem
            // saldo"), substitui a reserva pela visita paga — ANTES das checagens abaixo.
            if ($plano->assinatura_id) {
                AgendamentoModel::where('assinatura_id', $plano->assinatura_id)
                    ->where('status', AgendamentoModel::STATUS_RESERVA_RENOVACAO)
                    ->where(function ($q) use ($inicioAg, $fimAg) {
                        $q->where('data_inicio', '<', $fimAg)->where('data_fim', '>', $inicioAg);
                    })
                    ->delete();
            }

            // Semana (dom..sáb) desta ocorrência: se a assinatura (ou este plano) já
            // tem uma visita reservada nesta semana (ex.: reagendou a sexta para o
            // sábado, ou já existe reserva/pré-reserva), não recria — evita duplicidade.
            $domingo = $data->copy()->startOfWeek(Carbon::SUNDAY);
            $jaNaSemana = AgendamentoModel::whereBetween('data_inicio', [$domingo, $domingo->copy()->addDays(6)->endOfDay()])
                ->whereIn('status', AgendamentoModel::OCUPANTES)
                ->where(function ($q) use ($plano) {
                    $q->where('plano_mensal_id', $plano->id);
                    if ($plano->assinatura_id) {
                        $q->orWhere('assinatura_id', $plano->assinatura_id);
                    }
                })
                ->exists();
            if ($jaNaSemana) {
                continue;
            }

            // Plano mensal = slot fixo GARANTIDO: não exige que o barbeiro tenha aberto
            // o slot explicitamente (o plano é um compromisso fixo do cliente).

            // Conflito por barbeiro (ou já existe pré-reserva deste plano)?
            $conflito = AgendamentoModel::where('funcionario_id', $plano->funcionario_id)
                ->where(function ($q) use ($inicioAg, $fimAg) {
                    $q->where('data_inicio', '<', $fimAg)->where('data_fim', '>', $inicioAg);
                })
                ->whereIn('status', AgendamentoModel::OCUPANTES)
                ->exists();
            if ($conflito) {
                continue;
            }

            AgendamentoModel::create([
                'user_id'         => $plano->user_id,
                'servico_id'      => $plano->servico_id,
                'funcionario_id'  => $plano->funcionario_id,
                'plano_mensal_id' => $plano->id,
                'assinatura_id'   => $plano->assinatura_id,
                'plano_ordem'     => $idx + 1, // posição (1-based) na distribuição
                'data_inicio'     => $inicioAg,
                'data_fim'        => $fimAg,
                'status'          => AgendamentoModel::STATUS_PAGO_AGUARDANDO,
                'confirmado'      => 0,
            ]);
            $criados++;
        }

        // Visitas pagas criadas = assinatura com saldo: dispensa o aviso "sem saldo".
        if ($criados > 0 && $plano->assinatura_id) {
            Aviso::where('tipo', 'plano_sem_saldo')
                ->where('user_id', $plano->user_id)
                ->whereNull('dispensado_at')
                ->update(['dispensado_at' => now()]);
        }

        return $criados;
    }

    /**
     * Ao abrir a semana (salvarSemana), pré-reserva os slots fixos dos planos mensais
     * ativos dos barbeiros cuja janela intersecta a semana aberta.
     */
    public function materializarPlanosSemana(array $funcionarioIds, Carbon $domingo): void
    {
        if (empty($funcionarioIds)) {
            return;
        }
        $sabado = $domingo->copy()->addDays(6);

        $planos = PlanoMensal::ativo()
            ->whereIn('funcionario_id', $funcionarioIds)
            ->get();

        foreach ($planos as $plano) {
            $this->materializarPlano($plano, $domingo, $sabado);
        }
    }

    /**
     * Ao abrir a semana, reserva o slot fixo de assinaturas ativas que estão SEM
     * ciclo pago cobrindo esta semana (serviços esgotados / aguardando renovação).
     * O slot fica ocupado (status reserva_renovacao, bloqueia outros clientes) e
     * cria 1 aviso ao staff por cliente. Ao renovar, materializarPlano substitui a
     * reserva pela visita paga e dispensa o aviso.
     */
    public function materializarReservasSemana(array $funcionarioIds, Carbon $domingo): void
    {
        if (empty($funcionarioIds)) {
            return;
        }
        $sabado = $domingo->copy()->addDays(6)->endOfDay();
        $agora  = Carbon::now();

        $assinaturas = \App\Models\AssinaturaMensal::ativo()
            ->whereIn('funcionario_id', $funcionarioIds)
            ->whereNotNull('servico_base_id')
            ->with('servicoBase')
            ->get();

        foreach ($assinaturas as $a) {
            // Ocorrência do dia_semana dentro desta semana (dom..sáb).
            $oc  = null;
            $cur = $domingo->copy();
            while ($cur <= $sabado) {
                if ((int) $cur->format('w') === (int) $a->dia_semana) {
                    $oc = $cur->copy();
                    break;
                }
                $cur->addDay();
            }
            if (!$oc) {
                continue;
            }

            // QUINZENAL: nas semanas que NÃO são do cliente o slot fica LIVRE —
            // disponível para avulsos ou para o quinzenal "complementar" (fase
            // oposta) que divide este horário. Só reserva nas semanas da fase dele.
            if ($a->servicoBase && $a->servicoBase->quinzenal
                && $a->quinzenal_fase !== null
                && PlanoMensal::faseSemana($oc) !== (int) $a->quinzenal_fase) {
                continue;
            }

            $horaStr  = (string) $a->hora;
            $inicioAg = $oc->copy()->setTimeFromTimeString(substr($horaStr, 0, 5));
            if ($inicioAg <= $agora) {
                continue; // só datas futuras
            }

            // Slot fixo da assinatura = garantido, mesmo sem o barbeiro ter aberto.

            // Já existe visita/reserva desta assinatura nesta semana? -> nada a fazer.
            $dom = $oc->copy()->startOfWeek(Carbon::SUNDAY);
            $ja = AgendamentoModel::where('assinatura_id', $a->id)
                ->whereBetween('data_inicio', [$dom, $dom->copy()->addDays(6)->endOfDay()])
                ->whereIn('status', AgendamentoModel::OCUPANTES)
                ->exists();
            if ($ja) {
                continue;
            }

            // Conflito real (outro cliente já no slot)? -> não reserva por cima.
            $duracao = $this->timeToMinutes((string) ($a->servicoBase->duracao ?? '00:30:00'));
            $fimAg   = $inicioAg->copy()->addMinutes($duracao);
            $conflito = AgendamentoModel::where('funcionario_id', $a->funcionario_id)
                ->where(function ($q) use ($inicioAg, $fimAg) {
                    $q->where('data_inicio', '<', $fimAg)->where('data_fim', '>', $inicioAg);
                })
                ->whereIn('status', AgendamentoModel::OCUPANTES)
                ->exists();
            if ($conflito) {
                continue;
            }

            $reserva = AgendamentoModel::create([
                'user_id'        => $a->user_id,
                'servico_id'     => $a->servico_base_id,
                'funcionario_id' => $a->funcionario_id,
                'assinatura_id'  => $a->id,
                'data_inicio'    => $inicioAg,
                'data_fim'       => $fimAg,
                'status'         => AgendamentoModel::STATUS_RESERVA_RENOVACAO,
                'confirmado'     => 0,
            ]);

            // 1 aviso ao staff por cliente (enquanto estiver sem saldo).
            $temAviso = Aviso::where('tipo', 'plano_sem_saldo')
                ->where('user_id', $a->user_id)
                ->whereNull('dispensado_at')
                ->exists();
            if (!$temAviso) {
                Aviso::create([
                    'tipo'       => 'plano_sem_saldo',
                    'user_id'    => $a->user_id,
                    'servico_id' => $a->servico_base_id,
                    'data_antiga'=> $inicioAg,
                ]);

                // 5ª semana com valores_extra definidos: avisa o CLIENTE no WhatsApp
                // que o pacote acabou e oferece pagar a visita extra (self-service).
                $ciclo = $a->cicloAtual();
                if ($ciclo && !empty($ciclo->valores_extra) && $a->user && $a->user->whatsapp) {
                    $this->avisarVisitaExtra($a->user, $reserva);
                }
            }
        }
    }

    /**
     * Avisa o cliente que seu pacote mensal acabou (5ª semana) e oferece pagar a
     * visita extra pelo site (self-service). Best-effort — não derruba o fluxo.
     */
    private function avisarVisitaExtra(User $cliente, AgendamentoModel $reserva): void
    {
        $phoneId = env('PHONE_NUMBER_ID');
        if (!$phoneId) {
            return;
        }

        $nome = ucfirst($cliente->name ?? 'você');
        $data = Carbon::parse($reserva->data_inicio)->locale('pt_BR')->translatedFormat('d/m \à\s H:i');

        // O autoatendimento de visita-extra foi desativado (pacote mensal é só presencial /
        // WhatsApp humano). Avisamos o cliente para combinar a visita extra com a barbearia.
        $msg = "🔔 {$nome}, seu pacote mensal já acabou neste período.\n"
             . "Seu horário de {$data} segue reservado. Para manter a visita extra, é só falar com a barbearia (presencial ou no nosso WhatsApp) — ela combina com você o serviço (só corte ou corte + barba) e o valor. 😊";

        try {
            WhatsappController::enviarMsg($phoneId, $cliente->whatsapp, $msg);
        } catch (\Throwable $e) {
            \Log::channel('single')->warning('[VISITA-EXTRA] falha ao avisar cliente', ['msg' => $e->getMessage()]);
        }
    }

    private function notificarStaffCancelamento(AgendamentoModel $agendamento): void
    {
        $phoneId = env('PHONE_NUMBER_ID');
        if (!$phoneId) return;

        $staffUsers = User::where(function ($q) {
                $q->where('adm', 1)->orWhere('func', 1);
            })
            ->whereNotNull('whatsapp')
            ->where('excluido', 0)
            ->get();

        $nomeCliente = ucfirst($agendamento->user->name ?? '');
        $data    = Carbon::parse($agendamento->data_inicio)->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y');
        $hora    = Carbon::parse($agendamento->data_inicio)->format('H:i');
        $servico = $agendamento->servico->descricao ?? '';

        // "aviso_cancelamento" nunca existiu neste WABA (herança da clínica) — o
        // envio falhava em silêncio a cada cancelamento. Reaproveita o template
        // genérico de aviso ({{1}} = nome comercial, {{2}} = resumo), o mesmo do
        // AvisoObserver.
        $nomeComercial = env('WHATSAPP_NOME_COMERCIAL', config('app.name'));
        $resumo = "{$nomeCliente} cancelou o agendamento de {$data} às {$hora}"
            . ($servico !== '' ? " ({$servico})" : '') . '.';

        foreach ($staffUsers as $staff) {
            WhatsappController::enviarModelo($phoneId, $staff->whatsapp, env('WHATSAPP_TEMPLATE_AVISO', 'novo_aviso_sistema_fr'), [
                ['type' => 'text', 'text' => $nomeComercial],
                ['type' => 'text', 'text' => $resumo],
            ]);
        }
    }

    public function search(Request $request)
    {
        $user = auth()->user();
        $q    = trim($request->q);

        if (!$user->adm && !$user->func) {
            abort(403);
        }

        $consultas = AgendamentoModel::with([
                'user',
                'servico',
                'lembretes',
                'creditoServico' => fn($q) => $q->with(['servico', 'agendamentos:id,credito_servico_id'])->withCount('agendamentos'),
            ])
            ->when($q !== '', fn($query) =>
                $query->whereHas('user', fn($u) => $u->where('name', 'like', "%{$q}%"))
            )
            ->orderBy('data_inicio')
            ->get()
            ->groupBy(fn($item) =>
                Carbon::parse($item->data_inicio)->locale('pt_BR')->translatedFormat('F Y')
            );

        return response()->json($consultas);
    }
}
