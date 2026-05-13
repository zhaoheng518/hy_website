<?php

define('APP_DEBUG', true);

define('ROOT_PATH', __DIR__);
define('APP_PATH', __DIR__ . '/app');
define('DATA_PATH', __DIR__ . '/app/data');
define('UPLOAD_PATH', __DIR__ . '/uploads');
define('VIEW_PATH', __DIR__ . '/app/views');
define('ASSET_PATH', __DIR__ . '/app/assets');
define('APP_VERSION', '1.0.0');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', DATA_PATH . '/error.log');

set_exception_handler(function (\Throwable $e) {
    error_log(sprintf(
        "[%s] %s in %s:%d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (APP_DEBUG) {
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>500 - Debug</title>';
        echo '<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4}';
        echo '.error{background:#2d2d2d;border:1px solid #555;padding:20px;border-radius:8px;margin-bottom:20px}';
        echo 'h1{color:#f44747}h2{color:#569cd6}.file{color:#4ec9b0}.line{color:#ce9178}';
        echo 'pre{background:#1a1a1a;padding:15px;border-radius:4px;overflow-x:auto;border:1px solid #333}';
        echo '.info{color:#608b4e;font-size:14px;margin-top:20px}</style></head><body>';
        echo '<h1>500 - Internal Server Error</h1>';
        echo '<div class="error">';
        echo '<h2>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</h2>';
        echo '<p><span class="file">' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . '</span>';
        echo ' : <span class="line">' . $e->getLine() . '</span></p>';
        echo '</div>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
        echo '<div class="info">PHP ' . PHP_VERSION . ' | ' . PHP_OS . ' | ' . php_sapi_name() . '</div>';
        echo '</body></html>';
    } elseif (file_exists(VIEW_PATH . '/front/500.php')) {
        require VIEW_PATH . '/front/500.php';
    } else {
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>500 - Server Error</title></head><body><h1>500 - Internal Server Error</h1><p>An unexpected error occurred. Please try again later.</p></body></html>';
    }
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        error_log("[DEPRECATED] {$message} in {$file}:{$line}");
        return true;
    }
    if ($severity === E_NOTICE || $severity === E_USER_NOTICE || $severity === E_STRICT) {
        error_log("[NOTICE] {$message} in {$file}:{$line}");
        return true;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $parts = explode('\\', $relativeClass);
    $fileName = array_pop($parts);
    $parts = array_map('strtolower', $parts);
    $file = $baseDir . implode('/', $parts) . '/' . $fileName . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

if (session_status() === PHP_SESSION_NONE) {
    try {
        $sessionSecure = false;
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $sessionSecure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $sessionSecure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            $sessionSecure = true;
        } elseif (($_SERVER['SERVER_PORT'] ?? '') === '443') {
            $sessionSecure = true;
        }

        $sessionOpts = [
            'cookie_httponly' => true,
            'cookie_secure' => $sessionSecure,
            'use_strict_mode' => true,
            'name' => 'STITCH_SID',
        ];
        if (PHP_VERSION_ID >= 70300) {
            $sessionOpts['cookie_samesite'] = 'Strict';
        }
        session_start($sessionOpts);
    } catch (\Throwable $e) {
        error_log("[SESSION] " . $e->getMessage());
    }
}

use App\Core\Config;
use App\Core\LegacyUrlRedirect;
use App\Core\Router;

if (!file_exists(DATA_PATH . '/site.json')) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Setup Required</title></head><body>';
    echo '<h1>Setup Required</h1>';
    echo '<p>The site configuration file is missing. Please create <code>app/data/site.json</code> before running the application.</p>';
    echo '</body></html>';
    exit;
}

Config::load(DATA_PATH . '/site.json');

function ensureLanguageDataDirectories(): void
{
    $supported = Config::get('supported_langs', ['en', 'cn', 'es']);
    if (!is_array($supported) || empty($supported)) {
        return;
    }
    $defaultLangDir = DATA_PATH . '/' . trim((string) Config::get('default_lang', 'en'));
    $templateDir = is_dir($defaultLangDir) ? $defaultLangDir : (DATA_PATH . '/en');
    if (!is_dir($templateDir)) {
        return;
    }

    $templateJsonFiles = glob($templateDir . '/*.json') ?: [];
    foreach ($supported as $lang) {
        $lang = trim((string) $lang);
        if ($lang === '') {
            continue;
        }
        $dir = DATA_PATH . '/' . $lang;
        $wasCreated = false;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            $wasCreated = true;
        }

        if ($wasCreated) {
            foreach ($templateJsonFiles as $srcFile) {
                $name = basename($srcFile);
                $dst = $dir . '/' . $name;
                if (!is_file($dst)) {
                    @copy($srcFile, $dst);
                }
            }
        }
    }
}

ensureLanguageDataDirectories();

LegacyUrlRedirect::maybeRedirect();

function isHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        return true;
    }
    if ($_SERVER['SERVER_PORT'] ?? '' === '443') {
        return true;
    }
    return false;
}

$host = $_SERVER['HTTP_HOST'] ?? '';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$needRedirect = false;
$redirectUrl = '';

if (strncmp($host, 'www.', 4) === 0) {
    $needRedirect = true;
    $redirectUrl = 'https://' . substr($host, 4) . $uri;
} elseif (!isHttps() && $host !== 'localhost' && $host !== '127.0.0.1') {
    $needRedirect = true;
    $redirectUrl = 'https://' . $host . $uri;
}

if ($needRedirect && $redirectUrl !== '') {
    header('Location: ' . $redirectUrl, true, 301);
    exit;
}

$router = new Router();
$router->dispatch();
