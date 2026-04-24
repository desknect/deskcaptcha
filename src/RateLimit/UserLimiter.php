<?php

namespace DeskCaptcha\RateLimit;

use DeskCaptcha\Config;
use DeskCaptcha\Database\Connection;

class UserLimiter
{
    private array  $limits;
    private string $fingerprint;
    private string $endpoint;

    public function __construct(string $fingerprint, string $endpoint = 'default')
    {
        $this->fingerprint = $fingerprint;
        $this->endpoint    = $endpoint;

        $cfg = Config::limits();
        // Use endpoint-specific limits if defined, else fall back to 'default', then 'user'
        $this->limits = $cfg['endpoints'][$endpoint]
                     ?? $cfg['endpoints']['default']
                     ?? $cfg['user'];
    }

    public static function buildFingerprint(string $ip, array $headers): string
    {
        $ua  = $headers['HTTP_USER_AGENT']       ?? '';
        $al  = $headers['HTTP_ACCEPT_LANGUAGE']  ?? '';
        $ae  = $headers['HTTP_ACCEPT_ENCODING']  ?? '';
        $xfp = $headers['HTTP_X_FINGERPRINT']    ?? '';
        return hash('sha256', $ip . $ua . $al . $ae . $xfp);
    }

    // Returns null if allowed, or block info if rate-limited
    public function check(): ?array
    {
        // image endpoint has no user-level rate limit
        if ($this->endpoint === 'image') return null;

        $db = Connection::get();
        $fp = $this->fingerprint;

        $windows = [
            'second' => ['start' => date('Y-m-d H:i:s'),    'limit' => $this->limits['per_second'], 'seconds' => 1,     'code' => 429],
            'minute' => ['start' => date('Y-m-d H:i:00'),   'limit' => $this->limits['per_minute'], 'seconds' => 60,    'code' => 429],
            'hour'   => ['start' => date('Y-m-d H:00:00'),  'limit' => $this->limits['per_hour'],   'seconds' => 3600,  'code' => 429],
            'day'    => ['start' => date('Y-m-d 00:00:00'), 'limit' => $this->limits['per_day'],    'seconds' => 86400, 'code' => 429],
        ];

        foreach ($windows as $type => $cfg) {
            $row = $db->prepare(
                "SELECT count FROM rate_limit_user
                 WHERE fingerprint = :fp AND window_type = :t AND window_start = :s"
            );
            $row->execute([':fp' => $fp, ':t' => $type . '_' . $this->endpoint, ':s' => $cfg['start']]);
            $current = (int)($row->fetchColumn() ?: 0);

            if ($current >= $cfg['limit']) {
                $windowEnd  = strtotime($cfg['start']) + $cfg['seconds'];
                $retryAfter = max(1, $windowEnd - time());
                return [
                    'window'      => $type,
                    'retry_after' => $retryAfter,
                    'limit'       => $cfg['limit'],
                    'current'     => $current,
                    'code'        => $cfg['code'],
                    'endpoint'    => $this->endpoint,
                ];
            }
        }

        return null;
    }

    public function increment(): void
    {
        if ($this->endpoint === 'image') return;

        $db  = Connection::get();
        $fp  = $this->fingerprint;
        $now = time();

        $windows = [
            'second' => date('Y-m-d H:i:s',    $now),
            'minute' => date('Y-m-d H:i:00',   $now),
            'hour'   => date('Y-m-d H:00:00',  $now),
            'day'    => date('Y-m-d 00:00:00', $now),
        ];

        foreach ($windows as $type => $start) {
            $db->prepare(
                "INSERT INTO rate_limit_user (fingerprint, window_type, window_start, count)
                 VALUES (:fp, :t, :s, 1)
                 ON CONFLICT(fingerprint, window_type, window_start)
                 DO UPDATE SET count = count + 1"
            )->execute([':fp' => $fp, ':t' => $type . '_' . $this->endpoint, ':s' => $start]);
        }
    }

    public function upsertUser(string $ip): int
    {
        $db  = Connection::get();
        $now = date('Y-m-d H:i:s');
        $fp  = $this->fingerprint;

        $db->prepare(
            "INSERT INTO users (ip, fingerprint, first_seen, last_seen, total_requests)
             VALUES (:ip, :fp, :now, :now, 1)
             ON CONFLICT(fingerprint)
             DO UPDATE SET last_seen = :now, total_requests = total_requests + 1, ip = :ip"
        )->execute([':ip' => $ip, ':fp' => $fp, ':now' => $now]);

        return (int)$db->query(
            "SELECT id FROM users WHERE fingerprint = " . $db->quote($fp)
        )->fetchColumn();
    }
}
