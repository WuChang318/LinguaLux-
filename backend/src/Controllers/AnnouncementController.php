<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\HtmlSanitizer;
use App\Request;
use App\Response;
use PDO;

class AnnouncementController
{
    public static function index(array $params): void
    {
        Auth::admin();
        Response::json(Database::pdo()
            ->query('SELECT * FROM announcements ORDER BY sort_order ASC, id DESC')
            ->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create(array $params): void
    {
        $admin = Auth::admin();
        $title = trim((string) Request::input('title', ''));
        $content = HtmlSanitizer::clean((string) Request::input('content', ''));
        if ($title === '' || HtmlSanitizer::toText($content) === '') {
            Response::error('标题和内容不能为空', 400);
        }
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO announcements (title, content, is_pinned, sort_order, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $title,
            $content,
            Request::bool('is_pinned', false) ? 1 : 0,
            (int) Request::input('sort_order', 0),
            Request::bool('is_active', true) ? 1 : 0,
            $admin['id'],
        ]);
        Response::json(self::one((int) $pdo->lastInsertId()));
    }

    public static function update(array $params): void
    {
        Auth::admin();
        $id = (int) $params['id'];
        $body = Request::body();
        $sets = [];
        $values = [];
        if (array_key_exists('title', $body)) {
            $t = trim((string) $body['title']);
            if ($t === '') {
                Response::error('标题不能为空', 400);
            }
            $sets[] = 'title = ?';
            $values[] = $t;
        }
        if (array_key_exists('content', $body)) {
            $c = HtmlSanitizer::clean((string) $body['content']);
            if (HtmlSanitizer::toText($c) === '') {
                Response::error('内容不能为空', 400);
            }
            $sets[] = 'content = ?';
            $values[] = $c;
        }
        if (array_key_exists('is_pinned', $body)) {
            $sets[] = 'is_pinned = ?';
            $values[] = Request::bool('is_pinned', false) ? 1 : 0;
        }
        if (array_key_exists('sort_order', $body)) {
            $sets[] = 'sort_order = ?';
            $values[] = (int) $body['sort_order'];
        }
        if (array_key_exists('is_active', $body)) {
            $sets[] = 'is_active = ?';
            $values[] = Request::bool('is_active', true) ? 1 : 0;
        }
        if ($sets) {
            $values[] = $id;
            Database::pdo()->prepare('UPDATE announcements SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
        }
        Response::json(self::one($id));
    }

    public static function destroy(array $params): void
    {
        Auth::admin();
        Database::pdo()->prepare('DELETE FROM announcements WHERE id = ?')->execute([(int) $params['id']]);
        Response::ok('已删除');
    }

    private static function one(int $id): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM announcements WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
