<?php
declare(strict_types=1);

namespace App;

use PDO;

class Auth
{
    private static $user = null;

    /** 获取当前登录用户，未登录则返回 401 并终止 */
    public static function require(): array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $token = Request::bearerToken();
        if ($token === null) {
            Response::error('未登录', 401, 401);
        }
        $stmt = Database::pdo()->prepare(
            'SELECT u.id, u.username, u.email, u.email_verified, u.role,
                    u.wordbook_limit_override, u.autofill_limit_override, u.created_at,
                    t.id AS token_id, t.last_activity
             FROM auth_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token = ? AND t.expires_at > NOW()'
        );
        $stmt->execute([$token]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            Response::error('登录已失效，请重新登录', 401, 401);
        }

        // 空闲超时自动退出
        $idleMin = (int) SiteSettings::get('session_idle_timeout_minutes');
        if ($idleMin > 0 && !empty($u['last_activity'])) {
            $last = strtotime((string) $u['last_activity']);
            if ($last !== false && time() - $last > $idleMin * 60) {
                Database::pdo()->prepare('DELETE FROM auth_tokens WHERE id = ?')->execute([$u['token_id']]);
                Response::error('长时间未操作，请重新登录', 401, 401);
            }
        }
        // 限流刷新 last_activity（最多每 30 秒写一次）
        $lastTs = !empty($u['last_activity']) ? strtotime((string) $u['last_activity']) : 0;
        if ($lastTs === false || time() - $lastTs >= 30) {
            Database::pdo()->prepare('UPDATE auth_tokens SET last_activity = NOW() WHERE id = ?')->execute([$u['token_id']]);
        }

        unset($u['token_id'], $u['last_activity']);
        self::$user = $u;
        return $u;
    }

    /** 获取当前登录用户（若已通过 require 则复用，否则抛出 401） */
    public static function user(): array
    {
        return self::require();
    }

    public static function admin(): array
    {
        $u = self::require();
        if ($u['role'] !== 'admin') {
            Response::error('无权限', 403, 403);
        }
        return $u;
    }

    public static function issueToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = Database::pdo()->prepare(
            'INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))'
        );
        $stmt->execute([$userId, $token]);
        return $token;
    }

    public static function logout(): void
    {
        $token = Request::bearerToken();
        if ($token !== null) {
            $stmt = Database::pdo()->prepare('DELETE FROM auth_tokens WHERE token = ?');
            $stmt->execute([$token]);
        }
        self::$user = null;
    }
}
