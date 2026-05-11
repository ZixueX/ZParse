<?php
namespace MediaParser\Parsers;

use MediaParser\HttpClient;
use MediaParser\UrlParser;

final class SixroomParser extends BaseParser
{
    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->data = $this->fetchHtmlData();
    }

    private function fetchHtmlData(): array
    {
        $videoId = UrlParser::getVideoId($this->realUrl);
        return HttpClient::get('https://v.6.cn/profile/tmv/getVideoInfo.php', $this->headers, ['vid' => $videoId])->json();
    }

    public function getRealVideoUrl(): ?string { return $this->arr($this->data, ['content', 'playurl']); }
    public function getCoverPhotoUrl(): ?string { return $this->arr($this->data, ['content', 'picurl']); }
    public function getTitleContent(): ?string { return $this->arr($this->data, ['content', 'title']); }
}

