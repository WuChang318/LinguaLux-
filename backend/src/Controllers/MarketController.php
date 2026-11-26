<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Request;
use App\Response;
use PDO;

class MarketController
{
    public static function index(array $params): void
    {
        Auth::require();
        $q = trim((string) Request::query('q', ''));
        $page = max(1, (int) Request::query('page', 1));
        $size = min(50, max(1, (int) Request::query('size', 12)));
        $offset = ($page - 1) * $size;

        $pdo = Database::pdo();
        $where = "wb.is_public = 1 AND wb.status = 'active'";
        $args = [];
        if ($q !== '') {
            $where .= " AND (wb.name LIKE ? OR wb.description LIKE ?)";
            $args[] = "%{$q}%";
            $args[] = "%{$q}%";
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM wordbooks wb WHERE {$where}");
        $countStmt->execute($args);
        $total = (int) $countStmt->fetch()['c'];

        $sql = "SELECT wb.id, wb.name, wb.description, wb.copied_count, wb.created_at,
                       u.username AS owner_username,
                       (SELECT COUNT(*) FROM words w WHERE w.wordbook_id = wb.id) AS word_count
                FROM wordbooks wb JOIN users u ON u.id = wb.user_id
                WHERE {$where}
                ORDER BY wb.copied_count DESC, wb.id DESC
                LIMIT {$size} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json(['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size]);
    }

    public static function show(array $params): void
    {
        Auth::require();
        $id = (int) $params['id'];
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT wb.*, u.username AS owner_username
             FROM wordbooks wb JOIN users u ON u.id = wb.user_id
             WHERE wb.id = ? AND wb.is_public = 1 AND wb.status = 'active'"
        );
        $stmt->execute([$id]);
        $wb = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wb) {
            Response::error('词库不存在或已下架', 404, 404);
        }
        $wstmt = $pdo->prepare(
            'SELECT id, word, phonetic, audio_url, pos, meaning_zh, definition_en, example, example_zh, note
             FROM words WHERE wordbook_id = ? ORDER BY id ASC'
        );
        $wstmt->execute([$id]);
        $wb['words'] = $wstmt->fetchAll(PDO::FETCH_ASSOC);
        $wb['word_count'] = count($wb['words']);
        Response::json($wb);
    }

    public static function use(array $params): void
    {
        $user = Auth::require();
        $id = (int) $params['id'];
        $pdo = Database::pdo();

        $stmt = $pdo->prepare("SELECT * FROM wordbooks WHERE id = ? AND is_public = 1 AND status = 'active'");
        $stmt->execute([$id]);
        $src = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$src) {
            Response::error('词库不存在或已下架', 404, 404);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO wordbooks (user_id, name, description, source_wordbook_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$user['id'], $src['name'] . '（副本）', $src['description'], $src['id']]);
            $newId = (int) $pdo->lastInsertId();

            $wstmt = $pdo->prepare(
                'SELECT word, phonetic, audio_url, pos, meaning_zh, definition_en, example, example_zh, note FROM words WHERE wordbook_id = ?'
            );
            $wstmt->execute([$id]);
            $insert = $pdo->prepare(
                'INSERT INTO words (wordbook_id, word, phonetic, audio_url, pos, meaning_zh, definition_en, example, example_zh, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($wstmt->fetchAll(PDO::FETCH_ASSOC) as $w) {
                $insert->execute([
                    $newId, $w['word'], $w['phonetic'], $w['audio_url'], $w['pos'],
                    $w['meaning_zh'], $w['definition_en'], $w['example'], $w['example_zh'], $w['note'],
                ]);
            }
            $pdo->prepare('UPDATE wordbooks SET copied_count = copied_count + 1 WHERE id = ?')->execute([$id]);
            $pdo->commit();
            Response::json(['wordbook_id' => $newId]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::error('复制失败：' . $e->getMessage(), 500, 500);
        }
    }

    public static function report(array $params): void
    {
        $user = Auth::require();
        $id = (int) $params['id'];
        $reason = trim((string) Request::input('reason', ''));
        if ($reason === '') {
            Response::error('请填写举报原因', 400);
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM wordbooks WHERE id = ? AND is_public = 1');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            Response::error('词库不存在', 404, 404);
        }
        $stmt = $pdo->prepare('SELECT id FROM reports WHERE wordbook_id = ? AND reporter_id = ? AND status = "pending"');
        $stmt->execute([$id, $user['id']]);
        if ($stmt->fetch()) {
            Response::error('你已举报过该词库，正在处理中', 409, 409);
        }
        $pdo->prepare('INSERT INTO reports (wordbook_id, reporter_id, reason) VALUES (?, ?, ?)')
            ->execute([$id, $user['id'], $reason]);
        Response::ok('举报已提交');
    }
}
