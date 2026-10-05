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
    // URL del webhook (donde MP notifica) → siempre a la API
    'notification_url' => env('MERCADOPAGO_NOTIFICATION_URL', env('APP_URL') . '/verificar_pago'),

    // URL base del frontend (a donde regresa el usuario tras pagar)
    'frontend_url' => env('FRONTEND_URL', 'https://deliverysobreruedas.com'),

    // URL base del backend (para webhooks de ads y otros)
    'backend_url' => env('APP_URL', 'https://api.deliverysobreruedas.com'),

    // URL de ngrok (solo para desarrollo local)
    'sandbox_url' => env('MERCADOPAGO_SANDBOX_URL', 'https://thing-climatic-driller.ngrok-free.dev'),
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
