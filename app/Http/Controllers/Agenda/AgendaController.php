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
use App\Services\InfinitePayService;
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
        if (!$user || !$user->adm) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $data = $request->validate([
            'inicio' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'fim'    => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);

        foreach (['inicio' => 'comercial_inicio', 'fim' => 'comercial_fim'] as $campo => $key) {
            \App\Models\PageContent::updateOrCreate(
                ['section' => 'agenda', 'key' => $key],
                ['value' => $data[$campo], 'type' => 'text', 'label' => "Agenda — horário comercial ($campo)"]
            );
        }

        return response()->json(['ok' => true, 'inicio' => $data['inicio'], 'fim' => $data['fim']]);
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

        $horaIni = \App\Models\PageContent::get('agenda', 'comercial_inicio', '08:00');
        $horaFim = \App\Models\PageContent::get('agenda', 'comercial_fim', '17:45');
        [$hI, $mI] = array_pad(explode(':', $horaIni), 2, '0');
        [$hF, $mF] = array_pad(explode(':', $horaFim), 2, '0');
        $cursor = Carbon::createFromTime((int) $hI, (int) $mI, 0);
        $fim    = Carbon::createFromTime((int) $hF, (int) $mF, 0);
        $slots  = [];
        while ($cursor <= $fim) {
            $slots[] = $cursor->format('H:i:s');
            $cursor->addMinutes(15);
        }

        // Barbeiros-alvo: func → só si; adm → 'all' (todos os barbeiros) ou um específico.
        if ($user->func) {
            $alvo = [$user->id];
        } else {
            $fidParam = $request->input('funcionario_id', 'all');
            $alvo = ($fidParam === 'all' || !$fidParam)
                ? User::barbeiros()->pluck('id')->all()
                : [(int) $fidParam];
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
                foreach ($slots as $hora) {
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

        return response()->json([
            'ok'          => true,
            'preenchidos' => $preenchidos,
            'ignorados'   => $ignorados,
            'inicio'      => $horaIni,
            'fim'         => $horaFim,
        ]);
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
            // confirmacao_reagendamento: nome, nome_comercial, data, hora
            WhatsappController::enviarModelo($phoneId, $user->whatsapp, 'confirmacao_reagendamento', [
                ['type' => 'text', 'text' => $nome],
                ['type' => 'text', 'text' => env('WHATSAPP_NOME_COMERCIAL', config('app.name'))],
                ['type' => 'text', 'text' => $data],
                ['type' => 'text', 'text' => $hora],
            ]);
        } else {
            // confirmacao_agendamento: nome, data+hora, servico, numero de confirmacao
            WhatsappController::enviarModelo($phoneId, $user->whatsapp, 'confirmacao_agendamento', [
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

        $agendamento = AgendamentoModel::with(['user', 'servico'])->findOrFail($id);
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

        $resultado = WhatsappController::enviarModelo($phoneId, $cliente->whatsapp, $template, [
            ['type' => 'text', 'text' => $nome],
            ['type' => 'text', 'text' => $nomeComercial],
            ['type' => 'text', 'text' => $data],
            ['type' => 'text', 'text' => $hora],
        ], 'pt_BR', [
            'confirmar_' . $agendamento->id,
            'reagendar_' . $agendamento->id,
        ]);

        if (isset($resultado['erro'])) {
            return response()->json(['error' => $resultado['msg'] ?? 'Falha ao enviar lembrete.'], 422);
        }

        // Envio manual substitui a véspera: marca o lembrete '24h' como enviado para o
        // cron diário não reenviar. O de '2h' continua liberado e será disparado pelo cron.
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

        $agendamento = AgendamentoModel::with(['user', 'servico'])->findOrFail($id);
        if ($user->func && (int) $agendamento->funcionario_id !== (int) $user->id) {
            return response()->json(['error' => 'Não autorizado'], 403);
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

        // Plano mensal: desconta 1 unidade ao confirmar pela primeira vez.
        if (!$eraConfirmado && $agendamento->plano_mensal_id) {
            $this->descontarPlanoMensal($agendamento->plano_mensal_id);
        }

        $this->notificarWhatsApp($agendamento, false);

        return response()->json(['ok' => true, 'confirmado_em' => $agendamento->confirmado_em->format('d/m H:i')]);
    }

    /**
     * Recusa um agendamento do booking público (pago ou pendente): marca
     * STATUS_RECUSADO (libera o slot), tenta reembolso automático da ordem
     * vinculada (best-effort → fallback 'reembolso_pendente') e avisa o cliente.
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

        // Reembolso da ordem aprovada vinculada (best-effort → fallback manual).
        // "Pagar no local" não tem ordem de pagamento online — nada a reembolsar.
        $reembolso = ['ok' => false, 'motivo' => 'Sem ordem aprovada vinculada'];

        if ($agendamento->pagar_no_local) {
            $reembolso = ['ok' => true, 'motivo' => 'Sem pagamento online (pagar no local)'];
        } else {
            $ordem = OrdemPagamento::where('agendamento_id', $agendamento->id)
                ->whereIn('status', ['approved'])
                ->latest('id')
                ->first();

            if ($ordem) {
                $reembolso = app(InfinitePayService::class)->reembolsar($ordem);
                $ordem->status = $reembolso['ok'] ? 'refunded' : 'reembolso_pendente';
                $ordem->eventos()->create([
                    'status'  => $ordem->status,
                    'origem'  => 'manual',
                    'payload' => $reembolso,
                ]);
                $ordem->save();
            }
        }

        $this->notificarClienteRecusa($agendamento, $reembolso);

        return response()->json([
            'ok'        => true,
            'reembolso' => $reembolso['ok'] ? 'automatico' : 'manual',
            'motivo'    => $reembolso['motivo'] ?? null,
        ]);
    }

    private function notificarClienteRecusa(AgendamentoModel $agendamento, array $reembolso): void
    {
        $user     = $agendamento->user;
        $phoneId  = env('PHONE_NUMBER_ID');
        if (!$user || !$user->whatsapp || !$phoneId) {
            return;
        }

        $msg = "Olá, " . ucfirst($user->name) . "! Não conseguimos confirmar seu horário na data escolhida. ";
        $msg .= $reembolso['ok']
            ? "O reembolso foi solicitado e deve constar em alguns dias úteis."
            : "O valor será devolvido em até alguns dias úteis.";

        try {
            WhatsappController::enviarMsg($phoneId, $user->whatsapp, $msg);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('single')->warning('[BOOKING] falha ao avisar cliente da recusa', ['msg' => $e->getMessage()]);
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

        foreach ($plano->datasOcorrencias() as $data) {
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

            // O barbeiro abriu este slot?
            $aberto = DisponibilidadeModel::where('funcionario_id', $plano->funcionario_id)
                ->where('data', $data->toDateString())
                ->where('hora', $horaStr)
                ->exists();
            if (!$aberto) {
                continue;
            }

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
                'data_inicio'     => $inicioAg,
                'data_fim'        => $fimAg,
                'status'          => AgendamentoModel::STATUS_PAGO_AGUARDANDO,
                'confirmado'      => 0,
            ]);
            $criados++;
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
     * Desconta 1 unidade do plano ao confirmar um atendimento do plano. Marca consumido
     * quando todas as unidades forem usadas.
     */
    private function descontarPlanoMensal($planoId): void
    {
        $plano = PlanoMensal::find($planoId);
        if (!$plano || $plano->status !== PlanoMensal::STATUS_ATIVO) {
            return;
        }
        $plano->increment('unidades_usadas');
        if ((int) $plano->fresh()->unidades_usadas >= (int) $plano->unidades_total) {
            $plano->status = PlanoMensal::STATUS_CONSUMIDO;
            $plano->save();
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

        $nomePaciente = ucfirst($agendamento->user->name ?? '');
        $data    = Carbon::parse($agendamento->data_inicio)->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y');
        $hora    = Carbon::parse($agendamento->data_inicio)->format('H:i');
        $servico = $agendamento->servico->descricao ?? '';

        foreach ($staffUsers as $staff) {
            WhatsappController::enviarModelo($phoneId, $staff->whatsapp, 'aviso_cancelamento', [
                ['type' => 'text', 'text' => $nomePaciente],
                ['type' => 'text', 'text' => $data . ' às ' . $hora],
                ['type' => 'text', 'text' => $servico],
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
