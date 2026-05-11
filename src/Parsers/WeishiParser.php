<?php
namespace MediaParser\Parsers;

use MediaParser\Config;

final class WeishiParser extends BaseParser
{
    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = [
            'content-type' => 'application/json; charset=UTF-8',
            'User-Agent' => Config::randomUserAgentPc(),
            'referer' => 'https://isee.weishi.qq.com',
        ];
        $html = $this->fetchHtmlContent();
        $json = self::parseHtmlData($html, '/window\.Vise\.initState\s*=\s*(\{.*\};)/s');
        $this->data = $json ? (json_decode($json, true) ?: []) : [];
    }

    public function getRealVideoUrl(): ?string
    {
        $url = $this->arr($this->data, ['feedsList', 0, 'videoUrl']);
        return $url ? str_replace('\\u002F', '/', $url) : null;
    }

    public function getTitleContent(): ?string
    {
        return $this->arr($this->data, ['feedsList', 0, 'feedDesc']);
    }

    public function getCoverPhotoUrl(): ?string
    {
        $url = $this->arr($this->data, ['feedsList', 0, 'videoCover']);
        return $url ? str_replace('\\u002F', '/', $url) : null;
    }

    public function getAuthorInfo(): ?array
    {
        $poster = $this->arr($this->data, ['feedsList', 0, 'poster'], []);
        return [
            'nickname' => $poster['nick'] ?? '未知用户',
            'avatar' => str_replace('\\u002F', '/', $poster['avatar'] ?? ''),
            'author_id' => (string)($poster['id'] ?? ''),
        ];
    }
}

