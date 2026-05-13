<?php

namespace App\Controllers;

use App\Core\JsonStore;

/**
 * Read-only helpers for storefront category JSON (no admin routes).
 */
final class CategoryController
{
    /**
     * @return array<string, mixed>|null
     */
    public static function resolveLayer(string $lang, ?string $categorySlug): ?array
    {
        $slug = trim((string) $categorySlug);
        if ($slug === '') {
            return null;
        }
        $rows = JsonStore::langData($lang, 'categories')->read();
        if (!is_array($rows)) {
            return null;
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['slug'] ?? '') === $slug) {
                return $row;
            }
        }

        return null;
    }
}
