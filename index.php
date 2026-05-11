<?php
declare(strict_types=1);

require_once __DIR__ . '/src/autoload.php';

use MediaParser\ParserService;
use MediaParser\MediaProxy;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($scriptDir !== '/' && $scriptDir !== '.' && $scriptDir !== '' && strpos($path, $scriptDir . '/') === 0) {
    $path = substr($path, strlen($scriptDir));
}
if ($path === '/index.php') {
    $path = '/';
}

if (PHP_SAPI === 'cli-server' && $path !== '/') {
    $candidate = realpath(__DIR__ . $path);
    $root = realpath(__DIR__);
    if ($candidate && $root && str_starts_with($candidate, $root) && is_file($candidate)) {
        return false;
    }
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($path === '/api/parse' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $text = (string)($body['text'] ?? '');
    [$status, $payload] = (new ParserService())->parseWithStatus($text);
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($path === '/api/health' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'retcode' => 200,
        'retdesc' => 'ok',
        'data' => [
            'runtime' => 'php',
            'php_version' => PHP_VERSION,
            'curl' => function_exists('curl_init'),
            'openssl' => extension_loaded('openssl'),
            'curl_cainfo' => ini_get('curl.cainfo') ?: '',
            'openssl_cafile' => ini_get('openssl.cafile') ?: '',
            'allow_url_fopen' => filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN),
            'exec_enabled' => function_exists('exec') && stripos((string)ini_get('disable_functions'), 'exec') === false,
            'proc_open_enabled' => function_exists('proc_open') && stripos((string)ini_get('disable_functions'), 'proc_open') === false,
            'ffmpeg' => (static function (): bool {
                if (!function_exists('exec') || stripos((string)ini_get('disable_functions'), 'exec') !== false) {
                    return false;
                }
                @exec('ffmpeg -version 2>&1', $out, $code);
                return $code === 0;
            })(),
        ],
        'succ' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($path === '/api/proxy' && ($method === 'GET' || $method === 'HEAD')) {
    MediaProxy::stream((string)($_GET['u'] ?? ''), (string)($_GET['p'] ?? ''), (string)($_GET['s'] ?? ''), !empty($_GET['download']));
    exit;
}

if ($path === '/api/merge' && ($method === 'GET' || $method === 'HEAD')) {
    MediaProxy::streamMerged(
        (string)($_GET['v'] ?? ''),
        (string)($_GET['a'] ?? ''),
        (string)($_GET['p'] ?? ''),
        (string)($_GET['s'] ?? ''),
        !empty($_GET['download']),
        (string)($_GET['name'] ?? '')
    );
    exit;
}

if ($method === 'GET' && str_starts_with($path, '/static/')) {
    $relative = ltrim(substr($path, strlen('/static/')), '/\\');
    $roots = [__DIR__ . '/static', dirname(__DIR__) . '/static'];
    foreach ($roots as $rootDir) {
        $candidate = realpath($rootDir . '/' . $relative);
        $root = realpath($rootDir);
        if ($candidate && $root && str_starts_with($candidate, $root) && is_file($candidate)) {
            send_file($candidate);
            exit;
        }
    }
    http_response_code(404);
    echo 'Not Found';
    exit;
}

if ($path === '/' && $method === 'GET') {
    $template = is_file(__DIR__ . '/templates/landing.html')
        ? __DIR__ . '/templates/landing.html'
        : dirname(__DIR__) . '/templates/landing.html';
    header('Content-Type: text/html; charset=utf-8');
    if (is_file($template)) {
        readfile($template);
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>media-parser PHP</title><h1>media-parser PHP</h1><p>POST JSON to <code>/api/parse</code> with <code>{"text":"..."}</code>.</p>';
    }
    exit;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['retcode' => 404, 'retdesc' => 'Not Found', 'data' => null, 'succ' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function send_file(string $file): void
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = [
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4',
        'm4a' => 'audio/mp4',
    ];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    readfile($file);
}
