<?php

namespace DeskCaptcha\Captcha;

use DeskCaptcha\Config;
use DeskCaptcha\Database\Connection;

class Pool
{
    private array $config;

    public function __construct()
    {
        $this->config = Config::api();
    }

    // Called after every generation to enforce pool size
    public function enforce(): void
    {
        $db        = Connection::get();
        $poolSize  = $this->config['pool_size'];
        $maxBefore = $this->config['pool_max_before_clean'];

        $count = (int)$db->query(
            "SELECT COUNT(*) FROM captchas WHERE deleted = 0"
        )->fetchColumn();

        if ($count > $maxBefore) {
            $this->deleteOldest($count - $poolSize);
        }
    }

    // Expire captchas that have passed their TTL
    public function expireOld(): void
    {
        $db  = Connection::get();
        $now = date('Y-m-d H:i:s');

        $expired = $db->prepare(
            "SELECT id, filename FROM captchas
             WHERE deleted = 0 AND used = 0 AND expires_at < :now"
        );
        $expired->execute([':now' => $now]);
        $rows = $expired->fetchAll();

        foreach ($rows as $row) {
            $this->deleteFile($row['id'], $row['filename'], 'expired');
        }

        // Also clean up used captchas older than 5 minutes
        $usedExpiry = date('Y-m-d H:i:s', time() - 300);
        $used = $db->prepare(
            "SELECT id, filename FROM captchas
             WHERE deleted = 0 AND used = 1 AND used_at < :exp"
        );
        $used->execute([':exp' => $usedExpiry]);
        foreach ($used->fetchAll() as $row) {
            $this->deleteFile($row['id'], $row['filename'], 'used_cleanup');
        }
    }

    private function deleteOldest(int $count): void
    {
        $db = Connection::get();
        $rows = $db->query(
            "SELECT id, filename FROM captchas
             WHERE deleted = 0
             ORDER BY created_at ASC
             LIMIT $count"
        )->fetchAll();

        foreach ($rows as $row) {
            $this->deleteFile($row['id'], $row['filename'], 'pool_overflow');
        }
    }

    private function deleteFile(int $id, string $filename, string $reason): void
    {
        $db      = Connection::get();
        $storage = rtrim($this->config['storage_dir'], '/');
        $path    = $storage . '/' . $filename;

        if (file_exists($path)) {
            unlink($path);
        }

        $now = date('Y-m-d H:i:s');

        $db->prepare(
            "UPDATE captchas SET deleted = 1, deleted_at = :now, delete_reason = :reason WHERE id = :id"
        )->execute([':now' => $now, ':reason' => $reason, ':id' => $id]);

        $db->prepare(
            "INSERT INTO pool_log (filename, action, reason, created_at) VALUES (:f, 'deleted', :r, :t)"
        )->execute([':f' => $filename, ':r' => $reason, ':t' => $now]);
    }

    public function countActive(): int
    {
        return (int)Connection::get()->query(
            "SELECT COUNT(*) FROM captchas WHERE deleted = 0"
        )->fetchColumn();
    }
}
