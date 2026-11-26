<?php
declare(strict_types=1);

namespace App;

/**
 * 纯 PHP SMTP 发信（无第三方依赖）。支持 ssl（如 QQ 465）与 tls(STARTTLS)。
 * 配置来自 config/.env：SMTP_HOST / SMTP_PORT / SMTP_ENCRYPTION / SMTP_USERNAME / SMTP_PASSWORD / SMTP_FROM_NAME
 */
class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $host       = (string) Config::get('SMTP_HOST', '');
        $port       = (int) Config::get('SMTP_PORT', 465);
        $encryption = strtolower((string) Config::get('SMTP_ENCRYPTION', 'ssl'));
        $username   = (string) Config::get('SMTP_USERNAME', '');
        $password   = (string) Config::get('SMTP_PASSWORD', '');
        $fromName   = (string) Config::get('SMTP_FROM_NAME', 'LinguaLux');

        if ($host === '' || $username === '' || $password === '') {
            throw new \RuntimeException('SMTP 未配置完整，请在 config/.env 填写 SMTP_HOST / SMTP_USERNAME / SMTP_PASSWORD');
        }

        $scheme = ($encryption === 'ssl') ? 'ssl' : 'tcp';
        $remote = $scheme . '://' . $host . ':' . $port;
        $errno  = 0;
        $errstr = '';
        $conn   = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
        if (!$conn) {
            throw new \RuntimeException("无法连接 SMTP 服务器 {$host}:{$port}：{$errstr} ({$errno})");
        }
        stream_set_timeout($conn, 15);
        $ehlo = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');

        try {
            self::expect($conn, 220);
            self::cmd($conn, 'EHLO ' . $ehlo, 250);
            if ($encryption === 'tls') {
                self::cmd($conn, 'STARTTLS', 220);
                if (!@stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS 加密协商失败');
                }
                self::cmd($conn, 'EHLO ' . $ehlo, 250);
            }
            self::cmd($conn, 'AUTH LOGIN', 334);
            self::cmd($conn, base64_encode($username), 334);
            self::cmd($conn, base64_encode($password), 235);
            self::cmd($conn, 'MAIL FROM:<' . $username . '>', 250);
            self::cmd($conn, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::cmd($conn, 'DATA', 354);

            $headers = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$username}>\r\n"
                . "To: <{$to}>\r\n"
                . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "Date: " . date('r') . "\r\n";
            fwrite($conn, $headers . "\r\n" . chunk_split(base64_encode($body)) . "\r\n.");
            self::expect($conn, 250);
            self::cmd($conn, 'QUIT', 221);
            fclose($conn);
            return true;
        } catch (\Throwable $e) {
            @fclose($conn);
            throw $e;
        }
    }

    private static function cmd($conn, string $cmd, $expected): void
    {
        fwrite($conn, $cmd . "\r\n");
        self::expect($conn, $expected);
    }

    private static function expect($conn, $expected): void
    {
        $code = self::readCode($conn);
        $codes = is_array($expected) ? $expected : [$expected];
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException("SMTP 响应异常：{$code}");
        }
    }

    private static function readCode($conn): int
    {
        while (($line = fgets($conn, 512)) !== false) {
            if (strlen($line) >= 4 && $line[3] === ' ') {
                return (int) substr($line, 0, 3);
            }
        }
        throw new \RuntimeException('SMTP 连接已断开');
    }
}
