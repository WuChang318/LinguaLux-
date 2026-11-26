<?php
declare(strict_types=1);

namespace App;

/**
 * DeepSeek 聊天模型驱动的单词信息生成。
 * 为把 token 消耗降到最低：
 *   - 批量：一次请求传入多个单词，系统提示词只付一次（且命中缓存更便宜）
 *   - 极短系统提示词 + temperature=0 + json_object 模式
 *   - max_tokens 按单词数封顶，防止失控
 */
class DeepSeekService
{
    public static function isEnabled(): bool
    {
        return Config::get('DEEPSEEK_API_KEY', '') !== '';
    }

    /**
     * 批量生成单词信息。
     * @param string[] $words 单词列表（非空、去重）
     * @return array|null 成功返回 [ ['word'=>'apple','phonetic'=>...,'pos'=>...,'meaning_zh'=>...,'definition_en'=>...,'example'=>...,'example_zh'=>...], ... ]；失败返回 null
     */
    public static function lookup(array $words)
    {
        $words = array_values(array_unique(array_filter(array_map('trim', $words))));
        if (empty($words)) {
            return null;
        }

        $key = (string) Config::get('DEEPSEEK_API_KEY', '');
        $model = (string) Config::get('DEEPSEEK_MODEL', 'deepseek-chat');
        $base = rtrim((string) Config::get('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'), '/');

        $system = '你是英语词典助手。对给定单词数组输出 JSON：{"words":[{"word":"原词","phonetic":"音标","pos":"词性","meaning_zh":"简体中文释义","definition_en":"简洁英文释义","example":"简短例句","example_zh":"例句中文翻译"}]}。缺失项填 null。只输出 JSON，不要解释。';

        $payload = [
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($words, JSON_UNESCAPED_UNICODE)],
            ],
            'temperature'       => 0,
            'max_tokens'        => max(400, min(4000, 200 * count($words))),
            'stream'            => false,
            'response_format'   => ['type' => 'json_object'],
        ];

        $json = self::post($base . '/chat/completions', $payload, $key);
        if ($json === null) {
            return null;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if ($content === null) {
            return null;
        }

        $content = self::stripCodeFence((string) $content);
        $parsed = json_decode($content, true);
        if (!is_array($parsed) || !isset($parsed['words']) || !is_array($parsed['words'])) {
            return null;
        }

        $result = [];
        foreach ($parsed['words'] as $w) {
            if (!is_array($w) || empty($w['word'])) {
                continue;
            }
            $result[] = [
                'word'          => trim((string) $w['word']),
                'phonetic'      => isset($w['phonetic']) ? (string) $w['phonetic'] : null,
                'audio_url'     => null,
                'pos'           => isset($w['pos']) ? (string) $w['pos'] : null,
                'meaning_zh'    => isset($w['meaning_zh']) ? (string) $w['meaning_zh'] : null,
                'definition_en' => isset($w['definition_en']) ? (string) $w['definition_en'] : null,
                'example'       => isset($w['example']) ? (string) $w['example'] : null,
                'example_zh'    => isset($w['example_zh']) ? (string) $w['example_zh'] : null,
            ];
        }
        return $result;
    }

    private static function stripCodeFence(string $s): string
    {
        $s = trim($s);
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $s, $m)) {
            return $m[1];
        }
        return $s;
    }

    private static function post(string $url, array $payload, string $key)
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key,
                ],
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            return $res === false ? null : $res;
        }

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$key}\r\n",
                'content' => $body,
                'timeout' => 60,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return $res === false ? null : $res;
    }
}
