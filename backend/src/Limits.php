<?php
declare(strict_types=1);

namespace App;

/**
 * 用户配额：词本上限、一键填充上限（支持单用户覆盖 + 站点默认）。
 */
class Limits
{
    public static function wordbookLimit(array $user): int
    {
        if ($user['wordbook_limit_override'] !== null) {
            return (int) $user['wordbook_limit_override'];
        }
        return (int) SiteSettings::get('user_max_wordbooks');
    }

    public static function autofillLimit(array $user): int
    {
        if ($user['autofill_limit_override'] !== null) {
            return (int) $user['autofill_limit_override'];
        }
        return (int) SiteSettings::get('user_weekly_autofill_limit');
    }

    public static function wordbookCount(int $userId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM wordbooks WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function autofillUsed(int $userId, string $cycle): int
    {
        return Usage::count($userId, 'autofill', $cycle);
    }

    public static function assertCanCreateWordbook(array $user): void
    {
        $limit = self::wordbookLimit($user);
        if ($limit <= 0) {
            return;
        }
        if (self::wordbookCount((int) $user['id']) >= $limit) {
            Response::error("词本数量已达上限（{$limit} 个），如需更多请联系管理员", 403, 403);
        }
    }

    public static function assertCanAutofill(array $user): void
    {
        $limit = self::autofillLimit($user);
        if ($limit <= 0) {
            return;
        }
        $cycle = (string) SiteSettings::get('autofill_reset_cycle');
        if (self::autofillUsed((int) $user['id'], $cycle) >= $limit) {
            Response::error("本周期一键填充次数已达上限（{$limit} 次）", 403, 403);
        }
    }

    /** 记录一次一键填充用量 */
    public static function recordAutofill(array $user): void
    {
        $cycle = (string) SiteSettings::get('autofill_reset_cycle');
        Usage::hit((int) $user['id'], 'autofill', $cycle);
    }
}
