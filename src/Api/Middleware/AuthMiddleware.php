<?php

namespace DeskCaptcha\Api\Middleware;

use DeskCaptcha\Config;
use DeskCaptcha\Api\Request;
use DeskCaptcha\Api\Response;

class AuthMiddleware
{
    public static function handle(Request $request): void
    {
        $cfg = Config::api();

        if (!$cfg['require_api_key']) return;

        $key = $request->header('X-API-Key');

        if (empty($key) || !in_array($key, $cfg['api_keys'], true)) {
            Response::error('Invalid or missing API key. Provide a valid X-API-Key header.', 401);
        }
    }
}
