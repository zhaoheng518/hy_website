<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Media Metadata Store — centralized image SEO metadata layer.
 *
 * Stores alt, title, caption, dimensions, WebP status, file info,
 * and referenced_by for all uploaded images.
 *
 * Key format  : /uploads/images/example.jpg  (leading slash, full web path)
 * Storage     : app/data/media_metadata.json
 * Concurrency : flock(LOCK_EX) on every write
 * Performance : in-process read cache; disk only written on set()/delete()
 * Lazy scan   : lazyScan() is NEVER called automatically during list reads;
 *               callers invoke it explicitly for individual images or in batches.
 *
 * Security    : all path operations go through absPath() which enforces
 *               realpath() + UPLOAD_PATH whitelist; directory traversal is
 *               blocked at normalization time.
 *
 * PHP 8.0+ (matches existing codebase).
 */
final class MediaMetaStore
{
    // ── Constants ─────────────────────────────────────────────────────────────

    public const MAX_REFERENCED_BY = 20;
    public const LARGE_FILE_BYTES  = 512000; // 500 KB threshold for "large_file" filter

    /** Valid filter keys accepted by listImages(). */
    public const FILTERS = [
        'missing_alt',
        'missing_title',
        'missing_caption',
        'non_webp',
        'missing_dimensions',
        'large_file',
        'unreferenced',
    ];

    /** Image extensions scanned by listImages(). */
    private const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    // ── In-process read cache ─────────────────────────────────────────────────

    private static ?array $cache = null;

    // =========================================================================
    // Schema
    // =========================================================================

    /**
     * Canonical empty entry. All stored entries are merged against this so
     * consumers always receive every key even if the JSON predates a new field.
     */
    public static function defaultEntry(): array
    {
        return [
            'alt'           => '',
            'title'         => '',
            'caption'       => '',
            'width'         => 0,
            'height'        => 0,
            'mime'          => '',
            'filesize'      => 0,
            'webp'          => false,
            'webp_url'      => '',
            'referenced_by' => [],
            'updated_at'    => '',
        ];
    }

    // =========================================================================
    // Path helpers (security-critical)
    // =========================================================================

    /**
     * Normalise any uploads web path to /uploads/... with exactly one leading slash.
     * Returns '' if the path cannot be resolved to an uploads/ path.
     *
     * Input examples accepted:
     *   /uploads/images/xxx.jpg
     *   uploads/images/xxx.jpg
     *   /uploads/xxx.jpg          (legacy flat bucket)
     */
    public static function normalizeWebPath(string $webPath): string
    {
        $p = trim(str_replace('\\', '/', $webPath));
        $p = ltrim($p, '/');

        if ($p === '') {
            return '';
        }

        // Must be under uploads/
        if (!str_starts_with($p, 'uploads/')) {
            return '';
        }

        return '/' . $p;
    }

    /**
     * Validate that a normalized web path resolves to a real file that is
     * physically under UPLOAD_PATH (prevents directory traversal).
     */
    public static function validateWebPath(string $webPath): bool
    {
        $abs = self::absPath($webPath);
        return $abs !== null;
    }

    /**
     * Resolve a web path to its absolute filesystem path, or null if:
     *   – path cannot be normalised to /uploads/...
     *   – file does not exist
     *   – realpath is outside UPLOAD_PATH (traversal attempt)
     */
    private static function absPath(string $webPath): ?string
    {
        if (!defined('ROOT_PATH') || !defined('UPLOAD_PATH')) {
            return null;
        }

        $normalized = self::normalizeWebPath($webPath);
        if ($normalized === '') {
            return null;
        }

        $candidate = ROOT_PATH . $normalized;
        $real      = @realpath($candidate);
        if ($real === false || !is_file($real)) {
            return null;
        }

        $uploadRoot = @realpath(UPLOAD_PATH);
        if ($uploadRoot === false) {
            return null;
        }

        $normFile = str_replace('\\', '/', $real);
        $normRoot = str_replace('\\', '/', $uploadRoot);

        if ($normFile !== $normRoot && !str_starts_with($normFile, $normRoot . '/')) {
            return null; // traversal blocked
        }

        return $real;
    }

    // =========================================================================
    // File I/O
    // =========================================================================

    private static function filePath(): string
    {
        return defined('DATA_PATH') ? DATA_PATH . '/media_metadata.json' : '';
    }

    /**
     * Read the entire metadata file into the in-process cache.
     * Subsequent calls return the cached array without re-reading disk.
     */
    public static function readAll(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = self::filePath();
        if ($path === '' || !is_file($path)) {
            self::$cache = [];
            return [];
        }

        $raw  = @file_get_contents($path);
        $data = ($raw !== false && $raw !== '') ? @json_decode($raw, true) : null;

        self::$cache = is_array($data) ? $data : [];
        return self::$cache;
    }

    /**
     * Persist the entire $data array to disk.
     * Uses exclusive flock + truncate + rewind to prevent concurrent corruption.
     */
    private static function writeAll(array $data): bool
    {
        $path = self::filePath();
        if ($path === '') {
            return false;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            error_log('[MediaMetaStore] json_encode failed');
            return false;
        }

        $fh = @fopen($path, 'c');
        if ($fh === false) {
            error_log('[MediaMetaStore] fopen failed: ' . $path);
            return false;
        }

        $ok = false;
        if (flock($fh, LOCK_EX)) {
            ftruncate($fh, 0);
            rewind($fh);
            $written = fwrite($fh, $json);
            fflush($fh);
            flock($fh, LOCK_UN);
            $ok = ($written !== false);
        } else {
            error_log('[MediaMetaStore] flock failed: ' . $path);
        }
        fclose($fh);

        if ($ok) {
            // Update cache to the new state (avoid stale read after write)
            self::$cache = $data;
        }

        return $ok;
    }

    /**
     * Invalidate the in-process read cache.
     * Call after any external process modifies media_metadata.json.
     */
    public static function invalidateCache(): void
    {
        self::$cache = null;
    }

    // =========================================================================
    // Public CRUD
    // =========================================================================

    /**
     * Get metadata for a single web path.
     * Returns defaults merged with stored data; never triggers a lazy scan.
     */
    public static function get(string $webPath): array
    {
        $key   = self::normalizeWebPath($webPath);
        $all   = self::readAll();
        $entry = $all[$key] ?? null;

        if (!is_array($entry)) {
            return self::defaultEntry();
        }

        return array_merge(self::defaultEntry(), $entry);
    }

    /**
     * Save/merge metadata for a single web path.
     *
     * Only keys present in $updates are written; all other keys are preserved.
     * referenced_by is automatically capped at MAX_REFERENCED_BY.
     * updated_at is always refreshed on every call.
     *
     * Allowed updatable keys: alt, title, caption, width, height, mime,
     *   filesize, webp, webp_url, referenced_by.
     * The 'updated_at' key in $updates is ignored (auto-set here).
     */
    public static function set(string $webPath, array $updates): bool
    {
        $key = self::normalizeWebPath($webPath);
        if ($key === '') {
            error_log('[MediaMetaStore] set() invalid web path: ' . $webPath);
            return false;
        }

        // Re-read without cache to get the freshest state before merging.
        // This matters when multiple requests write to different keys concurrently.
        self::$cache = null;
        $all = self::readAll();

        $existing = is_array($all[$key] ?? null)
            ? array_merge(self::defaultEntry(), $all[$key])
            : self::defaultEntry();

        // Merge: only update the keys present in $updates
        $schema = self::defaultEntry();
        foreach ($updates as $k => $v) {
            if ($k === 'updated_at') {
                continue; // controlled below
            }
            if (array_key_exists($k, $schema)) {
                $existing[$k] = $v;
            }
        }

        // Cap referenced_by
        if (is_array($existing['referenced_by'])) {
            $existing['referenced_by'] = array_values(
                array_slice($existing['referenced_by'], 0, self::MAX_REFERENCED_BY)
            );
        }

        $existing['updated_at'] = date('Y-m-d H:i:s');
        $all[$key]              = $existing;
        self::$cache            = null; // cleared before write; writeAll sets new cache

        return self::writeAll($all);
    }

    /**
     * Remove a metadata entry (call when the corresponding file is deleted).
     * No-ops silently if the key is not found.
     */
    public static function delete(string $webPath): bool
    {
        $key = self::normalizeWebPath($webPath);
        if ($key === '') {
            return false;
        }

        self::$cache = null;
        $all = self::readAll();

        if (!array_key_exists($key, $all)) {
            return true; // nothing to delete
        }

        unset($all[$key]);
        self::$cache = null;
        return self::writeAll($all);
    }

    // =========================================================================
    // Lazy scan — dimensions, MIME, filesize, WebP
    // =========================================================================

    /**
     * Populate width/height/mime/filesize/webp for one image and persist.
     *
     * Rules:
     *  – If $force=false and width > 0 and mime !== '', skip and return cached.
     *  – getimagesize() is wrapped in try/catch + @ ; any failure → width=0, height=0.
     *  – WebP sidecar check: file_exists() + filesize() > 0  (not just naming).
     *  – A file that is itself a .webp has webp=true, webp_url = its own path.
     *  – Never throws; always returns an array.
     *
     * @param bool $force Re-scan even if dimensions are already stored.
     */
    public static function lazyScan(string $webPath, bool $force = false): array
    {
        $key     = self::normalizeWebPath($webPath);
        $current = self::get($key);

        if (!$force && (int) $current['width'] > 0 && $current['mime'] !== '') {
            return $current;
        }

        $abs = self::absPath($key);
        if ($abs === null) {
            return $current; // file doesn't exist; nothing to scan
        }

        $updates = [];

        // ── Dimensions + MIME ─────────────────────────────────────────────────
        try {
            $info = @getimagesize($abs);
            if (is_array($info)) {
                $updates['width']  = max(0, (int) ($info[0] ?? 0));
                $updates['height'] = max(0, (int) ($info[1] ?? 0));
                $updates['mime']   = (string) ($info['mime'] ?? '');
            } else {
                $updates['width']  = 0;
                $updates['height'] = 0;
                // finfo fallback for MIME (e.g. .gif, non-standard encodings)
                $updates['mime'] = self::detectMimeFinfo($abs);
            }
        } catch (\Throwable $e) {
            $updates['width']  = 0;
            $updates['height'] = 0;
            $updates['mime']   = '';
            error_log('[MediaMetaStore] lazyScan getimagesize error: ' . $e->getMessage() . ' path=' . $abs);
        }

        // ── Filesize ──────────────────────────────────────────────────────────
        try {
            $fs                = @filesize($abs);
            $updates['filesize'] = ($fs !== false) ? max(0, (int) $fs) : 0;
        } catch (\Throwable $e) {
            $updates['filesize'] = 0;
        }

        // ── WebP sidecar / self-WebP ──────────────────────────────────────────
        try {
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

            if ($ext === 'webp') {
                // The file itself is WebP — no sidecar needed
                $updates['webp']     = true;
                $updates['webp_url'] = $key;
            } elseif (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                // Look for ImageProcessor-generated sidecar: same name, .webp extension
                $webpAbs = (string) preg_replace('/\.(jpe?g|png)$/i', '.webp', $abs);
                if ($webpAbs !== $abs
                    && file_exists($webpAbs)
                    && filesize($webpAbs) > 0
                ) {
                    $updates['webp']     = true;
                    $updates['webp_url'] = self::absToWebPath($webpAbs);
                } else {
                    $updates['webp']     = false;
                    $updates['webp_url'] = '';
                }
            } else {
                $updates['webp']     = false;
                $updates['webp_url'] = '';
            }
        } catch (\Throwable $e) {
            $updates['webp']     = false;
            $updates['webp_url'] = '';
            error_log('[MediaMetaStore] lazyScan webp check error: ' . $e->getMessage() . ' path=' . $abs);
        }

        self::set($key, $updates);
        return self::get($key);
    }

    // =========================================================================
    // referenced_by — scan JSON data sources
    // =========================================================================

    /**
     * Scan all data JSON files across all supported languages and return the
     * list of content items that reference this image URL.
     *
     * Checks:
     *   products.json  → images[].url  +  image (legacy)
     *   blog.json      → image
     *   cases.json     → image
     *   pages.json     → featured_image
     *
     * Returns array of {type, slug, lang}; capped at MAX_REFERENCED_BY.
     * Does NOT write to the store — caller must call set() with the result.
     *
     * @return array{type:string,slug:string,lang:string}[]
     */
    public static function buildReferencedBy(string $webPath): array
    {
        if (!defined('DATA_PATH')) {
            return [];
        }

        $key  = self::normalizeWebPath($webPath);
        if ($key === '') {
            return [];
        }

        $langs = self::supportedLangs();
        $refs  = [];

        foreach ($langs as $lang) {
            if (count($refs) >= self::MAX_REFERENCED_BY) {
                break;
            }

            // products.json
            $new = self::scanJsonForRef(
                DATA_PATH . "/{$lang}/products.json",
                $key, $lang, 'product',
                static function (array $item): array {
                    $urls = [];
                    if (!empty($item['image']) && is_string($item['image'])) {
                        $urls[] = $item['image'];
                    }
                    if (!empty($item['images']) && is_array($item['images'])) {
                        foreach ($item['images'] as $img) {
                            if (is_array($img) && !empty($img['url'])) {
                                $urls[] = (string) $img['url'];
                            }
                        }
                    }
                    return $urls;
                }
            );
            $refs = array_merge($refs, $new);
            if (count($refs) >= self::MAX_REFERENCED_BY) {
                break;
            }

            // blog.json
            $refs = array_merge($refs, self::scanJsonForRef(
                DATA_PATH . "/{$lang}/blog.json",
                $key, $lang, 'blog',
                static function (array $item): array {
                    return (!empty($item['image']) && is_string($item['image']))
                        ? [$item['image']] : [];
                }
            ));
            if (count($refs) >= self::MAX_REFERENCED_BY) {
                break;
            }

            // cases.json
            $refs = array_merge($refs, self::scanJsonForRef(
                DATA_PATH . "/{$lang}/cases.json",
                $key, $lang, 'case',
                static function (array $item): array {
                    return (!empty($item['image']) && is_string($item['image']))
                        ? [$item['image']] : [];
                }
            ));
            if (count($refs) >= self::MAX_REFERENCED_BY) {
                break;
            }

            // pages.json
            $refs = array_merge($refs, self::scanJsonForRef(
                DATA_PATH . "/{$lang}/pages.json",
                $key, $lang, 'page',
                static function (array $item): array {
                    return (!empty($item['featured_image']) && is_string($item['featured_image']))
                        ? [$item['featured_image']] : [];
                }
            ));
        }

        return array_slice($refs, 0, self::MAX_REFERENCED_BY);
    }

    /**
     * Scan one JSON file for items that reference $targetKey.
     * Returns at most (MAX_REFERENCED_BY) entries per file.
     *
     * @param  callable $urlExtractor  fn(array $item): string[]
     * @return array{type:string,slug:string,lang:string}[]
     */
    private static function scanJsonForRef(
        string $file,
        string $targetKey,
        string $lang,
        string $type,
        callable $urlExtractor
    ): array {
        if (!is_file($file)) {
            return [];
        }

        $raw   = @file_get_contents($file);
        $items = ($raw !== false && $raw !== '') ? @json_decode($raw, true) : null;
        if (!is_array($items)) {
            return [];
        }

        $refs = [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['slug'])) {
                continue;
            }
            $urls = $urlExtractor($item);
            foreach ($urls as $u) {
                if (self::normalizeWebPath((string) $u) === $targetKey) {
                    $refs[] = [
                        'type' => $type,
                        'slug' => (string) $item['slug'],
                        'lang' => $lang,
                    ];
                    break; // one ref entry per content item
                }
            }
            if (count($refs) >= self::MAX_REFERENCED_BY) {
                break;
            }
        }

        return $refs;
    }

    // =========================================================================
    // Listing — for admin backend (Module B)
    // =========================================================================

    /**
     * Return a paginated list of images found on disk, merged with metadata.
     *
     * Performance contract:
     *  – Reads metadata from the in-process cache (no repeated disk reads).
     *  – Does NOT call getimagesize() or filesize() during listing.
     *  – Scans uploads/images/ directory + uploads/ (legacy flat) via scandir().
     *  – Each image entry has metadata merged in; missing entries get defaultEntry().
     *
     * @param int    $page   1-based page number
     * @param int    $limit  Items per page; clamped to [1, 500]; default 200
     * @param string $filter One of FILTERS constant values, or '' for no filter
     * @return array{
     *   items: array<array{web_path:string,alt:string,...}>,
     *   total: int,
     *   page: int,
     *   limit: int,
     *   pages: int
     * }
     */
    public static function listImages(int $page = 1, int $limit = 200, string $filter = ''): array
    {
        $limit = max(1, min(500, $limit));
        $page  = max(1, $page);
        $all   = self::readAll();

        // Scan both the images bucket and legacy flat root
        $rows = self::collectDiskImages($all);

        // Apply filter (operates on metadata fields only — no disk I/O)
        if ($filter !== '' && in_array($filter, self::FILTERS, true)) {
            $rows = array_values(array_filter(
                $rows,
                static fn(array $r) => self::matchesFilter($r, $filter)
            ));
        }

        $total  = count($rows);
        $pages  = max(1, (int) ceil($total / $limit));
        $offset = ($page - 1) * $limit;

        return [
            'items' => array_slice($rows, $offset, $limit),
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'pages' => $pages,
        ];
    }

    /**
     * Scan upload directories and merge each file with its cached metadata.
     * Called only from listImages(); no getimagesize() here.
     *
     * @return array<array{web_path:string,...}>
     */
    private static function collectDiskImages(array $all): array
    {
        if (!defined('UPLOAD_PATH')) {
            return [];
        }

        $uploadRoot = rtrim(str_replace('\\', '/', UPLOAD_PATH), '/');
        $scanDirs   = [
            UPLOAD_PATH . '/images',
            UPLOAD_PATH,
        ];

        $seen = [];
        $rows = [];

        foreach ($scanDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $items = @scandir($dir);
            if ($items === false) {
                continue;
            }

            $dirNorm   = rtrim(str_replace('\\', '/', $dir), '/');
            $relDir    = str_starts_with($dirNorm, $uploadRoot)
                ? substr($dirNorm, strlen($uploadRoot))
                : '';
            $webPrefix = '/uploads' . $relDir; // e.g. /uploads/images or /uploads

            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || $item[0] === '.') {
                    continue;
                }
                $absFile = $dir . '/' . $item;
                if (!is_file($absFile)) {
                    continue;
                }
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (!in_array($ext, self::IMAGE_EXTS, true)) {
                    continue;
                }

                $webPath = $webPrefix . '/' . $item;
                if (isset($seen[$webPath])) {
                    continue; // dedup (images dir takes priority over flat root)
                }
                $seen[$webPath] = true;

                $meta = is_array($all[$webPath] ?? null)
                    ? array_merge(self::defaultEntry(), $all[$webPath])
                    : self::defaultEntry();

                $rows[] = array_merge(['web_path' => $webPath], $meta);
            }
        }

        return $rows;
    }

    /**
     * Check whether a row matches the given filter key.
     * All comparisons use only fields from the metadata (no disk I/O).
     */
    private static function matchesFilter(array $row, string $filter): bool
    {
        return match ($filter) {
            'missing_alt'        => trim((string) ($row['alt'] ?? '')) === '',
            'missing_title'      => trim((string) ($row['title'] ?? '')) === '',
            'missing_caption'    => trim((string) ($row['caption'] ?? '')) === '',
            'non_webp'           => in_array(
                                        strtolower(pathinfo((string) ($row['web_path'] ?? ''), PATHINFO_EXTENSION)),
                                        ['jpg', 'jpeg', 'png'],
                                        true
                                    ) && empty($row['webp']),
            'missing_dimensions' => (int) ($row['width'] ?? 0) === 0,
            'large_file'         => (int) ($row['filesize'] ?? 0) > self::LARGE_FILE_BYTES,
            'unreferenced'       => empty($row['referenced_by']),
            default              => true,
        };
    }

    // =========================================================================
    // SEO badge helper
    // =========================================================================

    /**
     * Compute the SEO badge for a metadata row.
     *
     * Badge rules:
     *   critical  — missing alt AND the image is referenced (used in content)
     *   warning   — non-WebP original  OR  missing width/height
     *   info      — unreferenced (orphan image)
     *   ok        — alt present + WebP available (or is itself WebP) + has dimensions
     *
     * Returns one of: 'critical' | 'warning' | 'info' | 'ok'
     */
    public static function seobadge(array $row): string
    {
        $webPath    = (string) ($row['web_path'] ?? '');
        $ext        = strtolower(pathinfo($webPath, PATHINFO_EXTENSION));
        $alt        = trim((string) ($row['alt'] ?? ''));
        $referenced = !empty($row['referenced_by']);
        $hasWebp    = !empty($row['webp']) || $ext === 'webp';
        $hasDims    = (int) ($row['width'] ?? 0) > 0;
        $isOriginal = in_array($ext, ['jpg', 'jpeg', 'png'], true);

        if ($alt === '' && $referenced) {
            return 'critical';
        }

        if (($isOriginal && !$hasWebp) || !$hasDims) {
            return 'warning';
        }

        if (!$referenced) {
            return 'info';
        }

        return 'ok';
    }

    // =========================================================================
    // Private utilities
    // =========================================================================

    /**
     * Convert an absolute filesystem path inside UPLOAD_PATH to a web path.
     * Returns '' if the path is outside UPLOAD_PATH.
     */
    private static function absToWebPath(string $absPath): string
    {
        if (!defined('UPLOAD_PATH')) {
            return '';
        }
        $uploadRoot = @realpath(UPLOAD_PATH);
        if ($uploadRoot === false) {
            return '';
        }
        $real = @realpath($absPath);
        if ($real === false) {
            // File may not exist yet (e.g., pre-check)
            // Fall back to string manipulation for non-existent WebP sidecar paths
            $normAbs  = str_replace('\\', '/', $absPath);
            $normRoot = str_replace('\\', '/', $uploadRoot);
            if (!str_starts_with($normAbs, $normRoot)) {
                return '';
            }
            $rel = substr($normAbs, strlen($normRoot));
            return '/uploads' . $rel;
        }

        $normFile = str_replace('\\', '/', $real);
        $normRoot = str_replace('\\', '/', $uploadRoot);

        if (!str_starts_with($normFile, $normRoot . '/') && $normFile !== $normRoot) {
            return '';
        }

        $rel = substr($normFile, strlen($normRoot));
        return '/uploads' . $rel;
    }

    /**
     * Detect MIME type via finfo_file() as a fallback when getimagesize() fails.
     * Returns '' gracefully if finfo is unavailable.
     */
    private static function detectMimeFinfo(string $absPath): string
    {
        if (!function_exists('finfo_open')) {
            return '';
        }
        try {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi === false) {
                return '';
            }
            $mime = @finfo_file($fi, $absPath);
            finfo_close($fi);
            return is_string($mime) ? $mime : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Read the supported_langs array from site.json.
     * Falls back to ['en'] if site.json is unavailable or malformed.
     *
     * @return string[]
     */
    private static function supportedLangs(): array
    {
        if (!defined('DATA_PATH')) {
            return ['en'];
        }
        $path = DATA_PATH . '/site.json';
        if (!is_file($path)) {
            return ['en'];
        }
        $raw  = @file_get_contents($path);
        $site = ($raw !== false) ? @json_decode($raw, true) : null;
        if (!is_array($site) || empty($site['supported_langs'])) {
            return ['en'];
        }
        $langs = array_filter(array_map('trim', (array) $site['supported_langs']));
        return array_values($langs) ?: ['en'];
    }
}
