<?php

namespace DeskCaptcha\Api;

class Request
{
    public readonly string $method;
    public readonly string $path;
    public readonly string $ip;
    public readonly array  $query;
    public readonly array  $body;
    public readonly array  $headers;
    public readonly string $fingerprint;

    public function __construct()
    {
        $this->method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path     = $this->parsePath();
        $this->ip       = $this->resolveIp();
        $this->query    = $_GET;
        $this->body     = $this->parseBody();
        $this->headers  = $_SERVER;
        $this->fingerprint = \DeskCaptcha\RateLimit\UserLimiter::buildFingerprint($this->ip, $_SERVER);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $this->body[$key] ?? $default;
    }

    public function header(string $name, string $default = ''): string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$normalized] ?? $default;
    }

    private function parsePath(): string
    {
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        $path   = parse_url($uri, PHP_URL_PATH);
        // Strip base prefix (e.g. /deskcaptcha/public or /deskcaptcha/public/index.php)
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        // Remove script filename from path if present
        if ($script !== '' && str_starts_with($path, $script)) {
            $path = substr($path, strlen($script));
        } else {
            $dir = dirname($script);
            if ($dir !== '/' && str_starts_with($path, $dir)) {
                $path = substr($path, strlen($dir));
            }
        }
        return '/' . ltrim($path ?: '/', '/');
    }

    private function resolveIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return explode(',', $_SERVER[$key])[0];
            }
        }
        return '0.0.0.0';
    }

    private function parseBody(): array
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) {
            $raw = file_get_contents('php://input');
            return json_decode($raw, true) ?? [];
        }
        return $_POST;
    }
}
