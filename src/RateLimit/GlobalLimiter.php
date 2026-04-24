<?php

namespace DeskCaptcha\RateLimit;

use DeskCaptcha\Config;
use DeskCaptcha\Database\Connection;

class GlobalLimiter
{
    private array $limits;

    public function __construct()
    {
        $this->limits = Config::limits()['global'];
    }

    // Returns null if allowed, or ['window' => ..., 'retry_after' => ..., 'code' => ...] if blocked
    public function check(): ?array
    {
        $db = Connection::get();

        $windows = [
            'minute' => ['start' => date('Y-m-d H:i:00'), 'limit' => $this->limits['per_minute'], 'seconds' => 60,   'code' => 429],
            'hour'   => ['start' => date('Y-m-d H:00:00'), 'limit' => $this->limits['per_hour'],   'seconds' => 3600, 'code' => 429],
            'day'    => ['start' => date('Y-m-d 00:00:00'), 'limit' => $this->limits['per_day'],    'seconds' => 86400,'code' => 503],
        ];

        foreach ($windows as $type => $cfg) {
            $row = $db->prepare(
                "SELECT count FROM rate_limit_global WHERE window_type = :t AND window_start = :s"
            );
            $row->execute([':t' => $type, ':s' => $cfg['start']]);
            $current = (int)($row->fetchColumn() ?: 0);

            if ($current >= $cfg['limit']) {
                $windowEnd   = strtotime($cfg['start']) + $cfg['seconds'];
                $retryAfter  = max(1, $windowEnd - time());
                return [
                    'window'      => $type,
                    'retry_after' => $retryAfter,
                    'limit'       => $cfg['limit'],
                    'current'     => $current,
                    'code'        => $cfg['code'],
                ];
            }
        }

        return null;
    }

    public function increment(): void
    {
        $db = Connection::get();
        $now = time();

        $windows = [
            'minute' => date('Y-m-d H:i:00', $now),
            'hour'   => date('Y-m-d H:00:00', $now),
            'day'    => date('Y-m-d 00:00:00', $now),
        ];

        foreach ($windows as $type => $start) {
            $db->prepare(
                "INSERT INTO rate_limit_global (window_type, window_start, count)
                 VALUES (:t, :s, 1)
                 ON CONFLICT(window_type, window_start)
                 DO UPDATE SET count = count + 1"
            )->execute([':t' => $type, ':s' => $start]);
        }
    }

    public function remaining(): array
    {
        $db  = Connection::get();
        $now = time();
        $out = [];

        $windows = [
            'minute' => [date('Y-m-d H:i:00', $now), $this->limits['per_minute']],
            'hour'   => [date('Y-m-d H:00:00', $now), $this->limits['per_hour']],
            'day'    => [date('Y-m-d 00:00:00', $now), $this->limits['per_day']],
        ];

        foreach ($windows as $type => [$start, $limit]) {
            $row = $db->prepare(
                "SELECT count FROM rate_limit_global WHERE window_type = :t AND window_start = :s"
            );
            $row->execute([':t' => $type, ':s' => $start]);
            $current  = (int)($row->fetchColumn() ?: 0);
            $out[$type] = max(0, $limit - $current);
        }

        return $out;
    }
}
