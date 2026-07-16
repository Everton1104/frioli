<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CriarTemplateRetirada extends Command
{
    protected $signature = 'whatsapp:criar-template-retirada
                            {--waba= : ID da WhatsApp Business Account (senão usa WHATSAPP_WABA_ID ou deriva do PHONE_NUMBER_ID)}
                            {--nome=pedido_pronto_retirada : Nome do template}';

    protected $description = 'Cria na Meta o template UTILITY de aviso de pedido pronto para retirada';

    public function handle(): int
    {
        $token = env('GRAPH_API_TOKEN');
        $nome  = $this->option('nome');

        if (!$token) {
            $this->error('GRAPH_API_TOKEN não configurado no .env');
            return self::FAILURE;
        }

        $waba = $this->option('waba') ?: env('WHATSAPP_WABA_ID') ?: $this->descobrirWaba($token);
        if (!$waba) {
            $this->error('Não foi possível determinar o WABA ID. Informe com --waba=XXX ou defina WHATSAPP_WABA_ID no .env.');
            return self::FAILURE;
        }

        $this->info("WABA: {$waba} | Template: {$nome}");

        $client  = new \GuzzleHttp\Client();
        $payload = [
            'name'       => $nome,
            'language'   => 'pt_BR',
            'category'   => 'UTILITY',
            'components' => [[
                'type'    => 'BODY',
                // Meta não permite variável no início/fim: por isso a frase termina em texto.
                'text'    => 'Olá {{1}}, seu pedido está pronto para ser retirado na data {{2}}. Aguardamos você!',
                'example' => ['body_text' => [['Maria', '20/06/2026']]],
            ]],
        ];

        try {
            $resp = $client->post("https://graph.facebook.com/v25.0/{$waba}/message_templates", [
                'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
                'json'    => $payload,
            ]);
            $body = json_decode($resp->getBody()->getContents(), true);
            $this->info('Template criado/enviado para revisão:');
            $this->line(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->newLine();
            $this->warn("Adicione ao .env:  WHATSAPP_TEMPLATE_RETIRADA={$nome}");
            return self::SUCCESS;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $err = json_decode($e->getResponse()->getBody(), true);
            // Template já existente não é erro fatal para o nosso fluxo.
            $this->error($err['error']['message'] ?? 'Erro ao criar template');
            $this->line(json_encode($err, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    // Descobre o WABA dono do PHONE_NUMBER_ID percorrendo os negócios do token →
    // contas WhatsApp (próprias e de cliente) → números. Casa pelo número configurado.
    private function descobrirWaba(string $token): ?string
    {
        $phone = (string) env('PHONE_NUMBER_ID');

        try {
            $client = new \GuzzleHttp\Client();

            $get = function (string $path, array $query = []) use ($client, $token) {
                $resp = $client->get("https://graph.facebook.com/v25.0/{$path}", [
                    'query' => array_merge($query, ['access_token' => $token]),
                ]);
                return json_decode($resp->getBody()->getContents(), true);
            };

            $businesses = $get('me/businesses', ['fields' => 'id,name', 'limit' => 100])['data'] ?? [];
            $todos      = [];

            foreach ($businesses as $biz) {
                foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $rel) {
                    $wabas = $get("{$biz['id']}/{$rel}", ['fields' => 'id,name,phone_numbers{id,display_phone_number}', 'limit' => 100])['data'] ?? [];
                    foreach ($wabas as $w) {
                        $nums   = array_map(fn($pn) => ($pn['display_phone_number'] ?? $pn['id'] ?? '?'), $w['phone_numbers']['data'] ?? []);
                        $todos[] = "  {$w['id']}  {$w['name']}  [" . implode(', ', $nums) . "]  (negócio: {$biz['name']})";
                        foreach (($w['phone_numbers']['data'] ?? []) as $pn) {
                            if (($pn['id'] ?? null) === $phone) {
                                $this->info("WABA do número encontrado: {$w['id']} ({$w['name']})");
                                return $w['id'];
                            }
                        }
                    }
                }
            }

            // Sem casar o número: não chuta (app é compartilhado). Lista para escolha manual.
            $this->warn('Nenhum WABA contém o PHONE_NUMBER_ID. WABAs visíveis para este token:');
            foreach ($todos as $linha) $this->line($linha);
            $this->warn('Rode de novo com --waba=<id correto>.');
            return null;
        } catch (\Throwable $e) {
            $this->warn('Falha ao derivar WABA: ' . $e->getMessage());
            return null;
        }
    }
}
