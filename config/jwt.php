<?php

return [
    'secret' => env('JWT_SECRET'),
    'ttl_minutes' => (int) env('JWT_TTL_MINUTES', 60),

    'cookie_name' => env('JWT_COOKIE_NAME', 'auth_token'),
    'cookie_secure' => filter_var(env('JWT_COOKIE_SECURE', false), FILTER_VALIDATE_BOOLEAN),
    'cookie_same_site' => env('JWT_COOKIE_SAME_SITE', 'lax'),
    'cookie_domain' => env('JWT_COOKIE_DOMAIN'),
    'cookie_path' => env('JWT_COOKIE_PATH', '/'),

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    'mobile_oauth_allowed_redirect_prefixes' => array_values(array_filter(array_map(
        static fn ($value) => trim($value),
        explode(',', (string) env('JWT_MOBILE_OAUTH_ALLOWED_REDIRECT_PREFIXES', 'myapp://,exp://'))
    ))),
];
