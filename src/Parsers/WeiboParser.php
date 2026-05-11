<?php
namespace MediaParser\Parsers;

use MediaParser\Config;
use MediaParser\HttpClient;
use MediaParser\Logger;

final class WeiboParser extends BaseParser
{
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private ?string $numericId = null;
    private array $postData = [];

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = ['User-Agent' => Config::randomUserAgentPc(), 'referer' => 'https://weibo.com/'];
        $this->numericId = $this->extractId();
        $this->postData = $this->fetchPostData();
    }

    private static function base62Decode(string $s): int
    {
        $res = 0;
        foreach (str_split($s) as $ch) {
            $idx = strpos(self::ALPHABET, $ch);
            if ($idx === false) {
                $idx = 0;
            }
            $res = $res * 62 + $idx;
        }
        return $res;
    }

    private static function midToId(string $mid): string
    {
        $mid = strrev($mid);
        $size = (int)ceil(strlen($mid) / 4);
        $parts = [];
        for ($i = 0; $i < $size; $i++) {
            $part = strrev(substr($mid, $i * 4, 4));
            $decoded = (string)self::base62Decode($part);
            if ($i !== $size - 1) {
                $decoded = str_pad($decoded, 7, '0', STR_PAD_LEFT);
            }
            $parts[] = $decoded;
        }
        return ltrim(implode('', array_reverse($parts)), '0') ?: '0';
    }

    private function extractId(): ?string
    {
        if (preg_match('/weibo\.com\/\d+\/([a-zA-Z0-9]+)/', $this->realUrl, $m)) {
            return self::midToId($m[1]);
        }
        if (preg_match('/weibo\.cn\/(?:status\/|detail\/|statuses\/show\?id=)(\d+)/', $this->realUrl, $m)) {
            return $m[1];
        }
        if (preg_match('/[?&]id=(\d+)/', $this->realUrl, $m)) {
            return $m[1];
        }
        if (preg_match('/[?&]id=([a-zA-Z0-9]+)/', $this->realUrl, $m)) {
            return self::midToId($m[1]);
        }
        if (preg_match('/\/([a-zA-Z0-9]{9})\b/', $this->realUrl, $m)) {
            return self::midToId($m[1]);
        }
        return null;
    }

    private function fetchPostData(): array
    {
        if (!$this->numericId) {
            return [];
        }
        $headers = [
            'User-Agent' => Config::randomUserAgentM(),
            'Accept' => 'application/json, text/plain, */*',
            'MWeibo-Pwa' => '1',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer' => 'https://m.weibo.cn/detail/' . $this->numericId,
        ];
        try {
            $resp = HttpClient::get('https://m.weibo.cn/statuses/show', $headers, ['id' => $this->numericId], 10);
            $data = $resp->json();
            if (($data['ok'] ?? null) == 1) {
                return $data['data'] ?? [];
            }
        } catch (\Throwable $e) {
            Logger::warning('Weibo API fetch failed: ' . $e->getMessage());
        }
        return $this->fallbackFetchAjax();
    }

    private function fallbackFetchAjax(): array
    {
        try {
            $resp = HttpClient::get('https://m.weibo.cn/detail/' . $this->numericId, [
                'User-Agent' => Config::randomUserAgentM(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;',
            ], [], 10);
            if (preg_match('/\$render_data\s*=\s*\[(.*?)\]\[0\]\s*\|\|/s', $resp->body, $m)) {
                $data = json_decode($m[1], true);
                if (is_array($data)) {
                    return $data['status'] ?? [];
                }
            }
        } catch (\Throwable $e) {
            Logger::warning('Weibo chunk fallback fetch failed: ' . $e->getMessage());
        }

        try {
            $resp = HttpClient::get('https://weibo.com/ajax/statuses/show', [
                'User-Agent' => Config::randomUserAgentPc(),
                'Referer' => 'https://weibo.com/',
            ], ['id' => $this->numericId], 10);
            return $resp->json();
        } catch (\Throwable $e) {
            Logger::error('Weibo PC Ajax fetch failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getRealVideoUrl(): ?string
    {
        $media = $this->arr($this->postData, ['page_info', 'media_info'], []);
        $url = $media['mp4_hd_url'] ?? ($media['mp4_sd_url'] ?? ($media['stream_url_hd'] ?? ($media['stream_url'] ?? null)));
        if ($url) {
            return $url;
        }
        foreach (($media['playback_list'] ?? []) as $pb) {
            if (!empty($pb['play_info']['url'])) {
                return $pb['play_info']['url'];
            }
        }
        return null;
    }

    public function getTitleContent(): ?string
    {
        return $this->cleanHtml($this->postData['text_raw'] ?? ($this->postData['text'] ?? ''));
    }

    public function getCoverPhotoUrl(): ?string
    {
        return $this->arr($this->postData, ['page_info', 'page_pic', 'url']);
    }

    public function getImageList(): array
    {
        $out = [];
        foreach (($this->postData['pics'] ?? []) as $pic) {
            if (!empty($pic['large']['url'])) {
                $out[] = $pic['large']['url'];
            }
        }
        return $out;
    }

    public function getAuthorInfo(): ?array
    {
        $user = $this->postData['user'] ?? [];
        if (!$user) {
            return null;
        }
        return [
            'nickname' => $user['screen_name'] ?? '',
            'author_id' => (string)($user['id'] ?? ''),
            'avatar' => $user['avatar_hd'] ?? ($user['profile_image_url'] ?? ''),
        ];
    }
}

