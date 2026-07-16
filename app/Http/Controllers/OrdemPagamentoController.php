<?php

namespace App\Http\Controllers;

use App\Models\OrdemPagamento;
use App\Services\InfinitePayService;
use Illuminate\Http\Request;
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
            'user_id.exists'     => 'Paciente inválido.',
            'valor.min'          => 'O valor deve ser maior que zero.',
            'descricao.required' => 'Informe uma descrição.',
        ]);

        // Paciente deve ser um cliente (não adm/func) e não excluído.
        $paciente = \App\Models\User::where('id', $dados['user_id'])
            ->where('excluido', 0)
            ->where(fn($q) => $q->where('adm', 0)->orWhere('func', 0))
            ->first();
        if (!$paciente) {
            return redirect()->back()->withErrors(['user_id' => 'Paciente inválido.'])->withInput();
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

    // ── Staff: cancelar ordem (só se ainda não aprovada) ────────────────────
    public function cancelar(Request $request, $id)
    {
        abort_unless(auth()->user()->adm, 403);

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
                env('WHATSAPP_TEMPLATE_ORDEM_PAGAMENTO', 'ordem_pagamento_disponivel'),
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
                env('WHATSAPP_TEMPLATE_PAGAMENTO_APROVADO', 'pagamento_confirmado'),
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
}
