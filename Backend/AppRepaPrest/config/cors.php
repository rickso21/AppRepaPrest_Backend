<?php
// config/cors.php

return [
    'paths' => ['*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        // Producción
        'https://deliverysobreruedas.com',
        'https://www.deliverysobreruedas.com',

        // Desarrollo local (Vite)
        'http://localhost:5173',
        'http://localhost:3000',

        // Desarrollo local (Expo / React Native - opcional)
        'http://localhost:19006',
        'http://192.168.1.17:19006',
    ],

    'allowed_origins_patterns' => [
        // Opcional: permitir cualquier puerto localhost en dev
        // '#^http://localhost:\d+$#',
        // '#^http://192\.168\.\d+\.\d+:\d+$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600, // Cache del preflight: 1 hora

    'supports_credentials' => false,
];
