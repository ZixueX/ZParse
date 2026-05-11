<?php
namespace MediaParser\Support;

use MediaParser\Logger;

final class YtDlp
{
    public static function extract(string $url, ?string $format = null): array
    {
        $cmd = ['yt-dlp', '-J', '--no-check-certificate', '--skip-download', '--quiet'];
        if ($format) {
            $cmd[] = '-f';
            $cmd[] = $format;
        }
        $cmd[] = $url;
        $command = implode(' ', array_map('escapeshellarg', $cmd));
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        @exec($command . ' 2>' . $null, $out, $code);
        if ($code !== 0 || !$out) {
            Logger::warning('yt-dlp extract failed for ' . $url);
            return [];
        }
        $json = json_decode(implode("\n", $out), true);
        return is_array($json) ? $json : [];
    }
}
