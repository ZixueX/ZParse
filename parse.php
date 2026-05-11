<?php
declare(strict_types=1);

require_once __DIR__ . '/src/autoload.php';

use MediaParser\ParserService;

if (($argc ?? 0) < 2) {
    fwrite(STDERR, "Usage: php parse.php \"分享文本或URL\"\n");
    exit(2);
}

$text = implode(' ', array_slice($argv, 1));
[$status, $payload] = (new ParserService())->parseWithStatus($text);
fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
exit($status >= 400 ? 1 : 0);

