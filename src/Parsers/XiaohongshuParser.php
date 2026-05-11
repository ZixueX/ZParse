<?php
namespace MediaParser\Parsers;

use MediaParser\Logger;

final class XiaohongshuParser extends BaseParser
{
    private array $noteData = [];

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = [
            'content-type' => 'application/json; charset=UTF-8',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'referer' => 'https://www.xiaohongshu.com/',
        ];
        $html = $this->fetchHtmlContent();
        $jsonStr = self::parseHtmlData($html, '/window\.__INITIAL_STATE__\s*=\s*(\{.*\})/s');
        if ($jsonStr) {
            $full = json_decode($jsonStr, true);
            if (is_array($full)) {
                $first = $full['note']['firstNoteId'] ?? null;
                if ($first) {
                    $this->noteData = $full['note']['noteDetailMap'][$first]['note'] ?? [];
                }
            } else {
                Logger::error('小红书 __INITIAL_STATE__ JSON 解码失败');
            }
        }
    }

    public function getAuthorInfo(): ?array
    {
        $user = $this->noteData['user'] ?? [];
        return [
            'nickname' => $user['nickname'] ?? '',
            'author_id' => $user['userId'] ?? '',
            'avatar' => $user['avatar'] ?? '',
        ];
    }

    public function getRealVideoUrl(): ?string
    {
        $url = $this->arr($this->noteData, ['video', 'media', 'stream', 'h264', 0, 'masterUrl']);
        return $url ? str_replace('\\u002F', '/', $url) : null;
    }

    public function getTitleContent(): ?string
    {
        return trim(($this->noteData['title'] ?? '') . "\n" . ($this->noteData['desc'] ?? ''));
    }

    public function getCoverPhotoUrl(): ?string
    {
        $url = $this->arr($this->noteData, ['imageList', 0, 'urlDefault']);
        return $url ? str_replace('\\u002F', '/', $url) : null;
    }

    public function getImageList(): array
    {
        $out = [];
        foreach (($this->noteData['imageList'] ?? []) as $image) {
            $url = $image['urlDefault'] ?? '';
            if (!$url) {
                continue;
            }
            $img = str_replace('\\u002F', '/', $url);
            if (!empty($image['livePhoto'])) {
                $master = $image['stream']['h264'][0]['masterUrl'] ?? '';
                if ($master) {
                    $img = ['url' => $img, 'live_photo_url' => str_replace('\\u002F', '/', $master)];
                }
            }
            $out[] = $img;
        }
        return $out;
    }
}
