<?php
namespace MediaParser;

final class ParserService
{
    public function parse(string $text): array
    {
        [$status, $payload] = $this->parseWithStatus($text);
        return $payload;
    }

    public function parseWithStatus(string $text): array
    {
        try {
            $shareUrl = UrlParser::getUrl($text);
            $redirectUrl = WebFetcher::fetchRedirectUrl($shareUrl);
            $platform = Config::domainToName()[UrlParser::getDomain($redirectUrl) ?? ''] ?? null;
            $realUrl = UrlParser::extractVideoAddress($redirectUrl);
            Logger::debug('real_url ' . (string)$realUrl);

            if (!$platform || !$realUrl) {
                return [400, Response::make(400, '该链接尚未支持提取', null, false)];
            }

            $parser = ParserFactory::createParser($platform, $realUrl);
            $content = $this->fetchWithRetry($parser, $platform);

            if (empty($content['video_url']) && empty($content['image_list'])) {
                Logger::error('Failed to retrieve media content for ' . $platform);
                return [500, Response::make(500, '解析失败，请检查链接是否正确或稍后重试', null, false)];
            }

            $processedImages = [];
            foreach (($content['image_list'] ?? []) as $img) {
                if (is_array($img)) {
                    $imgUrl = UrlParser::convertToHttps($img['url'] ?? null);
                    $livePhotoUrl = UrlParser::convertToHttps($img['live_photo_url'] ?? null);
                    $processedImages[] = [
                        'url' => $imgUrl,
                        'proxy_url' => MediaProxy::makeProxyUrl($imgUrl, $platform),
                        'live_photo_url' => $livePhotoUrl,
                        'live_photo_proxy_url' => MediaProxy::makeProxyUrl($livePhotoUrl, $platform),
                    ];
                } else {
                    $imgUrl = UrlParser::convertToHttps((string)$img);
                    $processedImages[] = [
                        'url' => $imgUrl,
                        'proxy_url' => MediaProxy::makeProxyUrl($imgUrl, $platform),
                    ];
                }
            }

            $videoUrl = UrlParser::convertToHttps($content['video_url'] ?? null);
            $audioUrl = UrlParser::convertToHttps($content['audio_url'] ?? null);
            $coverUrl = UrlParser::convertToHttps($content['cover_url'] ?? null);
            $author = is_array($content['author'] ?? null) ? $content['author'] : null;
            if ($author) {
                $avatarUrl = UrlParser::convertToHttps($author['avatar'] ?? null);
                $author['avatar'] = $avatarUrl;
                $author['avatar_proxy_url'] = MediaProxy::makeProxyUrl($avatarUrl, $platform);
            }

            $data = [
                'video_id' => UrlParser::getVideoId($redirectUrl),
                'platform' => $platform,
                'title' => $content['title'] ?? null,
                'video_url' => $videoUrl,
                'video_proxy_url' => MediaProxy::makeProxyUrl($videoUrl, $platform),
                'audio_url' => $audioUrl,
                'audio_proxy_url' => MediaProxy::makeProxyUrl($audioUrl, $platform),
                'merge_proxy_url' => $platform === '哔哩哔哩' ? MediaProxy::makeMergeUrl($videoUrl, $audioUrl, $platform) : null,
                'cover_url' => $coverUrl,
                'cover_proxy_url' => MediaProxy::makeProxyUrl($coverUrl, $platform),
                'author' => $author,
                'image_list' => $processedImages,
            ];
            Logger::debug('Parse Success for platform ' . $platform);
            return [200, Response::make(200, '成功', $data, true)];
        } catch (\Throwable $e) {
            Logger::exception('Parse Error', $e);
            return [500, Response::make(500, '功能太火爆啦，请稍后再试', null, false)];
        }
    }

    private function fetchWithRetry(object $parser, string $platform): array
    {
        $max = $platform === '小红书' ? 3 : 1;
        $res = [];
        for ($i = 0; $i < $max; $i++) {
            $res = [
                'title' => $this->safe(fn() => $parser->getTitleContent()),
                'video_url' => $this->safe(fn() => $parser->getRealVideoUrl()),
                'cover_url' => $this->safe(fn() => $parser->getCoverPhotoUrl()),
                'author' => $this->safe(fn() => $parser->getAuthorInfo()),
                'image_list' => $this->safe(fn() => $parser->getImageList(), []),
                'audio_url' => $this->safe(fn() => $parser->getAudioUrl()),
            ];
            if (!empty($res['video_url']) || !empty($res['image_list'])) {
                return $res;
            }
            if ($i < $max - 1) {
                Logger::debug('Attempt ' . ($i + 1) . ' failed. Retrying...');
            }
        }
        return $res;
    }

    private function safe(callable $fn, $default = null)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
