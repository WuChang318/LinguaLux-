<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Request;
use App\Response;
use PDO;

class AuthController
{
    public static function register(array $params): void
    {
        $username = trim((string) Request::input('username', ''));
        $password = (string) Request::input('password', '');

        if (mb_strlen($username) < 2) {
            Response::error('用户名至少 2 个字符', 400);
        }
        if (mb_strlen($username) > 50) {
            Response::error('用户名过长', 400);
        }
        if (strlen($password) < 6) {
            Response::error('密码至少 6 位', 400);
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            Response::error('用户名已存在', 409, 409);
        }

        // 首个注册用户自动成为管理员
        $count = (int) $pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
        $role = $count === 0 ? 'admin' : 'user';

        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
        self::respondWithUser((int) $pdo->lastInsertId());
    }

    public static function login(array $params): void
    {
        $username = trim((string) Request::input('username', ''));
        $password = (string) Request::input('password', '');

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('用户名或密码错误', 400);
        }
        self::respondWithUser((int) $user['id']);
    }

    public static function logout(array $params): void
    {
        Auth::logout();
        Response::ok('已退出登录');
    }

    public static function me(array $params): void
    {
        $u = Auth::require();
        $cycle = (string) \App\SiteSettings::get('autofill_reset_cycle');
        $u['wordbook_limit'] = \App\Limits::wordbookLimit($u);
        $u['wordbook_count'] = \App\Limits::wordbookCount((int) $u['id']);
        $u['autofill_limit'] = \App\Limits::autofillLimit($u);
        $u['autofill_used'] = \App\Usage::count((int) $u['id'], 'autofill', $cycle);
        Response::json($u);
    }

    private static function respondWithUser(int $userId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, username, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $token = Auth::issueToken($userId);
        Response::json(['token' => $token, 'user' => $user]);
    }
}
