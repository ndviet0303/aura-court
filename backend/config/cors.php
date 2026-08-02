<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map(
        // Portainer values are occasionally pasted as `KEY=value`, leaving a
        // trailing `=` in the origin. An origin itself cannot contain `=`, so
        // normalize that harmless typo before emitting CORS headers.
        static fn (string $origin): string => rtrim(trim($origin), '='),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:5173'))),
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
