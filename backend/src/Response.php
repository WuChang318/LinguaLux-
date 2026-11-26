<?php
declare(strict_types=1);

namespace App;

class Response
{
    public static function json($data, int $http = 200): void
    {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 0, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $msg, int $code = 400, int $http = 400): void
    {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => $code, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function ok(string $msg = 'ok'): void
    {
        self::json(['msg' => $msg], 200);
    }
}
