<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Request;
use App\Response;
use PDO;

class QuizController
{
    private const TYPES = ['choice', 'spelling', 'dictation', 'matching'];

    public static function generate(array $params): void
    {
        $user = Auth::require();
        $wbId = (int) Request::input('wordbook_id', 0);
        $type = (string) Request::input('type', 'choice');
        $count = (int) Request::input('count', 10);
        if ($count < 1) {
            $count = 10;
        }
        if ($count > 50) {
            $count = 50;
        }
        if (!in_array($type, self::TYPES, true)) {
            Response::error('未知题型', 400);
        }

        $wb = WordbookController::ownWordbook($wbId);

        $stmt = Database::pdo()->prepare('SELECT * FROM words WHERE wordbook_id = ?');
        $stmt->execute([$wb['id']]);
        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($words)) {
            Response::error('该词库还没有单词，请先添加', 400);
        }

        $questions = self::buildQuestions($type, $words, $count);
        if (empty($questions)) {
            Response::error('无法生成题目（可能单词数量不足或缺少释义）', 400);
        }

        Response::json([
            'wordbook_id' => (int) $wb['id'],
            'type'        => $type,
            'questions'   => $questions,
        ]);
    }

    public static function submit(array $params): void
    {
        $user = Auth::require();
        $wbId = (int) Request::input('wordbook_id', 0);
        $type = (string) Request::input('type', '');
        $total = (int) Request::input('total', 0);
        $correct = (int) Request::input('correct', 0);
        $detail = Request::input('detail', null);

        if (!in_array($type, self::TYPES, true)) {
            Response::error('未知题型', 400);
        }
        if ($total < 0 || $correct < 0 || $correct > $total) {
            Response::error('数据非法', 400);
        }
        WordbookController::ownWordbook($wbId);

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO quiz_records (user_id, wordbook_id, type, total, correct, detail) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], $wbId, $type, $total, $correct, json_encode($detail, JSON_UNESCAPED_UNICODE)]);
        $id = (int) $pdo->lastInsertId();
        $rate = $total > 0 ? round($correct / $total * 100, 1) : 0.0;

        Response::json(['id' => $id, 'correct_rate' => $rate]);
    }

    // ------------------------------------------------------------------

    private static function buildQuestions(string $type, array $words, int $count): array
    {
        switch ($type) {
            case 'choice':
                return self::buildChoice($words, $count);
            case 'spelling':
                return self::buildSpelling($words, $count);
            case 'dictation':
                return self::buildDictation($words, $count);
            case 'matching':
                return self::buildMatching($words, $count);
        }
        return [];
    }

    /** 返回同时具备“单词 + 释义”的条目 */
    private static function pool(array $words): array
    {
        $out = [];
        foreach ($words as $w) {
            $d = self::display($w);
            if ($d !== null) {
                $out[] = [
                    'word_id'   => (int) $w['id'],
                    'word'      => $w['word'],
                    'display'   => $d,
                    'audio_url' => $w['audio_url'],
                    'phonetic'  => $w['phonetic'],
                ];
            }
        }
        return $out;
    }

    private static function display(array $w)
    {
        if (!empty($w['meaning_zh'])) {
            return (string) $w['meaning_zh'];
        }
        if (!empty($w['definition_en'])) {
            return (string) $w['definition_en'];
        }
        return null;
    }

    private static function sample(array $items, int $n): array
    {
        shuffle($items);
        return array_slice($items, 0, $n);
    }

    private static function buildChoice(array $words, int $count): array
    {
        $pool = self::pool($words);
        $n = count($pool);
        if ($n < 2) {
            return [];
        }
        $qs = [];
        foreach (self::sample($pool, min($count, $n)) as $item) {
            $direction = random_int(0, 1) === 1 ? 'zh2en' : 'en2zh';
            if ($direction === 'en2zh') {
                $correct = $item['display'];
                $distractors = self::distractors($pool, (int) $item['word_id'], 'display');
                $prompt = $item['word'];
            } else {
                $correct = $item['word'];
                $distractors = self::distractors($pool, (int) $item['word_id'], 'word');
                $prompt = $item['display'];
            }
            $options = array_values(array_unique(array_merge([$correct], $distractors)));
            if (count($options) < 2) {
                continue;
            }
            $answerIndex = array_search($correct, $options, true);
            $keys = array_keys($options);
            shuffle($keys);
            $shuffled = [];
            $answerIndexNew = -1;
            foreach ($keys as $i => $k) {
                $shuffled[] = $options[$k];
                if ($k === $answerIndex) {
                    $answerIndexNew = $i;
                }
            }
            $qs[] = [
                'type'         => 'choice',
                'word_id'      => (int) $item['word_id'],
                'direction'    => $direction,
                'prompt'       => $prompt,
                'options'      => $shuffled,
                'answer_index' => $answerIndexNew,
                'audio_url'    => $item['audio_url'],
            ];
        }
        return $qs;
    }

    private static function buildSpelling(array $words, int $count): array
    {
        $pool = self::pool($words);
        if (empty($pool)) {
            return [];
        }
        $qs = [];
        foreach (self::sample($pool, min($count, count($pool))) as $item) {
            $qs[] = [
                'type'      => 'spelling',
                'word_id'   => (int) $item['word_id'],
                'prompt'    => $item['display'],
                'answer'    => $item['word'],
                'phonetic'  => $item['phonetic'],
                'audio_url' => null,
            ];
        }
        return $qs;
    }

    private static function buildDictation(array $words, int $count): array
    {
        $qs = [];
        foreach (self::sample($words, min($count, count($words))) as $w) {
            $qs[] = [
                'type'      => 'dictation',
                'word_id'   => (int) $w['id'],
                'audio_url' => $w['audio_url'],
                'phonetic'  => $w['phonetic'],
                'answer'    => $w['word'],
            ];
        }
        return $qs;
    }

    private static function buildMatching(array $words, int $count): array
    {
        $pool = self::pool($words);
        $n = min($count, count($pool));
        if ($n < 2) {
            return [];
        }
        $pairs = [];
        foreach (self::sample($pool, $n) as $item) {
            $pairs[] = [
                'word_id' => (int) $item['word_id'],
                'left'    => $item['word'],
                'right'   => $item['display'],
            ];
        }
        return [['type' => 'matching', 'pairs' => $pairs]];
    }

    private static function distractors(array $pool, int $excludeId, string $field): array
    {
        $vals = [];
        foreach ($pool as $p) {
            if ((int) $p['word_id'] === $excludeId) {
                continue;
            }
            $v = (string) $p[$field];
            if ($v !== '' && !in_array($v, $vals, true)) {
                $vals[] = $v;
            }
            if (count($vals) >= 3) {
                break;
            }
        }
        return $vals;
    }
}
