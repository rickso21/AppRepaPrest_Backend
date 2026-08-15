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
    'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    'notification_url' => env('MERCADOPAGO_NOTIFICATION_URL'),
    'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),

    // Opcional: para ambiente de pruebas
    'sandbox_mode' => env('MERCADOPAGO_SANDBOX_MODE', true),
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

];
