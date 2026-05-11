<?php
namespace MediaParser\Parsers;

use MediaParser\Config;
use MediaParser\HttpClient;
use MediaParser\Logger;
use MediaParser\UrlParser;

final class LishipinParser extends BaseParser
{
    private string $videoId = '';

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->headers = [
            'content-type' => 'application/json; charset=UTF-8',
            'User-Agent' => Config::randomUserAgentPc(),
            'referer' => $this->realUrl,
        ];
        $this->videoId = (string)UrlParser::getVideoId($this->realUrl);
        $this->data = $this->fetchHtmlData();
        $this->htmlContent = $this->fetchHtmlContent() ?? '';
    }

    private function fetchHtmlData(): array
    {
        $contId = preg_replace('/\D+/', '', $this->videoId);
        try {
            $resp = HttpClient::get('https://www.pearvideo.com/videoStatus.jsp', $this->headers, [
                'contId' => $contId,
                'mrd' => mt_rand() / mt_getrandmax(),
            ], 5);
            return $resp->json();
        } catch (\Throwable $e) {
            Logger::error('Lishipin API error: ' . $e->getMessage());
            return [];
        }
    }

    public function getRealVideoUrl(): ?string
    {
        $url = $this->arr($this->data, ['videoInfo', 'videos', 'srcUrl']);
        if (!$url) {
            return null;
        }
        return preg_replace('/(\d+)-(\d+-hd\.mp4)/', 'cont-' . $this->videoId . '-$2', $url) ?: $url;
    }

    public function getTitleContent(): ?string
    {
        if (preg_match('/<div[^>]*class=(["\'])(?:(?!\1).)*\bsummary\b(?:(?!\1).)*\1[^>]*>(.*?)<\/div>/is', $this->htmlContent ?? '', $m)) {
            return $this->cleanHtml($m[2]);
        }
        return '';
    }

    public function getCoverPhotoUrl(): ?string
    {
        return $this->arr($this->data, ['videoInfo', 'video_image'], '');
    }

    public function getAuthorInfo(): ?array
    {
        $html = $this->htmlContent ?? '';
        $start = stripos($html, 'thiscat');
        $scope = $start === false ? $html : substr($html, $start, 5000);

        $nickname = '';
        if (preg_match('/class=(["\'])(?:(?!\1).)*\bcol-name\b(?:(?!\1).)*\1[^>]*>(.*?)<\/div>/is', $scope, $m)) {
            $nickname = $this->cleanHtml($m[2]);
        }

        $avatar = '';
        if (preg_match('/<img\b[^>]*\bsrc=(["\'])(.*?)\1/is', $scope, $m)) {
            $avatar = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $uid = '';
        if (preg_match('/class=(["\'])(?:(?!\1).)*\bcolumn-subscribe\b(?:(?!\1).)*\1[^>]*\bdata-userid=(["\'])(.*?)\2/is', $scope, $m)) {
            $uid = $m[3];
        } elseif (preg_match('/author_(\d+)/', $scope, $m)) {
            $uid = $m[1];
        }

        return ['nickname' => $nickname, 'author_id' => $uid, 'avatar' => $avatar];
    }
}

