<?php
namespace MediaParser\Parsers;

use MediaParser\HttpClient;
use MediaParser\UrlParser;

final class QuanminParser extends BaseParser
{
    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->data = $this->fetchHtmlData();
    }

    private function fetchHtmlData(): array
    {
        $videoId = UrlParser::getVideoId($this->realUrl);
        return HttpClient::get('https://haokan.baidu.com/haokan/ui-web/video/info', $this->headers, ['vid' => $videoId])->json();
    }

    public function getRealVideoUrl(): ?string { return $this->arr($this->data, ['data', 'meta', 'video_info', 'clarityUrl', 1, 'url']); }
    public function getCoverPhotoUrl(): ?string { return $this->arr($this->data, ['data', 'meta', 'image']); }

    public function getTitleContent(): ?string
    {
        $title = $this->arr($this->data, ['data', 'meta', 'title'], '');
        return $title !== '' ? $title : $this->arr($this->data, ['data', 'shareInfo', 'title']);
    }

    public function getAuthorInfo(): ?array
    {
        $author = $this->arr($this->data, ['data', 'author'], []);
        return [
            'nickname' => $author['name'] ?? '',
            'author_id' => (string)($author['id'] ?? ''),
            'avatar' => $author['icon'] ?? '',
        ];
    }
}

