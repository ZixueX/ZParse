<?php
namespace MediaParser\Parsers;

use MediaParser\HttpClient;
use MediaParser\Logger;
use MediaParser\Support\DouyinSigner;
use MediaParser\UrlParser;

final class DouyinParser extends BaseParser
{
    private DouyinSigner $signer;
    private string $msToken;
    private string $awemeId = '';
    private $postData = [];
    private static ?string $ttwidCache = null;

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->signer = new DouyinSigner();
        $this->headers = [
            'sec-ch-ua' => '"Google Chrome";v="123", "Not:A-Brand";v="8", "Chromium";v="123"',
            'Accept' => 'application/json, text/plain, */*',
            'sec-ch-ua-mobile' => '?0',
            'User-Agent' => $this->signer->userAgent,
            'sec-ch-ua-platform' => '"Windows"',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Dest' => 'empty',
            'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
        ];
        $this->msToken = $this->signer->getMsToken();
        $this->fetchHtmlContent();
        $this->awemeId = (string)UrlParser::getVideoId($this->realUrl);
        if ($this->awemeId === '' || $this->awemeId === 'www.douyin.com') {
            Logger::warning('抖音 aweme_id 提取异常: real_url=' . $this->realUrl . ' aweme_id=' . $this->awemeId);
        }
        $this->postData = $this->fetchHtmlData();
    }

    private function getTtwid(): ?string
    {
        if (self::$ttwidCache) {
            return self::$ttwidCache;
        }
        try {
            $resp = HttpClient::post(
                'https://ttwid.bytedance.com/ttwid/union/register/',
                ['Content-Type' => 'application/json; charset=UTF-8'],
                null,
                [
                    'region' => 'cn', 'aid' => 6383, 'need_t' => 1, 'service' => 'www.douyin.com',
                    'migrate_priority' => 0, 'cb_url_protocol' => 'https', 'domain' => '.douyin.com',
                ],
                5,
                true,
                false
            );
            $ttwid = $resp->cookies['ttwid'] ?? null;
            if ($ttwid) {
                self::$ttwidCache = $ttwid;
            }
            return $ttwid;
        } catch (\Throwable $e) {
            Logger::warning('Failed to get dynamic ttwid: ' . $e->getMessage());
            return null;
        }
    }

    private function fetchHtmlData(): array
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $ttwid = $this->getTtwid() ?: '1%7CvDWCB8tYdKPbdOlqwNTkDPhizBaV9i91KjYLKJbqurg%7C1723536402%7C314e63000decb79f46b8ff255560b29f4d8c57352dad465b41977db4830b4c7e';
            $referer = "https://www.douyin.com/video/{$this->awemeId}?previous_page=web_code_link";
            $playUrl = "https://www.douyin.com/aweme/v1/web/aweme/detail/?device_platform=webapp&aid=6383&channel=channel_pc_web&aweme_id={$this->awemeId}&msToken={$this->msToken}";
            $headers = $this->headers;
            $headers['Referer'] = $referer;
            $headers['Cookie'] = 'ttwid=' . $ttwid;
            $abogus = $this->signer->getABogus($playUrl, $this->signer->userAgent);
            if ($abogus === '') {
                Logger::warning('抖音 a_bogus 为空，请检查服务器 node 和 assets/douyin_utils/a_bogus.js');
            }
            $url = $playUrl . ($abogus !== '' ? '&a_bogus=' . $abogus : '');
            try {
                $resp = HttpClient::get($url, $headers, [], 5, true, false);
                if ($resp->statusCode === 200 && $resp->body !== '') {
                    $data = $resp->json();
                    if (empty($data['aweme_detail']) && $attempt === 0) {
                        Logger::warning('抖音详情无 aweme_detail，准备重试: body=' . substr($resp->body, 0, 300));
                        self::$ttwidCache = null;
                        continue;
                    }
                    if (empty($data['aweme_detail'])) {
                        Logger::warning('抖音详情无 aweme_detail: body=' . substr($resp->body, 0, 300));
                    }
                    return $data;
                }
                if ($attempt === 0) {
                    self::$ttwidCache = null;
                    continue;
                }
                Logger::warning('获取抖音视频详情失败: Status=' . $resp->statusCode . ' BodyLen=' . strlen($resp->body));
            } catch (\Throwable $e) {
                Logger::error('请求抖音详情接口异常: ' . $e->getMessage());
                if ($attempt === 0) {
                    self::$ttwidCache = null;
                    continue;
                }
            }
        }
        return [];
    }

    public function getRealVideoUrl(): ?string
    {
        $bitRate = $this->arr($this->postData, ['aweme_detail', 'video', 'bit_rate'], []);
        if (!$bitRate) {
            return null;
        }
        $list = $bitRate[0]['play_addr']['url_list'] ?? [];
        if (count($list) < 3) {
            return $list[0] ?? null;
        }
        return $list[2] ?? null;
    }

    public function getTitleContent(): ?string
    {
        return $this->arr($this->postData, ['aweme_detail', 'desc']);
    }

    public function getCoverPhotoUrl(): ?string
    {
        $detail = $this->postData['aweme_detail'] ?? [];
        $videoCover = $detail['video']['dynamic_cover']['url_list'][0] ?? null;
        $imagesCover = $detail['images'][0]['url_list'][0] ?? null;
        return $videoCover ?: $imagesCover;
    }

    public function getAudioUrl(): ?string
    {
        return $this->arr($this->postData, ['aweme_detail', 'music', 'play_url', 'url_list', 0]);
    }

    public function getAuthorInfo(): ?array
    {
        $author = $this->arr($this->postData, ['aweme_detail', 'author'], []);
        if (!$author) {
            return null;
        }
        return [
            'nickname' => $author['nickname'] ?? '',
            'author_id' => $author['unique_id'] ?? ($author['short_id'] ?? ''),
            'avatar' => $author['avatar_thumb']['url_list'][0] ?? null,
        ];
    }

    public function getImageList(): array
    {
        $detail = $this->postData['aweme_detail'] ?? [];
        $images = $detail['images'] ?? ($detail['image_list'] ?? []);
        $out = [];
        foreach ($images as $img) {
            $urls = $img['url_list'] ?? [];
            if (!$urls) {
                continue;
            }
            $data = end($urls);
            $liveUrls = $img['video']['play_addr']['url_list'] ?? [];
            if ($liveUrls) {
                $data = ['url' => $data, 'live_photo_url' => $liveUrls[0]];
            }
            $out[] = $data;
        }
        return $out;
    }
}

