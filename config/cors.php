<?php

$allowedOrigins = array_filter(
    array_map(
        'trim',
        explode(
            ',',
            env(
                'CORS_ALLOWED_ORIGINS',
                'http://localhost:5173,http://127.0.0.1:5173'
            )
        )
    )
);

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => [
        '*',
    ],

    'allowed_origins' => array_values($allowedOrigins),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        '*',
    ],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,

];