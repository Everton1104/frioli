<?php

namespace App\Http\Controllers;

use App\Models\AgendamentoModel;
use App\Models\Aviso;
use App\Models\WhatsappLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class WhatsappController extends Controller
{
    // ── Webhook ──────────────────────────────────────────────────────────────

    public function verifyToken(Request $request)
    {
        $verifyToken = env('WEBHOOK_VERIFY_TOKEN');
        $challenge   = $request['hub_challenge'];
        $token       = $request['hub_verify_token'];

        if ($token === $verifyToken) {
            return response($challenge, 200);
        }

        return response('Token de verificação inválido', 403);
    }

    public function getMsgs(Request $request)
    {
        if (!$this->assinaturaValida($request)) {
            \Log::channel('single')->warning('[WA-WEBHOOK] assinatura X-Hub-Signature-256 inválida');
            return response()->json(['error' => 'invalid signature'], 403);
        }

        $business_phone_number_id = $request['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? 0;
        $msg                      = $request['entry'][0]['changes'][0]['value']['messages'][0] ?? '';
        $status                   = $request['entry'][0]['changes'][0]['value']['statuses'] ?? null;
        $number                   = $msg['from'] ?? 0;

        // Log enxuto: só o necessário para depurar o fluxo de botões. Evita gravar o
        // payload inteiro (texto livre do paciente é PII) a cada evento da Meta.
        \Log::channel('single')->info('[WA-WEBHOOK]', [
            'from'    => $number,
            'type'    => $msg['type'] ?? ($status ? 'status' : 'desconhecido'),
            'payload' => $msg['button']['payload'] ?? null,
        ]);

        try {
            if ($status) {
                return response()->json([], 200);
            }

            $msgType = $msg['type'] ?? '';
            $payload = $msg['button']['payload'] ?? '';

            if ($msgType === 'button' && $payload) {
                $this->rotearButtonPayload($business_phone_number_id, (string) $number, $payload);
            }

            return response()->json([], 200);
        } catch (\Throwable $th) {
            $this->enviarMsg($business_phone_number_id, $number, 'Erro interno. Tente novamente.');
            return response()->json([], 200);
        }
    }

    // ── Callback do gateway central (evtu.com.br) ─────────────────────────────
    //
    // Quando o webhook da Meta aponta para o gateway (mesmo número compartilhado
    // entre vários sistemas), é ele quem recebe o clique do botão. O gateway
    // extrai o slug (ex.: "av_"), stripa o prefixo e reenvia aqui o payload puro
    // ("confirmar_42") assinado com a WHATSAPP_GATEWAY_KEY. Rodamos a MESMA lógica
    // de tratamento do webhook direto — só mudou o ponto de entrada.

    public function inbound(Request $request)
    {
        if (!$this->assinaturaGatewayValida($request)) {
            \Log::channel('single')->warning('[WA-INBOUND] assinatura X-Gateway-Signature inválida');
            return response()->json(['error' => 'invalid signature'], 403);
        }

        $number  = (string) $request->input('from', '');
        $payload = (string) $request->input('payload', '');

        try {
            if ($payload) {
                // phoneId vazio: em modo gateway o envio das respostas de confirmação
                // passa pelo gateway, que usa o phone_number_id dele (este é ignorado).
                $this->rotearButtonPayload('', $number, $payload);
            }
            return response()->json([], 200);
        } catch (\Throwable $th) {
            \Log::channel('single')->error('[WA-INBOUND] erro ao processar callback', ['msg' => $th->getMessage()]);
            return response()->json([], 200);
        }
    }

    // Distribui um payload de botão para o tratamento adequado. Compartilhado
    // entre o webhook direto da Meta (getMsgs) e o callback do gateway (inbound).
    private function rotearButtonPayload(string $phoneId, string $number, string $payload): void
    {
        if (str_starts_with($payload, 'confirmar_pre_')) {
            $id = (int) str_replace('confirmar_pre_', '', $payload);
            $this->tratarPreConfirmacao($phoneId, $number, $id);
        } elseif (str_starts_with($payload, 'confirmar_')) {
            $id = (int) str_replace('confirmar_', '', $payload);
            $this->tratarConfirmacao($phoneId, $number, $id);
        } elseif (str_starts_with($payload, 'reagendar_')) {
            $id = (int) str_replace('reagendar_', '', $payload);
            $this->tratarReagendamento($phoneId, $number, $id);
        }
    }

    // Valida a assinatura HMAC do gateway (X-Gateway-Signature) sobre o corpo
    // bruto com a WHATSAPP_GATEWAY_KEY. Fail-open se a chave não estiver
    // configurada, para não quebrar ambientes ainda pré-gateway.
    private function assinaturaGatewayValida(Request $request): bool
    {
        $key = env('WHATSAPP_GATEWAY_KEY');
        if (!$key) {
            return true;
        }

        $assinatura = (string) $request->header('X-Gateway-Signature', '');
        if (!str_starts_with($assinatura, 'sha256=')) {
            return false;
        }

        $esperado = 'sha256=' . hash_hmac('sha256', $request->getContent(), $key);
        return hash_equals($esperado, $assinatura);
    }

    // Confirma que o POST veio mesmo da Meta: o corpo bruto é assinado por ela
    // (HMAC-SHA256 com o App Secret) no header X-Hub-Signature-256. Sem isso,
    // qualquer um que descubra a URL poderia injetar eventos falsos.
    // Se WHATSAPP_APP_SECRET não estiver configurado, não bloqueia (fail-open),
    // para não derrubar ambientes ainda não configurados.
    private function assinaturaValida(Request $request): bool
    {
        $appSecret = env('WHATSAPP_APP_SECRET');
        if (!$appSecret) {
            return true;
        }

        $assinatura = (string) $request->header('X-Hub-Signature-256', '');
        if (!str_starts_with($assinatura, 'sha256=')) {
            return false;
        }

        $esperado = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
        return hash_equals($esperado, $assinatura);
    }

    // ── Tratamento de respostas de botões ────────────────────────────────────

    // Pré-confirmação: cliente respondeu o lembrete da véspera.
    private function tratarPreConfirmacao(string $phoneId, string $number, int $agendamentoId): void
    {
        $agendamento = AgendamentoModel::with('user')->find($agendamentoId);
        if (!$agendamento) return;

        // Botão de um agendamento que já passou: ignora o clique (evita confirmar
        // consultas antigas via lembrete expirado).
        if (Carbon::parse($agendamento->data_inicio)->isPast()) {
            self::enviarMsg($phoneId, $number, 'Este agendamento já passou e não pode mais ser confirmado. Se precisar de um novo horário, fale com a equipe. 😊');
            return;
        }

        // Mantém o primeiro horário de pré-confirmação; ignora cliques repetidos.
        if (!$agendamento->pre_confirmado_em) {
            $agendamento->pre_confirmado_em = now();
            $agendamento->save();
        }

        $nome          = ucfirst($agendamento->user->name ?? 'você');
        $nomeComercial = env('WHATSAPP_NOME_COMERCIAL', config('app.name'));
        $data          = Carbon::parse($agendamento->data_inicio)
                             ->locale('pt_BR')
                             ->translatedFormat('d \d\e F');
        $hora          = Carbon::parse($agendamento->data_inicio)->format('H:i');

        self::enviarMsg($phoneId, $number,
            "Obrigado por confirmar, {$nome}! ✅ Sua presença para o dia *{$data}* às *{$hora}* está pré-confirmada.\n\n"
            . "No dia da consulta enviaremos uma última confirmação. Até lá! 😊"
        );
    }

    // Confirmação oficial: cliente respondeu o lembrete de 2h antes (ou staff confirmou manualmente).
    private function tratarConfirmacao(string $phoneId, string $number, int $agendamentoId): void
    {
        $agendamento = AgendamentoModel::with('user')->find($agendamentoId);
        if (!$agendamento) return;

        // Botão de um agendamento que já passou: ignora o clique (evita confirmar
        // consultas antigas via lembrete expirado).
        if (Carbon::parse($agendamento->data_inicio)->isPast()) {
            self::enviarMsg($phoneId, $number, 'Este agendamento já passou e não pode mais ser confirmado. Se precisar de um novo horário, fale com a equipe. 😊');
            return;
        }

        // Idempotência: o WhatsApp mantém o botão clicável após o clique. Se já
        // está confirmado, ignora cliques repetidos (não reenvia as mensagens).
        if ($agendamento->confirmado) return;

        $agendamento->confirmado    = true;
        $agendamento->confirmado_em = now();
        // Confirmar a consulta vale também como pré-confirmação: se o cliente
        // confirma direto (ex.: pelo "Enviar pedido de confirmação agora", antes
        // da véspera), considera as duas etapas concluídas.
        if (!$agendamento->pre_confirmado_em) {
            $agendamento->pre_confirmado_em = now();
        }
        $agendamento->save();

        // Cliente decidiu confirmar: dispensa qualquer pedido de reagendamento em
        // aberto (evita estado contraditório se antes havia clicado em "reagendar").
        Aviso::where('tipo', 'reagendamento_solicitado')
            ->where('user_id', $agendamento->user_id)
            ->where('data_antiga', $agendamento->data_inicio)
            ->whereNull('dispensado_at')
            ->update(['dispensado_at' => now()]);

        $nome          = ucfirst($agendamento->user->name ?? 'você');
        $nomeComercial = env('WHATSAPP_NOME_COMERCIAL', config('app.name'));
        $data          = Carbon::parse($agendamento->data_inicio)
                             ->locale('pt_BR')
                             ->translatedFormat('d \d\e F');
        $hora          = Carbon::parse($agendamento->data_inicio)->format('H:i');
        $endereco      = env('WHATSAPP_ENDERECO', 'Rua 23 de Maio, n° 790, Vila Vianelo, Jundiaí - Sala n.º 35 - 3° andar Bloco A "Condomínio Centro Comercial Tebas"');
        $maps          = env('WHATSAPP_MAPS_LINK', 'https://maps.app.goo.gl/gPbt9ZuejqqJRrA56');

        self::enviarMsg($phoneId, $number,
            "Perfeito, {$nome}! ✅ Sua presença está confirmada para o dia *{$data}* às *{$hora}*.\n\n"
            . "Muito obrigado por confirmar! Estamos te esperando na {$nomeComercial}. Até lá! 😊"
        );

        // Mensagem separada com o endereço da clínica + link do Google Maps em texto.
        // (O botão interativo cta_url não estava sendo entregue; link em texto é
        // clicável no WhatsApp e usa o mesmo enviarMsg que funciona de forma confiável.)
        self::enviarMsg($phoneId, $number,
            "📍 *Endereço da clínica:*\n{$endereco}\n\n"
            . "🗺️ Como chegar: {$maps}"
        );
    }

    private function tratarReagendamento(string $phoneId, string $number, int $agendamentoId): void
    {
        $agendamento = AgendamentoModel::with(['user', 'servico'])->find($agendamentoId);
        if (!$agendamento) return;

        // Botão de um agendamento que já passou: ignora o clique.
        if (Carbon::parse($agendamento->data_inicio)->isPast()) {
            self::enviarMsg($phoneId, $number, 'Não é possível reagendar um agendamento que já passou. Se precisar de um novo horário, fale com a equipe. 😊');
            return;
        }

        // Já existe um novo agendamento futuro: o paciente compareceu a este sem
        // confirmar (ou já remarcou) — ignora o reagendamento do antigo.
        $temFuturo = AgendamentoModel::where('user_id', $agendamento->user_id)
            ->where('id', '!=', $agendamento->id)
            ->where('data_inicio', '>', now())
            ->exists();
        if ($temFuturo) {
            self::enviarMsg($phoneId, $number, 'Você já tem um novo horário marcado — não é necessário reagendar este. Te esperamos no novo horário! 😊');
            return;
        }

        // Idempotência: se já há um pedido de reagendamento em aberto para esta
        // consulta, ignora o clique repetido (não duplica o aviso nem re-notifica a admin).
        $jaSolicitado = Aviso::where('tipo', 'reagendamento_solicitado')
            ->where('user_id', $agendamento->user_id)
            ->where('data_antiga', $agendamento->data_inicio)
            ->whereNull('dispensado_at')
            ->exists();
        if ($jaSolicitado) return;

        // Cliente quer trocar de horário: desfaz uma eventual confirmação anterior
        // (evita estado contraditório se antes havia clicado em "confirmar").
        if ($agendamento->confirmado) {
            $agendamento->confirmado    = false;
            $agendamento->confirmado_em = null;
            $agendamento->save();
        }

        Aviso::create([
            'tipo'        => 'reagendamento_solicitado',
            'user_id'     => $agendamento->user_id,
            'servico_id'  => $agendamento->servico_id,
            'especial'    => $agendamento->especial,
            'data_antiga' => $agendamento->data_inicio,
        ]);

        $nome   = ucfirst($agendamento->user->name ?? 'você');
        $appUrl = rtrim(env('APP_URL', config('app.url')), '/');

        self::enviarMsg($phoneId, $number,
            "Tudo bem, {$nome}! 🔄 Para escolher um novo horário, acesse o link abaixo e faça login com o seu WhatsApp:\n\n{$appUrl}/dashboard\n\nSe preferir, nossa equipe pode te ajudar a encontrar o melhor horário. 😊"
        );

        // O aviso ao número de atendimento é enviado automaticamente pelo AvisoObserver
        // ao criar o Aviso 'reagendamento_solicitado' acima (evita duplicar aqui).
    }

    // ── Envio de mensagens ────────────────────────────────────────────────────

    // Encaminha um envio ao gateway central (evtu.com.br). Retorna o mesmo
    // formato dos métodos de envio (['msg'=>...] no sucesso, ['erro'=>1,'msg'=>...]
    // no erro). Acionado quando WA_GATEWAY_ACTIVE está ligado; com a flag desligada
    // cada método cai no envio direto pela Graph API — rollback instantâneo.
    private static function enviarViaGateway(string $numero, array $payloadBody): array
    {
        $base = rtrim((string) env('WHATSAPP_GATEWAY_URL', ''), '/');
        if ($base === '') {
            return ['erro' => 1, 'msg' => 'WHATSAPP_GATEWAY_URL não configurado'];
        }

        try {
            $client   = new \GuzzleHttp\Client();
            $response = $client->request('POST', $base . '/api/whatsapp/send', [
                'headers' => [
                    'X-System'     => env('WHATSAPP_SYSTEM_SLUG', 'av'),
                    'X-Api-Key'    => env('WHATSAPP_GATEWAY_KEY', ''),
                    'Content-Type' => 'application/json',
                ],
                'json'    => array_merge(['to' => $numero], $payloadBody),
                'timeout' => 15,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            if (($body['ok'] ?? false) === true) {
                return ['msg' => 'Enviado via gateway', 'message_id' => $body['message_id'] ?? null];
            }
            return ['erro' => 1, 'msg' => $body['error'] ?? 'Erro no gateway'];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errBody = $e->hasResponse() ? json_decode($e->getResponse()->getBody(), true) : null;
            return ['erro' => 1, 'msg' => $errBody['error'] ?? $e->getMessage()];
        } catch (\Throwable $e) {
            return ['erro' => 1, 'msg' => $e->getMessage()];
        }
    }

    public static function enviarMsg($business_phone_number_id, $numero, $msg)
    {
        if (env('WA_GATEWAY_ACTIVE')) {
            $r = self::enviarViaGateway($numero, ['tipo' => 'texto', 'texto' => $msg]);
            if (!isset($r['erro'])) {
                self::log($numero, Auth::id(), $msg, null, $r['message_id'] ?? null);
            }
            return $r;
        }

        try {
            $client   = new \GuzzleHttp\Client();
            $response = $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to'                => $numero,
                    'type'              => 'text',
                    'text'              => ['body' => $msg],
                ],
            ]);

            $body      = json_decode($response->getBody(), true);
            $messageId = $body['messages'][0]['id'] ?? null;

            self::log($numero, Auth::id(), $msg, null, $messageId);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $err = json_decode($e->getResponse()->getBody(), true);
            return ['erro' => 1, 'msg' => $err['error']['message'] ?? 'Erro desconhecido'];
        } catch (\Exception $e) {
            return ['erro' => 1, 'msg' => $e->getMessage()];
        }
    }

    public static function enviarModelo($business_phone_number_id, $numero, $templateName, $parametros = [], $language = 'pt_BR', $botoes = [])
    {
        if (env('WA_GATEWAY_ACTIVE')) {
            $r = self::enviarViaGateway($numero, [
                'tipo'       => 'template',
                'template'   => $templateName,
                'parametros' => $parametros,
                'language'   => $language,
                'botoes'     => $botoes,
            ]);
            if (!isset($r['erro'])) {
                self::log($numero, Auth::id(), $templateName, null, $r['message_id'] ?? null);
            }
            return $r;
        }

        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => $numero,
                'type'              => 'template',
                'template'          => [
                    'name'     => $templateName,
                    'language' => ['code' => $language],
                ],
            ];

            $components = [];

            if (!empty($parametros)) {
                $components[] = ['type' => 'body', 'parameters' => $parametros];
            }

            foreach ($botoes as $index => $payloadBotao) {
                $components[] = [
                    'type'       => 'button',
                    'sub_type'   => 'quick_reply',
                    'index'      => $index,
                    'parameters' => [['type' => 'payload', 'payload' => $payloadBotao]],
                ];
            }

            if (!empty($components)) {
                $payload['template']['components'] = $components;
            }

            $client   = new \GuzzleHttp\Client();
            $response = $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['error'])) {
                return ['erro' => 1, 'msg' => $body['error']['message'] ?? 'Erro na API do WhatsApp'];
            }

            self::log($numero, Auth::id(), $templateName, null, $business_phone_number_id);
            return ['msg' => 'Modelo enviado com sucesso'];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $err = json_decode($e->getResponse()->getBody(), true);
            return ['erro' => 1, 'msg' => $err['error']['message'] ?? 'Erro desconhecido'];
        } catch (\Exception $e) {
            return ['erro' => 1, 'msg' => $e->getMessage()];
        }
    }

    // ── Verificação de número ─────────────────────────────────────────────────

    public static function enviarCodigoVerificacao(\App\Models\User $user): void
    {
        $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->whatsapp_code            = $codigo;
        $user->whatsapp_code_expires_at = now()->addMinutes(10);
        $user->save();

        if (env('WA_GATEWAY_ACTIVE')) {
            // user_code tem botão de copiar código (sub_type url) — enviamos os
            // components completos em modo pass-through (sem namespacing de botão,
            // pois não há reply de botão nesse template).
            $r = self::enviarViaGateway($user->whatsapp, [
                'tipo'       => 'template',
                'template'   => 'user_code',
                'language'   => 'pt_BR',
                'components' => [
                    [
                        'type'       => 'body',
                        'parameters' => [['type' => 'text', 'text' => $codigo]],
                    ],
                    [
                        'type'       => 'button',
                        'sub_type'   => 'url',
                        'index'      => 0,
                        'parameters' => [['type' => 'text', 'text' => $codigo]],
                    ],
                ],
            ]);
            \Illuminate\Support\Facades\Log::info('WhatsApp user_code via gateway', ['para' => $user->whatsapp, 'resp' => $r]);
            if (!isset($r['erro'])) {
                self::log($user->whatsapp, $user->id, 'Código de verificação enviado', null, $r['message_id'] ?? null);
            }
            return;
        }

        try {
            $client   = new \GuzzleHttp\Client();
            $response = $client->request('POST', "https://graph.facebook.com/v25.0/" . env('PHONE_NUMBER_ID') . "/messages", [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to'                => $user->whatsapp,
                    'type'              => 'template',
                    'template'          => [
                        'name'       => 'user_code',
                        'language'   => ['code' => 'pt_BR'],
                        'components' => [
                            [
                                'type'       => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $codigo],
                                ],
                            ],
                            [
                                'type'       => 'button',
                                'sub_type'   => 'url',
                                'index'      => 0,
                                'parameters' => [
                                    ['type' => 'text', 'text' => $codigo],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            \Illuminate\Support\Facades\Log::info('WhatsApp user_code enviado', ['para' => $user->whatsapp, 'resp' => $body]);
            self::log($user->whatsapp, $user->id, 'Código de verificação enviado', null, env('PHONE_NUMBER_ID'));
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $erro = json_decode($e->getResponse()->getBody(), true);
            \Illuminate\Support\Facades\Log::error('WhatsApp user_code ERRO', ['para' => $user->whatsapp, 'erro' => $erro]);
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::error('WhatsApp user_code ERRO', ['para' => $user->whatsapp, 'msg' => $th->getMessage()]);
        }
    }

    // ── Log ───────────────────────────────────────────────────────────────────

    public static function log($number = null, $user_id = null, $msg = null, $dep_id = null, $business_phone_number_id = null)
    {
        WhatsappLog::create([
            'number'                   => $number,
            'user_id'                  => $user_id,
            'msg'                      => $msg,
            'dep_id'                   => $dep_id,
            'business_phone_number_id' => $business_phone_number_id,
        ]);
    }
}
