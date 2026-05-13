<?php

namespace App\Core;

/**
 * Unified slug generation (delegates to View::slugify) and uniqueness helpers.
 */
class SlugHelper
{
    public static function slugify(string $text): string
    {
        return View::slugify($text);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return string Unique slug within rows (checks "slug" key)
     */
    public static function ensureUnique(string $baseSlug, array $rows, string $excludeSlug = ''): string
    {
        $slug = self::slugify($baseSlug);
        if ($slug === '') {
            $slug = 'item-' . substr(uniqid('', true), 0, 8);
        }
        $existing = [];
        foreach ($rows as $row) {
            $s = $row['slug'] ?? '';
            if ($s !== '' && $s !== $excludeSlug) {
                $existing[$s] = true;
            }
        }
        $candidate = $slug;
        $n = 1;
        while (isset($existing[$candidate])) {
            $candidate = $slug . '-' . $n;
            $n++;
        }
        return $candidate;
    }
}
