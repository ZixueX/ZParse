<?php
namespace MediaParser;

final class MediaProxy
{
    public static function makeProxyUrl(?string $url, string $platform): ?string
    {
        if (!$url || !preg_match('/^https?:\/\//i', $url)) {
            return null;
        }
        $encoded = self::base64UrlEncode($url);
        $sig = self::sign($platform, $url);
        return 'api/proxy?u=' . rawurlencode($encoded) . '&p=' . rawurlencode($platform) . '&s=' . rawurlencode($sig);
    }

    public static function makeMergeUrl(?string $videoUrl, ?string $audioUrl, string $platform): ?string
    {
        if (!$videoUrl || !$audioUrl || !preg_match('/^https?:\/\//i', $videoUrl) || !preg_match('/^https?:\/\//i', $audioUrl)) {
            return null;
        }
        $video = self::base64UrlEncode($videoUrl);
        $audio = self::base64UrlEncode($audioUrl);
        $sig = self::signMerge($platform, $videoUrl, $audioUrl);
        return 'api/merge?v=' . rawurlencode($video) . '&a=' . rawurlencode($audio) . '&p=' . rawurlencode($platform) . '&s=' . rawurlencode($sig);
    }

    public static function stream(string $encodedUrl, string $platform, string $sig, bool $forceDownload = false): void
    {
        $url = self::base64UrlDecode($encodedUrl);
        if (!$url || !hash_equals(self::sign($platform, $url), $sig) || !self::isAllowedUrl($url)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['retcode' => 403, 'retdesc' => 'Forbidden proxy url', 'data' => null, 'succ' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!function_exists('curl_init')) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['retcode' => 500, 'retdesc' => 'cURL extension is required for media proxy', 'data' => null, 'succ' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        @set_time_limit($forceDownload ? 1800 : 180);
        ignore_user_abort(false);
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $referer = self::refererForPlatform($platform, $url);
        $requestHeaders = [
            'User-Agent: ' . Config::randomUserAgentPc(),
            'Referer: ' . $referer,
            'Accept: */*',
            'Accept-Encoding: identity',
            'Connection: close',
        ];
        if (!empty($_SERVER['HTTP_RANGE'])) {
            $requestHeaders[] = 'Range: ' . preg_replace('/[\r\n].*/', '', (string)$_SERVER['HTTP_RANGE']);
        }

        $responseHeaders = [];
        $statusCode = 200;
        $headersSent = false;
        $fallbackType = self::guessContentType($url);

        $sendHeaders = function () use (&$headersSent, &$responseHeaders, &$statusCode, $fallbackType, $forceDownload, $url): void {
            if ($headersSent) {
                return;
            }
            $headersSent = true;
            http_response_code($statusCode ?: 200);
            header('Access-Control-Allow-Origin: *');
            header('X-Accel-Buffering: no');
            if (empty($responseHeaders['content-type'])) {
                header('Content-Type: ' . $fallbackType);
            }
            if ($forceDownload) {
                header('Content-Disposition: attachment; filename="' . self::downloadFilename($url) . '"');
            }
            foreach ($responseHeaders as $name => $value) {
                $name = strtolower($name);
                if ($forceDownload && $name === 'content-disposition') {
                    continue;
                }
                if (!in_array($name, ['content-type', 'content-length', 'content-range', 'accept-ranges', 'cache-control', 'expires', 'last-modified', 'etag', 'content-disposition'], true)) {
                    continue;
                }
                $safeValue = preg_replace('/[\r\n]+/', ' ', (string)$value);
                header(self::canonicalHeaderName($name) . ': ' . $safeValue);
            }
        };

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => $forceDownload ? 1800 : 180,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => 30,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders, &$statusCode): int {
                $trim = trim($line);
                if ($trim === '') {
                    return strlen($line);
                }
                if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $trim, $m)) {
                    $statusCode = (int)$m[1];
                    $responseHeaders = [];
                    return strlen($line);
                }
                if (strpos($trim, ':') !== false) {
                    [$name, $value] = explode(':', $trim, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use ($sendHeaders): int {
                if (connection_aborted()) {
                    return 0;
                }
                $sendHeaders();
                echo $chunk;
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
                if (connection_aborted()) {
                    return 0;
                }
                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        if ($ok === false && $headersSent && connection_aborted()) {
            curl_close($ch);
            return;
        }
        if (!$headersSent) {
            if ($ok === false) {
                http_response_code(502);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['retcode' => 502, 'retdesc' => 'Proxy upstream error: ' . curl_error($ch), 'data' => null, 'succ' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $sendHeaders();
            }
        }
        curl_close($ch);
    }

    public static function streamMerged(string $encodedVideoUrl, string $encodedAudioUrl, string $platform, string $sig, bool $forceDownload = true, string $filename = ''): void
    {
        $videoUrl = self::base64UrlDecode($encodedVideoUrl);
        $audioUrl = self::base64UrlDecode($encodedAudioUrl);
        if (
            !$videoUrl || !$audioUrl ||
            !hash_equals(self::signMerge($platform, $videoUrl, $audioUrl), $sig) ||
            !self::isAllowedUrl($videoUrl) ||
            !self::isAllowedUrl($audioUrl)
        ) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['retcode' => 403, 'retdesc' => 'Forbidden merge url', 'data' => null, 'succ' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!self::isFunctionAvailable('proc_open')) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['retcode' => 500, 'retdesc' => 'proc_open is required for ffmpeg merge', 'data' => null, 'succ' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $ffmpeg = self::ffmpegBinary();
        if (!self::canRunCommand($ffmpeg)) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['retcode' => 500, 'retdesc' => 'ffmpeg not found or not executable', 'data' => null, 'succ' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        @set_time_limit(1800);
        ignore_user_abort(false);
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $referer = self::refererForPlatform($platform, $videoUrl);
        $userAgent = Config::randomUserAgentPc();
        $ffmpegHeaders = "Referer: {$referer}\r\nUser-Agent: {$userAgent}\r\nAccept: */*\r\n";
        $cmd = [
            $ffmpeg,
            '-hide_banner',
            '-loglevel',
            'error',
            '-headers',
            $ffmpegHeaders,
            '-i',
            $videoUrl,
            '-headers',
            $ffmpegHeaders,
            '-i',
            $audioUrl,
            '-map',
            '0:v:0',
            '-map',
            '1:a:0',
            '-c',
            'copy',
            '-movflags',
            'frag_keyframe+empty_moov',
            '-f',
            'mp4',
            'pipe:1',
        ];
        $command = implode(' ', array_map('escapeshellarg', $cmd));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['retcode' => 500, 'retdesc' => 'failed to start ffmpeg', 'data' => null, 'succ' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        http_response_code(200);
        header('Access-Control-Allow-Origin: *');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-store');
        header('Content-Type: video/mp4');
        if ($forceDownload) {
            header('Content-Disposition: attachment; filename="' . self::safeDownloadName($filename ?: 'media-parser-video.mp4') . '"');
        }

        $stderr = '';
        $stderrLimit = 4096;
        while (true) {
            if (connection_aborted()) {
                @proc_terminate($process);
                break;
            }

            $read = [];
            if (!feof($pipes[1])) {
                $read[] = $pipes[1];
            }
            if (!feof($pipes[2])) {
                $read[] = $pipes[2];
            }
            if (!$read) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                usleep(100000);
                continue;
            }

            $write = null;
            $except = null;
            $changed = @stream_select($read, $write, $except, 1, 0);
            if ($changed === false) {
                break;
            }
            if ($changed === 0) {
                $status = proc_get_status($process);
                if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                    break;
                }
                continue;
            }
            foreach ($read as $stream) {
                if ($stream === $pipes[1]) {
                    $chunk = fread($pipes[1], 8192);
                    if ($chunk !== false && $chunk !== '') {
                        echo $chunk;
                        flush();
                    }
                } else {
                    $err = fread($pipes[2], 4096);
                    if ($err !== false && $err !== '' && strlen($stderr) < $stderrLimit) {
                        $stderr .= $err;
                    }
                }
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code > 0) {
            Logger::error('ffmpeg merge failed code=' . $code . ' stderr=' . trim($stderr));
        }
    }

    private static function sign(string $platform, string $url): string
    {
        $secret = getenv('SECRET_KEY');
        if ($secret === false || $secret === '') {
            $secret = 'default_secret_key';
        }
        return hash_hmac('sha256', $platform . '|' . $url, $secret);
    }

    private static function signMerge(string $platform, string $videoUrl, string $audioUrl): string
    {
        $secret = getenv('SECRET_KEY');
        if ($secret === false || $secret === '') {
            $secret = 'default_secret_key';
        }
        return hash_hmac('sha256', 'merge|' . $platform . '|' . $videoUrl . '|' . $audioUrl, $secret);
    }

    private static function refererForPlatform(string $platform, string $url): string
    {
        $map = [
            '哔哩哔哩' => 'https://www.bilibili.com/',
            '快手' => 'https://www.kuaishou.com/',
            '抖音' => 'https://www.douyin.com/',
            '小红书' => 'https://www.xiaohongshu.com/',
            '微博' => 'https://weibo.com/',
            '西瓜视频' => 'https://www.ixigua.com/',
            '好看视频' => 'https://haokan.baidu.com/',
            '微视' => 'https://isee.weishi.qq.com/',
            '梨视频' => 'https://www.pearvideo.com/',
            'AcFun' => 'https://www.acfun.cn/',
            'YouTube' => 'https://www.youtube.com/',
            'TikTok' => 'https://www.tiktok.com/',
        ];
        if (isset($map[$platform])) {
            return $map[$platform];
        }
        $parts = parse_url($url);
        return (($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . '/');
    }

    private static function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower($parts['host']);
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        return true;
    }

    private static function guessContentType(string $url): string
    {
        $path = strtolower((string)(parse_url($url, PHP_URL_PATH) ?: ''));
        if (preg_match('/\.m3u8$/', $path)) return 'application/vnd.apple.mpegurl';
        if (preg_match('/\.(mp4|m4s)$/', $path)) return 'video/mp4';
        if (preg_match('/\.m4a$/', $path)) return 'audio/mp4';
        if (preg_match('/\.mp3$/', $path)) return 'audio/mpeg';
        if (preg_match('/\.webm$/', $path)) return 'video/webm';
        if (preg_match('/\.jpe?g$/', $path)) return 'image/jpeg';
        if (preg_match('/\.png$/', $path)) return 'image/png';
        if (preg_match('/\.gif$/', $path)) return 'image/gif';
        return 'application/octet-stream';
    }

    private static function canonicalHeaderName(string $name): string
    {
        return implode('-', array_map('ucfirst', explode('-', $name)));
    }

    private static function downloadFilename(string $url): string
    {
        $path = (string)(parse_url($url, PHP_URL_PATH) ?: '');
        $name = basename($path);
        if ($name === '' || $name === '/' || strpos($name, '.') === false) {
            $name = 'media-parser-video.mp4';
        }
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
        return $name ?: 'media-parser-video.mp4';
    }

    private static function safeDownloadName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
        if (!$name || strpos($name, '.') === false) {
            return 'media-parser-video.mp4';
        }
        return $name;
    }

    private static function ffmpegBinary(): string
    {
        $binary = getenv('FFMPEG_BINARY');
        if ($binary !== false && $binary !== '') {
            return $binary;
        }
        $assetsDir = dirname(__DIR__) . '/assets/';
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $local = $assetsDir . ($isWindows ? 'ffmpeg.exe' : 'ffmpeg');
        if (is_file($local)) {
            return $local;
        }
        return 'ffmpeg';
    }

    private static function isFunctionAvailable(string $name): bool
    {
        if (!function_exists($name)) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        return !in_array($name, $disabled, true);
    }

    private static function canRunCommand(string $binary): bool
    {
        if (strpos($binary, '/') !== false || strpos($binary, '\\') !== false) {
            return is_file($binary) && (DIRECTORY_SEPARATOR === '\\' || is_executable($binary));
        }
        if (!self::isFunctionAvailable('exec')) {
            return true;
        }
        $cmd = DIRECTORY_SEPARATOR === '\\' ? 'where ' : 'command -v ';
        @exec($cmd . escapeshellarg($binary) . ' 2>&1', $out, $code);
        return $code === 0 && !empty($out);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $padded = strtr($value, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad) {
            $padded .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($padded, true);
        return $decoded === false ? null : $decoded;
    }
}
