<?php

namespace DeskCaptcha\Api;

use DeskCaptcha\Config;

class Response
{
    private static ?string $requestId = null;
    private static float   $startTime = 0.0;

    public static function init(): void
    {
        self::$requestId = bin2hex(random_bytes(8));
        self::$startTime = microtime(true);
    }

    public static function requestId(): string
    {
        return self::$requestId ?? 'unknown';
    }

    public static function json(mixed $data, int $status = 200, array $extra = []): never
    {
        $config = Config::api();
        self::headers($status, 'success');
        echo json_encode(array_merge([
            'success' => true,
            'data'    => $data,
            'meta'    => array_merge([
                'version'    => $config['version'],
                'api'        => $config['name'],
                'request_id' => self::requestId(),
                'time_ms'    => self::elapsedMs(),
            ], $extra),
        ]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function error(string $message, int $status = 400, array $extra = [], string $errorType = ''): never
    {
        $config = Config::api();
        $type   = $errorType ?: self::typeFromStatus($status);
        self::headers($status, $type);
        echo json_encode(array_merge([
            'success' => false,
            'error'   => [
                'code'    => $status,
                'type'    => $type,
                'message' => $message,
            ],
            'meta' => array_merge([
                'version'    => $config['version'],
                'api'        => $config['name'],
                'request_id' => self::requestId(),
                'time_ms'    => self::elapsedMs(),
            ], $extra),
        ]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function tooManyRequests(string $window, int $retryAfter, int $limit, int $code = 429): never
    {
        header('Retry-After: ' . $retryAfter);
        header('X-RateLimit-Limit: ' . $limit);
        $msg = $code === 503
            ? 'Service temporarily unavailable. Daily limit reached.'
            : 'Too many requests. Please wait before retrying.';
        self::error($msg, $code, [
            'rate_limit' => [
                'window'      => $window,
                'retry_after' => $retryAfter,
                'limit'       => $limit,
            ],
        ], 'rate_limit');
    }

    public static function serverError(\Throwable $e): never
    {
        $config = Config::api();
        self::headers(500, 'server_error');
        // Never expose internal details in production
        $detail = $config['local_mode']
            ? ['exception' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]
            : [];
        echo json_encode(array_merge([
            'success' => false,
            'error'   => [
                'code'    => 500,
                'type'    => 'server_error',
                'message' => 'An internal server error occurred.',
            ],
            'meta' => array_merge([
                'version'    => $config['version'],
                'api'        => $config['name'],
                'request_id' => self::requestId(),
                'time_ms'    => self::elapsedMs(),
            ], $detail),
        ]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function image(string $filepath): never
    {
        if (!file_exists($filepath)) {
            self::error('Image not found', 404, [], 'not_found');
        }
        http_response_code(200);
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Content-Length: ' . filesize($filepath));
        header('X-Request-ID: ' . self::requestId());
        header('X-Response-Time: ' . self::elapsedMs() . 'ms');
        // Remove JSON/page security headers — they break image loading in browsers
        header_remove('X-Frame-Options');
        header_remove('X-Content-Type-Options');
        header_remove('X-XSS-Protection');
        header_remove('Referrer-Policy');
        readfile($filepath);
        exit;
    }

    private static function headers(int $status, string $errorType = ''): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
        header('X-Request-ID: '     . self::requestId());
        header('X-Response-Time: '  . self::elapsedMs() . 'ms');
        if ($errorType) {
            header('X-Error-Type: ' . $errorType);
        }
    }

    private static function elapsedMs(): string
    {
        if (self::$startTime === 0.0) return '0.0';
        return number_format((microtime(true) - self::$startTime) * 1000, 1);
    }

    private static function typeFromStatus(int $status): string
    {
        return match(true) {
            $status === 400 => 'bad_request',
            $status === 401 => 'unauthorized',
            $status === 403 => 'forbidden',
            $status === 404 => 'not_found',
            $status === 409 => 'conflict',
            $status === 410 => 'gone',
            $status === 422 => 'validation',
            $status === 429 => 'rate_limit',
            $status === 503 => 'service_unavailable',
            $status >= 500  => 'server_error',
            default         => 'error',
        };
    }
}
