<?php
/**
 * DeskCaptcha API — Rate Limit Configuration
 */

return [
    'global' => [
        'per_minute' => (int)(getenv('GLOBAL_LIMIT_MINUTE') ?: 30),
        'per_hour'   => (int)(getenv('GLOBAL_LIMIT_HOUR')   ?: 3000),
        'per_day'    => (int)(getenv('GLOBAL_LIMIT_DAY')    ?: 10000),
    ],
    // Default user limits (fallback)
    'user' => [
        'per_second' => (int)(getenv('USER_LIMIT_SECOND') ?: 5),
        'per_minute' => (int)(getenv('USER_LIMIT_MINUTE') ?: 10),
        'per_hour'   => (int)(getenv('USER_LIMIT_HOUR')   ?: 60),
        'per_day'    => (int)(getenv('USER_LIMIT_DAY')    ?: 120),
    ],
    // Per-endpoint user limits — reflect the real cost of each operation
    'endpoints' => [
        'generate' => ['per_second' => 2,  'per_minute' => 10, 'per_hour' => 60,  'per_day' => 120],
        'validate' => ['per_second' => 5,  'per_minute' => 20, 'per_hour' => 120, 'per_day' => 240],
        'image'    => null, // no limit — static file delivery
        'default'  => ['per_second' => 5,  'per_minute' => 10, 'per_hour' => 60,  'per_day' => 120],
    ],
];
