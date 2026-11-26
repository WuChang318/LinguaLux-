<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Request;
use App\Response;
use PDO;

class WordController
{
    private const FIELDS = ['word', 'phonetic', 'audio_url', 'pos', 'meaning_zh', 'definition_en', 'example', 'example_zh', 'note'];

    public static function index(array $params): void
    {
        $wb = WordbookController::ownWordbook($params['id']);
        $stmt = Database::pdo()->prepare('SELECT * FROM words WHERE wordbook_id = ? ORDER BY id DESC');
        $stmt->execute([$wb['id']]);
        Response::json($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create(array $params): void
    {
        $wb = WordbookController::ownWordbook($params['id']);
        $data = self::normalize(Request::body());
        if (trim((string) $data['word']) === '') {
            Response::error('单词不能为空', 400);
        }
        Response::json(self::one(self::insert((int) $wb['id'], $data)));
    }

    public static function import(array $params): void
    {
        $wb = WordbookController::ownWordbook($params['id']);
        $words = Request::input('words', null);
        if (!is_array($words) || empty($words)) {
            Response::error('没有可导入的单词', 400);
        }
        $inserted = 0;
        foreach ($words as $item) {
            if (!is_array($item)) {
                continue;
            }
            $data = self::normalize($item);
            $word = trim((string) $data['word']);
            if ($word === '') {
                continue;
            }
            $data['word'] = $word;
            self::insert((int) $wb['id'], $data);
            $inserted++;
        }
        Response::json(['imported' => $inserted]);
    }

    public static function update(array $params): void
    {
        $word = self::ownWord((int) $params['id']);
        $body = Request::body();
        $sets = [];
        $values = [];
        foreach (self::FIELDS as $f) {
            if (array_key_exists($f, $body)) {
                $v = trim((string) $body[$f]);
                if ($f === 'word' && $v === '') {
                    Response::error('单词不能为空', 400);
                }
                $sets[] = "`$f` = ?";
                $values[] = $v;
            }
        }
        if ($sets) {
            $values[] = $word['id'];
            Database::pdo()->prepare('UPDATE words SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
        }
        Response::json(self::one((int) $word['id']));
    }

    public static function destroy(array $params): void
    {
        $word = self::ownWord((int) $params['id']);
        Database::pdo()->prepare('DELETE FROM words WHERE id = ?')->execute([$word['id']]);
        Response::ok('已删除');
    }

    /** 一键填充：用 DeepSeek 批量补齐单词信息（默认只补缺失中文释义的单词） */
    public static function autofill(array $params): void
    {
        $user = Auth::require();
        $wb = WordbookController::ownWordbook($params['id']);
        \App\Limits::assertCanAutofill($user);
        if (!\App\DeepSeekService::isEnabled()) {
            Response::error('未配置 DeepSeek API Key，请先在 config/.env 中填写 DEEPSEEK_API_KEY', 400);
        }

        $body = Request::body();
        $wordIds = (isset($body['word_ids']) && is_array($body['word_ids'])) ? $body['word_ids'] : null;

        $pdo = Database::pdo();
        if ($wordIds !== null && !empty($wordIds)) {
            $ids = [];
            foreach ($wordIds as $v) {
                $iv = (int) $v;
                if ($iv > 0) {
                    $ids[] = $iv;
                }
            }
            if (empty($ids)) {
                Response::error('无有效单词', 400);
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM words WHERE wordbook_id = ? AND id IN ({$placeholders})");
            $stmt->execute(array_merge([$wb['id']], $ids));
        } else {
            $stmt = $pdo->prepare("SELECT * FROM words WHERE wordbook_id = ? AND (meaning_zh IS NULL OR meaning_zh = '') ORDER BY id ASC");
            $stmt->execute([$wb['id']]);
        }
        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($words)) {
            Response::json(['filled' => 0, 'total' => 0, 'failed' => 0, 'message' => '没有需要填充的单词']);
        }
        \App\Limits::recordAutofill($user);

        $total = count($words);
        $filled = 0;
        $failed = 0;
        foreach (array_chunk($words, 20) as $chunk) {
            $list = [];
            foreach ($chunk as $w) {
                $list[] = $w['word'];
            }
            $r = \App\DeepSeekService::lookup($list);
            if ($r === null) {
                $failed += count($chunk);
                continue;
            }
            $map = [];
            foreach ($r as $d) {
                if (isset($d['word'])) {
                    $map[strtolower(trim((string) $d['word']))] = $d;
                }
            }
            foreach ($chunk as $w) {
                $key = strtolower(trim((string) $w['word']));
                if (!isset($map[$key])) {
                    $failed++;
                    continue;
                }
                self::mergeAutofill((int) $w['id'], $map[$key]);
                $filled++;
            }
        }
        Response::json(['filled' => $filled, 'total' => $total, 'failed' => $failed]);
    }

    /** 只填充空字段，不覆盖已有内容 */
    private static function mergeAutofill(int $id, array $d): void
    {
        Database::pdo()->prepare(
            "UPDATE words SET
                phonetic = IF(phonetic IS NULL OR phonetic = '', ?, phonetic),
                pos = IF(pos IS NULL OR pos = '', ?, pos),
                meaning_zh = IF(meaning_zh IS NULL OR meaning_zh = '', ?, meaning_zh),
                definition_en = IF(definition_en IS NULL OR definition_en = '', ?, definition_en),
                example = IF(example IS NULL OR example = '', ?, example),
                example_zh = IF(example_zh IS NULL OR example_zh = '', ?, example_zh)
             WHERE id = ?"
        )->execute([
            self::nv($d['phonetic'] ?? null),
            self::nv($d['pos'] ?? null),
            self::nv($d['meaning_zh'] ?? null),
            self::nv($d['definition_en'] ?? null),
            self::nv($d['example'] ?? null),
            self::nv($d['example_zh'] ?? null),
            $id,
        ]);
    }

    private static function nv($v)
    {
        if ($v === null || $v === '') {
            return null;
        }
        return (string) $v;
    }

    /** 校验单词属于当前用户（通过词库归属）并返回记录 */
    public static function ownWord(int $id): array
    {
        $user = Auth::require();
        $stmt = Database::pdo()->prepare(
            'SELECT w.* FROM words w JOIN wordbooks wb ON wb.id = w.wordbook_id WHERE w.id = ? AND wb.user_id = ?'
        );
        $stmt->execute([$id, $user['id']]);
        $w = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$w) {
            Response::error('单词不存在', 404, 404);
        }
        return $w;
    }

    private static function normalize(array $item): array
    {
        $out = [];
        foreach (self::FIELDS as $f) {
            $out[$f] = isset($item[$f]) ? trim((string) $item[$f]) : null;
        }
        return $out;
    }

    private static function insert(int $wordbookId, array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO words (wordbook_id, word, phonetic, audio_url, pos, meaning_zh, definition_en, example, example_zh, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $wordbookId,
            $data['word'],
            $data['phonetic'],
            $data['audio_url'],
            $data['pos'],
            $data['meaning_zh'],
            $data['definition_en'],
            $data['example'],
            $data['example_zh'],
            $data['note'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    private static function one(int $id): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM words WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
