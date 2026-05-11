<?php
namespace MediaParser\Parsers;

use MediaParser\HttpClient;
use MediaParser\UrlParser;

final class DoupaiParser extends BaseParser
{
    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->data = $this->fetchHtmlData();
    }

    private function fetchHtmlData(): array
    {
        $videoId = UrlParser::getVideoId($this->realUrl);
        $resp = HttpClient::get('https://v2.doupai.cc/topic/' . $videoId . '.json', $this->headers);
        return $resp->json();
    }

    public function getRealVideoUrl(): ?string { return $this->arr($this->data, ['data', 'videoUrl']); }
    public function getCoverPhotoUrl(): ?string { return $this->arr($this->data, ['data', 'imageUrl']); }
    public function getTitleContent(): ?string { return $this->arr($this->data, ['data', 'name']); }
}

