<?php
namespace MediaParser\Parsers;

use MediaParser\Config;
use MediaParser\HttpClient;
use MediaParser\Logger;

final class XiguaParser extends BaseParser
{
    private ?string $videoId = null;
    private array $postData = [];

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = ['User-Agent' => Config::randomUserAgentM()];
        $this->videoId = $this->extractVideoId();
        $this->postData = $this->fetchPostData();
    }

    private function extractVideoId(): ?string
    {
        return preg_match('/ixigua\.com\/(?:video\/)?(\d+)/', $this->realUrl, $m) ? $m[1] : null;
    }

    private function fetchPostData(): array
    {
        if (!$this->videoId) {
            return [];
        }
        $url = "https://m.ixigua.com/douyin/share/video/{$this->videoId}?aweme_type=107&schema_type=1&utm_source=copy&utm_campaign=client_share&utm_medium=android&app=aweme";
        try {
            $resp = HttpClient::get($url, $this->headers, [], 10);
            if (preg_match('/window\._ROUTER_DATA\s*=\s*(.*?)<\/script>/s', $resp->body, $m)) {
                $json = json_decode(trim($m[1]), true);
                $item = $this->arr($json, ['loaderData', 'video_(id)/page', 'videoInfoRes', 'item_list', 0]);
                if (is_array($item)) {
                    return $item;
                }
            }
            if (preg_match('/window\._SSR_HYDRATED_DATA\s*=\s*(.*?)<\/script>/s', $resp->body, $m)) {
                $json = json_decode(str_replace('undefined', 'null', trim($m[1])), true);
                $item = $this->arr($json, ['anyVideo', 'item_info']);
                if (is_array($item)) {
                    return $item;
                }
            }
        } catch (\Throwable $e) {
            Logger::warning('Xigua API fetch failed: ' . $e->getMessage());
        }
        return [];
    }

    public function getRealVideoUrl(): ?string
    {
        $list = $this->arr($this->postData, ['video', 'play_addr', 'url_list'], []);
        if ($list) {
            return str_replace('playwm', 'play', $list[0]);
        }
        $videoList = $this->arr($this->postData, ['video', 'video_list'], []);
        foreach (['video_4', 'video_3', 'video_2', 'video_1'] as $key) {
            if (!empty($videoList[$key]['main_url'])) {
                $decoded = base64_decode($videoList[$key]['main_url'], true);
                return $decoded !== false ? $decoded : null;
            }
        }
        return null;
    }

    public function getTitleContent(): ?string
    {
        return $this->postData['desc'] ?? ($this->postData['title'] ?? '');
    }

    public function getCoverPhotoUrl(): ?string
    {
        $list = $this->arr($this->postData, ['video', 'cover', 'url_list'], []);
        if ($list) {
            return $list[0];
        }
        return $this->arr($this->postData, ['video', 'poster_url']);
    }

    public function getImageList(): array
    {
        return [];
    }

    public function getAuthorInfo(): ?array
    {
        $author = $this->postData['author'] ?? ($this->postData['user_info'] ?? []);
        if (!$author) {
            return null;
        }
        $avatar = '';
        $avatarThumb = $author['avatar_thumb'] ?? [];
        if (!empty($avatarThumb['url_list'])) {
            $avatar = $avatarThumb['url_list'][0];
        } else {
            $avatar = $author['avatar_url'] ?? '';
        }
        return [
            'nickname' => $author['nickname'] ?? ($author['name'] ?? ''),
            'author_id' => (string)($author['unique_id'] ?? ($author['user_id'] ?? '')),
            'avatar' => $avatar,
        ];
    }
}

