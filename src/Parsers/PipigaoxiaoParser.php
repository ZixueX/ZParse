<?php
namespace MediaParser\Parsers;

use MediaParser\Config;
use MediaParser\HttpClient;
use MediaParser\Logger;
use MediaParser\UrlParser;

final class PipigaoxiaoParser extends BaseParser
{
    private string $videoPid = '';

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = [
            'content-type' => 'application/json; charset=UTF-8',
            'User-Agent' => Config::randomUserAgentPc(),
            'referer' => $this->realUrl,
        ];
        $this->videoPid = (string)UrlParser::getVideoId($this->realUrl);
        $this->data = $this->fetchHtmlData();
    }

    private function fetchHtmlData(): array
    {
        try {
            $resp = HttpClient::post('https://h5.pipigx.com/ppapi/share/fetch_content', $this->headers, null, [
                'mid' => 'null',
                'pid' => (int)$this->videoPid,
                'type' => 'post',
            ], 5);
            return $resp->json();
        } catch (\Throwable $e) {
            Logger::error('Pipigaoxiao API error: ' . $e->getMessage());
            return [];
        }
    }

    public function getRealVideoUrl(): ?string
    {
        $post = $this->arr($this->data, ['data', 'post'], []);
        $imgs = $post['imgs'] ?? [];
        if (!$imgs) {
            return null;
        }
        $id = (string)($imgs[0]['id'] ?? '');
        return $id && isset($post['videos'][$id]['url']) ? $post['videos'][$id]['url'] : null;
    }

    public function getTitleContent(): ?string
    {
        return $this->arr($this->data, ['data', 'post', 'content'], '');
    }

    public function getCoverPhotoUrl(): ?string
    {
        $id = $this->arr($this->data, ['data', 'post', 'imgs', 0, 'id']);
        return $id ? 'https://file.ippzone.com/img/view/id/' . $id : '';
    }

    public function getAuthorInfo(): ?array
    {
        $post = $this->arr($this->data, ['data', 'post'], []);
        $user = $this->arr($this->data, ['data', 'user'], []);
        $avatarVal = (string)($user['avatar'] ?? '');
        $avatar = ctype_digit($avatarVal) && $avatarVal !== '' ? 'https://file.ippzone.com/img/view/id/' . $avatarVal : $avatarVal;
        return [
            'nickname' => $user['name'] ?? '',
            'author_id' => (string)($user['mid'] ?? ($post['mid'] ?? '')),
            'avatar' => $avatar,
        ];
    }
}

