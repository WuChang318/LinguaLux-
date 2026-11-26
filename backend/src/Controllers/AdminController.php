<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Request;
use App\Response;
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
}
