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
     *   lang?:         string,
     *   source_url?:   string,
     *   referrer?:     string,
     *   utm_source?:   string,
     *   utm_medium?:   string,
     *   utm_campaign?: string,
     *   utm_term?:     string,
     *   utm_content?:  string,
     *   gclid?:        string,
     *   fbclid?:       string
     * } $data
     */
    public static function log(array $data): void
    {
        $ip = mb_substr((string) ($data['ip'] ?? ''), 0, 45, 'UTF-8');
        $ipHash = $ip !== '' ? hash('sha256', $ip . self::getIpSalt()) : '';

        $row = [
            'product_slug'  => mb_substr((string) ($data['product_slug']  ?? ''), 0, 128, 'UTF-8'),
            'file_name'     => mb_substr((string) ($data['file_name']     ?? ''), 0, 256, 'UTF-8'),
            'file_url'      => mb_substr((string) ($data['file_url']      ?? ''), 0, 512, 'UTF-8'),
            'ip'            => '', // Deprecated: do not store plaintext IP
            'ip_hash'       => $ipHash,
            'user_agent'    => mb_substr((string) ($data['user_agent']    ?? ''), 0, 255, 'UTF-8'),
            'lang'          => mb_substr((string) ($data['lang']          ?? 'en'), 0, 10, 'UTF-8'),
            'source_url'    => mb_substr((string) ($data['source_url']    ?? ''), 0, 512, 'UTF-8'),
            'referrer'      => mb_substr((string) ($data['referrer']      ?? ''), 0, 512, 'UTF-8'),
            'utm_source'    => mb_substr((string) ($data['utm_source']    ?? ''), 0, 256, 'UTF-8'),
            'utm_medium'    => mb_substr((string) ($data['utm_medium']    ?? ''), 0, 256, 'UTF-8'),
            'utm_campaign'  => mb_substr((string) ($data['utm_campaign']  ?? ''), 0, 256, 'UTF-8'),
            'utm_term'      => mb_substr((string) ($data['utm_term']      ?? ''), 0, 256, 'UTF-8'),
            'utm_content'   => mb_substr((string) ($data['utm_content']   ?? ''), 0, 256, 'UTF-8'),
            'gclid'         => mb_substr((string) ($data['gclid']         ?? ''), 0, 128, 'UTF-8'),
            'fbclid'        => mb_substr((string) ($data['fbclid']        ?? ''), 0, 128, 'UTF-8'),
            'created_at'    => date('Y-m-d H:i:s'),
        ];

        if (self::dbAvailable()) {
            self::insertDb($row);
        } else {
            self::appendJson($row);
        }
    }

    /**
     * Get a salt for IP hashing. Uses Config or falls back to a fixed value.
     */
    private static function getIpSalt(): string
    {
        static $salt = null;
        if ($salt === null) {
            $salt = (string) Config::get('download_ip_salt', 'download_tracking_salt_2026');
        }
        return $salt;
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
     * Supports DB first, JSON fallback when DB unavailable.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        if (self::dbAvailable()) {
            return self::recentFromDb($limit, $offset, $filters);
        }
        return self::recentFromJson($limit, $offset, $filters);
    }

    /**
     * Recent downloads from database with optional filters.
     */
    private static function recentFromDb(int $limit, int $offset, array $filters): array
    {
        try {
            $db = Database::getInstance();
            
            $whereParts = [];
            $params = ['limit' => $limit, 'offset' => $offset];
            
            if (!empty($filters['product_slug'])) {
                $whereParts[] = 'product_slug LIKE :product_slug';
                $params['product_slug'] = '%' . $filters['product_slug'] . '%';
            }
            if (!empty($filters['file_name'])) {
                $whereParts[] = 'file_name LIKE :file_name';
                $params['file_name'] = '%' . $filters['file_name'] . '%';
            }
            if (!empty($filters['utm_source'])) {
                $whereParts[] = 'utm_source LIKE :utm_source';
                $params['utm_source'] = '%' . $filters['utm_source'] . '%';
            }
            if (!empty($filters['utm_medium'])) {
                $whereParts[] = 'utm_medium LIKE :utm_medium';
                $params['utm_medium'] = '%' . $filters['utm_medium'] . '%';
            }
            if (!empty($filters['date_from'])) {
                $whereParts[] = 'created_at >= :date_from';
                $params['date_from'] = $filters['date_from'] . ' 00:00:00';
            }
            if (!empty($filters['date_to'])) {
                $whereParts[] = 'created_at <= :date_to';
                $params['date_to'] = $filters['date_to'] . ' 23:59:59';
            }
            
            $whereClause = empty($whereParts) ? '' : 'WHERE ' . implode(' AND ', $whereParts);
            
            return $db->fetchAll(
                'SELECT id, product_slug, file_name, ip, lang, utm_source, utm_medium, referrer, created_at
                   FROM ' . self::TABLE . '
                  ' . $whereClause . '
                  ORDER BY created_at DESC
                  LIMIT :limit OFFSET :offset',
                $params
            );
        } catch (\Throwable $e) {
            error_log('[DownloadLogger] recentFromDb: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Recent downloads from JSON fallback file.
     */
    private static function recentFromJson(int $limit, int $offset, array $filters): array
    {
        try {
            if (!defined('DATA_PATH') || !is_dir(DATA_PATH)) {
                return [];
            }
            $logFile = DATA_PATH . self::LOG_FILE;
            if (!file_exists($logFile)) {
                return [];
            }
            
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                return [];
            }
            
            // Limit to last 5000 lines for performance
            $lines = array_slice($lines, -5000);
            
            $results = [];
            foreach ($lines as $line) {
                $row = json_decode($line, true);
                if (!is_array($row)) {
                    continue;
                }
                
                // Apply filters
                if (!empty($filters['product_slug']) && 
                    strpos((string) ($row['product_slug'] ?? ''), $filters['product_slug']) === false) {
                    continue;
                }
                if (!empty($filters['file_name']) && 
                    strpos((string) ($row['file_name'] ?? ''), $filters['file_name']) === false) {
                    continue;
                }
                if (!empty($filters['utm_source']) && 
                    strpos((string) ($row['utm_source'] ?? ''), $filters['utm_source']) === false) {
                    continue;
                }
                if (!empty($filters['utm_medium']) && 
                    strpos((string) ($row['utm_medium'] ?? ''), $filters['utm_medium']) === false) {
                    continue;
                }
                
                $results[] = [
                    'id'           => null,
                    'product_slug' => $row['product_slug'] ?? '',
                    'file_name'    => $row['file_name'] ?? '',
                    'ip'           => '', // Don't expose IP from JSON
                    'lang'         => $row['lang'] ?? 'en',
                    'utm_source'   => $row['utm_source'] ?? '',
                    'utm_medium'   => $row['utm_medium'] ?? '',
                    'referrer'     => $row['referrer'] ?? '',
                    'created_at'   => $row['created_at'] ?? date('Y-m-d H:i:s'),
                ];
            }
            
            // Sort by created_at desc and apply offset/limit
            usort($results, function ($a, $b) {
                return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
            });
            
            return array_slice($results, $offset, $limit);
        } catch (\Throwable $e) {
            error_log('[DownloadLogger] recentFromJson: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Count downloads with optional filters.
     */
    public static function countWithFilters(array $filters): int
    {
        if (self::dbAvailable()) {
            return self::countWithFiltersFromDb($filters);
        }
        return self::countWithFiltersFromJson($filters);
    }

    private static function countWithFiltersFromDb(array $filters): int
    {
        try {
            $db = Database::getInstance();
            
            $whereParts = [];
            $params = [];
            
            if (!empty($filters['product_slug'])) {
                $whereParts[] = 'product_slug LIKE :product_slug';
                $params['product_slug'] = '%' . $filters['product_slug'] . '%';
            }
            if (!empty($filters['file_name'])) {
                $whereParts[] = 'file_name LIKE :file_name';
                $params['file_name'] = '%' . $filters['file_name'] . '%';
            }
            if (!empty($filters['utm_source'])) {
                $whereParts[] = 'utm_source LIKE :utm_source';
                $params['utm_source'] = '%' . $filters['utm_source'] . '%';
            }
            if (!empty($filters['utm_medium'])) {
                $whereParts[] = 'utm_medium LIKE :utm_medium';
                $params['utm_medium'] = '%' . $filters['utm_medium'] . '%';
            }
            if (!empty($filters['date_from'])) {
                $whereParts[] = 'created_at >= :date_from';
                $params['date_from'] = $filters['date_from'] . ' 00:00:00';
            }
            if (!empty($filters['date_to'])) {
                $whereParts[] = 'created_at <= :date_to';
                $params['date_to'] = $filters['date_to'] . ' 23:59:59';
            }
            
            $whereClause = empty($whereParts) ? '' : 'WHERE ' . implode(' AND ', $whereParts);
            
            return (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM ' . self::TABLE . ' ' . $whereClause,
                $params
            );
        } catch (\Throwable $e) {
            error_log('[DownloadLogger] countWithFiltersFromDb: ' . $e->getMessage());
            return 0;
        }
    }

    private static function countWithFiltersFromJson(array $filters): int
    {
        try {
            if (!defined('DATA_PATH') || !is_dir(DATA_PATH)) {
                return 0;
            }
            $logFile = DATA_PATH . self::LOG_FILE;
            if (!file_exists($logFile)) {
                return 0;
            }
            
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                return 0;
            }
            
            $count = 0;
            foreach ($lines as $line) {
                $row = json_decode($line, true);
                if (!is_array($row)) {
                    continue;
                }
                
                if (!empty($filters['product_slug']) && 
                    strpos((string) ($row['product_slug'] ?? ''), $filters['product_slug']) === false) {
                    continue;
                }
                if (!empty($filters['file_name']) && 
                    strpos((string) ($row['file_name'] ?? ''), $filters['file_name']) === false) {
                    continue;
                }
                $count++;
            }
            
            return $count;
        } catch (\Throwable $e) {
            error_log('[DownloadLogger] countWithFiltersFromJson: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Aggregated download counts grouped by file_name, ordered by count DESC.
     *
     * @return array<int, array{file_name: string, file_url: string, total: int, latest_download_at: string}>
     */
    public static function countByFile(int $limit = 10): array
    {
        if (self::dbAvailable()) {
            return self::countByFileFromDb($limit);
        }
        return self::countByFileFromJson($limit);
    }

    private static function countByFileFromDb(int $limit): array
    {
        try {
            $db = Database::getInstance();
            return $db->fetchAll(
                'SELECT file_name, file_url, COUNT(*) AS total, MAX(created_at) AS latest_download_at
                   FROM ' . self::TABLE . '
                  WHERE file_name != \'\'
                  GROUP BY file_name, file_url
                  ORDER BY total DESC
                  LIMIT :limit',
                ['limit' => $limit]
            );
        } catch (\Throwable $e) {
            error_log('[DownloadLogger] countByFileFromDb: ' . $e->getMessage());
            return [];
        }
    }

    private static function countByFileFromJson(int $limit): array
    {
        try {
            if (!defined('DATA_PATH') || !is_dir(DATA_PATH)) {
                return [];
            }
            $logFile = DATA_PATH . self::LOG_FILE;
            if (!file_exists($logFile)) {
                return [];
            }
            
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                return [];
            }
            
            $aggregated = [];
            foreach ($lines as $line) {
                $row = json_decode($line, true);
                if (!is_array($row)) {
                    continue;
                }
                
                $fileName = (string) ($row['file_name'] ?? '');
                if ($fileName === '') {
                    continue;
                }
                
                $fileUrl = (string) ($row['file_url'] ?? '');
                $key = $fileName . '|' . $fileUrl;
                
                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'file_name'          => $fileName,
                        'file_url'           => $fileUrl,
                        'total'              => 0,
                        'latest_download_at' => '',
                    ];
                }
                
                $aggregated[$key]['total']++;
                $aggregated[$key]['latest_download_at'] = max(
                    $aggregated[$key]['latest_download_at'],
                    (string) ($row['created_at'] ?? '')
                );
            }
            
            usort($aggregated, function ($a, $b) {
                return $b['total'] - $a['total'];
            });
            
            return array_slice($aggregated, 0, $limit);
        } catch (\Throwable $e) {
            error_log('[DownloadLogger] countByFileFromJson: ' . $e->getMessage());
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
                 (product_id, product_slug, file_name, file_url, ip, ip_hash, user_agent, lang,
                  source_url, referrer, utm_source, utm_medium, utm_campaign, utm_term, utm_content, gclid, fbclid, created_at)
                 VALUES (:product_id, :product_slug, :file_name, :file_url, :ip, :ip_hash, :user_agent, :lang,
                         :source_url, :referrer, :utm_source, :utm_medium, :utm_campaign, :utm_term, :utm_content, :gclid, :fbclid, :created_at)',
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