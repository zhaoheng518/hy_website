#!/usr/bin/env php
<?php

/**
 * Module 12: Generate Static Pages — CLI Command
 *
 * Pre-renders all product, category, and blog pages into static HTML files.
 * Requires the web server to be running and site_url configured in site.json.
 *
 * Usage:
 *   php app/commands/GenerateStaticPages.php
 *   php app/commands/GenerateStaticPages.php --lang=en
 *   php app/commands/GenerateStaticPages.php --lang=cn,es --type=blog
 *   php app/commands/GenerateStaticPages.php --type=product
 *   php app/commands/GenerateStaticPages.php --purge          (clear all cache)
 *   php app/commands/GenerateStaticPages.php --dry-run        (list URLs only)
 *   php app/commands/GenerateStaticPages.php --force          (re-generate even if cached)
 *
 * Exit codes: 0 = success, 1 = partial failure, 2 = configuration error
 *
 * Rollback: delete static_cache/ directory entirely, or run --purge.
 */

// ── Guard: CLI only ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.' . PHP_EOL);
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
define('ROOT_PATH',   dirname(__DIR__, 2));
define('APP_PATH',    ROOT_PATH . '/app');
define('DATA_PATH',   ROOT_PATH . '/app/data');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('VIEW_PATH',   ROOT_PATH . '/app/views');
define('ASSET_PATH',  ROOT_PATH . '/app/assets');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG',   false);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

spl_autoload_register(function (string $class) {
    $prefix  = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $rel   = substr($class, $len);
    $parts = explode('\\', $rel);
    $file  = array_pop($parts);
    $parts = array_map('strtolower', $parts);
    $fullPath = $baseDir . implode('/', $parts) . '/' . $file . '.php';
    if (file_exists($fullPath)) {
        require_once $fullPath;
    }
});

use App\Core\Config;
use App\Core\StaticCache;

// ── Load config ──────────────────────────────────────────────────────────────
if (!file_exists(DATA_PATH . '/site.json')) {
    fwrite(STDERR, "[ERROR] site.json not found at: " . DATA_PATH . "/site.json\n");
    exit(2);
}
Config::load(DATA_PATH . '/site.json');

// ── Parse CLI arguments ──────────────────────────────────────────────────────
$opts   = getopt('', array('lang:', 'type:', 'purge', 'dry-run', 'force', 'help'));
$dryRun = isset($opts['dry-run']);
$force  = isset($opts['force']);

if (isset($opts['help'])) {
    echo <<<HELP
Module 12: Static Page Generator

Usage:
  php app/commands/GenerateStaticPages.php [OPTIONS]

Options:
  --lang=en,cn,es      Languages to generate (default: all from site.json)
  --type=all|product|blog|category
                       Page types to generate (default: all)
  --force              Re-generate pages that are already cached
  --purge              Delete all static cache files and exit
  --dry-run            List URLs without generating anything
  --help               Show this help message

Examples:
  php app/commands/GenerateStaticPages.php
  php app/commands/GenerateStaticPages.php --lang=en --type=product
  php app/commands/GenerateStaticPages.php --purge

HELP;
    exit(0);
}

// ── Purge mode ───────────────────────────────────────────────────────────────
if (isset($opts['purge'])) {
    StaticCache::invalidateAll();
    echo "[OK] All static cache files have been deleted.\n";
    exit(0);
}

// ── Validate site_url ────────────────────────────────────────────────────────
$siteUrl = rtrim((string) Config::get('site_url', ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "[ERROR] site_url is not configured in site.json.\n");
    fwrite(STDERR, "        Set it to e.g. \"https://example.com\" and retry.\n");
    exit(2);
}

// ── Warn if static cache is disabled ─────────────────────────────────────────
if (!StaticCache::isEnabled()) {
    echo "[WARN] static_cache_enabled is false in site.json.\n";
    echo "       Cache files will be written but Router will NOT serve them.\n";
    echo "       Set \"static_cache_enabled\": true to activate serving.\n\n";
}

// ── Determine languages ──────────────────────────────────────────────────────
$allLangs = Config::get('supported_langs', array('en', 'cn', 'es'));
if (!is_array($allLangs)) {
    $allLangs = array('en');
}

if (isset($opts['lang'])) {
    $langs = array_filter(array_map('trim', explode(',', (string) $opts['lang'])));
} else {
    $langs = $allLangs;
}

$type = isset($opts['type']) ? strtolower(trim((string) $opts['type'])) : 'all';
$validTypes = array('all', 'product', 'blog', 'category');
if (!in_array($type, $validTypes, true)) {
    fwrite(STDERR, "[ERROR] Invalid --type value '{$type}'. Must be: all, product, blog, category\n");
    exit(2);
}

// ── Collect all URLs ─────────────────────────────────────────────────────────
echo "[INFO] Collecting URLs for languages: " . implode(', ', $langs) . "\n";
echo "[INFO] Page types: {$type}\n\n";

$urls = array();

foreach ($langs as $lang) {
    $lang    = trim((string) $lang);
    $langDir = DATA_PATH . '/' . $lang;

    if (!is_dir($langDir)) {
        echo "[SKIP] Language directory not found: {$langDir}\n";
        continue;
    }

    // Products index page
    if ($type === 'all' || $type === 'product') {
        $urls[] = $siteUrl . '/' . $lang . '/products';
    }

    // Category listing pages
    if ($type === 'all' || $type === 'category') {
        $catFile = $langDir . '/categories.json';
        $cats    = array();
        if (is_file($catFile)) {
            $decoded = json_decode(file_get_contents($catFile), true);
            $cats = is_array($decoded) ? $decoded : array();
        }
        foreach ($cats as $cat) {
            $slug = trim((string) ($cat['slug'] ?? ''));
            if ($slug !== '') {
                $urls[] = $siteUrl . '/' . $lang . '/products/' . rawurlencode($slug);
            }
        }
    }

    // Individual product detail pages
    if ($type === 'all' || $type === 'product') {
        $prodFile = $langDir . '/products.json';
        $products = array();
        if (is_file($prodFile)) {
            $decoded = json_decode(file_get_contents($prodFile), true);
            $products = is_array($decoded) ? $decoded : array();
        }

        // Also scan product shards directory if available
        $shardDir = $langDir . '/product_shards';
        if (is_dir($shardDir)) {
            $shards = glob($shardDir . '/*.json') ?: array();
            foreach ($shards as $shardFile) {
                $decoded = json_decode(file_get_contents($shardFile), true);
                if (is_array($decoded)) {
                    $products[] = $decoded;
                }
            }
        }

        $seenSlugs = array();
        foreach ($products as $p) {
            if (!is_array($p)) {
                continue;
            }
            $slug   = trim((string) ($p['slug'] ?? ''));
            $status = (string) ($p['status'] ?? 'published');
            if ($slug === '' || isset($seenSlugs[$slug])) {
                continue;
            }
            // Skip unpublished products
            if (!in_array($status, array('published', ''), true)) {
                continue;
            }
            $seenSlugs[$slug] = true;
            $urls[] = $siteUrl . '/' . $lang . '/product/' . rawurlencode($slug);
        }
    }

    // Blog index + individual posts
    if ($type === 'all' || $type === 'blog') {
        $urls[]   = $siteUrl . '/' . $lang . '/blog';
        $blogFile = $langDir . '/blog.json';
        $posts    = array();
        if (is_file($blogFile)) {
            $decoded = json_decode(file_get_contents($blogFile), true);
            $posts = is_array($decoded) ? $decoded : array();
        }
        foreach ($posts as $post) {
            if (!is_array($post)) {
                continue;
            }
            $slug   = trim((string) ($post['slug'] ?? ''));
            $status = (string) ($post['status'] ?? 'published');
            if ($slug !== '' && $status === 'published') {
                $urls[] = $siteUrl . '/' . $lang . '/blog/' . rawurlencode($slug);
            }
        }
    }
}

$total = count($urls);
echo "[INFO] Found {$total} URL(s) to process.\n\n";

if ($total === 0) {
    echo "[WARN] No URLs found. Check that data files exist and site_url is correct.\n";
    exit(0);
}

// ── Dry run: list and exit ────────────────────────────────────────────────────
if ($dryRun) {
    foreach ($urls as $u) {
        echo "  " . $u . "\n";
    }
    echo "\n[DRY-RUN] No files written.\n";
    exit(0);
}

// ── Generate pages ────────────────────────────────────────────────────────────
$ok = 0; $fail = 0; $skip = 0;
$startTime = microtime(true);

foreach ($urls as $i => $url) {
    $num = $i + 1;
    $path = (string) parse_url($url, PHP_URL_PATH);

    // Skip already-cached pages unless --force
    if (!$force && StaticCache::exists($path)) {
        echo "[SKIP] ({$num}/{$total}) {$url}\n";
        $skip++;
        continue;
    }

    $html = sgFetchPage($url);

    if ($html === null) {
        echo "[FAIL] ({$num}/{$total}) {$url}\n";
        $fail++;
    } else {
        // Write directly so cache is populated regardless of Controller path
        StaticCache::write($path, $html);
        echo "[OK]   ({$num}/{$total}) {$url}\n";
        $ok++;
    }

    // Polite delay to avoid overwhelming the server on large catalogs
    if ($ok % 10 === 0 && $ok > 0) {
        usleep(100000); // 100ms every 10 pages
    }
}

$elapsed = round(microtime(true) - $startTime, 1);
echo "\n";
echo str_repeat('-', 60) . "\n";
echo "[DONE] Generated: {$ok} | Skipped: {$skip} | Failed: {$fail} | Total: {$total} | Time: {$elapsed}s\n";

if ($fail > 0) {
    fwrite(STDERR, "[WARN] {$fail} page(s) failed to generate. Check error log.\n");
    exit(1);
}
exit(0);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Fetch a page via HTTP GET. Returns HTML string on HTTP 200, null otherwise.
 * Prefers cURL when available; falls back to file_get_contents stream.
 */
function sgFetchPage(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,   // do not follow 301 — we want exact page
            CURLOPT_USERAGENT      => 'StaticGenerator/1.0 (internal)',
            CURLOPT_HTTPHEADER     => array('X-Static-Generator: 1', 'Accept-Encoding: identity'),
            CURLOPT_ENCODING       => '',      // accept compressed responses
            CURLOPT_SSL_VERIFYPEER => false,   // allow self-signed certs on dev
            CURLOPT_SSL_VERIFYHOST => 0,
        ));
        $html = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            error_log('[GenerateStaticPages] cURL error for ' . $url . ': ' . $err);
            return null;
        }
        if ($code !== 200 || !is_string($html) || trim($html) === '') {
            error_log('[GenerateStaticPages] HTTP ' . $code . ' for ' . $url);
            return null;
        }
        return $html;
    }

    // Fallback: file_get_contents
    $ctx  = stream_context_create(array(
        'http' => array(
            'method'        => 'GET',
            'timeout'       => 30,
            'user_agent'    => 'StaticGenerator/1.0 (internal)',
            'header'        => "X-Static-Generator: 1\r\n",
            'ignore_errors' => true,
        ),
        'ssl' => array(
            'verify_peer'       => false,
            'verify_peer_name'  => false,
        ),
    ));
    $html = @file_get_contents($url, false, $ctx);
    if ($html === false || trim($html) === '') {
        error_log('[GenerateStaticPages] file_get_contents failed for ' . $url);
        return null;
    }

    // Verify HTTP 200 from response headers
    $responseHeaders = isset($http_response_header) ? $http_response_header : array();
    foreach ($responseHeaders as $h) {
        if (preg_match('#^HTTP/[\d.]+ (\d+)#i', $h, $m)) {
            if ((int) $m[1] !== 200) {
                error_log('[GenerateStaticPages] HTTP ' . $m[1] . ' for ' . $url);
                return null;
            }
        }
    }
    return $html;
}
