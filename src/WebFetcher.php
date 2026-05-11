<?php
namespace MediaParser;

final class WebFetcher
{
    public static function fetchRedirectUrl(?string $url, int $maxRedirects = 5): ?string
    {
        if (!$url) {
            return null;
        }
        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
            'User-Agent' => Config::randomUserAgentPc(),
        ];
        try {
            $current = $url;
            $redirect = null;
            for ($i = 0; $i < $maxRedirects; $i++) {
                $resp = HttpClient::get($current, $headers, [], 5, false, false);
                if ($resp->statusCode >= 400 || $resp->statusCode === 0) {
                    Logger::warning('redirect fetch failed status=' . $resp->statusCode . ' url=' . $current);
                    if (self::isSupportedUrl($current)) {
                        return UrlParser::extractVideoAddress($current);
                    }
                    if (self::isSupportedUrl($url)) {
                        return UrlParser::extractVideoAddress($url);
                    }
                    return null;
                }
                $redirect = $resp->header('location');
                if ($redirect) {
                    if (!preg_match('/^https?:\/\//i', $redirect)) {
                        $base = parse_url($current);
                        $redirect = ($base['scheme'] ?? 'https') . '://' . ($base['host'] ?? '') . (str_starts_with($redirect, '/') ? $redirect : '/' . $redirect);
                    }
                    $domain = UrlParser::getDomain($redirect);
                    if ($domain && isset(Config::domainToName()[$domain])) {
                        break;
                    }
                    $current = $redirect;
                } else {
                    break;
                }
            }
            if ($redirect) {
                return UrlParser::extractVideoAddress($redirect);
            }
            $domain = UrlParser::getDomain($url);
            if (!$domain || !isset(Config::domainToName()[$domain])) {
                return null;
            }
            return UrlParser::extractVideoAddress($url);
        } catch (\Throwable $e) {
            Logger::error('fetch redirect error: ' . $e->getMessage());
            return null;
        }
    }

    private static function isSupportedUrl(string $url): bool
    {
        $domain = UrlParser::getDomain($url);
        return $domain !== null && isset(Config::domainToName()[$domain]);
    }
}
