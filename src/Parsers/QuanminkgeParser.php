<?php
namespace MediaParser\Parsers;

use MediaParser\HttpClient;
use MediaParser\UrlParser;

final class QuanminkgeParser extends BaseParser
{
    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->data = $this->fetchHtmlData();
    }

    private function fetchHtmlData(): array
    {
        $videoId = UrlParser::getVideoId($this->realUrl);
        $resp = HttpClient::get('https://kg.qq.com/node/play', $this->headers, ['s' => $videoId]);
        if (preg_match('/window\.__DATA__\s*=\s*(.*?);\s*<\/script>/s', $resp->body, $m)) {
            $data = json_decode($m[1], true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    public function getRealVideoUrl(): ?string { return $this->arr($this->data, ['detail', 'playurl_video']); }
    public function getCoverPhotoUrl(): ?string { return $this->arr($this->data, ['detail', 'cover']); }
    public function getTitleContent(): ?string { return $this->arr($this->data, ['detail', 'content']); }
}

