<?php

namespace App\Core;

/**
 * DownloadLogger — records one row per tracked file download.
 *
 * Storage strategy (same pattern used by InquiryRepository):
 *   1. DB available  → INSERT into download_log table (see migration 20260511_create_download_log.sql).
 *   2. DB unavailable → append JSON line to DATA_PATH/.download_log.json (fallback, inspectable manually).
 *
 * All public methods are static; no instance state.
 * All failures are logged via error_log and silently swallowed — never bubble up to callers.
 */
class DownloadLogger
{
    private const TABLE    = 'download_log';
    private const LOG_FILE = '/.download_log.json';

    /**
     * Record a download event.
     *
     * @param array{
     *   product_slug?: string,
     *   file_name?:    string,
     *   file_url?:     string,
     *   ip?:           string,
     *   user_agent?:   string,
     *   lang?:         string
     * } $data
     */
    public static function log(array $data): void
    {
        $row = [
            'product_slug' => mb_substr((string) ($data['product_slug'] ?? ''), 0, 128, 'UTF-8'),
            'file_name'    => mb_substr((string) ($data['file_name']    ?? ''), 0, 256, 'UTF-8'),
            'file_url'     => mb_substr((string) ($data['file_url']     ?? ''), 0, 512, 'UTF-8'),
            'ip'           => mb_substr((string) ($data['ip']           ?? ''), 0, 45,  'UTF-8'),
            'user_agent'   => mb_substr((string) ($data['user_agent']   ?? ''), 0, 255, 'UTF-8'),
            'lang'         => mb_substr((string) ($data['lang']         ?? 'en'), 0, 10, 'UTF-8'),
            'created_at'   => date('Y-m-d H:i:s'),
        ];

        if (self::dbAvailable()) {
            self::insertDb($row);
        } else {
            self::appendJson($row);
        }
    }

    // ── Query helpers (used by AdminDownloadController) ────────────────────────

    /**
     * Total download count, optionally filtered by product_slug.
     */
    public static function countAll(string $productSlug = ''): int
    {
        if (!self::dbAvailable()) {
            return 0;
        }
        try {
            $db = Database::getInstance();
            if ($productSlug !== '') {
                return (int) $db->fetchColumn(
                    'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE product_slug = :slug',
                    ['slug' => $productSlug]
                );
            }
            return (int) $db->fetchColumn('SELECT COUNT(*) FROM ' . self::TABLE);
        } catch (\Throwable $e) {
            error_log('[DownloadLogger] countAll: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Aggregated download counts grouped by product_slug, ordered by count DESC.
     *
     * @return array<int, array{product_slug: string, total: int}>
     */
    public static function countByProduct(int $limit = 50): array
    {
        if (!self::dbAvailable()) {
            return [];
        }
        try {
            $db = Database::getInstance();
            return $db->fetchAll(
                'SELECT product_slug, COUNT(*) AS total
                   FROM ' . self::TABLE . '
                  GROUP BY product_slug
                  ORDER BY total DESC
                  LIMIT :limit',
                ['limit' => $limit]
            );
        } catch (\Throwable $e) {
            error_log('[DownloadLogger] countByProduct: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Recent download rows for admin list view.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $limit = 100, int $offset = 0): array
    {
        if (!self::dbAvailable()) {
            return [];
        }
        try {
            $db = Database::getInstance();
            return $db->fetchAll(
                'SELECT id, product_slug, file_name, ip, lang, created_at
                   FROM ' . self::TABLE . '
                  ORDER BY created_at DESC
                  LIMIT :limit OFFSET :offset',
                ['limit' => $limit, 'offset' => $offset]
            );
        } catch (\Throwable $e) {
            error_log('[DownloadLogger] recent: ' . $e->getMessage());
            return [];
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private static function dbAvailable(): bool
    {
        try {
            $db = Database::getInstance();
            // Lightweight check: confirm the table exists
            $db->fetchColumn('SELECT 1 FROM ' . self::TABLE . ' LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function insertDb(array $row): void
    {
        try {
            $db = Database::getInstance();

            // Try to resolve product_id from slug (nullable — non-blocking)
            $productId = null;
            if ($row['product_slug'] !== '') {
                try {
                    $productId = $db->fetchColumn(
                        'SELECT id FROM products WHERE slug = :slug LIMIT 1',
                        ['slug' => $row['product_slug']]
                    ) ?: null;
                } catch (\Throwable $e) {
                    // products table lookup failed — leave product_id NULL
                }
            }

            $db->insert(
                'INSERT INTO ' . self::TABLE . '
                 (product_id, product_slug, file_name, file_url, ip, user_agent, lang, created_at)
                 VALUES (:product_id, :product_slug, :file_name, :file_url, :ip, :user_agent, :lang, :created_at)',
                array_merge($row, ['product_id' => $productId])
            );
        } catch (\Throwable $e) {
            error_log('[DownloadLogger] DB insert failed: ' . $e->getMessage());
            // Fall back to JSON log so the event is not lost
            self::appendJson($row);
        }
    }

    private static function appendJson(array $row): void
    {
        if (!defined('DATA_PATH') || !is_dir(DATA_PATH)) {
            error_log('[DownloadLogger] fallback JSON: DATA_PATH not set. row=' . json_encode($row));
            return;
        }
        $line = json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents(DATA_PATH . self::LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    }
}
