<?php
/**
 * DeskCaptcha API — Front Controller
 *
 * @company  Desknect.com — https://desknect.com
 * @docs     https://dcaptcha.desknect.com/api-documentacao
 * @donate   https://desknect.com/donate
 * @source   https://github.com/desknect/deskcaptcha
 *
 * All HTTP requests are routed through this file.
 */

declare(strict_types=1);

// ── Autoloader ────────────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $base = dirname(__DIR__) . '/src/';
    $rel  = str_replace('DeskCaptcha\\', '', $class);
    $file = $base . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($file)) require_once $file;
});

use DeskCaptcha\Api\Request;
use DeskCaptcha\Api\Response;
use DeskCaptcha\Api\Router;
use DeskCaptcha\Api\Middleware\CorsMiddleware;
use DeskCaptcha\Api\Middleware\AuthMiddleware;
use DeskCaptcha\Api\Middleware\RateLimitMiddleware;
use DeskCaptcha\Captcha\Generator;
use DeskCaptcha\Captcha\Cleaner;
use DeskCaptcha\Captcha\Pool;
use DeskCaptcha\Database\Connection;
use DeskCaptcha\Database\MonthlyRotation;
use DeskCaptcha\Config;

// ── Bootstrap ─────────────────────────────────────────────────────────────────

// Initialize request ID and timer (must be first)
Response::init();

// Global exception handler — catches anything not handled below
set_exception_handler(function (\Throwable $e): void {
    error_log('[DeskCaptcha] Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Response::serverError($e);
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) return false;
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

$request = new Request();

// CORS (always first)
CorsMiddleware::handle($request);

// Monthly DB rotation check (throttled internally)
MonthlyRotation::check();

// Auth (API key enforcement if enabled)
AuthMiddleware::handle($request);

// Detect endpoint type for per-endpoint rate limiting
$endpointType = match(true) {
    str_contains($request->path, '/generate')  => 'generate',
    str_contains($request->path, '/validate')  => 'validate',
    str_ends_with($request->path, '.png')
        || (str_contains($request->path, '/captcha/')
            && !str_contains($request->path, '/generate')
            && !str_contains($request->path, '/validate')) => 'image',
    default => 'default',
};

// Rate limiting (skip for health/status)
$rateLimiter   = new RateLimitMiddleware($request->fingerprint, $endpointType);
$skipRateLimit = in_array($request->path, ['/v1/health', '/v1/status']);
if (!$skipRateLimit) {
    $rateLimiter->handle($request);
}

// Upsert user record & get user_id
$userId = $rateLimiter->upsertUser($request->ip);

// ── Router ────────────────────────────────────────────────────────────────────
$router = new Router();

// ── GET /v1/captcha/generate ───────────────────────────────────────────────────
$router->get('/v1/captcha/generate', function (Request $req, array $params) use ($userId, $rateLimiter): void {
    $apiCfg = Config::api();

    // Validate scale
    $scale = (int)($req->get('scale', 1));
    if (!in_array($scale, $apiCfg['allowed_scales'])) {
        Response::error('Invalid scale. Allowed values: 1, 2, 3.', 422);
    }

    // Validate chars
    $chars = (int)($req->get('chars', 4));
    if (!in_array($chars, $apiCfg['allowed_chars'])) {
        Response::error('Invalid chars count. Allowed values: 4, 6, 8.', 422);
    }

    // Run cleanup before generating
    Cleaner::run();

    // Generate captcha
    $gen    = new Generator($scale, $chars);
    $result = $gen->generate();

    // Persist to DB
    $db    = Connection::get();
    $token = bin2hex(random_bytes(16));
    $now   = date('Y-m-d H:i:s');
    $exp   = date('Y-m-d H:i:s', time() + $apiCfg['captcha_ttl_seconds']);

    $db->prepare(
        "INSERT INTO captchas (token, filename, answer, chars, scale, created_at, expires_at, user_id)
         VALUES (:t, :f, :a, :c, :s, :now, :exp, :uid)"
    )->execute([
        ':t'   => $token,
        ':f'   => $result['filename'],
        ':a'   => $result['answer'],
        ':c'   => $chars,
        ':s'   => $scale,
        ':now' => $now,
        ':exp' => $exp,
        ':uid' => $userId,
    ]);

    // Log pool creation
    $db->prepare(
        "INSERT INTO pool_log (filename, action, reason, created_at) VALUES (:f, 'created', 'generate', :t)"
    )->execute([':f' => $result['filename'], ':t' => $now]);

    // Log request
    $db->prepare(
        "INSERT INTO requests (ip, fingerprint, user_id, endpoint, method, scale, chars, status, created_at)
         VALUES (:ip, :fp, :uid, '/v1/captcha/generate', 'GET', :sc, :ch, 200, :t)"
    )->execute([
        ':ip'  => $req->ip,
        ':fp'  => $req->fingerprint,
        ':uid' => $userId,
        ':sc'  => $scale,
        ':ch'  => $chars,
        ':t'   => $now,
    ]);

    // Enforce pool size after generation
    (new Pool())->enforce();

    $baseUrl = rtrim($apiCfg['base_url'], '/');
    $remaining = $rateLimiter->globalRemaining();

    Response::json([
        'token'       => $token,
        'image_url'   => $baseUrl . '/v1/captcha/' . $result['filename'],
        'expires_in'  => $apiCfg['captcha_ttl_seconds'],
        'expires_at'  => $exp,
        'scale'       => $scale,
        'chars'       => $chars,
        'dimensions'  => [
            'width'  => $apiCfg['image_width']  * $scale,
            'height' => $apiCfg['image_height'] * $scale,
        ],
    ], 200, ['remaining' => $remaining]);
});

// ── GET /v1/captcha/{filename} ─────────────────────────────────────────────────
$router->get('/v1/captcha/{filename}', function (Request $req, array $params) use ($userId): void {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $params['filename']);
    $cfg      = Config::api();
    $path     = rtrim($cfg['storage_dir'], '/') . '/' . $filename;
    Response::image($path);
});

// ── POST /v1/captcha/validate ──────────────────────────────────────────────────
$router->post('/v1/captcha/validate', function (Request $req, array $params) use ($userId): void {
    $token  = trim((string)$req->get('token', ''));
    $answer = strtoupper(trim((string)$req->get('answer', '')));

    if (empty($token) || empty($answer)) {
        Response::error('Both token and answer are required.', 422);
    }

    $db  = Connection::get();
    $now = date('Y-m-d H:i:s');

    $row = $db->prepare(
        "SELECT id, answer, used, deleted, expires_at FROM captchas WHERE token = :t"
    );
    $row->execute([':t' => $token]);
    $captcha = $row->fetch();

    if (!$captcha) {
        Response::error('Captcha not found.', 404);
    }

    if ($captcha['deleted']) {
        Response::error('Captcha has been deleted or expired.', 410);
    }

    if ($captcha['used']) {
        Response::error('Captcha has already been used.', 409);
    }

    if ($now > $captcha['expires_at']) {
        Response::error('Captcha has expired.', 410);
    }

    $correct = strtoupper($captcha['answer']) === $answer;

    // Mark as used
    $db->prepare(
        "UPDATE captchas SET used = 1, used_at = :now WHERE id = :id"
    )->execute([':now' => $now, ':id' => $captcha['id']]);

    $db->prepare(
        "INSERT INTO requests (ip, fingerprint, user_id, endpoint, method, status, created_at)
         VALUES (:ip, :fp, :uid, '/v1/captcha/validate', 'POST', :st, :t)"
    )->execute([
        ':ip'  => $req->ip,
        ':fp'  => $req->fingerprint,
        ':uid' => $userId,
        ':st'  => $correct ? 200 : 422,
        ':t'   => $now,
    ]);

    if ($correct) {
        Response::json(['valid' => true, 'message' => 'Captcha validated successfully.']);
    } else {
        Response::json(['valid' => false, 'message' => 'Incorrect answer.'], 422);
    }
});

// ── GET /v1/status ─────────────────────────────────────────────────────────────
$router->get('/v1/status', function (Request $req, array $params) use ($rateLimiter): void {
    $remaining = $rateLimiter->globalRemaining();
    $pool      = new Pool();
    $cfg       = Config::api();

    Response::json([
        'status'   => 'operational',
        'pool'     => [
            'active' => $pool->countActive(),
            'target' => $cfg['pool_size'],
        ],
        'rate_limits' => [
            'global' => [
                'remaining' => $remaining,
                'limits'    => Config::limits()['global'],
            ],
        ],
        'database' => 'deskcaptcha_' . date('Y_m') . '.sqlite',
    ]);
});

// ── GET /v1/health ─────────────────────────────────────────────────────────────
$router->get('/v1/health', function (Request $req, array $params): void {
    try {
        Connection::get()->query('SELECT 1');
        $dbOk = true;
    } catch (\Throwable) {
        $dbOk = false;
    }

    $status = $dbOk ? 200 : 503;
    Response::json([
        'status'   => $dbOk ? 'healthy' : 'degraded',
        'database' => $dbOk ? 'ok' : 'error',
        'time'     => date('Y-m-d H:i:s'),
        'version'  => Config::api()['version'],
    ], $status);
});

// ── Dispatch ───────────────────────────────────────────────────────────────────
$router->dispatch($request);
