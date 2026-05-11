<?php
namespace MediaParser\Parsers;

use MediaParser\Config;
use MediaParser\HttpClient;
use MediaParser\Logger;

final class ZhihuParser extends BaseParser
{
    private ?string $questionId = null;
    private ?string $answerId = null;
    private ?string $zvideoId = null;
    private ?string $pinId = null;
    private ?string $articleId = null;

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = [
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1',
            'referer' => 'https://www.zhihu.com/',
        ];
        $this->extractIds();
        $this->data = $this->fetchData();
    }

    private function extractIds(): void
    {
        if (preg_match('/question\/(\d+)\/answer\/(\d+)/', $this->realUrl, $m)) {
            $this->questionId = $m[1];
            $this->answerId = $m[2];
            return;
        }
        if (preg_match('/\/answer\/(\d+)/', $this->realUrl, $m)) {
            $this->answerId = $m[1];
        }
        if (preg_match('/\/zvideo\/(\d+)/', $this->realUrl, $m)) {
            $this->zvideoId = $m[1];
        }
        if (preg_match('/\/pin\/(\d+)/', $this->realUrl, $m)) {
            $this->pinId = $m[1];
        }
        if (preg_match('/(?:zhuanlan\.zhihu\.com\/p\/|\/article\/)(\d+)/', $this->realUrl, $m)) {
            $this->articleId = $m[1];
        }
    }

    private function fetchData(): array
    {
        $headers = ['User-Agent' => Config::randomUserAgentM(), 'Accept' => 'application/json, text/plain, */*'];
        $url = null;
        if ($this->answerId) {
            $url = 'https://api.zhihu.com/answers/' . $this->answerId;
        } elseif ($this->zvideoId) {
            $url = 'https://api.zhihu.com/videos/' . $this->zvideoId;
        } elseif ($this->pinId) {
            $url = 'https://api.zhihu.com/pins/' . $this->pinId;
        } elseif ($this->articleId) {
            $url = 'https://api.zhihu.com/articles/' . $this->articleId;
        }
        if (!$url) {
            return [];
        }
        try {
            $resp = HttpClient::get($url, $headers, [], 5);
            return $resp->statusCode === 200 ? $resp->json() : [];
        } catch (\Throwable $e) {
            Logger::warning('ZhihuParser fetch API error: ' . $e->getMessage());
            return [];
        }
    }

    private function getLensVideoUrl(?string $lensId): ?string
    {
        if (!$lensId) {
            return null;
        }
        try {
            $resp = HttpClient::get('https://lens.zhihu.com/api/v4/videos/' . $lensId, [
                'User-Agent' => Config::randomUserAgentM(),
                'Referer' => 'https://v.vzuu.com/',
                'Origin' => 'https://v.vzuu.com',
            ], [], 5);
            $data = $resp->json();
            foreach (['HD', 'SD', 'LD'] as $quality) {
                if (!empty($data['playlist'][$quality]['play_url'])) {
                    return $data['playlist'][$quality]['play_url'];
                }
            }
        } catch (\Throwable $e) {
            Logger::warning('ZhihuParser lens video error: ' . $e->getMessage());
        }
        return null;
    }

    public function getRealVideoUrl(): ?string
    {
        foreach (['HD', 'SD', 'LD'] as $quality) {
            if (!empty($this->data['playlist'][$quality]['play_url'])) {
                return $this->data['playlist'][$quality]['play_url'];
            }
        }
        $content = $this->data['content'] ?? '';
        if (is_string($content)) {
            if (preg_match('/data-lens-id=(["\'])(\d+)\1/', $content, $m)) {
                $url = $this->getLensVideoUrl($m[2]);
                if ($url) {
                    return $url;
                }
            }
            if (preg_match('/a\s+href=(["\'])([^"\']+\.mp4[^"\']*)\1/i', $content, $m)) {
                return $m[2];
            }
        }
        return null;
    }

    public function getTitleContent(): ?string
    {
        if (!empty($this->data['question']['title'])) {
            return trim($this->data['question']['title'] . "\n" . ($this->data['excerpt'] ?? ''));
        }
        if (!empty($this->data['title'])) {
            return trim($this->data['title'] . "\n" . ($this->data['excerpt'] ?? ''));
        }
        if (isset($this->data['content']) && is_array($this->data['content'])) {
            $parts = [];
            foreach ($this->data['content'] as $item) {
                if (($item['type'] ?? '') === 'text' && !empty($item['content'])) {
                    $parts[] = $this->cleanHtml($item['content']);
                }
            }
            return trim(implode("\n", $parts));
        }
        $content = $this->data['excerpt'] ?? ($this->data['content'] ?? '');
        return $this->textSlice($this->cleanHtml(is_string($content) ? $content : ''), 0, 100);
    }

    public function getCoverPhotoUrl(): ?string
    {
        if (!empty($this->data['thumbnail'])) {
            return $this->data['thumbnail'];
        }
        if (!empty($this->data['image_url'])) {
            return $this->data['image_url'];
        }
        $content = $this->data['content'] ?? '';
        if (is_string($content) && preg_match('/<img\b[^>]*\bsrc=(["\'])(.*?)\1/is', $content, $m) && str_starts_with($m[2], 'http')) {
            return html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return null;
    }

    public function getImageList(): array
    {
        $images = [];
        if (isset($this->data['content']) && is_array($this->data['content'])) {
            foreach ($this->data['content'] as $item) {
                if (($item['type'] ?? '') === 'image' && !empty($item['url'])) {
                    $images[] = $item['url'];
                }
            }
            if ($images) {
                return array_values(array_unique($images));
            }
        }
        $content = $this->data['content'] ?? '';
        if (is_string($content) && preg_match_all('/<img\b[^>]*>/is', $content, $tags)) {
            foreach ($tags[0] as $tag) {
                $src = $this->htmlAttr($tag, 'data-original') ?: ($this->htmlAttr($tag, 'data-actualsrc') ?: $this->htmlAttr($tag, 'src'));
                if ($src && str_starts_with($src, 'http')) {
                    $images[] = str_replace(['_hd', '_hq'], '_r', $src);
                }
            }
        }
        return array_values(array_unique($images));
    }

    public function getAuthorInfo(): ?array
    {
        $author = $this->data['author'] ?? [];
        if (!is_array($author) || !$author) {
            return null;
        }
        return [
            'nickname' => $author['name'] ?? '',
            'author_id' => $author['id'] ?? '',
            'avatar' => $author['avatar_url'] ?? '',
        ];
    }
}
