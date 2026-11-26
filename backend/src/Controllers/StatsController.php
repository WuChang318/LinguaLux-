<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;
use PDO;

class StatsController
{
    public static function index(array $params): void
    {
        $user = Auth::require();
        $pdo = Database::pdo();

        $byType = [];
        foreach (['choice', 'spelling', 'dictation', 'matching'] as $t) {
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(total),0) AS total, COALESCE(SUM(correct),0) AS correct, COUNT(*) AS times
                 FROM quiz_records WHERE user_id = ? AND type = ?'
            );
            $stmt->execute([$user['id'], $t]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $byType[$t] = [
                'total'   => (int) $r['total'],
                'correct' => (int) $r['correct'],
                'times'   => (int) $r['times'],
            ];
        }

        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(total),0) AS total, COALESCE(SUM(correct),0) AS correct, COUNT(*) AS times
             FROM quiz_records WHERE user_id = ?'
        );
        $stmt->execute([$user['id']]);
        $o = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            'SELECT qr.*, wb.name AS wordbook_name
             FROM quiz_records qr LEFT JOIN wordbooks wb ON wb.id = qr.wordbook_id
             WHERE qr.user_id = ? ORDER BY qr.id DESC LIMIT 20'
        );
        $stmt->execute([$user['id']]);
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json([
            'total_questions' => (int) $o['total'],
            'total_correct'   => (int) $o['correct'],
            'total_quizzes'   => (int) $o['times'],
            'by_type'         => $byType,
            'recent'          => $recent,
        ]);
    }
}
