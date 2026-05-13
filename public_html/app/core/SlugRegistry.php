<?php

declare(strict_types=1);

namespace App\Core;

/**
 * SlugRegistry — Global slug uniqueness guard + 301/302/410 redirect manager.
 *
 * URL patterns by content type:
 *   product  → /{lang}/product/{slug}
 *   blog     → /{lang}/blog/{slug}
 *   page     → /{lang}/page/{slug}
 *   category → /{lang}/products/{slug}
 *   case     → /{lang}/cases/{slug}
 *
 * Compatible: PHP 7.1+, no Composer, no Node.
 */
final class SlugRegistry
{
    /** Content type → JsonStore data name (checked against default lang 'en') */
    private const DATA_SOURCES = [
        'product'  => 'products',
        'blog'     => 'blog',
        'page'     => 'pages',
        'category' => 'categories',
        'case'     => 'cases',
    ];

    /** URL path pattern per type; {lang} and {slug} are placeholders */
    private const URL_PATTERNS = [
        'product'  => '/{lang}/product/{slug}',
        'blog'     => '/{lang}/blog/{slug}',
        'page'     => '/{lang}/page/{slug}',
        'category' => '/{lang}/products/{slug}',
        'case'     => '/{lang}/cases/{slug}',
    ];

    private const ALLOWED_CODES = [301, 302, 410];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Check whether a slug is already taken by any content type (default lang = 'en').
     *
     * @param string $slug        The slug to test.
     * @param string $excludeType Content type of the item being saved (prevents self-conflict).
     * @param string $excludeSlug The original slug of the item being edited (excluded from check).
     * @return bool True when the slug is already in use by a different item.
     */
    public static function isSlugUsed(
        string $slug,
        string $excludeType = '',
        string $excludeSlug = ''
    ): bool {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        foreach (self::DATA_SOURCES as $type => $dataName) {
            try {
                $items = JsonStore::langData('en', $dataName)->read();
                if (!is_array($items)) {
                    continue;
                }
                foreach ($items as $item) {
                    $itemSlug = (string) ($item['slug'] ?? '');
                    if ($itemSlug !== $slug) {
                        continue;
                    }
                    // Same type + same slug = editing self; skip.
                    if ($type === $excludeType && $itemSlug === $excludeSlug) {
                        continue;
                    }
                    return true;
                }
            } catch (\Throwable $e) {
                // Data file unreadable — treat as empty, do not block save.
            }
        }

        return false;
    }

    /**
     * Ensure a slug is globally unique by appending a numeric suffix if needed.
     *
     * @param string $slug        Desired slug (already slugified).
     * @param string $type        Content type ('product', 'blog', etc.).
     * @param string $excludeSlug Current slug of the item being edited (not treated as conflict).
     * @return string Unique slug.
     */
    public static function makeUniqueSlug(
        string $slug,
        string $type,
        string $excludeSlug = ''
    ): string {
        $base    = $slug;
        $counter = 1;
        while (self::isSlugUsed($slug, $type, $excludeSlug)) {
            $slug = $base . '-' . $counter;
            $counter++;
        }
        return $slug;
    }

    /**
     * Register redirect entries in redirects.json for all supported languages.
     *
     * Idempotent: calling with the same $fromSlug replaces any existing entry.
     *
     * @param string      $fromSlug Old slug (before rename).
     * @param string|null $toSlug   New slug (after rename). Pass null or '' for 410 Gone.
     * @param string      $type     Content type key ('product', 'blog', 'page', 'category', 'case').
     * @param int         $code     HTTP status: 301 (permanent), 302 (temporary), or 410 (gone).
     */
    public static function registerRedirect(
        string $fromSlug,
        ?string $toSlug,
        string $type,
        int $code = 301
    ): void {
        $fromSlug = trim($fromSlug);
        if ($fromSlug === '' || !isset(self::URL_PATTERNS[$type])) {
            return;
        }

        $code    = in_array($code, self::ALLOWED_CODES, true) ? $code : 301;
        $pattern = self::URL_PATTERNS[$type];
        $langs   = Config::get('supported_langs', ['en', 'cn', 'es']);
        if (!is_array($langs) || empty($langs)) {
            $langs = ['en'];
        }

        $store    = JsonStore::globalData('redirects');
        $existing = $store->read();

        // Normalise whatever format is in the file to a flat indexed array.
        $redirects = self::normaliseToArray($existing);

        foreach ($langs as $lang) {
            $fromPath = str_replace(['{lang}', '{slug}'], [(string) $lang, $fromSlug], $pattern);
            $toPath   = null;
            if ($code !== 410 && $toSlug !== null && trim($toSlug) !== '') {
                $toPath = str_replace(['{lang}', '{slug}'], [(string) $lang, trim($toSlug)], $pattern);
            }

            // Remove any existing entry for this from-path (idempotent).
            $redirects = array_values(array_filter(
                $redirects,
                static function ($r) use ($fromPath) {
                    if (!is_array($r)) {
                        return true;
                    }
                    $existingFrom = '/' . ltrim(trim((string) ($r['from'] ?? '')), '/');
                    return $existingFrom !== $fromPath;
                }
            ));

            // Skip self-referencing redirects.
            if ($toPath !== null && $toPath === $fromPath) {
                continue;
            }

            $entry = ['from' => $fromPath, 'code' => $code];
            if ($toPath !== null) {
                $entry['to'] = $toPath;
            }
            $redirects[] = $entry;
        }

        $store->update(static function () use ($redirects) {
            return $redirects;
        });
    }

    /**
     * Build the public URL path for a content item in a given language.
     *
     * @param string $type Content type key.
     * @param string $slug Item slug.
     * @param string $lang Language code.
     * @return string      Path, e.g. /en/product/nexus-core — or '' if type unknown.
     */
    public static function buildUrl(string $type, string $slug, string $lang = 'en'): string
    {
        $pattern = self::URL_PATTERNS[$type] ?? '';
        if ($pattern === '') {
            return '';
        }
        return str_replace(['{lang}', '{slug}'], [$lang, $slug], $pattern);
    }

    /**
     * Scan all content types (default lang 'en') for duplicate slugs.
     *
     * @return array<int, array{slug: string, types: string[]}> List of duplicates.
     */
    public static function scanDuplicates(): array
    {
        $seen = []; // slug → [type, ...]

        foreach (self::DATA_SOURCES as $type => $dataName) {
            try {
                $items = JsonStore::langData('en', $dataName)->read();
                if (!is_array($items)) {
                    continue;
                }
                foreach ($items as $item) {
                    $slug = (string) ($item['slug'] ?? '');
                    if ($slug === '') {
                        continue;
                    }
                    $seen[$slug][] = $type;
                }
            } catch (\Throwable $e) {
                // Skip unreadable files.
            }
        }

        $dups = [];
        foreach ($seen as $slug => $types) {
            if (count($types) > 1) {
                $dups[] = ['slug' => $slug, 'types' => $types];
            }
        }

        return $dups;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Normalise the raw redirects.json content to a flat indexed array.
     *
     * Handles two legacy formats:
     *   - String map: {"/old": "/new"}  → [{from, to, code: 301}]
     *   - Indexed array: [{from, to, code?}]  → passed through as-is
     *
     * @param mixed $raw Decoded JSON value from redirects.json.
     * @return array<int, array<string, mixed>>
     */
    private static function normaliseToArray($raw): array
    {
        if (!is_array($raw) || empty($raw)) {
            return [];
        }

        // Detect legacy string-map format: all keys are strings, first value is a string.
        reset($raw);
        $firstKey = key($raw);
        $firstVal = current($raw);
        if (is_string($firstKey) && is_string($firstVal)) {
            $out = [];
            foreach ($raw as $from => $to) {
                $out[] = ['from' => (string) $from, 'to' => (string) $to, 'code' => 301];
            }
            return $out;
        }

        // Indexed array format — filter to valid entries only.
        return array_values(array_filter($raw, static function ($r) {
            return is_array($r) && isset($r['from']);
        }));
    }
}
