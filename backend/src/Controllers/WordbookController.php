<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Request;
use App\Response;
use PDO;

class WordbookController
{
    public static function index(array $params): void
    {
        $user = Auth::require();
        $stmt = Database::pdo()->prepare(
            'SELECT wb.*, (SELECT COUNT(*) FROM words w WHERE w.wordbook_id = wb.id) AS word_count
             FROM wordbooks wb WHERE wb.user_id = ? ORDER BY wb.updated_at DESC'
        );
        $stmt->execute([$user['id']]);
        Response::json($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create(array $params): void
    {
        $user = Auth::require();
        $name = trim((string) Request::input('name', ''));
        $description = trim((string) Request::input('description', ''));
        if ($name === '') {
            Response::error('词库名称不能为空', 400);
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO wordbooks (user_id, name, description) VALUES (?, ?, ?)');
        $stmt->execute([$user['id'], $name, $description]);
        Response::json(self::one($pdo, (int) $pdo->lastInsertId()));
    }

    public static function update(array $params): void
    {
        $wb = self::ownWordbook($params['id']);
        $body = Request::body();
        $fields = [];
        $values = [];
        if (array_key_exists('name', $body)) {
            $n = trim((string) $body['name']);
            if ($n === '') {
                Response::error('词库名称不能为空', 400);
            }
            $fields[] = 'name = ?';
            $values[] = $n;
        }
        if (array_key_exists('description', $body)) {
            $fields[] = 'description = ?';
            $values[] = trim((string) $body['description']);
        }
        if ($fields) {
            $values[] = $wb['id'];
            Database::pdo()->prepare('UPDATE wordbooks SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
        }
        Response::json(self::one(Database::pdo(), (int) $wb['id']));
    }

    public static function destroy(array $params): void
    {
        $wb = self::ownWordbook($params['id']);
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM words WHERE wordbook_id = ?')->execute([$wb['id']]);
        $pdo->prepare('DELETE FROM wordbooks WHERE id = ?')->execute([$wb['id']]);
        Response::ok('已删除');
    }

    public static function publish(array $params): void
    {
        $wb = self::ownWordbook($params['id']);
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE wordbooks SET is_public = 1, status = "active", published_at = NOW() WHERE id = ?')
            ->execute([$wb['id']]);
        Response::json(self::one($pdo, (int) $wb['id']));
    }

    public static function unpublish(array $params): void
    {
        $wb = self::ownWordbook($params['id']);
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE wordbooks SET is_public = 0 WHERE id = ?')->execute([$wb['id']]);
        Response::json(self::one($pdo, (int) $wb['id']));
    }

    /** 校验词库属于当前用户并返回记录 */
    public static function ownWordbook($id): array
    {
        $user = Auth::require();
        $id = (int) $id;
        $stmt = Database::pdo()->prepare('SELECT * FROM wordbooks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        $wb = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wb) {
            Response::error('词库不存在', 404, 404);
        }
        return $wb;
    }

    private static function one(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare(
            'SELECT wb.*, (SELECT COUNT(*) FROM words w WHERE w.wordbook_id = wb.id) AS word_count
             FROM wordbooks wb WHERE wb.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
