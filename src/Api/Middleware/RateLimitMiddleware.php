<?php

namespace DeskCaptcha\Api\Middleware;

use DeskCaptcha\Api\Request;
use DeskCaptcha\Api\Response;
use DeskCaptcha\RateLimit\GlobalLimiter;
use DeskCaptcha\RateLimit\UserLimiter;

class RateLimitMiddleware
{
    private GlobalLimiter $global;
    private UserLimiter   $user;

    public function __construct(string $fingerprint, string $endpoint = 'default')
    {
        $this->global = new GlobalLimiter();
        $this->user   = new UserLimiter($fingerprint, $endpoint);
    }

    public function handle(Request $request): void
    {
        // Check global limits first
        $blocked = $this->global->check();
        if ($blocked) {
            header('X-RateLimit-Limit: ' . $blocked['limit']);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . (time() + $blocked['retry_after']));
            Response::tooManyRequests($blocked['window'], $blocked['retry_after'], $blocked['limit'], $blocked['code']);
        }

        // Check per-user, per-endpoint limits
        $blocked = $this->user->check();
        if ($blocked) {
            header('X-RateLimit-Limit: ' . $blocked['limit']);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . (time() + $blocked['retry_after']));
            Response::tooManyRequests($blocked['window'], $blocked['retry_after'], $blocked['limit'], $blocked['code']);
        }

        // All good — increment both
        $this->global->increment();
        $this->user->increment();

        // Attach remaining headers
        $remaining    = $this->global->remaining();
        $minRemaining = min($remaining['minute'], $remaining['hour'], $remaining['day']);
        header('X-RateLimit-Remaining: ' . $minRemaining);
    }

    public function upsertUser(string $ip): int
    {
        return $this->user->upsertUser($ip);
    }

    public function globalRemaining(): array
    {
        return $this->global->remaining();
    }
}
