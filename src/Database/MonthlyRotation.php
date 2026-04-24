<?php

namespace DeskCaptcha\Database;

class MonthlyRotation
{
    private const FLAG_FILE = __DIR__ . '/../../storage/last_rotation_check.txt';
    private const CHECK_INTERVAL = 3600; // check at most once per hour

    public static function check(): void
    {
        // Throttle: only run once per hour
        if (file_exists(self::FLAG_FILE)) {
            $lastCheck = (int)file_get_contents(self::FLAG_FILE);
            if (time() - $lastCheck < self::CHECK_INTERVAL) {
                return;
            }
        }

        file_put_contents(self::FLAG_FILE, time());

        self::prepareNextMonth();
        self::archiveOldDatabases();
    }

    // If we are on day >= 20, pre-create next month's database
    private static function prepareNextMonth(): void
    {
        if ((int)date('j') < 20) {
            return;
        }

        $nextMonth = date('Y_m', strtotime('first day of next month'));
        $path = Connection::dbPathForMonth($nextMonth);

        if (!file_exists($path)) {
            Migrations::createForMonth($nextMonth);
        }
    }

    // Delete databases older than 90 days
    private static function archiveOldDatabases(): void
    {
        $config = require __DIR__ . '/../../config/api.php';
        $dbDir  = $config['db_dir'];
        $maxAge = $config['db_archive_days'] * 86400;

        foreach (glob($dbDir . '/deskcaptcha_*.sqlite') as $file) {
            // Never delete current or next month
            $current = 'deskcaptcha_' . date('Y_m') . '.sqlite';
            $next    = 'deskcaptcha_' . date('Y_m', strtotime('first day of next month')) . '.sqlite';

            if (basename($file) === $current || basename($file) === $next) {
                continue;
            }

            if (time() - filemtime($file) > $maxAge) {
                unlink($file);
            }
        }
    }
}
