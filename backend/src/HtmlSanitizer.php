<?php
declare(strict_types=1);

namespace App;

/**
 * 公告正文 HTML 白名单过滤（防 XSS）。
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS =
        '<p><br><b><strong><i><em><u><s><a><ul><ol><li><span><h1><h2><h3><h4><h5><blockquote><code><pre><hr><div><img>';

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        // 移除危险标签及其内容
        $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)[^>]*/?>#i', '', $html) ?? $html;
        // 移除事件属性 on*
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;
        // 仅保留白名单标签
        $html = strip_tags($html, self::ALLOWED_TAGS);
        // 移除 javascript: 伪协议
        $html = preg_replace('#(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\2#i', '$1="#"', $html) ?? $html;
        return $html;
    }

    /** 转纯文本（用于校验非空） */
    public static function toText(string $html): string
    {
        $s = self::clean($html);
        $s = preg_replace('#<br\s*/?>#i', "\n", $s) ?? $s;
        $s = preg_replace('#</(p|div|li|h1|h2|h3|h4|h5|blockquote)>#i', "\n", $s) ?? $s;
        return trim(strip_tags($s));
    }
}
