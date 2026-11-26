<?php
declare(strict_types=1);

namespace App;

class Request
{
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === false || $path === null || $path === '') {
            $path = '/';
        }
        return $path;
    }

    /** 去掉 /api 前缀后的路由路径 */
    public static function apiPath(): string
    {
        $path = self::path();
        if (strpos($path, '/api') === 0) {
            $path = substr($path, 4);
        }
        if ($path === '' || $path === false) {
            $path = '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    public static function query($key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public static function header($name, $default = null)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? $default;
    }

    public static function bearerToken()
    {
        $auth = (string) self::header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
            return $m[1];
        }
        return null;
    }

    public static function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function input($key, $default = null)
    {
        static $body = null;
        if ($body === null) {
            $body = self::body();
        }
        return $body[$key] ?? $default;
    }

    public static function bool($key, $default = false)
    {
        $v = self::input($key, $default);
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return $v != 0;
        }
        if (is_string($v)) {
            return in_array(strtolower($v), ['1', 'true', 'on', 'yes'], true);
        }
        return (bool) $v;
    }
}
