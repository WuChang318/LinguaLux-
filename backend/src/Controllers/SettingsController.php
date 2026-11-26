<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Request;
use App\Response;
use App\SiteSettings;

class SettingsController
{
    /** GET /admin/settings —— 分组 + 定义 + 当前值 */
    public static function adminIndex(array $params): void
    {
        Auth::admin();
        Response::json(SiteSettings::all());
    }

    /** PUT /admin/settings —— 批量保存 { key: value } */
    public static function adminUpdate(array $params): void
    {
        Auth::admin();
        $body = Request::body();
        $registry = SiteSettings::registry();
        $updated = 0;
        foreach ($registry as $group => $items) {
            foreach ($items as $key => $def) {
                if (!array_key_exists($key, $body)) {
                    continue;
                }
                $val = $body[$key];
                $type = $def['type'] ?? 'text';
                if ($type === 'int') {
                    $val = (int) $val;
                    if (isset($def['min']) && $val < $def['min']) {
                        $val = (int) $def['min'];
                    }
                    if (isset($def['max']) && $val > $def['max']) {
                        $val = (int) $def['max'];
                    }
                } elseif ($type === 'bool') {
                    $val = (bool) $val;
                }
                SiteSettings::set($key, $val);
                $updated++;
            }
        }
        Response::json(['updated' => $updated, 'settings' => SiteSettings::all()]);
    }

    /** GET /settings/public —— 公开配置（首页等使用） */
    public static function publicIndex(array $params): void
    {
        Response::json(SiteSettings::publicSettings());
    }
}
