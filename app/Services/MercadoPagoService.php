<?php

namespace App\Services;

use App\Models\OrdemPagamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Encapsula a REST API do Mercado Pago (Checkout Transparente). Mantém o
// ACCESS_TOKEN no backend (nunca exposto) e devolve resultados normalizados para
// os controllers. Segue o padrão de retorno do projeto: ['erro'=>1,'msg'=>...]
// em falha, ou array com os dados no sucesso.
class MercadoPagoService
{
    private const API = 'https://api.mercadopago.com';

    private function accessToken(): string
    {
        return (string) config('services.mercadopago.access_token');
    }

    /**
     * Cria um pagamento com cartão (tokenizado pelo Brick no front).
     * Valor e parcelas vêm sempre da ordem no banco — o front manda só o token
     * e os dados do portador.
     *
     * @param  array{token:string,payment_method_id:string,issuer_id:?string,installments:int,payer_email:?string,payer_doc_type:?string,payer_doc_number:?string}  $dadosBrick
     * @return array{erro?:int,msg?:string,id?:string,status?:string,status_detail?:?string}
     */
    public function criarPayment(OrdemPagamento $ordem, array $dadosBrick): array
    {
        if (!$token = $this->accessToken()) {
            return ['erro' => 1, 'msg' => 'MP_ACCESS_TOKEN não configurado'];
        }

        $installments = max(1, min((int) ($dadosBrick['installments'] ?? 1), (int) $ordem->max_parcelas));

        $payload = [
            'transaction_amount' => (float) $ordem->valor,
            'token'              => $dadosBrick['token'],
            'description'        => $ordem->descricao,
            'installments'       => $installments,
            'payment_method_id'  => $dadosBrick['payment_method_id'],
            'payer'              => [
                'email' => $dadosBrick['payer_email'] ?? ('paciente.' . $ordem->user_id . '@exemplo.com'),
            ],
            'external_reference' => (string) $ordem->external_reference,
            'statement_descriptor' => (string) config('services.mercadopago.statement_descriptor', config('app.name')),
            'notification_url'   => route('mercadopago.webhook'),
        ];

        if (!empty($dadosBrick['issuer_id'])) {
            $payload['issuer_id'] = (int) $dadosBrick['issuer_id'];
        }

        $doc = preg_replace('/\D/', '', (string) ($dadosBrick['payer_doc_number'] ?? ''));
        if ($doc !== '') {
            $payload['payer']['identification'] = [
                'type'   => $dadosBrick['payer_doc_type'] ?? 'CPF',
                'number' => $doc,
            ];
        }

        try {
            $resp = Http::withToken($token)
                ->withHeaders(['X-Idempotency-Key' => (string) \Illuminate\Support\Str::uuid()])
                ->timeout(20)
                ->post(self::API . '/v1/payments', $payload);
        } catch (\Throwable $e) {
            Log::error('[MP] criarPayment exceção', ['ordem' => $ordem->id, 'msg' => $e->getMessage()]);
            return ['erro' => 1, 'msg' => 'Falha ao conectar ao Mercado Pago.'];
        }

        if ($resp->status() >= 400) {
            $body = $resp->json();
            Log::warning('[MP] criarPayment erro', ['ordem' => $ordem->id, 'status' => $resp->status(), 'body' => $body]);
            $msg = $body['message'] ?? ($body['cause'][0]['description'] ?? 'Pagamento recusado pelo Mercado Pago.');
            return ['erro' => 1, 'msg' => $msg];
        }

        $data = $resp->json();
        return [
            'id'            => (string) ($data['id'] ?? ''),
            'status'        => (string) ($data['status'] ?? 'pending'),
            'status_detail' => (string) ($data['status_detail'] ?? ''),
        ];
    }

    /**
     * Consulta um pagamento (usado pelo webhook para confirmar o status).
     * @return array{erro?:int,msg?:string,id?:string,status?:string,status_detail?:?string,external_reference?:?string,transaction_amount?:?float}
     */
    public function getPayment(string $paymentId): array
    {
        if (!$token = $this->accessToken()) {
            return ['erro' => 1, 'msg' => 'MP_ACCESS_TOKEN não configurado'];
        }

        try {
            $resp = Http::withToken($token)->timeout(20)->get(self::API . "/v1/payments/{$paymentId}");
        } catch (\Throwable $e) {
            Log::error('[MP] getPayment exceção', ['payment' => $paymentId, 'msg' => $e->getMessage()]);
            return ['erro' => 1, 'msg' => 'Falha ao consultar o pagamento.'];
        }

        if ($resp->status() >= 400) {
            return ['erro' => 1, 'msg' => 'Pagamento não encontrado no Mercado Pago.'];
        }

        $data = $resp->json();
        return [
            'id'                  => (string) ($data['id'] ?? $paymentId),
            'status'              => (string) ($data['status'] ?? ''),
            'status_detail'       => (string) ($data['status_detail'] ?? ''),
            'external_reference'  => (string) ($data['external_reference'] ?? ''),
            'transaction_amount'  => isset($data['transaction_amount']) ? (float) $data['transaction_amount'] : null,
            'payment_method_id'   => (string) ($data['payment_method_id'] ?? ''),
            'installments'        => (int) ($data['installments'] ?? 0),
            // Detalhamento de custos do MP (para calcular o líquido recebido).
            'fee_details'         => $data['fee_details'] ?? [],
            'taxes'               => $data['taxes'] ?? [],
            'net_received_amount' => isset($data['net_received_amount']) ? (float) $data['net_received_amount'] : null,
        ];
    }

    // Valida a assinatura HMAC do webhook (header x-signature + x-request-id),
    // com a MP_WEBHOOK_SECRET. Fail-open (retorna true) quando a chave não está
    // configurada — mesmo princípio do assinaturaValida() do WhatsApp.
    public function assinaturaValida(Request $request): bool
    {
        $secret = config('services.mercadopago.webhook_secret');
        if (!$secret) {
            return true;
        }

        $signature = (string) $request->header('x-signature', '');
        $requestId = (string) $request->header('x-request-id', '');

        // x-signature vem como "ts=1730000000,v1=abc..."
        $parts = [];
        foreach (explode(',', $signature) as $par) {
            [$k, $v] = array_pad(explode('=', $par, 2), 2, '');
            $parts[trim($k)] = trim($v);
        }
        $ts = $parts['ts'] ?? '';
        $v1 = $parts['v1'] ?? '';
        if ($ts === '' || $v1 === '') {
            return false;
        }

        // data.id pode chegar no body ou na query (?data.id=...).
        $dataId = (string) ($request->input('data.id') ?? ($request->json('data.id') ?? $request->query('data.id')));

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $v1);
    }
}
