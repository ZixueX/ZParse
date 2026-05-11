<?php
namespace MediaParser\Parsers;

use MediaParser\Config;
use MediaParser\HttpClient;
use MediaParser\Logger;

final class TiktokParser extends BaseParser
{
    private ?string $videoId = null;
    private array $postData = [];

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = ['User-Agent' => Config::randomUserAgentPc()];
        $this->videoId = $this->extractId();
        $this->postData = $this->fetchPostData();
    }

    private function extractId(): ?string
    {
        if (preg_match('/\/video\/(\d+)/', $this->realUrl, $m)) {
            return $m[1];
        }
        if (preg_match('/\/v\/(\d+)/', $this->realUrl, $m)) {
            return $m[1];
        }
        return null;
    }

    private function fetchPostData(): array
    {
        if (!$this->videoId) {
            return [];
        }
        try {
            $resp = HttpClient::get('https://www.tikwm.com/api/', $this->headers, [
                'url' => $this->realUrl,
                'count' => 12,
                'cursor' => 0,
                'web' => 1,
                'hd' => 1,
            ], 10);
            $data = $resp->json();
            return (($data['code'] ?? null) === 0) ? ($data['data'] ?? []) : [];
        } catch (\Throwable $e) {
            Logger::warning('Tiktok API fetch failed: ' . $e->getMessage());
            return [];
        }
    }

    private function tikwmUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        return str_starts_with($url, '/') ? 'https://www.tikwm.com' . $url : $url;
    }

    public function getRealVideoUrl(): ?string
    {
        return $this->tikwmUrl($this->postData['hdplay'] ?? ($this->postData['play'] ?? null));
    }

    public function getTitleContent(): ?string
    {
        return $this->postData['title'] ?? '';
    }

    public function getCoverPhotoUrl(): ?string
    {
        return $this->tikwmUrl($this->postData['cover'] ?? null);
    }

    public function getImageList(): array
    {
        $images = $this->postData['images'] ?? [];
        return is_array($images) ? $images : [];
    }

    public function getAudioUrl(): ?string
    {
        $music = $this->postData['music'] ?? null;
        if (!$music) {
            $music = $this->arr($this->postData, ['music_info', 'play']) ?: ($this->postData['play'] ?? null);
        }
        return $this->tikwmUrl($music);
    }

    public function getAuthorInfo(): ?array
    {
        $author = $this->postData['author'] ?? [];
        if (!$author) {
            return null;
        }
        return [
            'nickname' => $author['nickname'] ?? '',
            'author_id' => (string)($author['unique_id'] ?? ($author['id'] ?? '')),
            'avatar' => $this->tikwmUrl($author['avatar'] ?? null),
        ];
    }
}

