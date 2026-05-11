<?php
namespace MediaParser;

final class Config
{
    private static ?array $business = null;

    public static function rootDir(): string
    {
        return dirname(__DIR__);
    }

    public static function businessConfig(): array
    {
        if (self::$business !== null) {
            return self::$business;
        }
        $paths = [
            self::rootDir() . '/config/business_config.json',
            dirname(self::rootDir()) . '/configs/business_config.json',
        ];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $json = json_decode((string)file_get_contents($path), true);
                if (is_array($json)) {
                    self::$business = $json;
                    return self::$business;
                }
            }
        }
        throw new \RuntimeException('business_config.json not found or invalid');
    }

    public static function domainToName(): array
    {
        return self::businessConfig()['DOMAIN_TO_NAME'] ?? [];
    }

    public static function userAgentPc(): array
    {
        return self::businessConfig()['USER_AGENT_PC'] ?? [];
    }

    public static function userAgentM(): array
    {
        return self::businessConfig()['USER_AGENT_M'] ?? [];
    }

    public static function randomUserAgentPc(): string
    {
        $items = self::userAgentPc();
        return $items ? $items[array_rand($items)] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome Safari/537.36';
    }

    public static function randomUserAgentM(): string
    {
        $items = self::userAgentM();
        return $items ? $items[array_rand($items)] : 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
    }

    public static function domain(): string
    {
        $domain = getenv('DOMAIN');
        return $domain === false ? '' : rtrim($domain, '/');
    }

    public static function staticDir(): string
    {
        return self::rootDir() . '/static';
    }

    public static function saveVideoPath(): string
    {
        $path = self::staticDir() . '/videos';
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        return $path;
    }

    public static function saveImagePath(): string
    {
        $path = self::staticDir() . '/images';
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        return $path;
    }
}
