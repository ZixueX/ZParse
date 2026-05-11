<?php
namespace MediaParser\Parsers;

use MediaParser\HttpClient;
use MediaParser\UrlParser;

final class ZuiyouParser extends BaseParser
{
    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->data = $this->fetchHtmlData();
    }

    private function fetchHtmlData(): array
    {
        $videoId = (int)(UrlParser::getVideoId($this->realUrl) ?: 0);
        $resp = HttpClient::post('https://share.xiaochuankeji.cn/planck/share/post/detail_h5', $this->headers, null, [
            'h_av' => '5.2.13.011',
            'pid' => $videoId,
        ]);
        return $resp->json();
    }

    public function getRealVideoUrl(): ?string
    {
        $post = $this->arr($this->data, ['data', 'post'], []);
        $key = (string)($post['imgs'][0]['id'] ?? '');
        return $key && !empty($post['videos'][$key]['url']) ? $post['videos'][$key]['url'] : null;
    }

    public function getCoverPhotoUrl(): ?string { return null; }
    public function getTitleContent(): ?string { return $this->arr($this->data, ['data', 'post', 'content']); }

    public function getAuthorInfo(): ?array
    {
        $member = $this->arr($this->data, ['data', 'post', 'member'], []);
        if (!$member) {
            return [];
        }
        return [
            'nickname' => $member['name'] ?? '',
            'author_id' => (string)($member['id'] ?? ''),
            'avatar' => $this->arr($member, ['avatar_urls', 'origin', 'urls', 0], ''),
        ];
    }
}

