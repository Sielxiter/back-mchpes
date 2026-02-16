<?php

return [
    'paths' => ['*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => (function () {
        $raw = env('CORS_ALLOWED_ORIGINS');

        if (is_string($raw) && trim($raw) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

        $defaults = [
            $frontendUrl,
            'http://localhost:3000',
            'http://127.0.0.1:3000',
        ];

        return array_values(array_unique(array_filter(array_map('trim', $defaults))));
    })(),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
