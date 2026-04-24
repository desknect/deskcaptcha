<?php
declare(strict_types=1);

chdir(dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $base = __DIR__ . '/../src/';
    $rel  = str_replace('DeskCaptcha\\', '', $class);
    $file = $base . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($file)) require_once $file;
});

use DeskCaptcha\Config;
use DeskCaptcha\Database\Connection;
use DeskCaptcha\Captcha\Generator;
use DeskCaptcha\Captcha\Pool;
use DeskCaptcha\RateLimit\GlobalLimiter;
use DeskCaptcha\RateLimit\UserLimiter;

$pass = 0;
$fail = 0;

function ok(string $label, bool $cond): void {
    global $pass, $fail;
    if ($cond) { echo "  ✓ $label\n"; $pass++; }
    else       { echo "  ✗ $label\n"; $fail++; }
}

echo "\n=== DeskCaptcha API Test Suite ===\n\n";

// ── Config ────────────────────────────────────────────────────────────────────
echo "[ Config ]\n";
$cfg = Config::api();
ok('api name is DeskCaptcha', $cfg['name'] === 'DeskCaptcha');
ok('allowed scales = [1,2,3]', $cfg['allowed_scales'] === [1, 2, 3]);
ok('allowed chars = [4,6,8]',  $cfg['allowed_chars']  === [4, 6, 8]);
ok('pool size = 50',           $cfg['pool_size'] === 50);

// ── Database ──────────────────────────────────────────────────────────────────
echo "\n[ Database ]\n";
$db = Connection::get();
ok('connection returns PDO', $db instanceof PDO);

$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
             ->fetchAll(PDO::FETCH_COLUMN);
$expected = ['captchas','pool_log','rate_limit_global','rate_limit_user','requests','users'];
ok('all tables created', $tables === $expected);

// ── Generator scale=1 chars=4 ─────────────────────────────────────────────────
echo "\n[ Generator scale=1, chars=4 ]\n";
$gen = new Generator(1, 4);
$r   = $gen->generate();
ok('filename generated',    !empty($r['filename']));
ok('answer length = 4',     strlen($r['answer']) === 4);
ok('scale = 1',             $r['scale'] === 1);
ok('chars = 4',             $r['chars'] === 4);
ok('file exists on disk',   file_exists($r['filepath']));
$size = getimagesize($r['filepath']);
ok('image width = 400',     $size[0] === 400);
ok('image height = 100',    $size[1] === 100);
ok('answer is L+N pattern', preg_match('/^[A-Z][1-9][A-Z][1-9]$/', $r['answer']) === 1);

// ── Generator scale=2 chars=6 ─────────────────────────────────────────────────
echo "\n[ Generator scale=2, chars=6 ]\n";
$gen2 = new Generator(2, 6);
$r2   = $gen2->generate();
ok('answer length = 6',   strlen($r2['answer']) === 6);
ok('scale = 2',           $r2['scale'] === 2);
$size2 = getimagesize($r2['filepath']);
ok('image width = 800',   $size2[0] === 800);
ok('image height = 200',  $size2[1] === 200);
ok('L+N pattern chars=6', preg_match('/^([A-Z][1-9]){3}$/', $r2['answer']) === 1);

// ── Generator scale=3 chars=8 ─────────────────────────────────────────────────
echo "\n[ Generator scale=3, chars=8 ]\n";
$gen3 = new Generator(3, 8);
$r3   = $gen3->generate();
ok('answer length = 8',    strlen($r3['answer']) === 8);
$size3 = getimagesize($r3['filepath']);
ok('image width = 1200',   $size3[0] === 1200);
ok('image height = 300',   $size3[1] === 300);

// ── Pool ─────────────────────────────────────────────────────────────────────
echo "\n[ Pool ]\n";
$pool = new Pool();
$count = $pool->countActive();
ok('pool count >= 0', $count >= 0);

// ── Rate Limiter ──────────────────────────────────────────────────────────────
echo "\n[ Rate Limiters ]\n";
$gl = new GlobalLimiter();
ok('global check returns null when under limit', $gl->check() === null);
$remaining = $gl->remaining();
ok('remaining has minute/hour/day', isset($remaining['minute'], $remaining['hour'], $remaining['day']));

$fp = UserLimiter::buildFingerprint('127.0.0.1', ['HTTP_USER_AGENT' => 'TestAgent']);
ok('fingerprint is 64-char hex', strlen($fp) === 64 && ctype_xdigit($fp));
$ul = new UserLimiter($fp);
ok('user check returns null when under limit', $ul->check() === null);

// ── Summary ───────────────────────────────────────────────────────────────────
echo "\n=== Results: $pass passed, $fail failed ===\n\n";
exit($fail > 0 ? 1 : 0);
