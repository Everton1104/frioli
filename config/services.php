<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    // Mercado Pago (Checkout Transparente) — gateway legado, webhook mantido inerte.
    'mercadopago' => [
        'public_key'           => env('MP_PUBLIC_KEY'),
        'access_token'         => env('MP_ACCESS_TOKEN'),
        'webhook_secret'       => env('MP_WEBHOOK_SECRET'),
        'statement_descriptor' => env('MP_STATEMENT_DESCRIPTOR', config('app.name')),
        'taxa_credito_6x'      => (float) env('MP_TAXA_CREDITO_6X', 14.94),
    ],

    // InfinitePay — Link de Pagamento (redirect). API pública; o lojista é
    // identificado pelo HANDLE (InfiniteTag sem o '$'). WEBHOOK_TOKEN é segredo
    // nosso embutido no PATH do webhook (server-to-server), validado no controller.
    'infinitepay' => [
        'handle'        => env('INFINITEPAY_HANDLE'),
        'webhook_token' => env('INFINITEPAY_WEBHOOK_TOKEN'),
        'taxa_credito'  => (float) env('INFINITEPAY_TAXA_CREDITO', 4.99),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    // Google reCAPTCHA v2 — proteção anti-bot no agendamento online do cliente.
    // Sem chaves configuradas (dev) o serviço NÃO exige verificação.
    'recaptcha' => [
        'site_key'   => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'min_score'  => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
