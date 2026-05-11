<?php
namespace MediaParser;

final class Logger
{
    public static function debug(string $message): void { self::write('DEBUG', $message); }
    public static function info(string $message): void { self::write('INFO', $message); }
    public static function warning(string $message): void { self::write('WARNING', $message); }
    public static function error(string $message): void { self::write('ERROR', $message); }

    public static function exception(string $message, \Throwable $e): void
    {
        self::write('ERROR', $message . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    }

    private static function write(string $level, string $message): void
    {
        $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $level, $message);
        $dir = Config::rootDir() . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents($dir . '/media_parser_php.log', $line, FILE_APPEND);
    }
}
