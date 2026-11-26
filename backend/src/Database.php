<?php
declare(strict_types=1);

namespace App;

use PDO;

class Database
{
    private static $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $host = (string) Config::get('DB_HOST', '127.0.0.1');
            $port = (string) Config::get('DB_PORT', '3306');
            $name = (string) Config::get('DB_NAME', 'wordlearn');
            $user = (string) Config::get('DB_USER', 'root');
            $pass = (string) Config::get('DB_PASS', '');
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (\PDOException $e) {
                Response::error('数据库连接失败：' . $e->getMessage(), 500, 500);
            }
        }
        return self::$pdo;
    }
}
