
<?php

$defaultOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'https://solar-management-system-tau.vercel.app',
];

$envOrigins = array_filter(
    array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', ''))
    )
);

$allowedOrigins = array_values(
    array_unique(
        array_merge($defaultOrigins, $envOrigins)
    )
);

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,
];
