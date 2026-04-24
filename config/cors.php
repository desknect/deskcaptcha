<?php
/**
 * DeskCaptcha API — CORS Configuration
 *
 * allowed_origins: array of allowed origins, or ['*'] for open access.
 * For production, restrict to your domains:
 *   ['https://yourapp.com', 'https://admin.yourapp.com']
 */

return [
    'allowed_origins' => explode(',', getenv('CORS_ORIGINS') ?: '*'),
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'X-API-Key', 'X-Fingerprint', 'Accept'],
    'expose_headers'  => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset', 'Retry-After', 'X-Request-ID', 'X-Response-Time', 'X-Error-Type'],
    'max_age'         => 86400,
    'allow_credentials' => false,
];
