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
            'SELECT u.id, u.username, u.role, u.created_at
             FROM auth_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token = ? AND t.expires_at > NOW()'
        );
        $stmt->execute([$token]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            Response::error('登录已失效，请重新登录', 401, 401);
        }
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
