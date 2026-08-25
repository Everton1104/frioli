<?php

namespace App\Http\Controllers;

use App\Models\OrdemPagamento;
use App\Models\PlanoMensal;
use App\Services\InfinitePayService;
use App\Services\PlanoMensalService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Ordens de pagamento: o staff (adm/func) cria/cancela; o paciente paga via
// InfinitePay (Link de Pagamento / redirect). Valor e parcelas são definidos na
// ordem (banco) e no link gerado — nunca no front.
class OrdemPagamentoController extends Controller
{
    public function __construct(private readonly InfinitePayService $ip) {}

    // ── Staff: criar ordem ───────────────────────────────────────────────────
    public function store(Request $request)
    {
        abort_unless(auth()->user()->adm, 403);

        $dados = $request->validate([
            'user_id'   => ['required', 'integer', 'exists:users,id'],
            'valor'     => ['required', 'numeric', 'min:0.01'],
            'descricao' => ['required', 'string', 'max:255'],
        ], [
            'user_id.exists'     => 'Cliente inválido.',
            'valor.min'          => 'O valor deve ser maior que zero.',
            'descricao.required' => 'Informe uma descrição.',
        ]);

        // Paciente deve ser um cliente (não adm/func) e não excluído.
        $paciente = \App\Models\User::where('id', $dados['user_id'])
            ->where('excluido', 0)
            ->where(fn($q) => $q->where('adm', 0)->orWhere('func', 0))
            ->first();
        if (!$paciente) {
            return redirect()->back()->withErrors(['user_id' => 'Cliente inválido.'])->withInput();
        }

        $ordem = DB::transaction(function () use ($dados) {
            $ordem = OrdemPagamento::create([
                'user_id'           => $dados['user_id'],
                'criado_por'        => auth()->id(),
                'valor'             => $dados['valor'],
                'descricao'         => $dados['descricao'],
                'max_parcelas'      => OrdemPagamento::MAX_PARCELAS,
                'status'            => 'aberta',
                // Gerado antes do insert: a coluna é NOT NULL (modo estrito do
                // MySQL) e não temos o id ainda. O webhook ancora por este campo.
                'external_reference' => (string) \Illuminate\Support\Str::uuid(),
            ]);

            $ordem->eventos()->create([
                'status' => 'aberta',
                'origem' => 'manual',
            ]);
            return $ordem;
        });

        $this->avisarPaciente($paciente, $ordem);

        return redirect()->back()->with('msg', 'Ordem de pagamento criada com sucesso!');
    }

    // ── Staff: criar plano mensal (combo multi-serviço, horário fixo) p/ cliente ──
    // A composição (itens inclusos + quantidades) é definida aqui pelo staff; o
    // cliente indicado só renova depois (AgendaPublicaController::mensalComprar
    // replica os mesmos itens no próximo ciclo). Criação centralizada em
    // PlanoMensalService::criarCiclo, que valida itens/slot e lança ValidationException.
    public function mensalStore(Request $request)
    {
        // Adm ou funcionário podem criar um plano mensal para um cliente.
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);

        $dados = $request->validate([
            'user_id'             => ['required', 'integer', 'exists:users,id'],
            'servico_base_id'     => ['required', 'integer'],
            'funcionario_id'      => ['required', 'integer'],
            'dia_semana'          => ['required', 'integer', 'between:0,6'],
            'hora'                => ['required', 'regex:/^([01]\d|2[0-3]):(00|15|30|45)$/'],
            'mes'                 => ['nullable', 'date'],
            'dia_renovacao'       => ['required', 'integer', 'between:1,31'],
            // Fase 5: o combo (servico_base_id) define composição/distribuição/extra no
            // cadastro (a 5ª visita repete a 1ª automaticamente). Aqui só o consumo no ato.
            'consumir_no_ato'     => ['nullable', 'boolean'],
            // Legado (combos sem composição definida no cadastro):
            'itens_servico'       => ['nullable', 'array'],
            'itens_servico.*'     => ['integer'],
            'itens_qtd'           => ['nullable', 'array'],
            'itens_qtd.*'         => ['nullable', 'integer', 'min:0'],
            'distribuicao'        => ['nullable', 'array'],
            'distribuicao.*'      => ['array'],
            'distribuicao.*.*'    => ['integer'],
            'valores_extra'       => ['nullable', 'array'],
            'valores_extra.*'     => ['nullable', 'numeric', 'min:0'],
        ], [
            'user_id.exists'           => 'Cliente inválido.',
            'servico_base_id.required' => 'Selecione o combo (serviço mensal).',
            'funcionario_id.required'  => 'Selecione o barbeiro.',
            'dia_semana.required'      => 'Selecione o dia da semana.',
            'hora.required'            => 'Selecione o horário.',
            'mes.required'             => 'Selecione o mês.',
            'itens_servico.required'   => 'Inclua ao menos 1 serviço no combo.',
            'dia_renovacao.required'   => 'Escolha o melhor dia para pagar a renovação.',
        ]);

        // Cliente precisa ser cliente (não adm/func) e não excluído.
        $cliente = \App\Models\User::where('id', $dados['user_id'])
            ->where('excluido', 0)
            ->where(fn($q) => $q->where('adm', 0)->orWhere('func', 0))
            ->first();
        if (!$cliente) {
            return redirect()->back()->withErrors(['user_id' => 'Cliente inválido.'])->withInput();
        }

        // Monta os itens do combo a partir dos arrays paralelos (legado — combos sem
        // composição no cadastro). O form "Vincular plano" não envia itens/distribuicao.
        $itens = collect();
        foreach (($dados['itens_servico'] ?? []) as $i => $sid) {
            $qtd = (int) ($dados['itens_qtd'][$i] ?? 0);
            if ($sid && $qtd > 0) {
                $itens->push(['servico_id' => (int) $sid, 'quantidade' => $qtd]);
            }
        }

        $ordem = PlanoMensalService::criarCiclo([
            'user_id'         => $cliente->id,
            'funcionario_id'  => (int) $dados['funcionario_id'],
            'dia_semana'      => (int) $dados['dia_semana'],
            'hora'            => $dados['hora'],
            'servico_base_id' => (int) $dados['servico_base_id'],
            'itens'           => $itens,
            'dia_renovacao'   => (int) $dados['dia_renovacao'],
            'distribuicao'    => !empty($dados['distribuicao']) ? $dados['distribuicao'] : null,
            'valores_extra'   => !empty($dados['valores_extra']) ? $dados['valores_extra'] : null,
            'consumir_no_ato' => $request->boolean('consumir_no_ato'),
            'recorrente'      => true, // "Vincular plano" — sempre recorrente (renova sozinho).
            'criado_por'      => auth()->id(),
            'origem'          => 'manual',
        ]);

        $this->avisarPaciente($cliente, $ordem);

        // Avisa o staff quando for uma NOVA assinatura (slot fixo recém-reservado).
        $plano = PlanoMensal::with('assinatura')->find($ordem->plano_mensal_id);
        if ($plano && $plano->assinatura && $plano->assinatura->planos()->count() === 1) {
            WhatsappController::avisarStaffReservaSlot($plano);
        }

        return redirect()->back()->with('msg', 'Plano mensal criado. O cliente recebeu o link de pagamento no WhatsApp.');
    }

    // ── Staff: cancelar ordem (só se ainda não aprovada) ────────────────────
    public function cancelar(Request $request, $id)
    {
        // Adm ou funcionário podem cancelar uma ordem não aprovada.
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);

        $ordem = OrdemPagamento::findOrFail($id);
        if ($ordem->status === 'approved') {
            return redirect()->back()->with('msgErro', 'Não é possível cancelar uma ordem já aprovada.');
        }
        if ($ordem->status === 'cancelled') {
            return redirect()->back()->with('msg', 'A ordem já estava cancelada.');
        }

        $ordem->status = 'cancelled';
        $ordem->save();
        $ordem->eventos()->create(['status' => 'cancelled', 'origem' => 'manual']);

        return redirect()->back()->with('msg', 'Ordem cancelada.');
    }

    // ── Staff: excluir (apagar definitivamente) uma ordem criada por engano.
    // Bloqueada para ordens já aprovadas — não apagamos histórico de pagamento.
    public function destroy($id)
    {
        abort_unless(auth()->user()->adm, 403);

        $ordem = OrdemPagamento::findOrFail($id);
        if ($ordem->status === 'approved') {
            return redirect()->back()->with('msgErro', 'Não é possível excluir uma ordem já aprovada.');
        }

        $ordem->delete(); // hard delete — os eventos saem em cascade (FK)

        return redirect()->back()->with('msg', 'Ordem excluída.');
    }

    // ── Paciente: tela de checkout (resumo + botão para o link InfinitePay) ──
    public function pagar(OrdemPagamento $ordem)
    {
        abort_unless($ordem->user_id === auth()->id(), 403, 'Ordem não encontrada.');
        abort_if($ordem->status === 'approved', 403, 'Esta ordem já foi paga.');
        abort_if($ordem->status === 'cancelled', 403, 'Esta ordem foi cancelada.');

        // GET puro: só renderiza o resumo. O link de pagamento é criado no POST
        // /link (abaixo) — side-effect fora do GET, com throttle e lock.
        return view('pagamentos.pagar', ['ordem' => $ordem]);
    }

    // ── Paciente: criar o link de pagamento InfinitePay (chamado pelo botão) ─
    public function link(Request $request, OrdemPagamento $ordem)
    {
        abort_unless($ordem->user_id === auth()->id(), 403);

        if ($ordem->status === 'approved') {
            return response()->json(['erro' => 1, 'msg' => 'Esta ordem já foi paga.'], 422);
        }
        if ($ordem->status === 'cancelled') {
            return response()->json(['erro' => 1, 'msg' => 'Esta ordem foi cancelada.'], 422);
        }

        // Uma ordem = um link. Reusamos o link existente (lock evita race de duas
        // abas criando links distintos). O link da InfinitePay só é pagável UMA
        // vez — então o paciente que clicar de novo cai no MESMO link (já pago/
        // utilizado), o que previne pagamento em duplicidade.
        $reuso = DB::transaction(function () use ($ordem) {
            $locked = OrdemPagamento::where('id', $ordem->id)->lockForUpdate()->first();
            if (!$locked) {
                return null;
            }

            // Já temos um link válido pra esta ordem → reusa.
            if ($locked->infinitepay_url) {
                return ['url' => $locked->infinitepay_url];
            }

            $res = $this->ip->criarLink($locked);
            if (isset($res['erro'])) {
                return ['erro' => $res];
            }
            $locked->infinitepay_url = $res['url'];
            if (!empty($res['slug'])) {
                $locked->infinitepay_slug = $res['slug'];
            }
            $locked->gateway = 'infinitepay';
            $locked->save();

            return ['url' => $res['url']];
        });

        if ($reuso === null) {
            return response()->json(['erro' => 1, 'msg' => 'Ordem não encontrada.'], 422);
        }
        if (isset($reuso['erro'])) {
            return response()->json($reuso['erro'], 422);
        }

        return response()->json(['url' => $reuso['url']]);
    }

    // ── Paciente: tela de retorno após pagar no checkout InfinitePay (signed) ─
    // Rota assinada (sem auth) — sobrevive à expiração de sessão durante o
    // checkout off-site. A integridade do status é garantida pelo webhook; esta
    // tela é só exibição + polling.
    public function retorno(OrdemPagamento $ordem)
    {
        // Validação pelo `ref` (external_reference, UUID secreto) — tolera os query
        // params extras que a InfinitePay adiciona ao redirecionar, e não depende de
        // sessão (que pode expirar durante o checkout off-site).
        if ((string) request()->query('ref', '') !== (string) $ordem->external_reference) {
            abort(403);
        }

        return view('pagamentos.retorno', [
            'ordem'     => $ordem,
            'statusUrl' => route('pagamentos.status', ['ordem' => $ordem->id]) . '?ref=' . urlencode((string) $ordem->external_reference),
        ]);
    }

    // ── Paciente: status do pagamento para o polling da tela de retorno.
    // Confirma de forma autoritativa via payment_check (backstop caso o webhook
    // ainda não tenha chegado) e devolve o status atual.
    public function status(OrdemPagamento $ordem)
    {
        if ((string) request()->query('ref', '') !== (string) $ordem->external_reference) {
            abort(403);
        }

        if ($ordem->status !== 'approved') {
            app(InfinitePayWebhookController::class)->sincronizarOrdem($ordem);
            $ordem->refresh();
        }

        return response()->json([
            'status' => $ordem->status,
            'paid'   => $ordem->status === 'approved',
        ]);
    }

    // Avisa o paciente (por WhatsApp, se tiver número) que uma ordem foi criada.
    // Usa TEMPLATE (não texto livre): fora da janela de 24h a Meta só entrega
    // mensagem iniciada pela empresa se for um template aprovado.
    private function avisarPaciente(\App\Models\User $paciente, OrdemPagamento $ordem): void
    {
        if (!$paciente->whatsapp) {
            return;
        }

        $valor    = 'R$ ' . number_format((float) $ordem->valor, 2, ',', '.');
        // O template diz "...{{parcelas}}x sem juros..." — enviamos o nº de parcelas
        // SEM juros (6), não o teto total (12). O paciente ainda pode parcelar até
        // 12x (vê no checkout; da 7ª à 12ª há juros do cliente).
        $parcelas = (string) min((int) $ordem->max_parcelas, OrdemPagamento::MAX_SEM_JUROS);
        $link     = rtrim((string) env('APP_URL', config('app.url')), '/') . '/pagamentos/' . $ordem->id . '/pagar';
        $nome     = ucfirst($paciente->name);

        try {
            $r = WhatsappController::enviarModelo(
                env('PHONE_NUMBER_ID'),
                $paciente->whatsapp,
                env('WHATSAPP_TEMPLATE_ORDEM_PAGAMENTO', 'ordem_pagamento_disponivel_fr'),
                [
                    ['type' => 'text', 'text' => $nome],
                    ['type' => 'text', 'text' => $ordem->descricao],
                    ['type' => 'text', 'text' => $valor],
                    ['type' => 'text', 'text' => $parcelas],
                    ['type' => 'text', 'text' => $link],
                ]
            );
            if (isset($r['erro'])) {
                \Illuminate\Support\Facades\Log::warning('[OrdemPagamento] template de ordem falhou', [
                    'ordem' => $ordem->id, 'resp' => $r,
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[OrdemPagamento] falha ao avisar paciente', [
                'ordem' => $ordem->id, 'msg' => $e->getMessage(),
            ]);
        }
    }

    // Confirmação por WhatsApp quando o pagamento é aprovado. Também usa template,
    // pois o paciente pode estar fora da janela de 24h.
    private function confirmarPagamentoPaciente(OrdemPagamento $ordem): void
    {
        $paciente = $ordem->user;
        if (!$paciente || !$paciente->whatsapp) {
            return;
        }

        $valor = 'R$ ' . number_format((float) $ordem->valor, 2, ',', '.');
        $nome  = ucfirst($paciente->name ?? 'você');

        try {
            $r = WhatsappController::enviarModelo(
                env('PHONE_NUMBER_ID'),
                $paciente->whatsapp,
                env('WHATSAPP_TEMPLATE_PAGAMENTO_APROVADO', 'pagamento_confirmado_fr'),
                [
                    ['type' => 'text', 'text' => $nome],
                    ['type' => 'text', 'text' => $valor],
                    ['type' => 'text', 'text' => $ordem->descricao],
                ]
            );
            if (isset($r['erro'])) {
                \Illuminate\Support\Facades\Log::warning('[OrdemPagamento] template de aprovação falhou', [
                    'ordem' => $ordem->id, 'resp' => $r,
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[OrdemPagamento] falha ao confirmar pagamento', [
                'ordem' => $ordem->id, 'msg' => $e->getMessage(),
            ]);
        }
    }

    // ── Edição de plano mensal (somente adm): quantidades + valor unitário com desconto ─
    public function editarPlano(PlanoMensal $plano)
    {
        abort_unless(auth()->user()->adm, 403);
        $plano->load('itens.servico', 'assinatura');

        return response()->json([
            'id'              => $plano->id,
            'descricao'       => $plano->descricaoItens(),
            'itens'           => $plano->itens->map(fn ($i) => [
                'servico_id'     => $i->servico_id,
                'descricao'      => $i->servico->descricao ?? '—',
                'quantidade'     => (int) $i->quantidade,
                'valor_unitario' => (float) $i->valor_unitario,
            ]),
            'dia_renovacao'     => $plano->assinatura?->dia_renovacao,
            'servicos'          => \App\Models\ServicosModel::where('excluido', 0)
                ->where('recorrente', 0)->orderBy('descricao')->get(['id', 'descricao', 'valor']),
        ]);
    }

    public function updatePlano(Request $request, PlanoMensal $plano)
    {
        abort_unless(auth()->user()->adm, 403);

        $dados = $request->validate([
            'itens'                 => ['required', 'array'],
            'itens.*.servico_id'    => ['required', 'integer'],
            'itens.*.quantidade'    => ['required', 'integer', 'min:0'],
            'itens.*.valor_unitario'=> ['required', 'numeric', 'min:0'],
            'dia_renovacao'         => ['required', 'integer', 'between:1,31'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($plano, $dados) {
            // Preserva o consumo já registrado por serviço (clampa à nova quantidade).
            $usadosAntigos = $plano->itens->keyBy('servico_id')->map(fn ($i) => (int) $i->usados);
            $plano->itens()->delete();

            $valor = 0.0;
            foreach ($dados['itens'] as $linha) {
                $sid  = (int) $linha['servico_id'];
                $qtd  = (int) $linha['quantidade'];
                $unit = (float) $linha['valor_unitario'];
                $valor += $qtd * $unit;
                if ($qtd > 0) {
                    $plano->itens()->create([
                        'servico_id'     => $sid,
                        'quantidade'     => $qtd,
                        'usados'         => min($usadosAntigos[$sid] ?? 0, $qtd),
                        'valor_unitario' => $unit,
                    ]);
                }
            }
            $plano->valor_total = round($valor, 2);
            $plano->save();

            if ($plano->assinatura) {
                $plano->assinatura->dia_renovacao = (int) $dados['dia_renovacao'];
                $plano->assinatura->save();
            }
        });

        return redirect()->back()->with('msg', 'Plano atualizado.');
    }

    // ── Ativação manual de plano mensal (pagamento presencial: dinheiro/cartão na hora) ──
    // O staff clica em "Ativar (pago no local)" no plano aguardando_pagamento. Replica o
    // fluxo do webhook (ativarPlanoMensalSePago): ativa o plano, marca a ordem como
    // aprovada e materializa as pré-reservas do ciclo.
    public function ativarManual(Request $request, PlanoMensal $plano)
    {
        abort_unless($request->user()->adm || $request->user()->func, 403);

        if ($plano->status !== PlanoMensal::STATUS_AGUARDANDO_PAGAMENTO) {
            return redirect()->back()->with('msg', 'Este plano não está aguardando pagamento.');
        }

        $plano->status = PlanoMensal::STATUS_ATIVO;
        $plano->save();

        if ($plano->ordemPagamento) {
            $plano->ordemPagamento->status = 'approved';
            $plano->ordemPagamento->save();
            $plano->ordemPagamento->eventos()->create([
                'status' => 'approved',
                'origem' => 'manual_presencial',
            ]);
        }

        app(\App\Http\Controllers\Agenda\AgendaController::class)->materializarPlano($plano);

        return redirect()->back()->with('msg', 'Plano ativado (pagamento presencial). Visitas pré-reservadas.');
    }
}
