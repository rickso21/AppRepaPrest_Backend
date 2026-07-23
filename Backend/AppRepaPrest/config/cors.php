<?php
// config/cors.php

return [
    'paths' => ['*'], // Permite todas las rutas

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        '*', // En desarrollo permite todos
        // O específica los orígenes de tu app:
        // 'http://localhost:19006',
        // 'http://192.168.1.17:19006',
        // 'exp://192.168.1.17:19000' // Para Expo
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
