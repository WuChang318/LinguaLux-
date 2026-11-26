<?php
declare(strict_types=1);

// 自动加载 App\ 命名空间 -> src/ 目录
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) === 0) {
        $relative = substr($class, strlen($prefix));
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use App\Config;
use App\Response;

// 加载配置
Config::load(dirname(__DIR__) . '/config');

// 异常统一转为 JSON 响应
set_exception_handler(function ($e) {
    Response::error('服务器错误：' . $e->getMessage(), 500, 500);
});

// CORS（本地开发跨域用；生产同源时无影响）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
