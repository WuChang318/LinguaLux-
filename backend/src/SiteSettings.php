<?php
declare(strict_types=1);

namespace App;

/**
 * 站点设置服务：注册表（config/settings.php）+ site_settings 表覆盖。
 * 未写入数据库的键回退注册表默认值；类型在读取时转换。
 */
class SiteSettings
{
    private static $registry = null;
    private static $cache = [];

    public static function registry(): array
    {
        if (self::$registry === null) {
            $file = dirname(__DIR__) . '/config/settings.php';
            self::$registry = is_file($file) ? (require $file) : [];
        }
        return self::$registry;
    }

    private static function def(string $key): ?array
    {
        foreach (self::registry() as $group => $items) {
            if (isset($items[$key])) {
                $items[$key]['group'] = $group;
                return $items[$key];
            }
        }
        return null;
    }

    public static function get(string $key)
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $def = self::def($key);
        $default = $def['default'] ?? null;
        $type = $def['type'] ?? 'text';
        try {
            $stmt = Database::pdo()->prepare('SELECT `value` FROM site_settings WHERE `key` = ?');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
        } catch (\Throwable $e) {
            // site_settings 表尚未创建时回退默认值
            self::$cache[$key] = $default;
            return $default;
        }
        $raw = ($row && $row['value'] !== null) ? $row['value'] : null;
        $val = self::cast($raw, $type, $default);
        self::$cache[$key] = $val;
        return $val;
    }

    public static function set(string $key, $value): void
    {
        $def = self::def($key);
        if ($def === null) {
            return;
        }
        $type = $def['type'] ?? 'text';
        $str = self::stringify($value, $type);
        Database::pdo()->prepare(
            'INSERT INTO site_settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        )->execute([$key, $str]);
        self::$cache[$key] = self::cast($str, $type, $def['default'] ?? null);
    }

    /** 后台用：分组 + 完整定义 + 当前值 */
    public static function all(): array
    {
        $out = [];
        foreach (self::registry() as $group => $items) {
            foreach ($items as $key => $def) {
                $out[$group][$key] = [
                    'value'   => self::get($key),
                    'label'   => $def['label'] ?? $key,
                    'type'    => $def['type'] ?? 'text',
                    'options' => $def['options'] ?? null,
                    'default' => $def['default'] ?? null,
                    'desc'    => $def['desc'] ?? '',
                ];
            }
        }
        return $out;
    }

    /** 公开配置：拍平为 key => value（本注册表均为公开项） */
    public static function publicSettings(): array
    {
        $out = [];
        foreach (self::registry() as $group => $items) {
            foreach ($items as $key => $def) {
                $out[$key] = self::get($key);
            }
        }
        return $out;
    }

    private static function cast($raw, string $type, $default)
    {
        if ($raw === null) {
            return $default;
        }
        switch ($type) {
            case 'bool':
                return in_array($raw, ['1', 'true', 'on', 1, true], true);
            case 'int':
                return (int) $raw;
            default:
                return $raw;
        }
    }

    private static function stringify($value, string $type): string
    {
        if ($type === 'bool') {
            return $value ? '1' : '0';
        }
        if ($value === null) {
            return '';
        }
        return (string) $value;
    }
}
