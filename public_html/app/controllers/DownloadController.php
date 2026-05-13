<?php

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Config;
use App\Core\DownloadLogger;

/**
 * DownloadController — Module 10: Datasheet Download Tracking
 *
 * Route: /{lang}/download/track
 * Query params:
 *   file    (required) — URL-encoded original file path/URL
 *   product (optional) — product slug
 *   label   (optional) — human-readable file name shown in logs
 *
 * Flow: validate file URL → log download → 302 redirect to actual file
 *
 * Security: only permits relative paths (starting with /) or absolute
 * URLs whose origin matches the configured site_url.
 * This prevents open-redirect abuse.
 */
class DownloadController extends BaseController
{
    /**
     * GET /{lang}/download/track
     */
    public function track(): void
    {
        $fileParam   = trim($this->getQuery('file', ''));
        $productSlug = preg_replace('/[^a-z0-9\-_]/i', '', trim($this->getQuery('product', '')));
        $label       = trim($this->getQuery('label', ''));

        if ($fileParam === '') {
            $this->redirect('/' . $this->lang . '/products');
        }

        $resolvedUrl = $this->validateFileUrl($fileParam);
        if ($resolvedUrl === null) {
            // Blocked — unknown origin; redirect safely home
            error_log('[DownloadController] blocked open-redirect attempt: ' . $fileParam);
            $this->redirect('/' . $this->lang . '/products');
        }

        // Derive a readable file name: prefer label param, then URL basename
        $fileName = $label !== ''
            ? $label
            : rawurldecode(basename(parse_url($fileParam, PHP_URL_PATH) ?? $fileParam));
        if ($fileName === '') {
            $fileName = 'download';
        }

        // Log — failure must never block the download
        try {
            DownloadLogger::log([
                'product_slug' => $productSlug,
                'file_name'    => $fileName,
                'file_url'     => $fileParam,
                'ip'           => ClientIp::get(),
                'user_agent'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'lang'         => $this->lang,
            ]);
        } catch (\Throwable $e) {
            error_log('[DownloadController] log exception: ' . $e->getMessage());
        }

        // 302 to the actual file — browser downloads/opens it
        if (!headers_sent()) {
            header('Cache-Control: no-store');
        }
        $this->redirect($resolvedUrl, 302);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Validate and return the safe target URL, or null if blocked.
     *
     * Allowed:
     *   - Relative path: starts with /  (same-server file)
     *   - Absolute URL: origin matches site_url config
     *
     * Blocked:
     *   - javascript: / data: schemes
     *   - Any other external domain
     */
    private function validateFileUrl(string $raw): ?string
    {
        $lower = strtolower($raw);

        // Block dangerous schemes unconditionally
        if (strncmp($lower, 'javascript:', 11) === 0 || strncmp($lower, 'data:', 5) === 0) {
            return null;
        }

        // Relative path — allowed as-is
        if (strlen($raw) > 0 && $raw[0] === '/') {
            return $raw;
        }

        // Absolute URL — must match our site_url origin
        if (strncmp($lower, 'http://', 7) === 0 || strncmp($lower, 'https://', 8) === 0) {
            $siteUrl = rtrim((string) Config::get('site_url', ''), '/');
            if ($siteUrl !== '' && strncmp($raw, $siteUrl, strlen($siteUrl)) === 0) {
                return $raw;
            }
            return null; // external domain — blocked
        }

        // Anything else (no scheme, relative without leading slash, etc.) — blocked
        return null;
    }

    /**
     * Read a query string parameter (GET).
     */
    private function getQuery(string $key, string $default = ''): string
    {
        return isset($_GET[$key]) ? (string) $_GET[$key] : $default;
    }
}
