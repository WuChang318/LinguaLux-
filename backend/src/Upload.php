<?php
declare(strict_types=1);

namespace App;

/**
 * 图片上传：接收 base64（data URL 或纯 base64），保存到 public/uploads，返回相对 URL。
 */
class Upload
{
    private const MAX_BYTES = 2 * 1024 * 1024; // 2MB

    public static function saveBase64Image(string $data): string
    {
        if (preg_match('#^data:image/([a-zA-Z0-9.+]+);base64,(.+)$#s', $data, $m)) {
            $ext = strtolower($m[1]);
            $bin = base64_decode($m[2], true);
        } else {
            $ext = 'png';
            $bin = base64_decode($data, true);
        }
        if ($bin === false || $bin === '') {
            Response::error('图片数据无效', 400);
        }
        if (strlen($bin) > self::MAX_BYTES) {
            Response::error('图片不能超过 2MB', 400);
        }
        $extMap = ['png' => 'png', 'jpg' => 'jpg', 'jpeg' => 'jpg', 'gif' => 'gif', 'webp' => 'webp'];
        $ext = $extMap[$ext] ?? 'png';

        $dir = dirname(__DIR__) . '/public/uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $name = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (@file_put_contents($dir . '/' . $name, $bin) === false) {
            Response::error('图片保存失败，请检查 public/uploads 目录写入权限', 500, 500);
        }
        return '/uploads/' . $name;
    }
}
