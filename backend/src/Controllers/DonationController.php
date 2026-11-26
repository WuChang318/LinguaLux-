<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Request;
use App\Response;
use App\Upload;
use PDO;

class DonationController
{
    public static function index(array $params): void
    {
        Auth::admin();
        Response::json(Database::pdo()
            ->query('SELECT * FROM donation_channels ORDER BY sort_order ASC, id DESC')
            ->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create(array $params): void
    {
        Auth::admin();
        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            Response::error('渠道名称不能为空', 400);
        }
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO donation_channels (name, description, image_url, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $name,
            trim((string) Request::input('description', '')),
            self::resolveImage(Request::input('image', '')),
            (int) Request::input('sort_order', 0),
            Request::bool('is_active', true) ? 1 : 0,
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
        if (array_key_exists('name', $body)) {
            $n = trim((string) $body['name']);
            if ($n === '') {
                Response::error('渠道名称不能为空', 400);
            }
            $sets[] = 'name = ?';
            $values[] = $n;
        }
        if (array_key_exists('description', $body)) {
            $sets[] = 'description = ?';
            $values[] = trim((string) $body['description']);
        }
        if (array_key_exists('image', $body)) {
            $sets[] = 'image_url = ?';
            $values[] = self::resolveImage($body['image']);
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
            Database::pdo()->prepare('UPDATE donation_channels SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
        }
        Response::json(self::one($id));
    }

    public static function destroy(array $params): void
    {
        Auth::admin();
        Database::pdo()->prepare('DELETE FROM donation_channels WHERE id = ?')->execute([(int) $params['id']]);
        Response::ok('已删除');
    }

    /** image 可为 base64（需上传）或已有 URL（原样保留） */
    private static function resolveImage($image): ?string
    {
        $image = trim((string) $image);
        if ($image === '') {
            return null;
        }
        if (strpos($image, 'data:image') === 0 || (preg_match('#^[A-Za-z0-9+/=]+$#', $image) && strlen($image) > 64)) {
            return Upload::saveBase64Image($image);
        }
        return $image; // 已是 URL
    }

    private static function one(int $id): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM donation_channels WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
