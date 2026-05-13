<?php

namespace App\Core;

/**
 * 301 redirects from legacy ?id= PHP URLs to slug-based routes.
 */
class LegacyUrlRedirect
{
    public static function maybeRedirect(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        $base = strtolower(basename($path));
        if (!in_array($base, ['product.php', 'category.php', 'blog.php'], true)) {
            return;
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            return;
        }

        $defaultLang = Config::get('default_lang', 'en');
        $siteUrl = rtrim(Config::get('site_url', ''), '/');

        $target = '';
        if ($base === 'product.php') {
            $slug = self::productSlugByLegacyId($defaultLang, $id);
            if ($slug !== null) {
                $target = '/' . $defaultLang . '/product/' . rawurlencode($slug);
            }
        } elseif ($base === 'category.php') {
            $slug = self::categorySlugByLegacyId($defaultLang, $id);
            if ($slug !== null) {
                $target = '/' . $defaultLang . '/products/' . rawurlencode($slug);
            }
        } elseif ($base === 'blog.php') {
            $slug = self::blogSlugByLegacyId($defaultLang, $id);
            if ($slug !== null) {
                $target = '/' . $defaultLang . '/blog/' . rawurlencode($slug);
            }
        }

        if ($target === '') {
            return;
        }

        $full = $siteUrl !== '' ? $siteUrl . $target : $target;
        header('Location: ' . $full, true, 301);
        exit;
    }

    private static function productSlugByLegacyId(string $lang, int $id): ?string
    {
        $products = JsonStore::langData($lang, 'products')->read();
        foreach ($products as $p) {
            if (!empty($p['id']) && (int) $p['id'] === $id) {
                return $p['slug'] ?? null;
            }
        }
        $idx = $id - 1;
        if (isset($products[$idx]) && is_array($products[$idx])) {
            return $products[$idx]['slug'] ?? null;
        }
        return null;
    }

    private static function categorySlugByLegacyId(string $lang, int $id): ?string
    {
        $cats = JsonStore::langData($lang, 'categories')->read();
        foreach ($cats as $c) {
            if (!empty($c['id']) && (int) $c['id'] === $id) {
                return $c['slug'] ?? null;
            }
        }
        $idx = $id - 1;
        if (isset($cats[$idx]) && is_array($cats[$idx])) {
            return $cats[$idx]['slug'] ?? null;
        }
        return null;
    }

    private static function blogSlugByLegacyId(string $lang, int $id): ?string
    {
        $posts = JsonStore::langData($lang, 'blog')->read();
        foreach ($posts as $p) {
            if (!empty($p['id']) && (int) $p['id'] === $id) {
                return $p['slug'] ?? null;
            }
        }
        $idx = $id - 1;
        if (isset($posts[$idx]) && is_array($posts[$idx])) {
            return $posts[$idx]['slug'] ?? null;
        }
        return null;
    }
}
