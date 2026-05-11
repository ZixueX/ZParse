<?php
namespace MediaParser;

final class HttpResponse
{
    public int $statusCode;
    public array $headers;
    public string $body;
    public array $cookies;

    public function __construct(int $statusCode, array $headers, string $body, array $cookies = [])
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->cookies = $cookies;
    }

    public function json(): array
    {
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : [];
    }

    public function header(string $name): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? null;
    }
}

final class HttpClient
{
    public static function get(string $url, array $headers = [], array $params = [], int $timeout = 10, bool $allowRedirects = true, bool $verify = false): HttpResponse
    {
        if ($params) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }
        return self::request('GET', $url, $headers, null, $timeout, $allowRedirects, $verify);
    }

    public static function post(string $url, array $headers = [], $body = null, ?array $json = null, int $timeout = 10, bool $allowRedirects = true, bool $verify = false): HttpResponse
    {
        if ($json !== null) {
            $body = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!self::hasHeader($headers, 'content-type')) {
                $headers['Content-Type'] = 'application/json; charset=UTF-8';
            }
        }
        return self::request('POST', $url, $headers, $body, $timeout, $allowRedirects, $verify);
    }

    public static function download(string $url, string $path, array $headers = [], int $timeout = 30): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        if (function_exists('curl_init')) {
            return self::curlDownload($url, $path, $headers, $timeout);
        }

        return self::streamDownload($url, $path, $headers, $timeout);
    }

    private static function request(string $method, string $url, array $headers, $body, int $timeout, bool $allowRedirects, bool $verify): HttpResponse
    {
        if (function_exists('curl_init')) {
            return self::curlRequest($method, $url, $headers, $body, $timeout, $allowRedirects, $verify);
        }
        return self::streamRequest($method, $url, $headers, $body, $timeout, $allowRedirects);
    }

    private static function curlRequest(string $method, string $url, array $headers, $body, int $timeout, bool $allowRedirects, bool $verify): HttpResponse
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => $allowRedirects,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => self::headerLines($headers),
        ]);
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : http_build_query((array)$body));
            }
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            Logger::warning('HTTP request failed: ' . $err . ' url=' . $url);
            return new HttpResponse(0, [], '', []);
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $headerRaw = substr((string)$raw, 0, $headerSize);
        $responseBody = substr((string)$raw, $headerSize);
        [$parsedHeaders, $cookies] = self::parseHeaders($headerRaw);
        return new HttpResponse($status, $parsedHeaders, $responseBody, $cookies);
    }

    private static function curlDownload(string $url, string $path, array $headers, int $timeout): bool
    {
        $tmpPath = $path . '.part';
        $fp = @fopen($tmpPath, 'wb');
        if (!$fp) {
            Logger::warning('Cannot open download file for writing: ' . $tmpPath);
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => self::headerLines($headers),
        ]);

        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = $ok === false ? curl_error($ch) : '';
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $status < 200 || $status >= 400 || !is_file($tmpPath) || filesize($tmpPath) <= 0) {
            @unlink($tmpPath);
            Logger::warning('HTTP download failed: status=' . $status . ($err ? ' error=' . $err : '') . ' url=' . $url);
            return false;
        }

        if (is_file($path)) {
            @unlink($path);
        }
        if (!@rename($tmpPath, $path)) {
            @unlink($tmpPath);
            Logger::warning('Cannot move downloaded file to: ' . $path);
            return false;
        }
        return true;
    }

    private static function streamRequest(string $method, string $url, array $headers, $body, int $timeout, bool $allowRedirects): HttpResponse
    {
        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", self::headerLines($headers)),
                'ignore_errors' => true,
                'timeout' => $timeout,
                'follow_location' => $allowRedirects ? 1 : 0,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];
        if ($body !== null) {
            $opts['http']['content'] = is_string($body) ? $body : http_build_query((array)$body);
        }
        $context = stream_context_create($opts);
        $responseBody = @file_get_contents($url, false, $context);
        $headerRaw = isset($http_response_header) ? implode("\r\n", $http_response_header) : '';
        [$parsedHeaders, $cookies, $status] = self::parseStreamHeaders($headerRaw);
        return new HttpResponse($status, $parsedHeaders, $responseBody === false ? '' : $responseBody, $cookies);
    }

    private static function streamDownload(string $url, string $path, array $headers, int $timeout): bool
    {
        $tmpPath = $path . '.part';
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", self::headerLines($headers)),
                'ignore_errors' => true,
                'timeout' => $timeout,
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];
        $context = stream_context_create($opts);
        $in = @fopen($url, 'rb', false, $context);
        if (!$in) {
            Logger::warning('HTTP stream download open failed: ' . $url);
            return false;
        }
        $out = @fopen($tmpPath, 'wb');
        if (!$out) {
            fclose($in);
            Logger::warning('Cannot open download file for writing: ' . $tmpPath);
            return false;
        }
        $bytes = @stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        if ($bytes === false || $bytes <= 0 || !is_file($tmpPath)) {
            @unlink($tmpPath);
            Logger::warning('HTTP stream download failed: ' . $url);
            return false;
        }
        if (is_file($path)) {
            @unlink($path);
        }
        if (!@rename($tmpPath, $path)) {
            @unlink($tmpPath);
            Logger::warning('Cannot move downloaded file to: ' . $path);
            return false;
        }
        return true;
    }

    private static function hasHeader(array $headers, string $name): bool
    {
        $needle = strtolower($name);
        foreach ($headers as $key => $_) {
            if (strtolower((string)$key) === $needle) {
                return true;
            }
        }
        return false;
    }

    private static function headerLines(array $headers): array
    {
        $lines = [];
        foreach ($headers as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_int($key)) {
                $lines[] = (string)$value;
            } else {
                $lines[] = $key . ': ' . $value;
            }
        }
        return $lines;
    }

    private static function parseHeaders(string $raw): array
    {
        $blocks = preg_split('/\r\n\r\n|\n\n|\r\r/', trim($raw)) ?: [];
        $last = $blocks ? end($blocks) : '';
        $headers = [];
        $cookies = [];
        foreach (preg_split('/\r\n|\n|\r/', (string)$last) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $key = strtolower(trim($name));
            $value = trim($value);
            if ($key === 'set-cookie') {
                $pair = explode(';', $value, 2)[0];
                if (str_contains($pair, '=')) {
                    [$ck, $cv] = explode('=', $pair, 2);
                    $cookies[$ck] = $cv;
                }
            }
            $headers[$key] = $value;
        }
        return [$headers, $cookies];
    }

    private static function parseStreamHeaders(string $raw): array
    {
        $headers = [];
        $cookies = [];
        $status = 0;
        foreach (preg_split('/\r\n|\n|\r/', $raw) ?: [] as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $line, $m)) {
                $status = (int)$m[1];
                $headers = [];
                continue;
            }
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $key = strtolower(trim($name));
            $value = trim($value);
            if ($key === 'set-cookie') {
                $pair = explode(';', $value, 2)[0];
                if (str_contains($pair, '=')) {
                    [$ck, $cv] = explode('=', $pair, 2);
                    $cookies[$ck] = $cv;
                }
            }
            $headers[$key] = $value;
        }
        return [$headers, $cookies, $status];
    }
}
