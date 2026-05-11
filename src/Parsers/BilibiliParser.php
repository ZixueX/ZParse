<?php
namespace MediaParser\Parsers;

use MediaParser\Config;
use MediaParser\HttpClient;
use MediaParser\Logger;

final class BilibiliParser extends BaseParser
{
    private const API_VIEW = 'https://api.bilibili.com/x/web-interface/view';
    private const API_PLAYURL = 'https://api.bilibili.com/x/player/playurl';
    private ?string $bvid;
    private array $videoInfo = [];
    private array $playInfo = [];

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = ['User-Agent' => Config::randomUserAgentPc(), 'referer' => 'https://www.bilibili.com/'];
        $this->bvid = $this->extractBvid($realUrl);
        $this->videoInfo = $this->fetchVideoInfo();
        $this->playInfo = $this->fetchPlayInfo();
    }

    private function extractBvid(string $url): ?string
    {
        return preg_match('/(BV[a-zA-Z0-9]+)/', $url, $m) ? $m[1] : null;
    }

    private function fetchVideoInfo(): array
    {
        if (!$this->bvid) return [];
        $resp = HttpClient::get(self::API_VIEW, $this->headers, ['bvid' => $this->bvid], 10);
        $data = $resp->json();
        return (($data['code'] ?? null) === 0) ? ($data['data'] ?? []) : [];
    }

    private function fetchPlayInfo(): array
    {
        $cid = $this->getCid();
        if (!$this->bvid || !$cid) return [];
        $resp = HttpClient::get(self::API_PLAYURL, $this->headers, [
            'bvid' => $this->bvid,
            'cid' => $cid,
            'qn' => 80,
            'fnval' => 16,
            'fnver' => 0,
            'fourk' => 1,
        ], 10);
        $data = $resp->json();
        return (($data['code'] ?? null) === 0) ? ($data['data'] ?? []) : [];
    }

    private function getCid()
    {
        return $this->videoInfo['pages'][0]['cid'] ?? null;
    }

    public function getVideoM4sUrl(): ?string
    {
        return $this->pickBestDashUrl($this->playInfo['dash']['video'] ?? []);
    }

    public function getAudioM4sUrl(): ?string
    {
        return $this->pickBestDashUrl($this->playInfo['dash']['audio'] ?? []);
    }

    public function getAudioUrl(): ?string
    {
        return $this->getAudioM4sUrl();
    }

    public function getTitleContent(): ?string { return $this->videoInfo['title'] ?? ''; }
    public function getCoverPhotoUrl(): ?string { return $this->videoInfo['pic'] ?? ''; }

    public function getAuthorInfo(): ?array
    {
        $owner = $this->videoInfo['owner'] ?? [];
        $avatar = $owner['face'] ?? '';
        if ($avatar && str_starts_with($avatar, '//')) $avatar = 'https:' . $avatar;
        return ['nickname' => $owner['name'] ?? '', 'author_id' => (string)($owner['mid'] ?? ''), 'avatar' => $avatar];
    }

    public function getRealVideoUrl(): ?string
    {
        // 优先返回 DASH 原始视频分轨。原来的 durl(fnval=0) 是单文件播放地址，
        // 更容易被误认为“带水印/低清播放流”。DASH 视频流通常更接近 B 站播放器实际源流。
        $dash = $this->getVideoM4sUrl();
        if ($dash) {
            return $dash;
        }

        return $this->getDurlVideoUrl();
    }

    public function getRealVideoUrlHd(): ?string
    {
        return $this->getVideoM4sUrl();
    }

    private function getDurlVideoUrl(): ?string
    {
        $cid = $this->getCid();
        if (!$this->bvid || !$cid) return null;
        try {
            $resp = HttpClient::get(self::API_PLAYURL, $this->headers, [
                'bvid' => $this->bvid,
                'cid' => $cid,
                'qn' => 64,
                'fnval' => 0,
                'fnver' => 0,
            ], 10);
            $result = $resp->json();
            if (($result['code'] ?? null) === 0) {
                $cdn = $result['data']['durl'][0]['url'] ?? null;
                if ($cdn) {
                    return $cdn;
                }
            }
        } catch (\Throwable $e) {
            Logger::error('Bilibili durl failed: ' . $e->getMessage());
        }
        return null;
    }

    private function pickBestDashUrl(array $items): ?string
    {
        if (!$items) {
            return null;
        }
        usort($items, static function (array $a, array $b): int {
            $qualityA = (int)($a['id'] ?? 0);
            $qualityB = (int)($b['id'] ?? 0);
            if ($qualityA !== $qualityB) {
                return $qualityB <=> $qualityA;
            }
            return (int)($b['bandwidth'] ?? 0) <=> (int)($a['bandwidth'] ?? 0);
        });
        foreach ($items as $item) {
            $url = $item['baseUrl'] ?? ($item['base_url'] ?? null);
            if ($url) {
                return $url;
            }
            $backup = $item['backupUrl'] ?? ($item['backup_url'] ?? []);
            if (is_array($backup) && !empty($backup[0])) {
                return $backup[0];
            }
        }
        return null;
    }
}
