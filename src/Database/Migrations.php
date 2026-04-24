<?php

namespace DeskCaptcha\Database;

use PDO;

class Migrations
{
    public static function run(PDO $pdo): void
    {
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
        // Execute each statement individually
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
            if ($stmt !== '') {
                $pdo->exec($stmt);
            }
        }
    }

    public static function createForMonth(string $yearMonth): string
    {
        $path = Connection::dbPathForMonth($yearMonth);
        if (!file_exists($path)) {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');
            self::run($pdo);
        }
        return $path;
    }
}
