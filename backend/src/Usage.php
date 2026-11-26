<?php
declare(strict_types=1);

namespace App;

/**
 * 用量统计（usage_logs）：按 user + metric + 周期 key 累计。
 */
class Usage
{
    public static function periodKey(string $cycle): string
    {
        switch ($cycle) {
            case 'daily':
                return date('Y-m-d');
            case 'monthly':
                return date('Y-m');
            case 'weekly':
            default:
                return date('o-\WW'); // ISO 周，如 2026-W48
        }
    }

    public static function hit(int $userId, string $metric, string $cycle): void
    {
        $key = self::periodKey($cycle);
        Database::pdo()->prepare(
            'INSERT INTO usage_logs (user_id, metric, period_key, `count`) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE `count` = `count` + 1'
        )->execute([$userId, $metric, $key]);
    }

    public static function count(int $userId, string $metric, string $cycle): int
    {
        $key = self::periodKey($cycle);
        $stmt = Database::pdo()->prepare(
            'SELECT `count` FROM usage_logs WHERE user_id = ? AND metric = ? AND period_key = ?'
        );
        $stmt->execute([$userId, $metric, $key]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }
}
