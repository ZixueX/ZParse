<?php
namespace MediaParser\Parsers;

use MediaParser\Config;
use MediaParser\Logger;

final class AcfunParser extends BaseParser
{
    private ?string $videoId = null;
    private array $postData = [];

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = ['User-Agent' => Config::randomUserAgentPc()];
        $this->videoId = $this->extractVideoId();
        $this->postData = $this->fetchPostData();
    }

    private function extractVideoId(): ?string
    {
        return preg_match('/acfun\.cn\/v\/(ac\d+)/i', $this->realUrl, $m) ? $m[1] : null;
    }

    private function fetchPostData(): array
    {
        try {
            $html = $this->fetchHtmlContent(10) ?? '';
            $json = $this->extractJsonObjectFromMarker($html, 'window.pageInfo');
            $data = $json ? json_decode($json, true) : null;
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            Logger::warning('AcFun fetch failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getRealVideoUrl(): ?string
    {
        $ks = $this->arr($this->postData, ['currentVideoInfo', 'ksPlayJson']);
        if (!$ks) {
            return null;
        }
        $play = json_decode($ks, true);
        return is_array($play) ? $this->arr($play, ['adaptationSet', 0, 'representation', 0, 'url']) : null;
    }

    public function getTitleContent(): ?string
    {
        return $this->postData['title'] ?? '';
    }

    public function getCoverPhotoUrl(): ?string
    {
        return $this->postData['coverUrl'] ?? null;
    }

    public function getImageList(): array
    {
        return [];
    }

    public function getAuthorInfo(): ?array
    {
        $author = $this->postData['user'] ?? [];
        if (!$author) {
            return null;
        }
        return [
            'nickname' => $author['name'] ?? '',
            'author_id' => (string)($author['id'] ?? ''),
            'avatar' => $author['headUrl'] ?? '',
        ];
    }
}

