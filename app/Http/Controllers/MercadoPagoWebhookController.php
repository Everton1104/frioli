<?php

namespace App\Http\Controllers;

use App\Models\OrdemPagamento;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Recebe as notificações do Mercado Pago e sincroniza o status da ordem.
// Rota pública, fora do CSRF (ver bootstrap/app.php). Sempre responde 200 rápido
// para o MP não retentar indefinidamente — a confirmação pesada vem do GET.
class MercadoPagoWebhookController extends Controller
{
    public function __construct(private readonly MercadoPagoService $mp) {}

    public function handle(Request $request)
    {
        $sigOk = $this->mp->assinaturaValida($request);
        // Log de diagnóstico: registra toda notificação recebida (inclusive a de teste).
        Log::channel('single')->info('[MP-WEBHOOK] recebido', [
            'type'    => $request->input('type') ?? $request->query('type'),
            'action'  => $request->input('action'),
            'data_id' => $request->input('data.id') ?? $request->query('data.id'),
            'sig_ok'  => $sigOk,
        ]);

        if (!$sigOk) {
            return response()->json([], 200);
        }

        $type   = $request->input('type') ?? $request->query('type');
        $action = $request->input('action');
        $dataId = $request->input('data.id') ?? $request->query('data.id');

        // Aceita ambos os modelos de notificação do MP: "payment" (clássico) e
        // "order" (Orders API). O tipo assinado no painel define qual chega.
        try {
            if ($type === 'payment' && $dataId) {
                $this->sincronizar((string) $dataId);
            } elseif ($type === 'order') {
                $this->sincronizarOrder($request);
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('[MP-WEBHOOK] erro ao sincronizar', [
                'type' => $type, 'data_id' => $dataId, 'msg' => $e->getMessage(),
            ]);
        }

        return response()->json([], 200);
    }

    private function sincronizar(string $paymentId): void
    {
        $res = $this->mp->getPayment($paymentId);
        if (isset($res['erro'])) {
            return;
        }

        $extRef = $res['external_reference'] ?? '';
        $novoStatus = $res['status'] ?? '';
        if (!$extRef || !$novoStatus) {
            return;
        }

        DB::transaction(function () use ($extRef, $paymentId, $novoStatus, $res) {
            $ordem = OrdemPagamento::where('external_reference', $extRef)->lockForUpdate()->first();
            if (!$ordem) {
                Log::channel('single')->warning('[MP-WEBHOOK] ordem não encontrada', ['ext_ref' => $extRef]);
                return;
            }

            // Anti-golpe: o valor retornado pelo MP tem que bater com o da ordem.
            if (isset($res['transaction_amount']) && $res['transaction_amount'] !== null
                && abs((float) $res['transaction_amount'] - (float) $ordem->valor) > 0.01) {
                Log::channel('single')->error('[MP-WEBHOOK] divergência de valor', [
                    'ordem' => $ordem->id, 'ordem_valor' => $ordem->valor, 'mp' => $res['transaction_amount'],
                ]);
                return;
            }

            // Idempotência: já registramos este payment+status — não faz nada.
            $ja = $ordem->eventos()
                ->where('payment_id_mp', $paymentId)
                ->where('status', $novoStatus)
                ->exists();
            if ($ja) {
                return;
            }

            $anterior = $ordem->status;

            $ordem->payment_id_mp     = $paymentId;
            $ordem->status            = $novoStatus;
            $ordem->status_detail     = $res['status_detail'] ?? $ordem->status_detail;
            if (!empty($res['payment_method_id'])) {
                $ordem->payment_method_id = $res['payment_method_id'];
            }
            if (!empty($res['installments'])) {
                $ordem->installments = (int) $res['installments'];
            }
            if ($novoStatus === 'approved') {
                // Congela taxa/líquido reais do MP no momento do pagamento.
                $liq = $this->calcularLiquido($res, (float) $ordem->valor);
                if (!empty($liq)) {
                    $ordem->taxa_mp       = $liq['taxa_mp'];
                    $ordem->valor_liquido = $liq['valor_liquido'];
                }
                if (!$ordem->pago_em) {
                    $ordem->pago_em = now();
                }
            }
            $ordem->save();

            $ordem->eventos()->create([
                'payment_id_mp' => $paymentId,
                'status'        => $novoStatus,
                'origem'        => 'webhook',
                'payload'       => $res,
            ]);

            // Só avisa o paciente na transição para aprovado (evita duplicar o aviso
            // disparado no checkout ou reavisa a cada reenvio do webhook).
            if ($novoStatus === 'approved' && $anterior !== 'approved') {
                $this->confirmarPagamento($ordem);
            }
        });
    }

    // Notificação type=order (Orders API do MP): o payload já traz o
    // external_reference e os dados do payment — usamos direto, pois a assinatura
    // já foi validada. Não precisa de GET adicional.
    private function sincronizarOrder(Request $request): void
    {
        $data   = $request->input('data', []);
        $extRef = $data['external_reference'] ?? null;
        if (!$extRef) {
            return;
        }

        $payments    = $data['transactions']['payments'] ?? [];
        $pay         = $payments[0] ?? [];
        $paymentId   = $pay['id'] ?? ($data['id'] ?? null);
        $detail      = $pay['status_detail'] ?? ($data['status_detail'] ?? '');
        $method      = $pay['payment_method']['id'] ?? null;
        $installments = $pay['payment_method']['installments'] ?? null;
        $amount      = $data['total_paid_amount'] ?? null; // em centavos

        $novoStatus = $this->mapearStatusOrder($pay['status'] ?? '', $detail);
        if (!$novoStatus) {
            return;
        }

        DB::transaction(function () use ($extRef, $paymentId, $novoStatus, $detail, $method, $installments, $amount, $data) {
            $ordem = OrdemPagamento::where('external_reference', $extRef)->lockForUpdate()->first();
            if (!$ordem) {
                Log::channel('single')->warning('[MP-WEBHOOK] ordem não encontrada (order)', ['ext_ref' => $extRef]);
                return;
            }

            // Anti-golpe: total pago (centavos) tem que bater com o valor da ordem.
            if ($amount !== null && abs(((float) $amount / 100) - (float) $ordem->valor) > 0.01) {
                Log::channel('single')->error('[MP-WEBHOOK] divergência de valor (order)', [
                    'ordem' => $ordem->id, 'ordem_valor' => $ordem->valor, 'mp' => $amount,
                ]);
                return;
            }

            // Idempotência.
            $ja = $ordem->eventos()
                ->where('payment_id_mp', $paymentId)
                ->where('status', $novoStatus)
                ->exists();
            if ($ja) {
                return;
            }

            $anterior = $ordem->status;

            if ($paymentId) {
                $ordem->payment_id_mp = $paymentId;
            }
            $ordem->status = $novoStatus;
            if ($detail) {
                $ordem->status_detail = $detail;
            }
            if ($method) {
                $ordem->payment_method_id = $method;
            }
            if ($installments) {
                $ordem->installments = (int) $installments;
            }
            if ($novoStatus === 'approved') {
                // Congela taxa/líquido reais do MP (Orders API) no momento do pagamento.
                $payData = $data['transactions']['payments'][0] ?? [];
                $liq = $this->calcularLiquido([
                    'transaction_amount'  => $amount !== null ? ((float) $amount / 100) : null,
                    'fee_details'         => $payData['fee_details'] ?? [],
                    'taxes'               => $payData['taxes'] ?? [],
                    'net_received_amount' => $payData['net_received_amount'] ?? null,
                ], (float) $ordem->valor);
                if (!empty($liq)) {
                    $ordem->taxa_mp       = $liq['taxa_mp'];
                    $ordem->valor_liquido = $liq['valor_liquido'];
                }
                if (!$ordem->pago_em) {
                    $ordem->pago_em = now();
                }
            }
            $ordem->save();

            $ordem->eventos()->create([
                'payment_id_mp' => $paymentId,
                'status'        => $novoStatus,
                'origem'        => 'webhook',
                'payload'       => $data,
            ]);

            if ($novoStatus === 'approved' && $anterior !== 'approved') {
                $this->confirmarPagamento($ordem);
            }
        });
    }

    // Mapeia status/status_detail do Orders API (ex.: "processed"+"accredited")
    // para o status clássico do pagamento que usamos na ordem.
    private function mapearStatusOrder(string $status, string $detail): ?string
    {
        // status_detail é o mais confiável no Orders API.
        if (in_array($detail, ['accredited', 'approved'], true)) {
            return 'approved';
        }
        if (in_array($detail, ['pending', 'pending_waiting_payment', 'in_process'], true)) {
            return 'pending';
        }
        if (in_array($detail, ['cancelled'], true)) {
            return 'cancelled';
        }
        if (str_starts_with($detail, 'cc_rejected') || $detail === 'rejected') {
            return 'rejected';
        }
        // Fallback pelo status do order.
        return match ($status) {
            'processed', 'confirmed' => 'approved',
            'canceled'               => 'cancelled',
            default                  => null,
        };
    }

    // Calcula taxa total do MP e líquido recebido a partir da resposta do payment.
    // Prioriza fee_details + taxes (custo total da transação, independe do
    // calendário de liberação das parcelas); fallback para net_received_amount.
    // Retorna [] quando não há dados de taxa (a ordem fica sem líquido congelado).
    private function calcularLiquido(array $res, float $valorEsperado): array
    {
        $bruto = isset($res['transaction_amount']) ? (float) $res['transaction_amount'] : $valorEsperado;

        $custo = 0.0;
        foreach (($res['fee_details'] ?? []) as $f) {
            if (isset($f['amount'])) {
                $custo += (float) $f['amount'];
            }
        }
        foreach (($res['taxes'] ?? []) as $t) {
            if (isset($t['amount'])) {
                $custo += (float) $t['amount'];
            }
        }

        if ($custo > 0) {
            return ['taxa_mp' => round($custo, 2), 'valor_liquido' => round($bruto - $custo, 2)];
        }

        if (isset($res['net_received_amount']) && $res['net_received_amount'] !== null) {
            $liquido = (float) $res['net_received_amount'];
            return ['taxa_mp' => round($bruto - $liquido, 2), 'valor_liquido' => round($liquido, 2)];
        }

        return [];
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
            Log::channel('single')->warning('[MP-WEBHOOK] falha ao confirmar pagamento', ['msg' => $e->getMessage()]);
        }
    }
}
