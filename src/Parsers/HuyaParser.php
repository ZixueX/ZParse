<?php
namespace MediaParser\Parsers;

use MediaParser\HttpClient;
use MediaParser\UrlParser;

final class HuyaParser extends BaseParser
{
    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->data = $this->fetchHtmlData();
    }

    private function fetchHtmlData(): array
    {
        $videoId = UrlParser::getVideoId($this->realUrl);
        $resp = HttpClient::get('https://liveapi.huya.com/moment/getMomentContent', $this->headers, ['videoId' => $videoId]);
        return $resp->json();
    }

    public function getRealVideoUrl(): ?string { return $this->arr($this->data, ['data', 'moment', 'videoInfo', 'definitions', 0, 'url']); }
    public function getCoverPhotoUrl(): ?string { return $this->arr($this->data, ['data', 'moment', 'videoInfo', 'videoCover']); }
    public function getTitleContent(): ?string { return $this->arr($this->data, ['data', 'moment', 'videoInfo', 'videoTitle']); }
}

