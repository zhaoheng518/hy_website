<?php
/**
 * Module 11 — Products Shard Migration
 *
 * Usage (run from project root):
 *   php database_migrations/20260511_products_shard_migration.php
 *
 * What it does:
 *   For every supported language, reads app/data/{lang}/products.json and:
 *     1. Creates app/data/{lang}/products/ directory
 *     2. Writes one shard file per product: app/data/{lang}/products/{slug}.json
 *     3. Writes the lightweight index: app/data/{lang}/products_index.json
 *
 * Safe to re-run: existing shards are overwritten, no data is deleted from products.json.
 * Idempotent: repeated execution produces the same result.
 *
 * Rollback: the migration creates only NEW files and directories.
 *   To roll back: delete app/data/{lang}/products/ directories and products_index.json files.
 *   Original products.json files are NEVER modified.
 */

// ── Bootstrap ──────────────────────────────────────────────────────────────────

$projectRoot = dirname(__DIR__);

define('ROOT_PATH', $projectRoot);
define('APP_PATH',  $projectRoot . '/app');
define('DATA_PATH', $projectRoot . '/app/data');

// Load ProductFileStore (no autoloader needed — direct require)
require APP_PATH . '/core/ProductFileStore.php';

// ── Helpers ────────────────────────────────────────────────────────────────────

function migrationLog(string $msg): void
{
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] {$msg}" . PHP_EOL;
}

function atomicWriteShard(string $targetPath, array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new \RuntimeException('JSON encode failed: ' . json_last_error_msg());
    }
    $tmp     = $targetPath . '.tmp.' . uniqid('', true);
    $written = file_put_contents($tmp, $json, LOCK_EX);
    if ($written === false) {
        @unlink($tmp);
        throw new \RuntimeException('Write failed: ' . $tmp);
    }
    if (!rename($tmp, $targetPath)) {
        @unlink($tmp);
        throw new \RuntimeException('Rename failed: ' . $tmp . ' → ' . $targetPath);
    }
}

function deriveStatus(array $product): string
{
    $explicit = trim((string) ($product['status'] ?? ''));
    if ($explicit !== '') {
        return $explicit;
    }
    $isActive = !isset($product['is_active']) || (bool) $product['is_active'];
    $isDraft  = !empty($product['is_draft']);
    if ($isDraft)  { return 'draft'; }
    if (!$isActive){ return 'inactive'; }
    return 'published';
}

// ── Discover supported languages ───────────────────────────────────────────────

$siteJsonPath = DATA_PATH . '/site.json';
$supportedLangs = ['en', 'cn', 'es'];  // fallback defaults

if (is_file($siteJsonPath)) {
    $siteRaw = file_get_contents($siteJsonPath);
    if ($siteRaw !== false) {
        $site = json_decode($siteRaw, true);
        if (is_array($site) && isset($site['supported_langs']) && is_array($site['supported_langs'])) {
            $supportedLangs = $site['supported_langs'];
        }
    }
}

migrationLog('Supported languages: ' . implode(', ', $supportedLangs));
migrationLog('Data path: ' . DATA_PATH);
echo PHP_EOL;

// ── Main migration loop ─────────────────────────────────────────────────────────

$totalProducts = 0;
$totalErrors   = 0;

foreach ($supportedLangs as $lang) {
    $lang = (string) $lang;
    migrationLog("=== Language: {$lang} ===");

    $productsJson = DATA_PATH . '/' . $lang . '/products.json';

    if (!is_file($productsJson)) {
        migrationLog("  SKIP — {$productsJson} does not exist.");
        echo PHP_EOL;
        continue;
    }

    // Read source file with shared lock
    $handle = @fopen($productsJson, 'r');
    if ($handle === false) {
        migrationLog("  ERROR — Cannot open {$productsJson}");
        $totalErrors++;
        echo PHP_EOL;
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
        migrationLog("  SKIP — {$productsJson} is empty.");
        echo PHP_EOL;
        continue;
    }

    $products = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($products)) {
        migrationLog("  ERROR — JSON decode failed: " . json_last_error_msg());
        $totalErrors++;
        echo PHP_EOL;
        continue;
    }

    migrationLog('  Found ' . count($products) . ' products in products.json');

    // Ensure shard directory exists
    $shardDir = DATA_PATH . '/' . $lang . '/products';
    if (!is_dir($shardDir)) {
        if (!mkdir($shardDir, 0755, true)) {
            migrationLog("  ERROR — Cannot create shard directory: {$shardDir}");
            $totalErrors++;
            echo PHP_EOL;
            continue;
        }
        migrationLog("  Created directory: {$shardDir}");
    }

    // Process each product
    $indexEntries  = [];
    $writtenShards = 0;
    $langErrors    = 0;
    $currentSlugs  = [];

    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }

        $slug = trim((string) ($product['slug'] ?? ''));
        if ($slug === '') {
            migrationLog("  WARN — Product with no slug, skipping.");
            continue;
        }

        // Sanitise slug for filesystem safety
        $safeSlug = preg_replace('/[^a-z0-9\-_]/i', '', $slug);
        if ($safeSlug === '') {
            migrationLog("  WARN — Slug '{$slug}' produces empty filename after sanitisation, skipping.");
            continue;
        }

        $shardPath = $shardDir . '/' . $safeSlug . '.json';

        try {
            atomicWriteShard($shardPath, $product);
            $writtenShards++;
            $currentSlugs[$safeSlug] = true;
        } catch (\Throwable $e) {
            migrationLog("  ERROR — Shard write failed for slug '{$slug}': " . $e->getMessage());
            $langErrors++;
            continue;
        }

        // Build index entry
        $indexEntries[] = [
            'id'         => (int) ($product['id'] ?? 0),
            'title'      => (string) ($product['name'] ?? ''),
            'slug'       => $slug,
            'status'     => deriveStatus($product),
            'updated_at' => (string) ($product['updated_at'] ?? date('Y-m-d H:i:s')),
        ];
    }

    // Remove orphaned shards from a previous (possibly different) product set
    $existingFiles = glob($shardDir . '/*.json') ?: [];
    $removedOrphans = 0;
    foreach ($existingFiles as $file) {
        $fileSlug = basename($file, '.json');
        if (!isset($currentSlugs[$fileSlug])) {
            if (@unlink($file)) {
                $removedOrphans++;
                migrationLog("  REMOVED orphan shard: {$fileSlug}.json");
            }
        }
    }

    // Write index file
    $indexPath = DATA_PATH . '/' . $lang . '/products_index.json';
    try {
        atomicWriteShard($indexPath, $indexEntries);
        migrationLog("  Written index: {$indexPath} ({$writtenShards} entries)");
    } catch (\Throwable $e) {
        migrationLog("  ERROR — Index write failed: " . $e->getMessage());
        $langErrors++;
    }

    migrationLog("  Shards written : {$writtenShards}");
    if ($removedOrphans > 0) {
        migrationLog("  Orphans removed: {$removedOrphans}");
    }
    if ($langErrors > 0) {
        migrationLog("  Errors         : {$langErrors}");
    }

    $totalProducts += $writtenShards;
    $totalErrors   += $langErrors;
    echo PHP_EOL;
}

// ── Summary ─────────────────────────────────────────────────────────────────────

migrationLog('=== Migration Complete ===');
migrationLog("Total products sharded : {$totalProducts}");
migrationLog("Total errors           : {$totalErrors}");

if ($totalErrors > 0) {
    migrationLog('WARNING: Some errors occurred. Review logs above.');
    exit(1);
}

migrationLog('All languages processed successfully.');
migrationLog('Rollback: delete app/data/{lang}/products/ dirs and products_index.json files.');
exit(0);
