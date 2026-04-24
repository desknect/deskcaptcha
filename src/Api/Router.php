<?php

namespace DeskCaptcha\Api;

class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    public function dispatch(Request $request): void
    {
        // Handle preflight OPTIONS
        if ($request->method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        foreach ($this->routes as [$method, $pattern, $handler]) {
            if ($request->method !== $method) continue;

            $params = $this->match($pattern, $request->path);
            if ($params !== null) {
                $handler($request, $params);
                return;
            }
        }

        Response::error('Endpoint not found', 404);
    }

    private function match(string $pattern, string $path): ?array
    {
        // Convert {param} placeholders to named regex groups
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
