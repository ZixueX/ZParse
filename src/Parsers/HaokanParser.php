<?php
namespace MediaParser\Parsers;

use MediaParser\Config;

final class HaokanParser extends BaseParser
{
    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = [
            'content-type' => 'application/json; charset=UTF-8',
            'User-Agent' => Config::randomUserAgentM(),
            'referer' => 'https://haokan.baidu.com/v',
        ];
        $html = $this->fetchHtmlContent();
        $json = self::parseHtmlData($html, '/window\.__PRELOADED_STATE__\s*=\s*(\{.*\};)/s');
        $this->data = $json ? (json_decode($json, true) ?: []) : [];
    }

    public function getRealVideoUrl(): ?string
    {
        $clarity = $this->arr($this->data, ['curVideoMeta', 'clarityUrl'], []);
        if (!$clarity) {
            return null;
        }
        $last = end($clarity);
        $url = is_array($last) ? ($last['url'] ?? '') : '';
        return $url ? str_replace('\\/', '/', urldecode($url)) : null;
    }

    public function getTitleContent(): ?string
    {
        return $this->arr($this->data, ['curVideoMeta', 'title'], '');
    }

    public function getCoverPhotoUrl(): ?string
    {
        $url = $this->arr($this->data, ['curVideoMeta', 'poster'], '');
        return $url ? str_replace('\\/', '/', $url) : '';
    }

    public function getAuthorInfo(): ?array
    {
        $author = $this->arr($this->data, ['curVideoMeta', 'mth'], []);
        return [
            'nickname' => $author['author_name'] ?? '',
            'author_id' => (string)($author['mthid'] ?? ''),
            'avatar' => str_replace('\\/', '/', $author['author_photo'] ?? ''),
        ];
    }
}

