<?php
declare(strict_types=1);

namespace App;

/**
 * 极简配置加载：优先读环境变量，其次读 config/.env 文件。
 */
class Config
{
    private static $env = null;

    public static function load($dir): void
    {
        if (self::$env !== null) {
            return;
        }
        self::$env = [];
        $file = rtrim((string) $dir, '/\\') . DIRECTORY_SEPARATOR . '.env';
        if (is_file($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                $k = trim(substr($line, 0, $pos));
                $v = trim(substr($line, $pos + 1));
                if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
                    $v = substr($v, 1, -1);
                }
                self::$env[$k] = $v;
            }
        }
    }

    public static function get($key, $default = null)
    {
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return $v;
        }
        if (self::$env !== null && isset(self::$env[$key])) {
            return self::$env[$key];
        }
        return $default;
    }
}
