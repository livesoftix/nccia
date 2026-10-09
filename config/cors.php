<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    // Explicit deployment origins only. Never reflect arbitrary origins with credentials.
    'allowed_origins' => array_values(array_unique(array_filter(array_map('trim', array_merge(
        [rtrim((string) env('APP_URL', 'http://localhost'), '/')],
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => true,
];
