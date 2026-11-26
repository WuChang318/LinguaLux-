<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\HtmlSanitizer;
use App\Response;
use App\SiteSettings;
use PDO;

class HomeController
{
    /** GET /home —— 首页聚合：公告 + 捐款渠道 + 入口按钮 */
    public static function index(array $params): void
    {
        $pdo = Database::pdo();

        $announcements = [];
        if ((bool) SiteSettings::get('home_announcement_enabled')) {
            $stmt = $pdo->query(
                'SELECT id, title, content, is_pinned, created_at
                 FROM announcements WHERE is_active = 1
                 ORDER BY is_pinned DESC, sort_order ASC, id DESC LIMIT 10'
            );
            $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($announcements as &$a) {
                $a['content'] = HtmlSanitizer::clean((string) $a['content']);
                $a['is_pinned'] = (int) $a['is_pinned'];
            }
            unset($a);
        }

        $donations = [];
        if ((bool) SiteSettings::get('home_donation_enabled')) {
            $stmt = $pdo->query(
                'SELECT id, name, description, image_url
                 FROM donation_channels WHERE is_active = 1
                 ORDER BY sort_order ASC, id DESC'
            );
            $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $buttons = [];
        $raw = SiteSettings::get('home_entry_buttons');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $b) {
                    if (is_array($b) && isset($b['label'], $b['route'])) {
                        $buttons[] = ['label' => (string) $b['label'], 'route' => (string) $b['route']];
                    }
                }
            }
        }

        Response::json([
            'announcements_enabled' => (bool) SiteSettings::get('home_announcement_enabled'),
            'donation_enabled'      => (bool) SiteSettings::get('home_donation_enabled'),
            'donation_intro'        => (string) SiteSettings::get('donation_intro'),
            'announcements'         => $announcements,
            'donations'             => $donations,
            'entry_buttons'         => $buttons,
        ]);
    }
}
