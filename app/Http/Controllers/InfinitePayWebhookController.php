<?php

namespace App\Http\Controllers;

use App\Models\AgendamentoModel;
use App\Models\OrdemPagamento;
use App\Models\PlanoMensal;
use App\Http\Controllers\Agenda\AgendaController;
use App\Services\InfinitePayService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Recebe as notificações de pagamento da InfinitePay (Checkout Integrado / Link).
// Rota pública com token no path (infinitepay/webhook/{token}), fora do CSRF
// (ver bootstrap/app.php). A API de Link NÃO assina o webhook — por isso a
// aprovação SEMPRE é confirmada via InfinitePayService::consultarStatus()
// (payment_check), usando só dados da própria ordem. O body do webhook serve
// apenas de "gatilho".
//
// sincronizarOrdem() é público para ser reutilizado pelo command de reconciliação
// e pelo endpoint de status (polling na tela de retorno).
class InfinitePayWebhookController extends Controller
{
    public function __construct(private readonly InfinitePayService $ip) {}

    public function handle(Request $request, string $token)
    {
        $esperado = (string) config('services.infinitepay.webhook_token');
        // Fail-open (igual ao MP/WhatsApp) quando o token não está configurado:
        // a segurança real da aprovação está no payment_check (consultarStatus).
        // Se configurado, rejeita token divergente antes de qualquer lógica.
        if ($esperado !== '' && !hash_equals($esperado, $token)) {
            return response()->json([], 403);
        }

        $orderNsu = (string) ($request->input('order_nsu') ?? '');
        $transactionNsu = (string) ($request->input('transaction_nsu') ?? '');
        $invoiceSlug = (string) ($request->input('invoice_slug') ?? '');
        Log::channel('single')->info('[INFINITEPAY-WEBHOOK] recebido', [
            'order_nsu'       => $orderNsu,
            'transaction_nsu' => $transactionNsu,
            'invoice_slug'    => $invoiceSlug,
            'token_ok'        => $esperado !== '' ? hash_equals($esperado, $token) : 'fail-open',
        ]);

        // Responde 200 rápido; a confirmação pesada (payment_check + DB) vem abaixo.
        // Em qualquer erro ainda respondemos 200 para a InfinitePay não retentar.
        try {
            if ($orderNsu !== '') {
                $ordem = OrdemPagamento::where('external_reference', $orderNsu)->first();
                if ($ordem) {
                    $this->sincronizarOrdem($ordem, $transactionNsu, $invoiceSlug);
                } else {
                    Log::channel('single')->warning('[INFINITEPAY-WEBHOOK] ordem não encontrada', ['order_nsu' => $orderNsu]);
                }
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('[INFINITEPAY-WEBHOOK] erro ao sincronizar', [
                'order_nsu' => $orderNsu, 'msg' => $e->getMessage(),
            ]);
        }

        return response()->json([], 200);
    }

    /**
     * Confirma o status real do pagamento na InfinitePay e, se aprovado, atualiza
     * a ordem. Reutilizado pelo webhook, pelo command de reconciliação e pelo
     * endpoint de status (polling).
     */
    public function sincronizarOrdem(OrdemPagamento $ordem, string $transactionNsu = '', string $invoiceSlug = ''): void
    {
        // Persiste os identificadores vindos do webhook. O payment_check precisa
        // do transaction_nsu + invoice_slug para localizar a transação, e a CRIAÇÃO
        // do link não os devolve (só a URL). Salvando aqui, o reconciliador e o
        // endpoint de status também conseguem consultar depois.
        if ($transactionNsu !== '' || $invoiceSlug !== '') {
            $dirty = false;
            if ($transactionNsu !== '' && (string) $ordem->payment_id_mp !== $transactionNsu) {
                $ordem->payment_id_mp = $transactionNsu;
                $dirty = true;
            }
            if ($invoiceSlug !== '' && (string) $ordem->infinitepay_slug !== $invoiceSlug) {
                $ordem->infinitepay_slug = $invoiceSlug;
                $dirty = true;
            }
            if ($dirty) {
                $ordem->save();
            }
        }

        $res = $this->ip->consultarStatus($ordem);
        if (isset($res['erro']) || empty($res['paid'])) {
            return;
        }

        DB::transaction(function () use ($ordem, $transactionNsu, $res) {
            $ordem = OrdemPagamento::where('id', $ordem->id)->lockForUpdate()->first();
            if (!$ordem) {
                return;
            }

            // Guard: ordem cancelada foi paga — não aprova; exige reembolso manual.
            if ($ordem->status === 'cancelled') {
                Log::channel('single')->error('[INFINITEPAY] ordem cancelada foi paga — reembolsar manualmente', [
                    'ordem' => $ordem->id, 'transaction_nsu' => $transactionNsu,
                ]);
                return;
            }

            // Idempotência rápida: já aprovada.
            if ($ordem->status === 'approved') {
                return;
            }

            // Anti-golpe: o amount (valor pedido, em centavos) tem que bater com o
            // da ordem. NUNCA comparamos paid_amount (que inclui juros das 7–12x e
            // legitimamente é maior).
            if (isset($res['amount']) && $res['amount'] !== null
                && abs(((int) $res['amount'] / 100) - (float) $ordem->valor) > 0.01) {
                Log::channel('single')->error('[INFINITEPAY] divergência de valor', [
                    'ordem' => $ordem->id, 'ordem_valor' => $ordem->valor, 'amount' => $res['amount'],
                ]);
                return;
            }

            // Chave de idempotência (payment_id_mp): transaction_nsu do webhook,
            // fallback para o slug do link (quando chamado pelo command/status).
            $idKey = $transactionNsu !== '' ? $transactionNsu : ($ordem->infinitepay_slug ?? '');
            if ($idKey !== '') {
                $ja = $ordem->eventos()
                    ->where('payment_id_mp', $idKey)
                    ->where('status', 'approved')
                    ->exists();
                if ($ja) {
                    return;
                }
            }

            $anterior = $ordem->status;

            $ordem->status      = 'approved';
            $ordem->gateway     = 'infinitepay';
            if ($idKey !== '') {
                $ordem->payment_id_mp = $idKey;
            }
            if (!empty($res['capture_method'])) {
                $ordem->payment_method_id = (string) $res['capture_method']; // credit_card | pix
            }
            if (!empty($res['installments'])) {
                $ordem->installments = (int) $res['installments'];
            }
            // NÃO gravamos valor_liquido/taxa_mp: a API de Link não devolve o
            // líquido real do lojista — mantemos o "valor recebido" como estimativa
            // (~) até haver endpoint de conciliação.
            if (!$ordem->pago_em) {
                $ordem->pago_em = now();
            }
            $ordem->save();

            $ordem->eventos()->create([
                'payment_id_mp' => $idKey !== '' ? $idKey : null,
                'status'        => 'approved',
                'origem'        => 'webhook',
                'payload'       => $res,
            ]);

            // Só avisa o paciente na transição para aprovado.
            if ($anterior !== 'approved') {
                $this->confirmarPagamento($ordem);
                $this->ativarAgendamentoSePago($ordem);
                $this->ativarPlanoMensalSePago($ordem);
            }
        });
    }

    /**
     * Booking público: ao aprovar o pagamento de uma ordem vinculada a um
     * agendamento, avança o status para pago_aguardando (pré-confirmação) e avisa
     * o ADMIN no WhatsApp para revisar/confirmar. Idempotente.
     */
    private function ativarAgendamentoSePago(OrdemPagamento $ordem): void
    {
        if (!$ordem->agendamento_id) {
            return;
        }

        $ag = $ordem->agendamento()->with('user', 'servico')->first();
        if (!$ag || $ag->status !== AgendamentoModel::STATUS_AGUARDANDO_PAGAMENTO) {
            return;
        }

        $ag->status = AgendamentoModel::STATUS_PAGO_AGUARDANDO;
        if (!$ag->pre_confirmado_em) {
            $ag->pre_confirmado_em = now();
        }
        $ag->save();

        $this->notificarAdminNovoPedido($ag);
    }

    /**
     * Plano mensal: ao aprovar a ordem vinculada a um plano, ativa o plano e materializa
     * as pré-reservas das ocorrências cujo slot do barbeiro já esteja aberto no mês-alvo.
     */
    private function ativarPlanoMensalSePago(OrdemPagamento $ordem): void
    {
        if (!$ordem->plano_mensal_id) {
            return;
        }
        $plano = PlanoMensal::with('servico')->find($ordem->plano_mensal_id);
        if (!$plano || $plano->status !== PlanoMensal::STATUS_AGUARDANDO_PAGAMENTO) {
            return;
        }
        $plano->status = PlanoMensal::STATUS_ATIVO;
        $plano->save();

        app(AgendaController::class)->materializarPlano($plano);
    }

    private function notificarAdminNovoPedido(AgendamentoModel $ag): void
    {
        $admin = env('WHATSAPP_ADMIN_NUMBER') ?: env('WHATSAPP_ATENDIMENTO_NUMBER');
        $phoneId = env('PHONE_NUMBER_ID');
        if (!$admin || !$phoneId) {
            return;
        }

        $nome    = ucfirst($ag->user->name ?? 'cliente');
        $servico = $ag->servico->descricao ?? 'serviço';
        $quando  = Carbon::parse($ag->data_inicio)->locale('pt_BR')->translatedFormat('d/m H:i');
        $valor   = 'R$ ' . number_format((float) ($ag->servico->valor ?? 0), 2, ',', '.');

        $msg = "🆕 Novo agendamento pago aguardando confirmação!\n\n"
             . "👤 {$nome}\n"
             . "✂️ {$servico} — {$valor}\n"
             . "📅 {$quando}\n\n"
             . "Confirme no painel (dashboard).";

        try {
            WhatsappController::enviarMsg($phoneId, $admin, $msg);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('[BOOKING] falha ao avisar admin', ['msg' => $e->getMessage()]);
        }
    }

    private function confirmarPagamento(OrdemPagamento $ordem): void
    {
        $paciente = $ordem->user;
        if (!$paciente || !$paciente->whatsapp) {
            return;
        }

        $valor = 'R$ ' . number_format((float) $ordem->valor, 2, ',', '.');
        $nome  = ucfirst($paciente->name ?? 'você');

        try {
            WhatsappController::enviarModelo(
                env('PHONE_NUMBER_ID'),
                $paciente->whatsapp,
                env('WHATSAPP_TEMPLATE_PAGAMENTO_APROVADO', 'pagamento_confirmado'),
                [
                    ['type' => 'text', 'text' => $nome],
                    ['type' => 'text', 'text' => $valor],
                    ['type' => 'text', 'text' => $ordem->descricao],
                ]
            );
        } catch (\Throwable $e) {
            Log::channel('single')->warning('[INFINITEPAY] falha ao confirmar pagamento', ['msg' => $e->getMessage()]);
        }
    }
}
