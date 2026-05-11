<?php
namespace MediaParser\Support;

use MediaParser\Config;
use MediaParser\Logger;

final class DouyinSigner
{
    public string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

    public function getMsToken(int $length = 107): string
    {
        $base = 'ABCDEFGHIGKLMNOPQRSTUVWXYZabcdefghigklmnopqrstuvwxyz0123456789=';
        $out = '';
        $max = strlen($base) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $base[random_int(0, $max)];
        }
        return $out;
    }

    public function getABogus(string $reqUrl, string $userAgent): string
    {
        $query = (string)(parse_url($reqUrl, PHP_URL_QUERY) ?? '');
        $jsPath = Config::rootDir() . '/assets/douyin_utils/a_bogus.js';
        if (!is_file($jsPath)) {
            Logger::warning('a_bogus script not found: ' . $jsPath);
            return '';
        }
        $tmpBase = tempnam(sys_get_temp_dir(), 'abogus_');
        if ($tmpBase === false) {
            Logger::warning('a_bogus temp file creation failed in ' . sys_get_temp_dir());
            return '';
        }
        $tmp = $tmpBase . '.js';
        $script = "const fs=require('fs');\n" .
            "const code=fs.readFileSync(" . json_encode($jsPath, JSON_UNESCAPED_SLASHES) . ",'utf8');\n" .
            "eval(code);\n" .
            "process.stdout.write(generate_a_bogus(" . json_encode($query, JSON_UNESCAPED_SLASHES) . "," . json_encode($userAgent, JSON_UNESCAPED_SLASHES) . "));\n";
        if (@file_put_contents($tmp, $script) === false) {
            Logger::warning('a_bogus temp file write failed: ' . $tmp);
            @unlink($tmpBase);
            return '';
        }
        try {
            $node = getenv('NODE_BINARY');
            $node = ($node === false || $node === '') ? 'node' : $node;
            @exec(escapeshellarg($node) . ' ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
            if ($code === 0 && !empty($out)) {
                return trim(implode("\n", $out));
            }
            Logger::warning('a_bogus node bridge failed: code=' . $code . ' output=' . substr(implode("\n", $out), 0, 300));
        } catch (\Throwable $e) {
            Logger::warning('a_bogus node bridge failed: ' . $e->getMessage());
        } finally {
            @unlink($tmp);
            @unlink($tmpBase);
        }
        return '';
    }
}
