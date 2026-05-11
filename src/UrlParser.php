<?php
namespace MediaParser;

final class UrlParser
{
    public static function convertToHttps(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        if (str_starts_with($url, 'http://')) {
            return 'https://' . substr($url, 7);
        }
        return $url;
    }

    public static function getUrl(?string $text): ?string
    {
        if (!$text) {
            return null;
        }

        // 抖音分享口令里短链后面有时会拼上形如 "/z@g.BG 12/05 oQk:/"
        // 的噪声。v.douyin.com 的有效短链只需要第一个 path 片段。
        if (preg_match('/\bhttps?:\/\/v\.douyin\.com\/([A-Za-z0-9_-]+)\/?/i', $text, $m)) {
            return 'https://v.douyin.com/' . $m[1] . '/';
        }

        if (preg_match('/\bhttps?:\/\/(?:www\.|[-a-zA-Z0-9.@:%_+~#=]{1,256}\.[a-zA-Z0-9()]{1,6})\b(?:[-a-zA-Z0-9()@:%_+.~#?&\/=]*)?/u', $text, $m)) {
            return $m[0];
        }
        return null;
    }

    public static function getDomain(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) ? $host : null;
    }

    public static function extractVideoAddress(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return null;
        }
        $scheme = $parts['scheme'] ?? 'https';
        $domain = $parts['host'];
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';
        $platform = Config::domainToName()[$domain] ?? null;
        $address = $scheme . '://' . $domain . $path;
        $address = rtrim($address, '/');
        parse_str($query, $params);
        if ($platform === '好看视频' && !empty($params['vid'])) {
            $address .= '?vid=' . $params['vid'];
        } elseif ($platform === '微视' && !empty($params['id'])) {
            $address .= '?id=' . $params['id'];
        } elseif ($platform === '小红书' && !empty($params['xsec_token'])) {
            $address .= '?xsec_token=' . $params['xsec_token'];
        } elseif ($platform === '快手') {
            $address = str_replace('http://', 'https://', $address);
        } elseif ($platform === '抖音' && !empty($params['modal_id'])) {
            $address .= '?modal_id=' . $params['modal_id'];
        } elseif ($platform === 'YouTube' && !empty($params['v'])) {
            $address .= '?v=' . $params['v'];
        } elseif ($platform === '全民K歌' && !empty($params['s'])) {
            $address .= '?s=' . $params['s'];
        } elseif ($platform === '最右' && !empty($params['pid'])) {
            $address .= '?pid=' . $params['pid'];
        }
        return $address;
    }

    public static function getVideoId(?string $url): ?string
    {
        try {
            if (!$url) {
                return null;
            }
            $parts = parse_url($url);
            parse_str($parts['query'] ?? '', $params);
            foreach (['vid', 'id', 'modal_id', 'v', 's', 'pid'] as $key) {
                if (!empty($params[$key])) {
                    return (string)$params[$key];
                }
            }
            $path = trim((string)($parts['path'] ?? ''), '/');
            if ($path !== '') {
                $segments = explode('/', $path);
                $videoId = end($segments);
                if (str_ends_with($videoId, '.html')) {
                    $videoId = substr($videoId, 0, -5);
                }
                return $videoId;
            }
        } catch (\Throwable $e) {
            Logger::error('extract video id error: ' . $e->getMessage());
        }
        return null;
    }

    public static function generateVideoUrl(string $platform, string $videoId): string
    {
        $map = [
            '皮皮搞笑' => 'https://h5.pipigx.com/pp/post/',
            '好看视频' => 'https://haokan.hao123.com/v?vid=',
            '哔哩哔哩' => 'https://www.bilibili.com/video/',
            '抖音' => 'https://www.iesdouyin.com/share/video/',
            '快手' => 'https://www.kuaishou.com/short-video/',
            '梨视频' => 'https://www.pearvideo.com/',
            'AcFun' => 'https://www.acfun.cn/v/',
            'Instagram' => 'https://www.instagram.com/p/',
            'TikTok' => 'https://www.tiktok.com/@/video/',
            'Twitter' => 'https://twitter.com/x/status/',
            '微博' => 'https://m.weibo.cn/status/',
            '西瓜视频' => 'https://www.ixigua.com/',
            'YouTube' => 'https://www.youtube.com/watch?v=',
            '知乎' => 'https://www.zhihu.com/question/',
            '逗拍' => 'https://v2.doupai.cc/topic/',
            '虎牙' => 'https://v.huya.com/play/',
            '绿洲' => 'https://oasis.weibo.cn/v1/h5/share?sid=',
            '美拍' => 'https://www.meipai.com/media/',
            '皮皮虾' => 'https://h5.pipix.com/item/',
            '全民小视频' => 'https://quanmin.baidu.com/v/',
            '全民K歌' => 'https://kg.qq.com/node/play?s=',
            '六间房' => 'https://v.6.cn/video/',
            '新片场' => 'https://www.xinpianchang.com/a',
            '最右' => 'https://izuiyou.com/post/',
        ];
        return isset($map[$platform]) ? $map[$platform] . $videoId : 'Error: 不支持的平台';
    }
}
