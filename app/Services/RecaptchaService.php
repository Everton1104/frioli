<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/*
 * Verificação server-side do Google reCAPTCHA v2/v3 para o agendamento online.
 *
 * - Sem chaves configuradas (RECAPTCHA_SITE_KEY/SECRET_KEY) o método verificação()
 *   retorna TRUE (dev/liberação) — assim o app funciona sem reCAPTCHA em dev.
 *   Em PRODUÇÃO sempre sete as chaves para que a verificação seja exigida.
 * - Em caso de falha de rede com a Google, falha em "fechado" (recusa) para segurança,
 *   registrando no log.
 */
class RecaptchaService
{
    public function configurado(): bool
    {
        return (bool) config('services.recaptcha.site_key')
            && (bool) config('services.recaptcha.secret_key');
    }

    public function siteKey(): ?string
    {
        $k = config('services.recaptcha.site_key');
        return $k ? (string) $k : null;
    }

    public function verificado(?string $token): bool
    {
        $secret = config('services.recaptcha.secret_key');

        // Dev: sem configuração → não exige.
        if (!$this->configurado() || !$secret) {
            return true;
        }
        if (!$token) {
            return false;
        }

        try {
            $resp = Http::asForm()->timeout(10)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[reCAPTCHA] falha ao contatar a Google', ['msg' => $e->getMessage()]);
            return false;
        }

        $data = $resp->json() ?? [];

        // v3 traz 'score' (0..1); v2 não traz. Aplica limite só quando existir score.
        if (isset($data['score'])) {
            return (bool) ($data['success'] ?? false) && $data['score'] >= (float) config('services.recaptcha.min_score', 0.5);
        }

        return (bool) ($data['success'] ?? false);
    }
}
