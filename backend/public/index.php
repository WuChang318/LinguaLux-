<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Router;
use App\Request;

$path = Request::path();

// ---------- API 路由 ----------
if (strpos($path, '/api') === 0) {
    $router = new Router();
    $register = require __DIR__ . '/../src/routes.php';
    $register($router);
    $router->dispatch();
}

// ---------- 静态资源 / SPA ----------
$full = realpath(__DIR__ . $path);
$publicRoot = realpath(__DIR__);
$isAsset = (bool) preg_match('/\.(js|css|png|jpe?g|gif|svg|ico|webp|woff2?|ttf|eot|map|webmanifest|txt)$/i', $path);

if ($isAsset && $full !== false && strpos($full, $publicRoot) === 0 && is_file($full)) {
    serveFile($full);
}

serveIndex();

// ---------- helpers ----------
function serveFile(string $file): void
{
    $mime = [
        'js' => 'application/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'map' => 'application/json',
        'txt' => 'text/plain; charset=utf-8',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

function serveIndex(): void
{
    $index = __DIR__ . '/index.html';
    header('Content-Type: text/html; charset=utf-8');
    if (is_file($index)) {
        readfile($index);
    } else {
        echo '<!doctype html><html lang="zh-CN"><meta charset="utf-8"><title>LinguaLux</title><body style="font-family:sans-serif;padding:40px">前端尚未构建。请在 frontend 目录运行 <code>pnpm build</code> 后将产物放入 public/。</body></html>';
    }
    exit;
}
