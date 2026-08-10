<?php

$configuredOrigins = array_filter(array_map(
    fn (string $origin) => rtrim(trim($origin), '/'),
    [
        (string) env('CUSTOMER_FRONTEND_URL', ''),
        (string) env('PROVIDER_FRONTEND_URL', ''),
    ]
));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique($configuredOrigins)),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Request-ID', 'X-Correlation-ID'],
    'max_age' => 600,
    'supports_credentials' => true,
];
