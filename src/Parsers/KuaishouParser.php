<?php
namespace MediaParser\Parsers;

use MediaParser\Config;
use MediaParser\Logger;
use MediaParser\UrlParser;

final class KuaishouParser extends BaseParser
{
    private string $videoId = '';
    private string $pageType = 'UNKNOWN';
    private array $structuredData = [];
    private array $client = [];

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = [
            'content-type' => 'application/json; charset=UTF-8',
            'User-Agent' => Config::randomUserAgentPc(),
            'referer' => 'https://www.kuaishou.com/',
            'cookie' => 'kpf=PC_WEB; clientid=3; did=web_bfbcdb2f5b3dc663a745deabafcf61e6; kpn=KUAISHOU_VISION',
        ];
        $this->videoId = (string)UrlParser::getVideoId($this->realUrl);
        $this->htmlContent = $this->fetchHtmlContent() ?? '';
        [$this->pageType, $this->structuredData] = $this->identifyAndParseData();
        $this->client = $this->pageType === 'VIDEO' ? ($this->structuredData['defaultClient'] ?? []) : $this->structuredData;
    }

    private function extractJsonObject(string $text, int $start): ?string
    {
        if ($start < 0) {
            return null;
        }
        $count = 0;
        $found = false;
        $len = strlen($text);
        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];
            if ($ch === '{') {
                $count++;
                $found = true;
            } elseif ($ch === '}') {
                $count--;
                if ($found && $count === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }
        return null;
    }

    private function identifyAndParseData(): array
    {
        $html = $this->htmlContent ?? '';
        if (str_contains($html, 'window.__APOLLO_STATE__')) {
            $pos = strpos($html, 'window.__APOLLO_STATE__');
            $start = strpos($html, '{', $pos === false ? 0 : $pos);
            $json = $this->extractJsonObject($html, $start === false ? -1 : $start);
            $data = $json ? json_decode($json, true) : null;
            if (is_array($data)) {
                return ['VIDEO', $data];
            }
        }
        if (str_contains($html, 'window.INIT_STATE')) {
            $pos = strpos($html, 'window.INIT_STATE');
            $start = strpos($html, '{', $pos === false ? 0 : $pos);
            $json = $this->extractJsonObject($html, $start === false ? -1 : $start);
            $data = $json ? json_decode($json, true) : null;
            if (is_array($data)) {
                return ['ATLAS', $data];
            }
        }
        return ['UNKNOWN', []];
    }

    public function getRealVideoUrl(): ?string
    {
        if ($this->pageType !== 'VIDEO') {
            return null;
        }
        $url = $this->client['VisionVideoSetRepresentation:1']['url'] ?? null;
        if (!$url) {
            $url = $this->client['VisionVideoDetailPhoto:' . $this->videoId]['photoUrl'] ?? null;
        }
        return $url ? str_replace('\\u002F', '/', $url) : null;
    }

    public function getTitleContent(): ?string
    {
        if ($this->pageType === 'VIDEO') {
            return $this->client['VisionVideoDetailPhoto:' . $this->videoId]['caption'] ?? '';
        }
        return '';
    }

    public function getCoverPhotoUrl(): ?string
    {
        return $this->client['VisionVideoDetailPhoto:' . $this->videoId]['coverUrl'] ?? '';
    }

    public function getAuthorInfo(): ?array
    {
        try {
            if ($this->pageType === 'VIDEO') {
                $photoKey = 'VisionVideoDetailPhoto:' . $this->videoId;
                $authorRef = $this->client[$photoKey]['author'] ?? null;
                if (!$authorRef) {
                    foreach ($this->client as $k => $v) {
                        if (str_contains((string)$k, 'photoId":"' . $this->videoId . '"')) {
                            $authorRef = $v['author'] ?? null;
                            break;
                        }
                    }
                }
                if (is_array($authorRef) && isset($authorRef['id'], $this->client[$authorRef['id']])) {
                    $a = $this->client[$authorRef['id']];
                    return ['nickname' => $a['name'] ?? null, 'author_id' => $a['id'] ?? null, 'avatar' => $a['headerUrl'] ?? null];
                }
            } elseif ($this->pageType === 'ATLAS') {
                foreach ($this->structuredData as $val) {
                    if (is_array($val) && isset($val['userProfile']['profile'])) {
                        $p = $val['userProfile']['profile'];
                        return ['nickname' => $p['user_name'] ?? null, 'author_id' => $p['user_id'] ?? null, 'avatar' => $p['headurl'] ?? null];
                    }
                }
            }
        } catch (\Throwable $e) {
            Logger::error('Author parse error: ' . $e->getMessage());
        }
        return null;
    }

    public function getAudioUrl(): ?string
    {
        // 快手网页端通常不暴露独立音频直链。为避免本地缓存/ffmpeg 抽取，这里直接返回空。
        return null;
    }
}
