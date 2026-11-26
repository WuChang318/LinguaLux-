<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Request;
use App\Response;
use App\SiteSettings;
use App\Usage;
use PDO;

class AdminController
{
    public static function reports(array $params): void
    {
        Auth::admin();
        $sql = 'SELECT r.*, wb.name AS wordbook_name, u.username AS reporter_username
                FROM reports r
                JOIN wordbooks wb ON wb.id = r.wordbook_id
                JOIN users u ON u.id = r.reporter_id
                ORDER BY (r.status = "pending") DESC, r.id DESC';
        Response::json(Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function handle(array $params): void
    {
        $admin = Auth::admin();
        $id = (int) $params['id'];
        $action = (string) Request::input('action', '');
        if (!in_array($action, ['takedown', 'reject'], true)) {
            Response::error('非法操作', 400);
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM reports WHERE id = ?');
        $stmt->execute([$id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report) {
            Response::error('举报不存在', 404, 404);
        }

        if ($action === 'takedown') {
            $pdo->prepare('UPDATE wordbooks SET is_public = 0, status = "taken_down" WHERE id = ?')
                ->execute([$report['wordbook_id']]);
            $pdo->prepare('UPDATE reports SET status = "resolved", handled_by = ?, handled_at = NOW() WHERE id = ?')
                ->execute([$admin['id'], $id]);
        } else {
            $pdo->prepare('UPDATE reports SET status = "rejected", handled_by = ?, handled_at = NOW() WHERE id = ?')
                ->execute([$admin['id'], $id]);
        }
        Response::ok('已处理');
    }

    public static function users(array $params): void
    {
        Auth::admin();
        $q = trim((string) Request::query('q', ''));
        $page = max(1, (int) Request::query('page', 1));
        $size = min(100, max(1, (int) Request::query('size', 20)));
        $offset = ($page - 1) * $size;
        $pdo = Database::pdo();

        $where = '1=1';
        $args = [];
        if ($q !== '') {
            $where = '(u.username LIKE ? OR u.email LIKE ?)';
            $args[] = "%{$q}%";
            $args[] = "%{$q}%";
        }
        $countStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM users u WHERE {$where}");
        $countStmt->execute($args);
        $total = (int) $countStmt->fetch()['c'];

        $stmt = $pdo->prepare(
            "SELECT u.id, u.username, u.email, u.email_verified, u.role,
                    u.wordbook_limit_override, u.autofill_limit_override, u.created_at,
                    (SELECT COUNT(*) FROM wordbooks wb WHERE wb.user_id = u.id) AS wordbook_count
             FROM users u WHERE {$where}
             ORDER BY u.id DESC LIMIT {$size} OFFSET {$offset}"
        );
        $stmt->execute($args);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cycle = (string) SiteSettings::get('autofill_reset_cycle');
        foreach ($list as &$u) {
            $u['autofill_used'] = Usage::count((int) $u['id'], 'autofill', $cycle);
        }
        unset($u);
        Response::json(['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size]);
    }

    public static function updateUser(array $params): void
    {
        Auth::admin();
        $id = (int) $params['id'];
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            Response::error('用户不存在', 404, 404);
        }

        $body = Request::body();
        $sets = [];
        $values = [];
        if (array_key_exists('role', $body)) {
            $role = (string) $body['role'];
            if (!in_array($role, ['user', 'admin'], true)) {
                Response::error('角色不合法', 400);
            }
            $sets[] = 'role = ?';
            $values[] = $role;
        }
        if (array_key_exists('wordbook_limit_override', $body)) {
            $v = $body['wordbook_limit_override'];
            $sets[] = 'wordbook_limit_override = ?';
            $values[] = ($v === null || $v === '') ? null : (int) $v;
        }
        if (array_key_exists('autofill_limit_override', $body)) {
            $v = $body['autofill_limit_override'];
            $sets[] = 'autofill_limit_override = ?';
            $values[] = ($v === null || $v === '') ? null : (int) $v;
        }
        if ($sets) {
            $values[] = $id;
            $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
        }
        $stmt = $pdo->prepare('SELECT id, username, email, email_verified, role, wordbook_limit_override, autofill_limit_override, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        Response::json($stmt->fetch(PDO::FETCH_ASSOC));
    }
}
