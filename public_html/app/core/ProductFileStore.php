<?php

namespace App\Core;

/**
 * ProductFileStore — Module 11: JSON Shard Optimization
 *
 * Problem solved:
 *   As product catalog grows, app/data/{lang}/products.json becomes large.
 *   Every page load (list, detail, compare, search) reads the full file.
 *
 * Solution:
 *   Each product gets its own shard file:
 *     app/data/{lang}/products/{slug}.json
 *   A lightweight index is maintained:
 *     app/data/{lang}/products_index.json
 *   Index fields: id, title, slug, status, updated_at
 *
 * Backward compatibility:
 *   - products.json remains the master source of truth.
 *   - Shards are derived from products.json and regenerated after every write.
 *   - All existing controllers using JsonStore::langData($lang,'products')->read()
 *     continue to work unchanged.
 *   - ProductController::show() uses getBySlug() as a fast path that reads one
 *     shard instead of the full array; falls back gracefully if shards don't exist.
 *
 * flock strategy:
 *   - All writes use atomic write (temp file + rename) so readers never see partial data.
 *   - Index writes use LOCK_EX via fopen/flock for safe concurrent access.
 *
 * All public methods are static; no instance state.
 */
class ProductFileStore
{
    // ── Path helpers ────────────────────────────────────────────────────────────

    /**
     * Directory that holds shard files for one language.
     * e.g. /var/www/app/data/en/products/
     */
    public static function shardDir(string $lang): string
    {
        return DATA_PATH . '/' . $lang . '/products';
    }

    /**
     * Path to an individual product shard file.
     * e.g. /var/www/app/data/en/products/nexus-core-8.json
     */
    public static function shardPath(string $lang, string $slug): string
    {
        // Slug sanitisation: only allow filesystem-safe characters
        $safe = preg_replace('/[^a-z0-9\-_]/i', '', $slug);
        if ($safe === '') {
            throw new \InvalidArgumentException("Invalid product slug for shard path: '{$slug}'");
        }
        return self::shardDir($lang) . '/' . $safe . '.json';
    }

    /**
     * Path to the lightweight index file for one language.
     * e.g. /var/www/app/data/en/products_index.json
     */
    public static function indexPath(string $lang): string
    {
        return DATA_PATH . '/' . $lang . '/products_index.json';
    }

    // ── Read API ────────────────────────────────────────────────────────────────

    /**
     * Read a single product from its shard file.
     * Returns null if the shard doesn't exist (caller must fall back to products.json).
     *
     * @return array<string,mixed>|null
     */
    public static function getBySlug(string $lang, string $slug): ?array
    {
        try {
            $path = self::shardPath($lang, $slug);
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        if (!is_file($path)) {
            return null;
        }

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return null;
        }

        flock($handle, LOCK_SH);
        $content = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            if ($chunk !== false) {
                $content .= $chunk;
            }
        }
        flock($handle, LOCK_UN);
        fclose($handle);

        if (trim($content) === '') {
            return null;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            error_log('[ProductFileStore] JSON decode error in shard ' . $path . ': ' . json_last_error_msg());
            return null;
        }

        return $data;
    }

    /**
     * Read the lightweight index for one language.
     * Returns empty array if index hasn't been built yet.
     *
     * Each entry: {id, title, slug, status, updated_at}
     *
     * @return array<int, array<string,mixed>>
     */
    public static function getIndex(string $lang): array
    {
        $path = self::indexPath($lang);
        if (!is_file($path)) {
            return [];
        }

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        flock($handle, LOCK_SH);
        $content = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            if ($chunk !== false) {
                $content .= $chunk;
            }
        }
        flock($handle, LOCK_UN);
        fclose($handle);

        if (trim($content) === '') {
            return [];
        }

        $data = json_decode($content, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : [];
    }

    /**
     * Read all products from shards (same array format as products.json).
     * Falls back to empty array if shard directory doesn't exist.
     *
     * Note: this is NOT faster than reading products.json when all shards must
     * be loaded. Prefer getBySlug() for single-product access, and keep using
     * JsonStore::langData($lang,'products')->read() for full-list scenarios
     * until shards are the primary store.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function getAll(string $lang): array
    {
        $dir = self::shardDir($lang);
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.json');
        if ($files === false || $files === []) {
            return [];
        }

        $products = [];
        foreach ($files as $file) {
            $handle = @fopen($file, 'r');
            if ($handle === false) {
                continue;
            }
            flock($handle, LOCK_SH);
            $content = '';
            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk !== false) {
                    $content .= $chunk;
                }
            }
            flock($handle, LOCK_UN);
            fclose($handle);

            if (trim($content) === '') {
                continue;
            }
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $products[] = $data;
            }
        }

        return $products;
    }

    // ── Write API ───────────────────────────────────────────────────────────────

    /**
     * Write a single product shard and refresh the index entry.
     *
     * Used when you want to write one shard without touching products.json.
     * The index is updated by reading the existing index, replacing/inserting
     * the entry for this product, and rewriting atomically.
     *
     * @param array<string,mixed> $product
     */
    public static function saveProduct(string $lang, array $product): void
    {
        $slug = (string) ($product['slug'] ?? '');
        if ($slug === '') {
            throw new \InvalidArgumentException('Product must have a slug to be saved as a shard.');
        }

        self::ensureShardDir($lang);

        // Write shard (atomic: temp + rename)
        self::atomicWrite(self::shardPath($lang, $slug), $product);

        // Update index entry
        self::upsertIndexEntry($lang, $product);
    }

    /**
     * Remove one product's shard file and drop it from the index.
     * Called by AdminProductController::delete().
     */
    public static function removeShard(string $lang, string $slug): void
    {
        try {
            $path = self::shardPath($lang, $slug);
        } catch (\InvalidArgumentException $e) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }

        self::dropIndexEntry($lang, $slug);
    }

    // ── Sync helpers (called after products.json writes) ────────────────────────

    /**
     * Rebuild shards and index for one language by reading the master products.json.
     *
     * This is the hook called by AdminProductController after every write operation.
     * It ensures shards and index are always consistent with products.json.
     *
     * Orphaned shards (products removed from products.json) are detected and deleted.
     *
     * Failures are logged and swallowed — they must never break the admin save flow.
     */
    public static function syncFromJson(string $lang): void
    {
        try {
            $jsonPath = DATA_PATH . '/' . $lang . '/products.json';
            if (!is_file($jsonPath)) {
                return;
            }

            $handle = @fopen($jsonPath, 'r');
            if ($handle === false) {
                return;
            }
            flock($handle, LOCK_SH);
            $content = '';
            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk !== false) {
                    $content .= $chunk;
                }
            }
            flock($handle, LOCK_UN);
            fclose($handle);

            if (trim($content) === '') {
                return;
            }

            $products = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($products)) {
                error_log('[ProductFileStore] syncFromJson: JSON decode error for ' . $lang);
                return;
            }

            self::ensureShardDir($lang);

            // Build a set of current slugs for orphan detection
            $currentSlugs = [];
            $indexEntries = [];

            foreach ($products as $product) {
                if (!is_array($product)) {
                    continue;
                }
                $slug = (string) ($product['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }

                // Write shard (atomic)
                try {
                    self::atomicWrite(self::shardPath($lang, $slug), $product);
                } catch (\Throwable $e) {
                    error_log('[ProductFileStore] syncFromJson: shard write failed for ' . $slug . ': ' . $e->getMessage());
                    continue;
                }

                $currentSlugs[$slug] = true;

                // Build index entry
                $indexEntries[] = self::buildIndexEntry($product);
            }

            // Remove orphaned shards (slugs no longer in products.json)
            $existingFiles = glob(self::shardDir($lang) . '/*.json') ?: [];
            foreach ($existingFiles as $file) {
                $fileslug = basename($file, '.json');
                if (!isset($currentSlugs[$fileslug])) {
                    @unlink($file);
                }
            }

            // Write index atomically
            self::atomicWrite(self::indexPath($lang), $indexEntries);

        } catch (\Throwable $e) {
            error_log('[ProductFileStore] syncFromJson(' . $lang . ') failed: ' . $e->getMessage());
        }
    }

    /**
     * Sync all provided languages from their respective products.json files.
     * Called from handleCreate() and handleEdit() which touch multiple languages.
     *
     * @param array<int,string> $langs
     */
    public static function syncAllLangsFromJson(array $langs): void
    {
        foreach ($langs as $lang) {
            self::syncFromJson((string) $lang);
        }
    }

    // ── Private helpers ─────────────────────────────────────────────────────────

    /**
     * Ensure the shard directory exists for a language.
     */
    private static function ensureShardDir(string $lang): void
    {
        $dir = self::shardDir($lang);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Atomic write: encode to JSON, write to a temp file, then rename.
     * Rename is atomic on POSIX filesystems, preventing torn reads.
     *
     * @param array<mixed> $data
     */
    private static function atomicWrite(string $targetPath, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException('JSON encode failed: ' . json_last_error_msg());
        }

        $tmp = $targetPath . '.tmp.' . uniqid('', true);
        $written = file_put_contents($tmp, $json, LOCK_EX);
        if ($written === false) {
            @unlink($tmp);
            throw new \RuntimeException('Write failed for temp file: ' . $tmp);
        }

        if (!rename($tmp, $targetPath)) {
            @unlink($tmp);
            throw new \RuntimeException('Rename failed: ' . $tmp . ' → ' . $targetPath);
        }
    }

    /**
     * Build one lightweight index entry from a full product array.
     *
     * @param array<string,mixed> $product
     * @return array<string,mixed>
     */
    private static function buildIndexEntry(array $product): array
    {
        // Derive status string from publish-state flags
        $status = self::deriveStatus($product);

        return [
            'id'         => (int) ($product['id'] ?? 0),
            'title'      => (string) ($product['name'] ?? ''),
            'slug'       => (string) ($product['slug'] ?? ''),
            'status'     => $status,
            'updated_at' => (string) ($product['updated_at'] ?? date('Y-m-d H:i:s')),
        ];
    }

    /**
     * Derive a human-readable status string from product publish flags.
     * Compatible with ProductPublishState field names (is_active, is_draft, status).
     */
    private static function deriveStatus(array $product): string
    {
        // Explicit status field takes priority (newer products may have it)
        $explicit = trim((string) ($product['status'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $isActive = !isset($product['is_active']) || (bool) $product['is_active'];
        $isDraft  = !empty($product['is_draft']);

        if ($isDraft) {
            return 'draft';
        }
        if (!$isActive) {
            return 'inactive';
        }
        return 'published';
    }

    /**
     * Update (or insert) one entry in the index file using LOCK_EX.
     *
     * @param array<string,mixed> $product
     */
    private static function upsertIndexEntry(string $lang, array $product): void
    {
        $indexPath = self::indexPath($lang);
        $entries   = self::getIndex($lang);  // uses LOCK_SH read

        $newEntry = self::buildIndexEntry($product);
        $slug     = $newEntry['slug'];
        $found    = false;

        foreach ($entries as &$entry) {
            if (($entry['slug'] ?? '') === $slug) {
                $entry = $newEntry;
                $found = true;
                break;
            }
        }
        unset($entry);

        if (!$found) {
            $entries[] = $newEntry;
        }

        self::atomicWrite($indexPath, $entries);
    }

    /**
     * Remove one slug from the index file.
     */
    private static function dropIndexEntry(string $lang, string $slug): void
    {
        $indexPath = self::indexPath($lang);
        $entries   = self::getIndex($lang);

        $filtered = array_values(array_filter($entries, static function ($e) use ($slug) {
            return ($e['slug'] ?? '') !== $slug;
        }));

        self::atomicWrite($indexPath, $filtered);
    }
}
