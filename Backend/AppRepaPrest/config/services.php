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

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'mercadopago' => [
        // Webhook: MP notifica aquí
        'notification_url' => env('MERCADOPAGO_NOTIFICATION_URL', env('APP_URL') . '/verificar_pago'),

        // Credenciales (¡las que te faltaban!)
        'public_key'      => env('MERCADOPAGO_PUBLIC_KEY'),
        'access_token'    => env('MERCADOPAGO_ACCESS_TOKEN'),
        'webhook_secret'  => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'sandbox_mode'    => env('MERCADOPAGO_SANDBOX_MODE', false),

        // URLs base
        'frontend_url' => env('FRONTEND_URL', 'https://deliverysobreruedas.com'),
        'backend_url'  => env('BACKEND_URL', env('APP_URL')),
        'sandbox_url'  => env('MERCADOPAGO_SANDBOX_URL'),

        'ads_webhook_url' => env('MERCADOPAGO_ADS_WEBHOOK_URL', env('APP_URL') . '/api/ads/webhook'),

    ],
    'banco' => [
        'nombre' => env('BANCO_NOMBRE', 'BBVA Bancomer'),
        'clabe' => env('BANCO_CLABE', '012180004123456789'),
        'cuenta' => env('BANCO_CUENTA', '1234567890'),
        'beneficiario' => env('BANCO_BENEFICIARIO', 'Tu Empresa SA de CV'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'livekit' => [
        'url' => env('LIVEKIT_URL'),
        'key' => env('LIVEKIT_API_KEY'),
        'secret' => env('LIVEKIT_API_SECRET'),
    ],

];
