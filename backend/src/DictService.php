<?php
declare(strict_types=1);

namespace App;

/**
 * 免费词典 / 翻译聚合：
 *  - Free Dictionary API：音标、发音音频、英文释义、例句、词性
 *  - MyMemory：中文释义（en|zh-CN）
 * 结果写入 dict_cache 表缓存。
 */
class DictService
{
    private const CACHE_SECONDS = 30 * 86400; // 30 天

    public static function lookup(string $word): array
    {
        $word = trim($word);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare('SELECT data, updated_at FROM dict_cache WHERE word = ?');
        $stmt->execute([$word]);
        $row = $stmt->fetch();
        if ($row && (time() - strtotime($row['updated_at'])) < self::CACHE_SECONDS) {
            $d = json_decode($row['data'], true);
            if (is_array($d)) {
                return $d;
            }
        }

        $data = self::fetch($word);

        $pdo->prepare(
            'INSERT INTO dict_cache (word, data) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = NOW()'
        )->execute([$word, json_encode($data, JSON_UNESCAPED_UNICODE)]);

        return $data;
    }

    /** 优先 DeepSeek，失败或未配置时回退免费 API */
    private static function fetch(string $word): array
    {
        if (DeepSeekService::isEnabled()) {
            $r = DeepSeekService::lookup([$word]);
            if ($r !== null && isset($r[0])) {
                $data = $r[0];
                // DeepSeek 不提供发音音频，补一个免费音频
                $data['audio_url'] = self::fetchAudio($word);
                return $data;
            }
        }
        return self::fetchAll($word);
    }

    private static function fetchAudio(string $word)
    {
        $json = self::httpGet('https://api.dictionaryapi.dev/api/v2/entries/en/' . rawurlencode($word));
        if ($json === null) {
            return null;
        }
        $entries = json_decode($json, true);
        if (!is_array($entries) || !isset($entries[0]) || empty($entries[0]['phonetics'])) {
            return null;
        }
        foreach ($entries[0]['phonetics'] as $p) {
            if (!empty($p['audio'])) {
                return (string) $p['audio'];
            }
        }
        return null;
    }

    private static function fetchAll(string $word): array
    {
        $result = [
            'word'          => $word,
            'phonetic'      => null,
            'audio_url'     => null,
            'pos'           => null,
            'definition_en' => null,
            'example'       => null,
            'meaning_zh'    => null,
            'example_zh'    => null,
        ];
        self::mergeDictionary($word, $result);
        self::mergeTranslation($word, $result);
        return $result;
    }

    private static function mergeDictionary(string $word, array &$result): void
    {
        $json = self::httpGet('https://api.dictionaryapi.dev/api/v2/entries/en/' . rawurlencode($word));
        if ($json === null) {
            return;
        }
        $entries = json_decode($json, true);
        if (!is_array($entries) || !isset($entries[0])) {
            return;
        }
        $e = $entries[0];

        if ($result['phonetic'] === null && !empty($e['phonetic'])) {
            $result['phonetic'] = (string) $e['phonetic'];
        }
        if ($result['audio_url'] === null && !empty($e['phonetics']) && is_array($e['phonetics'])) {
            foreach ($e['phonetics'] as $p) {
                if (!empty($p['audio'])) {
                    $result['audio_url'] = (string) $p['audio'];
                    break;
                }
            }
        }
        if (!empty($e['meanings']) && is_array($e['meanings'])) {
            $first = $e['meanings'][0];
            if ($result['pos'] === null && !empty($first['partOfSpeech'])) {
                $result['pos'] = (string) $first['partOfSpeech'];
            }
            if (!empty($first['definitions'][0])) {
                $d = $first['definitions'][0];
                if ($result['definition_en'] === null && !empty($d['definition'])) {
                    $result['definition_en'] = (string) $d['definition'];
                }
                if ($result['example'] === null && !empty($d['example'])) {
                    $result['example'] = (string) $d['example'];
                }
            }
        }
    }

    private static function mergeTranslation(string $word, array &$result): void
    {
        $url = 'https://api.mymemory.translated.net/get?q=' . rawurlencode($word) . '&langpair=en|zh-CN';
        $json = self::httpGet($url);
        if ($json === null) {
            return;
        }
        $d = json_decode($json, true);
        if (!is_array($d)) {
            return;
        }
        if ($result['meaning_zh'] === null && !empty($d['responseData']['translatedText'])) {
            $result['meaning_zh'] = self::normalizeText((string) $d['responseData']['translatedText']);
        }
    }

    private static function normalizeText(string $s): string
    {
        $s = str_replace(['，', '。', '；'], [', ', '. ', '; '], $s);
        return trim($s);
    }

    private static function httpGet(string $url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'wordlearn/1.0',
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            return $res === false ? null : $res;
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => 5, 'header' => "User-Agent: wordlearn/1.0\r\n"],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return $res === false ? null : $res;
    }
}
