<?php

namespace App\Core;

/**
 * Module 12 — Static HTML Page Cache
 *
 * Pre-rendered HTML files are stored as:
 *   static_cache/{lang}/{type}/{slug}.html
 *
 * Enabled via site.json:
 *   { "static_cache_enabled": true, "static_cache_ttl": 86400 }
 *
 * TTL = 0 means files never expire (manual invalidation only).
 *
 * Rollback: set "static_cache_enabled": false in site.json — Router
 *           stops reading cache; existing files are ignored.
 */
class StaticCache
{
    /** Resolved once per process. */
    private static $cacheRoot = '';

    private static function root(): string
    {
        if (self::$cacheRoot === '') {
            self::$cacheRoot = defined('ROOT_PATH')
                ? ROOT_PATH . '/static_cache'
                : dirname(__DIR__, 2) . '/static_cache';
        }
        return self::$cacheRoot;
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    public static function isEnabled(): bool
    {
        return (bool) Config::get('static_cache_enabled', false);
    }

    public static function ttl(): int
    {
        return max(0, (int) Config::get('static_cache_ttl', 86400));
    }

    // -------------------------------------------------------------------------
    // Path helpers
    // -------------------------------------------------------------------------

    /**
     * Derive the cache file path from a clean URI string.
     *
     *   /en/product/my-slug       →  {root}/en/product/my-slug.html
     *   /en/products              →  {root}/en/products.html
     *   / or ''                   →  {root}/index.html
     */
    public static function filePath(string $uri): string
    {
        $uri = trim($uri, '/');
        if ($uri === '') {
            $uri = 'index';
        }

        // Guard against path traversal attacks
        $raw = explode('/', $uri);
        $parts = array();
        foreach ($raw as $segment) {
            if ($segment !== '' && $segment !== '..' && $segment !== '.') {
                $parts[] = $segment;
            }
        }
        if (empty($parts)) {
            $parts = array('index');
        }

        return self::root() . '/' . implode('/', $parts) . '.html';
    }

    // -------------------------------------------------------------------------
    // Read / Serve
    // -------------------------------------------------------------------------

    /**
     * Return true when a fresh (non-expired) cache file exists for the URI.
     */
    public static function exists(string $uri): bool
    {
        $path = self::filePath($uri);
        if (!is_file($path)) {
            return false;
        }
        $ttl = self::ttl();
        return $ttl === 0 || (time() - filemtime($path)) <= $ttl;
    }

    /**
     * Try to serve a cached page.
     *
     * Returns true (and outputs the HTML) on cache hit.
     * Returns false on cache miss — caller should fall through to normal dispatch.
     *
     * Never serves: non-GET requests, admin routes, API routes, URLs with
     * query strings (search results, previews, etc.).
     */
    public static function tryServe(string $uri): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        // Only GET requests can be served from static cache
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return false;
        }

        // Never cache admin or API paths (Module 13: admin path is now configurable)
        $lower     = strtolower(ltrim($uri, '/'));
        $adminPath = strtolower(trim((string) Config::get('admin_path', 'admin'))) ?: 'admin';
        if (strpos($lower, $adminPath) === 0 || strpos($lower, 'api') === 0) {
            return false;
        }

        // Never serve when query string is present
        if (!empty($_SERVER['QUERY_STRING'])) {
            return false;
        }

        if (!self::exists($uri)) {
            return false;
        }

        $html = @file_get_contents(self::filePath($uri));
        if ($html === false || $html === '') {
            return false;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Static-Cache: HIT');
            $ttl = self::ttl();
            if ($ttl > 0) {
                // Allow CDN / Cloudflare to cache; browsers must revalidate
                header('Cache-Control: public, s-maxage=' . $ttl . ', max-age=0, must-revalidate');
            }
        }

        echo $html;
        return true;
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Atomically write HTML to the cache for a URI.
     *
     * This method does NOT check isEnabled() — it always writes when called.
     * Guards: empty HTML is silently ignored; directory is created if missing.
     */
    public static function write(string $uri, string $html): void
    {
        if (trim($html) === '') {
            return;
        }

        $path = self::filePath($uri);
        $dir  = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir)) {
            error_log('[StaticCache] Cannot create cache directory: ' . $dir);
            return;
        }

        // Atomic write: write to temp file then rename
        $tmp = $path . '.tmp.' . getmypid();
        if (@file_put_contents($tmp, $html, LOCK_EX) !== false) {
            @rename($tmp, $path);
        } else {
            @unlink($tmp);
            error_log('[StaticCache] Failed to write cache file: ' . $path);
        }
    }

    // -------------------------------------------------------------------------
    // Invalidation
    // -------------------------------------------------------------------------

    /**
     * Delete the cache file for a single URI.
     *
     * Example: StaticCache::invalidate('/en/product/my-slug')
     */
    public static function invalidate(string $uri): void
    {
        $path = self::filePath($uri);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Delete all cache files whose path starts with the given prefix.
     *
     * Example: StaticCache::invalidatePrefix('en/product') clears all
     *          /en/product/* pages and the /en/products listing.
     *
     * @param string $prefix  Slash-separated path prefix (no leading slash needed)
     */
    public static function invalidatePrefix(string $prefix): void
    {
        $prefix = trim($prefix, '/');
        $base   = self::root() . '/' . $prefix;

        // Remove leaf file: e.g. static_cache/en/products.html
        if (is_file($base . '.html')) {
            @unlink($base . '.html');
        }

        // Remove subtree: e.g. static_cache/en/product/
        if (is_dir($base)) {
            self::rmdirRecursive($base, true);
        }
    }

    /**
     * Delete every cached HTML file in all languages.
     */
    public static function invalidateAll(): void
    {
        if (!is_dir(self::root())) {
            return;
        }
        self::rmdirRecursive(self::root(), false);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Recursively delete directory contents.
     *
     * @param bool $removeSelf  When true the directory itself is also removed.
     */
    private static function rmdirRecursive(string $dir, bool $removeSelf = true): void
    {
        $items = @glob($dir . '/*');
        if (!is_array($items)) {
            $items = array();
        }
        foreach ($items as $item) {
            if (is_dir($item)) {
                self::rmdirRecursive($item, true);
            } else {
                @unlink($item);
            }
        }
        if ($removeSelf) {
            @rmdir($dir);
        }
    }
}
