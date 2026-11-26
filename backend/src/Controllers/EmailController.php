<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Mailer;
use App\Request;
use App\Response;
use App\SiteSettings;
use PDO;

class EmailController
{
    /** POST /auth/send-email-code —— 给当前用户指定邮箱发送验证码 */
    public static function sendCode(array $params): void
    {
        Auth::require();
        $email = strtolower(trim((string) Request::input('email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('邮箱格式不正确', 400);
        }
        $pdo = Database::pdo();
        $interval = max(5, (int) SiteSettings::get('email_send_interval_seconds'));

        // 同邮箱发送间隔
        $stmt = $pdo->prepare('SELECT created_at FROM email_codes WHERE email = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$email]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($last && time() - strtotime((string) $last['created_at']) < $interval) {
            Response::error('发送过于频繁，请稍后再试', 429, 429);
        }
        // 同 IP 频率
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM email_codes WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)');
        $stmt->execute([$ip, $interval]);
        if ((int) $stmt->fetchColumn() >= 5) {
            Response::error('发送过于频繁，请稍后再试', 429, 429);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $validMin = max(1, (int) SiteSettings::get('email_code_valid_minutes'));
        $pdo->prepare(
            'INSERT INTO email_codes (email, code_hash, purpose, expires_at, ip)
             VALUES (?, ?, "verify", DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)'
        )->execute([$email, hash('sha256', $code), $validMin, $ip]);

        try {
            Mailer::send($email, '【LinguaLux】邮箱验证码', "你的验证码是：{$code}，{$validMin} 分钟内有效。若非本人操作请忽略。");
        } catch (\Throwable $e) {
            Response::error('邮件发送失败：' . $e->getMessage(), 500, 500);
        }
        Response::ok('验证码已发送，请查收邮箱');
    }

    /** POST /auth/verify-email-code —— 校验验证码并将邮箱绑定到当前用户 */
    public static function verifyCode(array $params): void
    {
        $user = Auth::require();
        $email = strtolower(trim((string) Request::input('email', '')));
        $code = trim((string) Request::input('code', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $code === '') {
            Response::error('参数不正确', 400);
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM email_codes WHERE email = ? AND purpose = "verify" ORDER BY id DESC LIMIT 1');
        $stmt->execute([$email]);
        $rec = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rec || strtotime((string) $rec['expires_at']) < time()) {
            Response::error('验证码已过期，请重新获取', 400);
        }
        if ((int) $rec['attempts'] >= 5) {
            Response::error('尝试次数过多，请重新获取', 400);
        }
        if (!hash_equals((string) $rec['code_hash'], hash('sha256', $code))) {
            $pdo->prepare('UPDATE email_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$rec['id']]);
            Response::error('验证码不正确', 400);
        }
        $pdo->prepare('DELETE FROM email_codes WHERE email = ? AND purpose = "verify"')->execute([$email]);
        $pdo->prepare('UPDATE users SET email = ?, email_verified = 1 WHERE id = ?')->execute([$email, $user['id']]);
        Response::ok('邮箱验证成功');
    }

    /** POST /admin/email/test —— 管理员发送测试邮件验证 SMTP 配置 */
    public static function test(array $params): void
    {
        Auth::admin();
        $email = strtolower(trim((string) Request::input('email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('邮箱格式不正确', 400);
        }
        try {
            Mailer::send($email, '【LinguaLux】测试邮件', '这是一封来自 LinguaLux 的测试邮件，收到即说明 SMTP 配置正确。');
        } catch (\Throwable $e) {
            Response::error('发送失败：' . $e->getMessage(), 500, 500);
        }
        Response::ok('测试邮件已发送');
    }
}
