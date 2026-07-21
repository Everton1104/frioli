<?php

namespace App\Services;

use App\Models\OrdemPagamento;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Encapsula a API pública de Checkout Integrado (Link de Pagamento) da InfinitePay
// (api.checkout.infinitepay.io). Não há credenciais: o lojista é identificado pelo
// HANDLE (InfiniteTag). O webhook da InfinitePay NÃO é assignado, então a confirmação
// de pagamento SEMPRE passa por consultarStatus() (payment_check) no controller —
// nunca confiamos no body do webhook para aprovar uma ordem.
//
// Segue o padrão de retorno do projeto: ['erro'=>1,'msg'=>...] em falha, ou array
// com os dados no sucesso (igual ao MercadoPagoService).
class InfinitePayService
{
    private const API = 'https://api.checkout.infinitepay.io';

    private function handle(): ?string
    {
        $h = config('services.infinitepay.handle');
        return $h ? trim(ltrim((string) $h, '$')) : null;
    }

    /**
     * Cria um link de pagamento (redirect) para a ordem.
     *
     * @return array{erro?:int,msg?:string,url?:string,slug?:string}
     */
    public function criarLink(OrdemPagamento $ordem): array
    {
        if (!$handle = $this->handle()) {
            return ['erro' => 1, 'msg' => 'INFINITEPAY_HANDLE não configurado'];
        }

        $payload = [
            'handle'   => $handle,
            'items'    => [
                [
                    'quantity'    => 1,
                    'price'       => (int) round((float) $ordem->valor * 100), // centavos
                    'description' => mb_substr((string) $ordem->descricao, 0, 100) ?: 'Agendamento',
                ],
            ],
            'order_nsu'    => (string) $ordem->external_reference, // ancora para casar webhook + payment_check
            // redirect_url valida pelo `ref` (= external_reference, UUID secreto) no
            // controller, em vez de rota assinada: a InfinitePay ADICIONA query params
            // ao redirecionar de volta, o que invalidaria uma URL assinada.
            'redirect_url' => route('pagamentos.retorno', ['ordem' => $ordem->id]) . '?ref=' . urlencode((string) $ordem->external_reference),
            'webhook_url'  => route('infinitepay.webhook', ['token' => (string) config('services.infinitepay.webhook_token')]),
        ];

        // Pré-preenche os dados do comprador quando disponíveis (facilita o checkout).
        $paciente = $ordem->user;
        if ($paciente && $paciente->name) {
            $customer = ['name' => $paciente->name];
            if ($paciente->email) {
                $customer['email'] = $paciente->email;
            }
            $phone = preg_replace('/\D/', '', (string) ($paciente->whatsapp ?? ''));
            if ($phone !== '') {
                $customer['phone_number'] = '+' . $phone;
            }
            $payload['customer'] = $customer;
        }

        try {
            $resp = Http::asJson()->timeout(20)->post(self::API . '/links', $payload);
        } catch (\Throwable $e) {
            Log::error('[InfinitePay] criarLink exceção', ['ordem' => $ordem->id, 'msg' => $e->getMessage()]);
            return ['erro' => 1, 'msg' => 'Falha ao conectar à InfinitePay.'];
        }

        if ($resp->status() >= 400) {
            Log::warning('[InfinitePay] criarLink erro', [
                'ordem' => $ordem->id, 'status' => $resp->status(), 'body' => $resp->json(),
            ]);
            return ['erro' => 1, 'msg' => 'Não foi possível gerar o link de pagamento.'];
        }

        $data = $resp->json() ?? [];
        // A resposta pode trazer a URL em chaves distintas conforme a versão da API.
        $url = $data['url'] ?? $data['link'] ?? $data['checkout_url'] ?? null;
        $slug = $data['slug'] ?? $data['invoice_slug'] ?? null;
        if (!$url) {
            Log::warning('[InfinitePay] criarLink sem url na resposta', ['ordem' => $ordem->id, 'body' => $data]);
            return ['erro' => 1, 'msg' => 'Resposta inválida da InfinitePay.'];
        }

        return ['url' => (string) $url, 'slug' => (string) ($slug ?? '')];
    }

    /**
     * Consulta o status real do pagamento na InfinitePay (payment_check).
     *
     * IMPORTANTE: a criação do link (/links) devolve SOMENTE {url}; o
     * `invoice_slug` e o `transaction_nsu` NÃO vêm lá — só chegam no webhook.
     * O payment_check precisa deles para localizar a transação; sem isso retorna
     * {success:false}. Por isso o webhook persiste esses identificadores na ordem
     * (payment_id_mp = transaction_nsu, infinitepay_slug = invoice_slug) e aqui
     * os lemos da própria ordem — nunca diretamente do body do webhook.
     *
     * Segurança: a confiança está no campo `paid` da RESPOSTA (que a InfinitePay
     * só retorna true para um pagamento real desta ordem), reforçada pelo
     * anti-golpe de valor no controller.
     *
     * @return array{erro?:int,msg?:string,paid?:bool,amount?:?int,paid_amount?:?int,installments?:int,capture_method?:?string}
     */
    public function consultarStatus(OrdemPagamento $ordem): array
    {
        if (!$handle = $this->handle()) {
            return ['erro' => 1, 'msg' => 'INFINITEPAY_HANDLE não configurado'];
        }

        $payload = [
            'handle'   => $handle,
            'order_nsu' => (string) $ordem->external_reference,
        ];
        if ($ordem->payment_id_mp) {
            $payload['transaction_nsu'] = (string) $ordem->payment_id_mp;
        }
        if ($ordem->infinitepay_slug) {
            $payload['slug'] = (string) $ordem->infinitepay_slug;
        }

        try {
            $resp = Http::asJson()->timeout(20)->post(self::API . '/payment_check', $payload);
        } catch (\Throwable $e) {
            Log::error('[InfinitePay] consultarStatus exceção', ['ordem' => $ordem->id, 'msg' => $e->getMessage()]);
            return ['erro' => 1, 'msg' => 'Falha ao consultar a InfinitePay.'];
        }

        $data = $resp->json() ?? [];
        // Loga só quando o payment_check NÃO confirma (sucesso falso / não pago) —
        // ajuda a diagnosticar falhas sem poluir o log no fluxo normal de aprovação.
        if ($resp->status() >= 400 || empty($data['paid'])) {
            Log::channel('single')->info('[InfinitePay] payment_check sem pagamento', [
                'ordem' => $ordem->id, 'http' => $resp->status(), 'resp' => $data,
            ]);
        }

        if ($resp->status() >= 400) {
            return ['erro' => 1, 'msg' => 'Não foi possível confirmar o pagamento.'];
        }

        return [
            'paid'           => (bool) ($data['paid'] ?? false),
            'amount'         => isset($data['amount']) ? (int) $data['amount'] : null,          // centavos
            'paid_amount'    => isset($data['paid_amount']) ? (int) $data['paid_amount'] : null, // centavos
            'installments'   => (int) ($data['installments'] ?? 0),
            'capture_method' => isset($data['capture_method']) ? (string) $data['capture_method'] : null,
        ];
    }

    /**
     * Tenta o reembolso automático na InfinitePay. A API pública de Checkout
     * (identificada só pelo handle) NÃO documenta refund — normalmente exige
     * credenciais do lojista. Por isso este método é BEST-EFFORT: em qualquer
     * falha/ausência retorna ok=false, e o controller cai no fallback (marcar a
     * ordem 'reembolso_pendente' + avisar o admin para estornar manualmente no
     * painel da InfinitePay). Se a InfinitePay passar a expor refund por aqui,
     * basta acertar o endpoint/payload abaixo.
     *
     * @return array{ok: bool, motivo?: string}
     */
    public function reembolsar(OrdemPagamento $ordem): array
    {
        if (!$handle = $this->handle()) {
            return ['ok' => false, 'motivo' => 'INFINITEPAY_HANDLE não configurado'];
        }
        if (!$ordem->payment_id_mp && !$ordem->infinitepay_slug) {
            return ['ok' => false, 'motivo' => 'Sem identificador da transação (transaction_nsu/slug)'];
        }

        $payload = [
            'handle'    => $handle,
            'order_nsu' => (string) $ordem->external_reference,
        ];
        if ($ordem->payment_id_mp) {
            $payload['transaction_nsu'] = (string) $ordem->payment_id_mp;
        }
        if ($ordem->infinitepay_slug) {
            $payload['slug'] = (string) $ordem->infinitepay_slug;
        }

        try {
            $resp = Http::asJson()->timeout(20)->post(self::API . '/refund', $payload);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('[InfinitePay] reembolsar exceção', ['ordem' => $ordem->id, 'msg' => $e->getMessage()]);
            return ['ok' => false, 'motivo' => 'Falha ao conectar à InfinitePay.'];
        }

        if ($resp->status() >= 400) {
            Log::channel('single')->warning('[InfinitePay] reembolso não suportado/erro', [
                'ordem' => $ordem->id, 'http' => $resp->status(), 'body' => $resp->json(),
            ]);
            return ['ok' => false, 'motivo' => 'Reembolso automático indisponível neste gateway — estorno manual necessário.'];
        }

        return ['ok' => true];
    }
}
