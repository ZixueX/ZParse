<?php
namespace MediaParser\Parsers;

use MediaParser\HttpClient;

final class PipixiaParser extends BaseParser
{
    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->data = $this->fetchHtmlData();
    }

    private function fetchHtmlData(): array
    {
        $resp = HttpClient::get($this->realUrl, $this->headers, [], 10, false);
        $location = $resp->header('location') ?: $this->realUrl;
        $path = (string)(parse_url($location, PHP_URL_PATH) ?: '');
        $videoId = basename(trim($path, '/'));
        $url = 'https://api.pipix.com/bds/cell/cell_comment/?offset=0&cell_type=1&api_version=1&cell_id=' . rawurlencode($videoId) . '&ac=wifi&channel=huawei_1319_64&aid=1319&app_name=super';
        return HttpClient::get($url, $this->headers)->json();
    }

    private function item(): array
    {
        return $this->arr($this->data, ['data', 'cell_comments', 0, 'comment_info', 'item'], []);
    }

    public function getRealVideoUrl(): ?string
    {
        $item = $this->item();
        return isset($item['video']) ? $this->arr($item, ['video', 'video_high', 'url_list', 0, 'url']) : null;
    }

    public function getImageList(): array
    {
        $out = [];
        $item = $this->item();
        if (isset($item['note']['multi_image']) && is_array($item['note']['multi_image'])) {
            foreach ($item['note']['multi_image'] as $img) {
                if (!empty($img['url_list'][0]['url'])) {
                    $out[] = $img['url_list'][0]['url'];
                }
            }
        }
        return $out;
    }

    public function getCoverPhotoUrl(): ?string { return $this->arr($this->item(), ['cover', 'url_list', 0, 'url']); }
    public function getTitleContent(): ?string { return $this->item()['content'] ?? null; }

    public function getAuthorInfo(): ?array
    {
        $author = $this->item()['author'] ?? [];
        if (!$author) {
            return [];
        }
        return [
            'nickname' => $author['name'] ?? '',
            'author_id' => (string)($author['id'] ?? ''),
            'avatar' => $this->arr($author, ['avatar', 'download_list', 0, 'url'], ''),
        ];
    }
}

