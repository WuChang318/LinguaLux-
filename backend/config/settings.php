<?php
// ============================================================
// 站点设置注册表：后台「站点设置」页据此渲染表单
// 分组 → 键 → { label, type, default, options?, desc? }
// type: text | int | bool | select
// ============================================================

return [
    'limits' => [
        'user_max_wordbooks' => [
            'label'   => '每位用户词本数量上限',
            'type'    => 'int',
            'default' => 50,
            'desc'    => '用户自建 + 从广场复制的词本总数达到该值后不能新建（0 表示不限制）',
        ],
        'user_weekly_autofill_limit' => [
            'label'   => '每位用户一键填充周期上限',
            'type'    => 'int',
            'default' => 20,
            'desc'    => '0 表示不限制',
        ],
        'autofill_reset_cycle' => [
            'label'   => '填充额度重置周期',
            'type'    => 'select',
            'options' => ['weekly' => '每周', 'daily' => '每天', 'monthly' => '每月'],
            'default' => 'weekly',
        ],
    ],
    'session' => [
        'session_idle_timeout_minutes' => [
            'label'   => '会话空闲自动退出（分钟）',
            'type'    => 'int',
            'default' => 30,
            'desc'    => '用户超过该分钟数无任何操作即自动退出（0 表示不启用）',
        ],
    ],
    'email' => [
        'email_code_valid_minutes' => [
            'label'   => '验证码有效期（分钟）',
            'type'    => 'int',
            'default' => 5,
        ],
        'email_send_interval_seconds' => [
            'label'   => '同一邮箱发送间隔（秒）',
            'type'    => 'int',
            'default' => 60,
        ],
    ],
    'home' => [
        'home_announcement_enabled' => [
            'label'   => '首页显示公告栏',
            'type'    => 'bool',
            'default' => true,
        ],
        'home_donation_enabled' => [
            'label'   => '首页显示捐款渠道',
            'type'    => 'bool',
            'default' => true,
        ],
        'home_entry_buttons' => [
            'label'   => '首页入口按钮（JSON 数组）',
            'type'    => 'text',
            'default' => '[{"label":"我的词库","route":"#/wordbooks"},{"label":"词库广场","route":"#/market"},{"label":"开始抽考","route":"#/quiz"}]',
            'desc'    => '形如 [{"label":"我的词库","route":"#/wordbooks"}]，route 为站内哈希路由',
        ],
    ],
    'donation' => [
        'donation_intro' => [
            'label'   => '捐款说明文案',
            'type'    => 'text',
            'default' => '如果你觉得 LinguaLux 对你有帮助，欢迎请开发者喝杯咖啡～',
        ],
    ],
];
