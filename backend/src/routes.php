<?php
declare(strict_types=1);

use App\Router;
use App\Controllers\AuthController;
use App\Controllers\WordbookController;
use App\Controllers\WordController;
use App\Controllers\DictController;
use App\Controllers\QuizController;
use App\Controllers\StatsController;
use App\Controllers\MarketController;
use App\Controllers\AdminController;

return function (Router $r): void {
    // 认证
    $r->post('/auth/register', [AuthController::class, 'register']);
    $r->post('/auth/login', [AuthController::class, 'login']);
    $r->post('/auth/logout', [AuthController::class, 'logout']);
    $r->get('/auth/me', [AuthController::class, 'me']);

    // 词库
    $r->get('/wordbooks', [WordbookController::class, 'index']);
    $r->post('/wordbooks', [WordbookController::class, 'create']);
    $r->put('/wordbooks/{id}', [WordbookController::class, 'update']);
    $r->delete('/wordbooks/{id}', [WordbookController::class, 'destroy']);
    $r->post('/wordbooks/{id}/publish', [WordbookController::class, 'publish']);
    $r->post('/wordbooks/{id}/unpublish', [WordbookController::class, 'unpublish']);

    // 单词
    $r->get('/wordbooks/{id}/words', [WordController::class, 'index']);
    $r->post('/wordbooks/{id}/words', [WordController::class, 'create']);
    $r->post('/wordbooks/{id}/words/import', [WordController::class, 'import']);
    $r->post('/wordbooks/{id}/autofill', [WordController::class, 'autofill']);
    $r->put('/words/{id}', [WordController::class, 'update']);
    $r->delete('/words/{id}', [WordController::class, 'destroy']);

    // 自动填充（翻译/词典聚合）
    $r->get('/dict/{word}', [DictController::class, 'lookup']);

    // 抽考
    $r->post('/quiz/generate', [QuizController::class, 'generate']);
    $r->post('/quiz/submit', [QuizController::class, 'submit']);
    $r->get('/stats', [StatsController::class, 'index']);

    // 广场
    $r->get('/market', [MarketController::class, 'index']);
    $r->get('/market/{id}', [MarketController::class, 'show']);
    $r->post('/market/{id}/use', [MarketController::class, 'use']);
    $r->post('/market/{id}/report', [MarketController::class, 'report']);

    // 管理（举报处理 / 下架）
    $r->get('/admin/reports', [AdminController::class, 'reports']);
    $r->post('/admin/reports/{id}/handle', [AdminController::class, 'handle']);
};
