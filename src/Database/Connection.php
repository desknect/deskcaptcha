<?php

/**
 * @see https://dcaptcha.desknect.com/api-documentacao
 * @see https://desknect.com
 */

namespace DeskCaptcha\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;
    private static string $currentDb = '';

    public static function get(): PDO
    {
        $dbPath = self::currentDbPath();

        if (self::$instance === null || self::$currentDb !== $dbPath) {
            self::$instance = self::connect($dbPath);
            self::$currentDb = $dbPath;
        }

        return self::$instance;
    }

    public static function currentDbPath(): string
    {
        $config = require __DIR__ . '/../../config/api.php';
        $file = 'deskcaptcha_' . date('Y_m') . '.sqlite';
        return rtrim($config['db_dir'], '/') . '/' . $file;
    }

    public static function dbPathForMonth(string $yearMonth): string
    {
        $config = require __DIR__ . '/../../config/api.php';
        $file = 'deskcaptcha_' . $yearMonth . '.sqlite';
        return rtrim($config['db_dir'], '/') . '/' . $file;
    }

    private static function connect(string $path): PDO
    {
        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');
            $pdo->exec('PRAGMA synchronous=NORMAL');
            Migrations::run($pdo);
            return $pdo;
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    // Reset singleton (used in rotation)
    public static function reset(): void
    {
        self::$instance = null;
        self::$currentDb = '';
    }
}
