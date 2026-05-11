<?php
namespace MediaParser\Parsers;

use MediaParser\HttpClient;
use MediaParser\Logger;
use MediaParser\Support\YtDlp;

final class YoutubeParser extends BaseParser
{
    private array $postData = [];

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];
        $this->postData = $this->fetchPostData();
    }

    private function fetchPostData(): array
    {
        try {
            $resp = HttpClient::get($this->realUrl, $this->headers, [], 10);
            $json = $this->extractJsonObjectFromMarker($resp->body, 'ytInitialPlayerResponse');
            $data = $json ? json_decode($json, true) : null;
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            Logger::warning('Youtube raw fetch failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getRealVideoUrl(): ?string
    {
        foreach ($this->arr($this->postData, ['streamingData', 'formats'], []) as $fmt) {
            if (!empty($fmt['url'])) {
                return $fmt['url'];
            }
        }
        foreach ($this->arr($this->postData, ['streamingData', 'formats'], []) as $fmt) {
            if (!empty($fmt['signatureCipher'])) {
                return $this->fallbackYtDlpUrl();
            }
        }
        return $this->fallbackYtDlpUrl();
    }

    private function fallbackYtDlpUrl(): ?string
    {
        $info = YtDlp::extract($this->realUrl, 'best[ext=mp4]/best');
        if (!empty($info['url'])) {
            return $info['url'];
        }
        foreach (($info['formats'] ?? []) as $fmt) {
            if (($fmt['ext'] ?? '') === 'mp4' && !empty($fmt['url']) && (($fmt['vcodec'] ?? 'none') !== 'none') && (($fmt['acodec'] ?? 'none') !== 'none')) {
                return $fmt['url'];
            }
        }
        return null;
    }

    public function getAudioUrl(): ?string
    {
        foreach ($this->arr($this->postData, ['streamingData', 'adaptiveFormats'], []) as $fmt) {
            if (str_contains($fmt['mimeType'] ?? '', 'audio/') && !empty($fmt['url'])) {
                return $fmt['url'];
            }
        }
        foreach ($this->arr($this->postData, ['streamingData', 'adaptiveFormats'], []) as $fmt) {
            if (str_contains($fmt['mimeType'] ?? '', 'audio/') && !empty($fmt['signatureCipher'])) {
                return $this->fallbackYtDlpAudioUrl();
            }
        }
        return $this->fallbackYtDlpAudioUrl();
    }

    private function fallbackYtDlpAudioUrl(): ?string
    {
        $info = YtDlp::extract($this->realUrl, 'bestaudio[ext=m4a]/bestaudio');
        return $info['url'] ?? null;
    }

    public function getTitleContent(): ?string
    {
        $details = $this->postData['videoDetails'] ?? [];
        $title = $details['title'] ?? '';
        $desc = $details['shortDescription'] ?? '';
        return $desc ? $title . "\n" . $this->textSlice($desc, 0, 200) : $title;
    }

    public function getCoverPhotoUrl(): ?string
    {
        $thumbs = $this->arr($this->postData, ['videoDetails', 'thumbnail', 'thumbnails'], []);
        if (!$thumbs) {
            return null;
        }
        $last = end($thumbs);
        return is_array($last) ? ($last['url'] ?? null) : null;
    }

    public function getImageList(): array
    {
        return [];
    }

    public function getAuthorInfo(): ?array
    {
        $details = $this->postData['videoDetails'] ?? [];
        return [
            'nickname' => $details['author'] ?? '',
            'author_id' => $details['channelId'] ?? '',
            'avatar' => $this->arr($this->postData, ['microformat', 'playerMicroformatRenderer', 'ownerProfileUrl']),
        ];
    }
}
