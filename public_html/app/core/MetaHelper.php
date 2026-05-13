<?php

namespace App\Core;

/**
 * Plain-text / meta description helpers and storefront meta merge
 * (page SEO > category SEO > site defaults). Output tags are built in SEO::renderMeta;
 * optional meta keywords are returned separately for controller append.
 *
 * Length: title ≤60 chars, description ≤160 (UTF-8 safe). Keywords are sanitized; empty meta tags are not emitted.
 */
class MetaHelper
{
    public const TITLE_MAX_LEN = 60;

    public const DESCRIPTION_MAX_LEN = 160;

    public static function stripHtml(string $html): string
    {
        $text = preg_replace('@<script\b[^>]*>.*?</script>@is', '', $html);
        $text = preg_replace('@<style\b[^>]*>.*?</style>@is', '', $text);
        $text = strip_tags($text ?? '');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text));
        return $text;
    }

    public static function excerpt(string $text, int $maxLen = 160, string $suffix = '…'): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $maxLen - mb_strlen($suffix))) . $suffix;
    }

    public static function descriptionFromHtml(string $html, int $maxLen = 160): string
    {
        return self::clampDescription(self::stripHtml($html), $maxLen);
    }

    /**
     * Meta / raw SEO title segment clamp (UTF-8), max length without ellipsis by default.
     */
    public static function clampTitle(string $title, int $maxLen = self::TITLE_MAX_LEN): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        return self::excerpt($title, max(1, $maxLen), '');
    }

    /**
     * Meta description clamp (UTF-8) with ellipsis when truncated.
     */
    public static function clampDescription(string $text, int $maxLen = self::DESCRIPTION_MAX_LEN): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return self::excerpt($text, max(1, $maxLen), '…');
    }

    /**
     * Normalize keyword lists: unify separators, drop empties/dupes (case-insensitive), strip junk characters.
     */
    public static function sanitizeKeywords(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $normalized = preg_replace('/[;；|｜，、\s\n\r\t]+/u', ',', $raw);
        $normalized = (string) preg_replace('/,+/', ',', (string) $normalized);
        $parts = array_map('trim', explode(',', (string) $normalized));
        $seen = [];
        $out = [];
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            $p = (string) preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $p);
            $p = trim(preg_replace('/\s+/u', ' ', $p));
            if ($p === '') {
                continue;
            }
            $key = mb_strtolower($p, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $p;
        }

        return implode(', ', $out);
    }

    /**
     * Site-level fallback for meta description when no page content is available.
     */
    public static function defaultMetaDescriptionFallback(): string
    {
        $d = self::stripHtml((string) Config::get('default_meta_description', ''));
        if ($d !== '') {
            return self::clampDescription($d);
        }
        $site = trim((string) Config::get('site_name', ''));
        $tag = trim((string) Config::get('site_tagline', ''));
        if ($site !== '' && $tag !== '') {
            return self::clampDescription($site . ' — ' . $tag);
        }
        if ($site !== '') {
            return self::clampDescription($site);
        }

        return '';
    }

    /**
     * Title in format "Segment | Brand" (single pipe, no duplicate brand).
     */
    public static function titleWithBrand(string $segment, string $brandName): string
    {
        $segment = trim($segment);
        $brandName = trim($brandName);
        if ($segment === '') {
            return $brandName !== '' ? $brandName : 'Home';
        }
        if ($brandName === '') {
            return $segment;
        }
        if (mb_stripos($segment, $brandName) !== false) {
            return $segment;
        }
        return $segment . ' | ' . $brandName;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function strVal(array $row, string $key): string
    {
        return trim((string) ($row[$key] ?? ''));
    }

    public static function firstNonEmpty(string ...$candidates): string
    {
        foreach ($candidates as $c) {
            $t = trim($c);
            if ($t !== '') {
                return $t;
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resolveBlogCategoryLayer(string $lang, ?string $categorySlug): ?array
    {
        $slug = trim((string) $categorySlug);
        if ($slug === '') {
            return null;
        }
        $rows = JsonStore::langData($lang, 'blog_categories')->read();
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

    /**
     * @param array<string, mixed> $product
     */
    public static function primaryProductImage(array $product): string
    {
        $img = self::strVal($product, 'image');
        if ($img !== '') {
            return $img;
        }
        $imgs = $product['images'] ?? [];
        if (!is_array($imgs)) {
            return '';
        }
        $fallback = '';
        foreach ($imgs as $row) {
            if (!is_array($row)) {
                continue;
            }
            $u = self::strVal($row, 'url');
            if ($u === '') {
                continue;
            }
            if (!empty($row['is_main'])) {
                return $u;
            }
            if ($fallback === '') {
                $fallback = $u;
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $category
     */
    public static function primaryCategoryImage(array $category): string
    {
        return self::strVal($category, 'image');
    }

    public static function metaKeywordsTag(string $keywords): string
    {
        $k = self::sanitizeKeywords($keywords);
        if ($k === '') {
            return '';
        }

        return '<meta name="keywords" content="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . "\">\n";
    }

    /**
     * First non-empty candidate after HTML stripping (for meta description overrides).
     *
     * @param list<string> $rawCandidates
     */
    public static function firstPlainDescription(array $rawCandidates): string
    {
        foreach ($rawCandidates as $raw) {
            $plain = self::stripHtml(trim((string) $raw));
            if ($plain !== '') {
                return $plain;
            }
        }

        return '';
    }

    /**
     * Product detail: page SEO > category SEO > site (via empty overrides + SEO defaults).
     *
     * @param array<string, mixed> $product
     * @param array<string, mixed>|null $category
     * @return array{overrides: array<string, mixed>, head_suffix: string}
     */
    public static function buildProductDetailMeta(array $product, ?array $category): array
    {
        $cat = is_array($category) ? $category : [];

        $seoTitle = self::firstNonEmpty(
            self::strVal($product, 'seo_title'),
            self::strVal($product, 'name'),
            self::strVal($cat, 'seo_title'),
            self::strVal($cat, 'name'),
            (string) Config::get('default_meta_title', ''),
            (string) Config::get('site_name', '')
        );
        if ($seoTitle === '') {
            $seoTitle = (string) Config::get('site_name', '');
        }
        $seoTitle = self::clampTitle($seoTitle);

        $descPlain = self::firstPlainDescription([
            self::strVal($product, 'seo_desc'),
            self::strVal($product, 'seo_description'),
            self::strVal($product, 'desc'),
            self::strVal($product, 'short_desc'),
            self::strVal($product, 'short_description'),
            self::strVal($cat, 'seo_desc'),
            self::strVal($cat, 'seo_description'),
            self::strVal($cat, 'description'),
        ]);

        $summaryPlain = self::stripHtml(
            self::firstNonEmpty(
                self::strVal($product, 'short_description'),
                self::strVal($product, 'short_desc')
            )
        );
        if ($summaryPlain !== '' && mb_strlen($descPlain) < 40) {
            $descPlain = $summaryPlain;
        }
        if ($descPlain === '') {
            $descPlain = self::defaultMetaDescriptionFallback();
        } else {
            $descPlain = self::clampDescription($descPlain);
        }

        $keywords = self::sanitizeKeywords(self::firstNonEmpty(
            self::strVal($product, 'seo_keywords'),
            self::strVal($product, 'keywords'),
            self::strVal($cat, 'seo_keywords'),
            self::strVal($cat, 'keywords'),
            (string) Config::get('default_meta_keywords', '')
        ));

        $image = self::firstNonEmpty(
            self::primaryProductImage($product),
            self::primaryCategoryImage($cat)
        );

        $entityTitle = self::clampTitle(self::firstNonEmpty(self::strVal($product, 'name'), $seoTitle));
        $overrides = [
            'title' => $entityTitle,
            'seo_title' => $seoTitle,
            'description' => $descPlain,
            'seo_desc' => $descPlain,
            'content_plain' => $descPlain,
            'content_html' => (string) ($product['content'] ?? ''),
        ];
        if ($image !== '') {
            $overrides['image'] = $image;
        }

        return [
            'overrides' => $overrides,
            'head_suffix' => self::metaKeywordsTag($keywords),
        ];
    }

    /**
     * Category product listing (/products/{slug}).
     *
     * @param array<string, mixed> $category
     * @return array{overrides: array<string, mixed>, head_suffix: string}
     */
    public static function buildCategoryListingMeta(array $category): array
    {
        $seoTitle = self::firstNonEmpty(
            self::strVal($category, 'seo_title'),
            self::strVal($category, 'name'),
            (string) Config::get('default_meta_title', ''),
            (string) Config::get('site_name', '')
        );
        if ($seoTitle === '') {
            $seoTitle = (string) Config::get('site_name', '');
        }
        $seoTitle = self::clampTitle($seoTitle);

        $descPlain = self::firstPlainDescription([
            self::strVal($category, 'seo_desc'),
            self::strVal($category, 'seo_description'),
            self::strVal($category, 'description'),
        ]);
        if ($descPlain === '') {
            $descPlain = self::defaultMetaDescriptionFallback();
        } else {
            $descPlain = self::clampDescription($descPlain);
        }

        $keywords = self::sanitizeKeywords(self::firstNonEmpty(
            self::strVal($category, 'seo_keywords'),
            self::strVal($category, 'keywords'),
            (string) Config::get('default_meta_keywords', '')
        ));

        $image = self::primaryCategoryImage($category);
        $catName = self::strVal($category, 'name');
        $entityTitle = self::clampTitle(self::firstNonEmpty($catName, $seoTitle));
        $overrides = [
            'title' => $entityTitle,
            'seo_title' => $seoTitle,
            'description' => $descPlain,
            'seo_desc' => $descPlain,
            'content_plain' => $descPlain,
        ];
        if ($image !== '') {
            $overrides['image'] = $image;
        }

        return [
            'overrides' => $overrides,
            'head_suffix' => self::metaKeywordsTag($keywords),
        ];
    }

    /**
     * /products listing (no category layer).
     *
     * @return array{overrides: array<string, mixed>, head_suffix: string}
     */
    public static function buildProductsIndexMeta(string $h1Title, string $introPlain): array
    {
        $seoTitle = self::firstNonEmpty(
            $h1Title,
            (string) Config::get('default_meta_title', ''),
            (string) Config::get('site_name', '')
        );
        if ($seoTitle === '') {
            $seoTitle = (string) Config::get('site_name', '');
        }
        $seoTitle = self::clampTitle($seoTitle);
        $introPlain = self::clampDescription(trim($introPlain));
        if ($introPlain === '') {
            $introPlain = self::defaultMetaDescriptionFallback();
        }
        $kw = self::sanitizeKeywords((string) Config::get('default_meta_keywords', ''));

        return [
            'overrides' => [
                'title' => self::clampTitle($h1Title),
                'seo_title' => $seoTitle,
                'description' => $introPlain,
                'seo_desc' => $introPlain,
                'content_plain' => $introPlain,
            ],
            'head_suffix' => self::metaKeywordsTag($kw),
        ];
    }

    /**
     * /blog listing.
     *
     * @return array{overrides: array<string, mixed>, head_suffix: string}
     */
    public static function buildBlogIndexMeta(string $h1Title, string $introPlain): array
    {
        $seoTitle = self::firstNonEmpty(
            $h1Title,
            (string) Config::get('default_meta_title', ''),
            (string) Config::get('site_name', '')
        );
        if ($seoTitle === '') {
            $seoTitle = (string) Config::get('site_name', '');
        }
        $seoTitle = self::clampTitle($seoTitle);
        $introPlain = self::clampDescription(trim($introPlain));
        if ($introPlain === '') {
            $introPlain = self::defaultMetaDescriptionFallback();
        }
        $kw = self::sanitizeKeywords((string) Config::get('default_meta_keywords', ''));

        return [
            'overrides' => [
                'title' => self::clampTitle($h1Title),
                'seo_title' => $seoTitle,
                'description' => $introPlain,
                'seo_desc' => $introPlain,
                'content_plain' => $introPlain,
            ],
            'head_suffix' => self::metaKeywordsTag($kw),
        ];
    }

    /**
     * Blog post detail.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $blogCategory
     * @return array{overrides: array<string, mixed>, head_suffix: string}
     */
    public static function buildBlogPostMeta(array $post, ?array $blogCategory): array
    {
        $cat = is_array($blogCategory) ? $blogCategory : [];

        $seoTitle = self::firstNonEmpty(
            self::strVal($post, 'seo_title'),
            self::strVal($post, 'title'),
            self::strVal($cat, 'seo_title'),
            self::strVal($cat, 'name'),
            (string) Config::get('default_meta_title', ''),
            (string) Config::get('site_name', '')
        );
        if ($seoTitle === '') {
            $seoTitle = (string) Config::get('site_name', '');
        }
        $seoTitle = self::clampTitle($seoTitle);

        $descPlain = self::firstPlainDescription([
            self::strVal($post, 'seo_desc'),
            self::strVal($post, 'seo_description'),
            self::strVal($post, 'desc'),
            self::strVal($post, 'excerpt'),
            self::strVal($cat, 'seo_desc'),
            self::strVal($cat, 'seo_description'),
            self::strVal($cat, 'description'),
        ]);
        if ($descPlain === '') {
            $descPlain = self::defaultMetaDescriptionFallback();
        } else {
            $descPlain = self::clampDescription($descPlain);
        }

        $keywords = self::sanitizeKeywords(self::firstNonEmpty(
            self::strVal($post, 'seo_keywords'),
            self::strVal($post, 'keywords'),
            self::strVal($cat, 'seo_keywords'),
            self::strVal($cat, 'keywords'),
            (string) Config::get('default_meta_keywords', '')
        ));

        $image = self::firstNonEmpty(
            self::strVal($post, 'image'),
            self::strVal($cat, 'image')
        );

        $postTitle = self::strVal($post, 'title');
        $entityTitle = self::clampTitle(self::firstNonEmpty($postTitle, $seoTitle));
        $overrides = [
            'title' => $entityTitle,
            'seo_title' => $seoTitle,
            'description' => $descPlain,
            'seo_desc' => $descPlain,
            'content_plain' => $descPlain,
            'content_html' => (string) ($post['content'] ?? ''),
        ];
        if ($image !== '') {
            $overrides['image'] = $image;
        }

        return [
            'overrides' => $overrides,
            'head_suffix' => self::metaKeywordsTag($keywords),
        ];
    }
}
