<?php

$defaultDevOrigins = [
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost:3001',
    'http://127.0.0.1:3001',
    'http://localhost:3002',
    'http://127.0.0.1:3002',
    'http://localhost:8081',
    'http://127.0.0.1:8081',
    'http://localhost:8082',
    'http://127.0.0.1:8082',
    'http://localhost:19006',
    'http://127.0.0.1:19006',
    'https://admin.sagansa.id', // Production Admin Web
    'https://pos.sagansa.id',   // Production POS
    'https://presence.sagansa.id', // Production Presence
];

$rawFrontendOrigins = array_filter(
    array_map('trim', explode(',', (string) env('FRONTEND_URLS', env('FRONTEND_URL'))))
);

$allowedOrigins = array_unique(array_merge($rawFrontendOrigins, $defaultDevOrigins));

return [
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [
        '#^https://([a-z0-9-]+\.)?sagansa\.id$#',
        '#^http://localhost:\d+$#',
        '#^http://127\.0\.0\.1:\d+$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
