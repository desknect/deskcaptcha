<?php
/**
 * DeskCaptcha API — Core Configuration
 * Desknect.com
 */

return [
    // API identity
    'name'        => 'DeskCaptcha',
    'version'     => '1.0.0',
    'company'     => 'Desknect.com',
    'base_url'    => getenv('API_BASE_URL') ?: 'http://localhost/deskcaptcha/public/index.php',

    // API Key (optional enforcement)
    'require_api_key' => filter_var(getenv('REQUIRE_API_KEY') ?: false, FILTER_VALIDATE_BOOLEAN),
    'api_keys'        => array_filter(explode(',', getenv('API_KEYS') ?: '')),

    // Local/internal network mode — disables CORS enforcement
    'local_mode'  => filter_var(getenv('LOCAL_MODE') ?: false, FILTER_VALIDATE_BOOLEAN),

    // Captcha generation
    'captcha_ttl_seconds' => (int)(getenv('CAPTCHA_TTL') ?: 600),   // 10 minutes
    'pool_size'           => (int)(getenv('POOL_SIZE') ?: 50),
    'pool_max_before_clean' => 55,

    // Allowed scale values
    'allowed_scales' => [1, 2, 3],

    // Allowed char counts (always letter+number pattern)
    'allowed_chars' => [4, 6, 8],

    // Base image dimensions (scale=1)
    'image_width'  => 400,
    'image_height' => 100,

    // Database
    'db_dir'          => __DIR__ . '/../database',
    'db_archive_days' => 90,

    // Storage
    'storage_dir' => __DIR__ . '/../storage/captchas',

    // Logging
    'log_requests' => true,
];
