<?php

namespace DeskCaptcha\Api\Middleware;

use DeskCaptcha\Config;
use DeskCaptcha\Api\Request;

class CorsMiddleware
{
    public static function handle(Request $request): void
    {
        $api  = Config::api();
        $cors = Config::cors();

        // Local mode: skip CORS entirely
        if ($api['local_mode']) return;

        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = $cors['allowed_origins'];

        $allowedOrigin = in_array('*', $allowed) ? '*' : (in_array($origin, $allowed) ? $origin : null);

        if ($allowedOrigin === null) return; // Silently reject unknown origins

        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Access-Control-Allow-Methods: ' . implode(', ', $cors['allowed_methods']));
        header('Access-Control-Allow-Headers: ' . implode(', ', $cors['allowed_headers']));
        header('Access-Control-Expose-Headers: ' . implode(', ', $cors['expose_headers']));
        header('Access-Control-Max-Age: ' . $cors['max_age']);

        if ($cors['allow_credentials']) {
            header('Access-Control-Allow-Credentials: true');
        }

        if ($request->method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
