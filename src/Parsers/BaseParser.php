<?php
namespace MediaParser\Parsers;

use MediaParser\Config;
use MediaParser\HttpClient;
use MediaParser\Logger;

abstract class BaseParser
{
    protected string $realUrl;
    protected array $headers = [];
    protected ?string $htmlContent = null;
    protected $data = null;

    public function __construct(string $realUrl)
    {
        $this->realUrl = $realUrl;
        $this->headers = ['User-Agent' => Config::randomUserAgentPc()];
    }

    public function getRealVideoUrl(): ?string { return null; }
    public function getTitleContent(): ?string { return null; }
    public function getCoverPhotoUrl(): ?string { return null; }
    public function getAuthorInfo(): ?array { return null; }
    public function getAudioUrl(): ?string { return null; }
    public function getImageList(): array { return []; }

    protected function fetchHtmlContent(int $timeout = 5): ?string
    {
        try {
            $resp = HttpClient::get($this->realUrl, $this->headers, [], $timeout, true, false);
            if ($resp->statusCode >= 200 && $resp->statusCode < 400) {
                $this->htmlContent = $resp->body;
                return $this->htmlContent;
            }
            Logger::error('Failed to get page: ' . $this->realUrl . ', status=' . $resp->statusCode);
        } catch (\Throwable $e) {
            Logger::error('fetch html error: ' . $e->getMessage());
        }
        return null;
    }

    protected static function parseHtmlData(?string $htmlContent, string $pattern): ?string
    {
        if (!$htmlContent) {
            return null;
        }
        $candidates = [];
        if (preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $htmlContent, $scripts)) {
            $candidates = $scripts[1];
        }
        $candidates[] = $htmlContent;
        foreach ($candidates as $candidate) {
            if (preg_match($pattern, $candidate, $m)) {
                $json = rtrim($m[1], ';');
                return str_replace('undefined', 'null', $json);
            }
        }
        return null;
    }

    protected function downloadAndSave(string $folder, ?string $url, string $extension): ?string
    {
        if (!$url) {
            return null;
        }
        if (!is_dir($folder)) {
            @mkdir($folder, 0777, true);
        }
        $filename = $this->uuidV4() . '.' . $extension;
        $path = $folder . '/' . $filename;
        try {
            if (HttpClient::download($url, $path, $this->headers, 300)) {
                return realpath($path) ?: $path;
            }
        } catch (\Throwable $e) {
            Logger::error('download failed: ' . $e->getMessage());
        }
        return null;
    }

    public function downloadAndSaveVideo(): ?string
    {
        return $this->downloadAndSave(Config::saveVideoPath(), $this->getRealVideoUrl(), 'mp4');
    }

    public function downloadAndSaveImage(): ?string
    {
        return $this->downloadAndSave(Config::saveImagePath(), $this->getCoverPhotoUrl(), 'jpg');
    }

    protected function arr($data, array $path, $default = null)
    {
        $cur = $data;
        foreach ($path as $key) {
            if (is_array($cur) && array_key_exists($key, $cur)) {
                $cur = $cur[$key];
            } else {
                return $default;
            }
        }
        return $cur;
    }

    protected function cleanHtml(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    protected function textSlice(string $text, int $start, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($text, $start, $length) : substr($text, $start, $length);
    }

    protected function firstRegex(string $pattern, ?string $text, int $group = 1): ?string
    {
        if ($text && preg_match($pattern, $text, $m)) {
            return $m[$group] ?? null;
        }
        return null;
    }

    protected function extractJsonObjectFromMarker(string $text, string $marker): ?string
    {
        $pos = strpos($text, $marker);
        if ($pos === false) {
            return null;
        }
        $start = strpos($text, '{', $pos + strlen($marker));
        return $this->extractJsonObjectAt($text, $start === false ? -1 : $start);
    }

    protected function extractJsonObjectAt(string $text, int $start): ?string
    {
        if ($start < 0) {
            return null;
        }
        $depth = 0;
        $inString = false;
        $quote = '';
        $escaped = false;
        $len = strlen($text);
        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($ch === $quote) {
                    $inString = false;
                }
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $inString = true;
                $quote = $ch;
                continue;
            }
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }
        return null;
    }

    protected function htmlAttr(string $tag, string $attr): ?string
    {
        $quoted = preg_quote($attr, '/');
        if (preg_match('/\s' . $quoted . '\s*=\s*(["\'])(.*?)\1/is', $tag, $m)) {
            return html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return null;
    }

    protected function prefixUrl(string $url, string $prefix): string
    {
        return str_starts_with($url, '/') ? $prefix . $url : $url;
    }

    protected function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    protected function runCommand(array $cmd): bool
    {
        $parts = array_map('escapeshellarg', $cmd);
        $command = implode(' ', $parts);
        @exec($command . ' 2>&1', $out, $code);
        if ($code !== 0) {
            Logger::error('command failed: ' . $command . ' output=' . implode("\n", $out));
        }
        return $code === 0;
    }
}
