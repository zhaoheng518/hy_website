<?php

declare(strict_types=1);

namespace App\Core;

/**
 * 404 Not-Found Logger
 *
 * Records 404 hits to app/data/404_logs.json.
 * - Deduplicates by URL (upserts: increments count, updates last_seen + context)
 * - Caps the log at MAX_ENTRIES unique URLs (keeps highest-count entries)
 * - All I/O is wrapped in try/catch; errors are silently swallowed so that
 *   a logging failure never breaks the 404 page displayed to the visitor.
 *
 * Log entry shape:
 * {
 *   "url":          "/en/product/old-slug",
 *   "count":        5,
 *   "first_seen":   "2026-05-11 10:55:00",
 *   "last_seen":    "2026-05-11 11:10:00",
 *   "last_referrer":"https://google.com/",
 *   "last_ip":      "1.2.3.4",
 *   "last_ua":      "Mozilla/5.0..."
 * }
 *
 * Compatible with PHP 7+, no Composer, no external dependencies.
 *
 * NOTE: referrer is recorded for diagnostic purposes ONLY.
 * It is never used for routing, redirect decisions, or any trusted logic.
 */
final class NotFoundLogger
{
    const MAX_ENTRIES    = 500;
    const URL_MAX_LEN    = 500;
    const REF_MAX_LEN    = 500;
    const UA_MAX_LEN     = 300;

    /**
     * Record a single 404 hit.  Never throws.
     *
     * @param string $url      The requested path (no query string)
     * @param string $referrer HTTP Referer header value (untrusted; audit only)
     * @param string $ip       Client IP from ClientIp::get()
     * @param string $ua       User-Agent string
     */
    public static function log(
        string $url,
        string $referrer,
        string $ip,
        string $ua
    ): void {
        try {
            $url      = mb_substr(trim($url),      0, self::URL_MAX_LEN);
            $referrer = mb_substr(trim($referrer), 0, self::REF_MAX_LEN);
            $ua       = mb_substr(trim($ua),       0, self::UA_MAX_LEN);
            $ip       = trim($ip);

            if ($url === '') {
                return;
            }

            $file = DATA_PATH . '/404_logs.json';
            $now  = date('Y-m-d H:i:s');

            // ── Atomic read-modify-write ──────────────────────────────────────
            $handle = @fopen($file, 'c+');
            if ($handle === false) {
                return;
            }

            // Acquire exclusive lock with short timeout (100 ms * 10 tries)
            $locked = false;
            for ($i = 0; $i < 10; $i++) {
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    $locked = true;
                    break;
                }
                usleep(10000);
            }
            if (!$locked) {
                fclose($handle);
                return;
            }

            try {
                // Read existing data
                $raw  = '';
                while (!feof($handle)) {
                    $chunk = fread($handle, 8192);
                    if ($chunk !== false) {
                        $raw .= $chunk;
                    }
                }
                $entries = [];
                if (trim($raw) !== '') {
                    $decoded = @json_decode($raw, true);
                    if (is_array($decoded)) {
                        $entries = $decoded;
                    }
                }

                // Upsert
                $found = false;
                foreach ($entries as &$entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    if (($entry['url'] ?? '') === $url) {
                        $entry['count']         = (int) ($entry['count'] ?? 0) + 1;
                        $entry['last_seen']     = $now;
                        $entry['last_referrer'] = $referrer;
                        $entry['last_ip']       = $ip;
                        $entry['last_ua']       = $ua;
                        $found = true;
                        break;
                    }
                }
                unset($entry);

                if (!$found) {
                    $entries[] = [
                        'url'           => $url,
                        'count'         => 1,
                        'first_seen'    => $now,
                        'last_seen'     => $now,
                        'last_referrer' => $referrer,
                        'last_ip'       => $ip,
                        'last_ua'       => $ua,
                    ];
                }

                // Sort by count desc; cap to MAX_ENTRIES
                usort($entries, static function (array $a, array $b): int {
                    return (int) ($b['count'] ?? 0) - (int) ($a['count'] ?? 0);
                });
                if (count($entries) > self::MAX_ENTRIES) {
                    $entries = array_slice($entries, 0, self::MAX_ENTRIES);
                }

                // Write back atomically
                $json = json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                if ($json !== false) {
                    ftruncate($handle, 0);
                    rewind($handle);
                    fwrite($handle, $json);
                    fflush($handle);
                }
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        } catch (\Throwable $e) {
            // Silently ignore — never let logging break the 404 response
            error_log('[NotFoundLogger] ' . $e->getMessage());
        }
    }

    /**
     * Read all log entries (sorted by count desc).
     * Returns [] on any error.
     */
    public static function readAll(): array
    {
        try {
            $file = DATA_PATH . '/404_logs.json';
            if (!is_file($file)) {
                return [];
            }
            $raw = @file_get_contents($file);
            if ($raw === false || trim($raw) === '') {
                return [];
            }
            $data = @json_decode($raw, true);
            if (!is_array($data)) {
                return [];
            }
            usort($data, static function (array $a, array $b): int {
                return (int) ($b['count'] ?? 0) - (int) ($a['count'] ?? 0);
            });
            return $data;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Remove a single entry by URL.
     */
    public static function dismiss(string $url): bool
    {
        try {
            $file = DATA_PATH . '/404_logs.json';
            $all  = self::readAll();
            $all  = array_values(array_filter($all, static function (array $e) use ($url): bool {
                return ($e['url'] ?? '') !== $url;
            }));
            return self::writeAll($all);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Clear entire log.
     */
    public static function clear(): bool
    {
        try {
            return self::writeAll([]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function writeAll(array $entries): bool
    {
        $file = DATA_PATH . '/404_logs.json';
        $json = json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }
        $tmp = $file . '.tmp.' . uniqid('', true);
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }
}
