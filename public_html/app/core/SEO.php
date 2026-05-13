<?php

namespace App\Core;

class SEO
{
    private string $lang;
    private string $siteUrl;
    private string $siteName;
    private array $supportedLangs;

    public function __construct(string $lang)
    {
        $this->lang = $lang;
        $this->siteUrl = rtrim(Config::get('site_url', ''), '/');
        $this->siteName = Config::get('site_name', 'Stitch Tech');
        $this->supportedLangs = Config::get('supported_langs', ['en', 'cn', 'es']);
    }

    /**
     * Query params that should force noindex (search / filters), excluding pagination ?page=.
     */
    public static function hasSearchOrFilterNoIndex(): bool
    {
        $keys = ['sort', 'filter', 'q', 'search'];
        foreach ($keys as $key) {
            if (isset($_GET[$key]) && (string) $_GET[$key] !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * True when request path is the dedicated search page (/lang/search).
     */
    public static function isSearchPathRequest(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        $path = '/' . trim(preg_replace('#/+#', '/', $path), '/');
        $langs = implode('|', array_map('preg_quote', Config::get('supported_langs', ['en', 'cn', 'es'])));
        return (bool) preg_match('#^/(' . $langs . ')/search/?$#', $path);
    }

    /**
     * Pagination suffix for self-canonical (?page=2+). Page 1 omits query.
     */
    public static function paginationQuerySuffix(): string
    {
        if (!isset($_GET['page'])) {
            return '';
        }
        $p = (int) $_GET['page'];
        if ($p < 2) {
            return '';
        }
        return '?page=' . $p;
    }

    public function renderMeta(string $page, string $slug = '', array $overrides = []): string
    {
        $seoData = $this->getSeoData($page, $slug, $overrides);
        $html = '';

        $html .= '<title>' . htmlspecialchars($seoData['title'], ENT_QUOTES, 'UTF-8') . "</title>\n";

        $robots = $seoData['robots'] ?? 'index, follow';
        $html .= '<meta name="description" content="' . htmlspecialchars($seoData['description'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= '<meta name="robots" content="' . htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        $metaAuthor = trim((string) ($seoData['meta_author'] ?? ''));
        if ($metaAuthor !== '') {
            $html .= '<meta name="author" content="' . htmlspecialchars($metaAuthor, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        $metaKeywords = trim((string) ($seoData['meta_keywords'] ?? ''));
        if ($metaKeywords !== '') {
            $html .= MetaHelper::metaKeywordsTag($metaKeywords);
        }

        $html .= $this->renderCanonical($seoData['canonical_rel']);
        $html .= $this->renderHreflang($seoData['hreflang_base'], $seoData['hreflang_query']);
        $html .= $this->renderOpenGraph($seoData);
        $html .= $this->renderTwitter($seoData);

        return $html;
    }

    public function renderCanonical(string $relativePathWithOptionalQuery): string
    {
        $url = $this->siteUrl . $relativePathWithOptionalQuery;
        return '<link rel="canonical" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }

    /**
     * hreflang for every configured language + x-default (default_lang).
     * $hreflangBase: path without query, e.g. /en/products
     * $querySuffix: e.g. ?page=2 — applied to all alternates
     */
    public function renderHreflang(string $hreflangBase, string $querySuffix = ''): string
    {
        $html = '';
        $remainder = $this->extractPathWithoutLang($hreflangBase);
        $pathSuffix = ($remainder === '' || $remainder === '/') ? '/' : $remainder;

        foreach (self::hreflangAlternateUrlSegments($this->supportedLangs) as $siteLang) {
            $href = $this->siteUrl . '/' . $siteLang . $pathSuffix . $querySuffix;
            $hreflangAttr = self::alternateHreflangAttribute($siteLang);
            $html .= '<link rel="alternate" hreflang="' . htmlspecialchars($hreflangAttr, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        $defaultLang = Config::get('default_lang', 'en');
        $defaultHref = $this->siteUrl . '/' . $defaultLang . $pathSuffix . $querySuffix;
        $html .= '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($defaultHref, ENT_QUOTES, 'UTF-8') . '">' . "\n";

        return $html;
    }

    /**
     * Site URL path segments (e.g. en, cn) that get rel="alternate" hreflang links.
     * Uses site.json "hreflang_seo.url_langs" when set; otherwise all supported_langs.
     *
     * @param list<string> $supportedLangs
     * @return list<string>
     */
    public static function hreflangAlternateUrlSegments(array $supportedLangs): array
    {
        $configured = Config::get('hreflang_seo.url_langs', null);
        if (!is_array($configured) || $configured === []) {
            return $supportedLangs;
        }
        $out = [];
        foreach ($configured as $code) {
            $code = trim((string) $code);
            if ($code !== '' && in_array($code, $supportedLangs, true)) {
                $out[] = $code;
            }
        }

        return $out !== [] ? $out : $supportedLangs;
    }

    /**
     * Value for the hreflang="" attribute (e.g. en, zh, es). Prefer lang_config.*.hreflang_link
     * for SEO short codes while keeping lang_config.*.hreflang for other consumers.
     */
    public static function alternateHreflangAttribute(string $siteLang): string
    {
        $link = trim((string) Config::get('lang_config.' . $siteLang . '.hreflang_link', ''));
        if ($link !== '') {
            return $link;
        }
        $legacy = trim((string) Config::get('lang_config.' . $siteLang . '.hreflang', ''));
        if ($legacy !== '') {
            return $legacy;
        }

        return $siteLang;
    }

    public function renderOpenGraph(array $seoData): string
    {
        $html = '';
        $absolutePage = $this->siteUrl . ($seoData['canonical_rel'] ?? '');

        $html .= '<meta property="og:title" content="' . htmlspecialchars($seoData['title'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        $html .= '<meta property="og:description" content="' . htmlspecialchars($seoData['description'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        $html .= '<meta property="og:url" content="' . htmlspecialchars($absolutePage, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        $html .= '<meta property="og:type" content="' . htmlspecialchars($seoData['og_type'] ?? 'website', ENT_QUOTES, 'UTF-8') . '">' . "\n";
        $html .= '<meta property="og:site_name" content="' . htmlspecialchars($this->siteName, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        $html .= '<meta property="og:locale" content="' . htmlspecialchars($this->getLocale(), ENT_QUOTES, 'UTF-8') . '">' . "\n";

        foreach ($this->supportedLangs as $other) {
            if ($other === $this->lang) {
                continue;
            }
            $alt = $this->getOgLocaleTag($other);
            if ($alt !== '') {
                $html .= '<meta property="og:locale:alternate" content="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
        }

        $img = $seoData['image'] ?? '';
        if ($img === '') {
            $img = (string) Config::get('default_og_image', '');
        }
        if ($img === '') {
            $img = Config::get('logo', '');
        }
        if ($img !== '') {
            $imgUrl = $this->absoluteUrl($img);
            $html .= '<meta property="og:image" content="' . htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            $ogImgAlt = trim((string) ($seoData['og_image_alt'] ?? ''));
            if ($ogImgAlt !== '') {
                $html .= '<meta property="og:image:alt" content="' . htmlspecialchars($ogImgAlt, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
        }

        return $html;
    }

    public function renderTwitter(array $seoData): string
    {
        $html = '';
        $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $html .= '<meta name="twitter:title" content="' . htmlspecialchars($seoData['title'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        $html .= '<meta name="twitter:description" content="' . htmlspecialchars($seoData['description'], ENT_QUOTES, 'UTF-8') . '">' . "\n";

        $img = $seoData['image'] ?? '';
        if ($img === '') {
            $img = (string) Config::get('default_og_image', '');
        }
        if ($img === '') {
            $img = Config::get('logo', '');
        }
        if ($img !== '') {
            $imgUrl = $this->absoluteUrl($img);
            $html .= '<meta name="twitter:image" content="' . htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        return $html;
    }

    private function getOgLocaleTag(string $lang): string
    {
        $loc = Config::get('lang_config.' . $lang . '.locale', '');
        if ($loc !== '') {
            return str_replace('-', '_', $loc);
        }
        static $map = ['en' => 'en_US', 'cn' => 'zh_CN', 'es' => 'es_ES'];
        return $map[$lang] ?? $lang;
    }

    public function renderBreadcrumbs(array $items): string
    {
        $html = '<div class="container"><nav aria-label="Breadcrumb" class="breadcrumb">';
        $html .= '<ol itemscope itemtype="https://schema.org/BreadcrumbList">';

        $position = 1;
        foreach ($items as $item) {
            $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
            if (!empty($item['url'])) {
                $html .= '<a itemprop="item" href="' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '">';
                $html .= '<span itemprop="name">' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . '</span>';
                $html .= '</a>';
            } else {
                $html .= '<span itemprop="name">' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . '</span>';
            }
            $html .= '<meta itemprop="position" content="' . $position . '">';
            $html .= '</li>';
            $position++;
        }

        $html .= '</ol></nav></div>';

        return $html;
    }

    public function renderBreadcrumbsSchema(array $items): string
    {
        $listItems = [];
        $position = 1;

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $listItem = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $name,
            ];

            if (!empty($item['url'])) {
                $listItem['item'] = $this->absoluteUrl((string) $item['url']);
            }

            $listItems[] = $listItem;
            $position++;
        }

        if ($listItems === []) {
            return '';
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems,
        ];

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    public function renderOrganizationSchema(): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $this->siteName,
            'url' => $this->siteUrl,
        ];

        $logo = Config::get('logo', '');
        if (!empty($logo)) {
            $schema['logo'] = $this->absoluteUrl($logo);
        }

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    /**
     * WebSite JSON-LD with optional Sitelinks Searchbox (potentialAction → SearchAction).
     *
     * The SearchAction target uses the existing /{lang}/search?q= route.
     * Can be disabled site-wide via site.json: "sitelinks_searchbox": false
     *
     * Schema.org reference: https://schema.org/WebSite
     * Google guide: https://developers.google.com/search/docs/appearance/sitelinks-searchbox
     */
    public function renderWebSiteSchema(): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $this->siteName,
            'url'      => $this->siteUrl . '/' . $this->lang . '/',
        ];

        // Sitelinks Searchbox — enabled by default, opt-out via sitelinks_searchbox: false
        if (Config::get('sitelinks_searchbox', true)) {
            $urlTemplate = $this->siteUrl . '/' . $this->lang . '/search?q={search_term_string}';

            $schema['potentialAction'] = [
                [
                    '@type'       => 'SearchAction',
                    'target'      => [
                        '@type'       => 'EntryPoint',
                        'urlTemplate' => $urlTemplate,
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ];
        }

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    public function renderCollectionPageSchema(string $name, string $path): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => $this->siteUrl . $path,
        ];

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    public function renderArticleSchema(array $post, string $canonicalPath): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post['title'] ?? '',
            'description' => MetaHelper::excerpt(MetaHelper::stripHtml($post['desc'] ?? ''), 300),
            'inLanguage' => $this->lang,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $this->siteUrl . $canonicalPath,
            ],
        ];
        if (!empty($post['date'])) {
            $schema['datePublished'] = $post['date'];
        }
        if (!empty($post['image'])) {
            $schema['image'] = [$this->absoluteUrl($post['image'])];
        }

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    public function renderLocalBusinessSchema(array $contact): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $this->siteName,
            'url' => $this->siteUrl . '/' . $this->lang . '/contact',
        ];
        if (!empty($contact['address'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $contact['address'],
            ];
        }
        if (!empty($contact['phone'])) {
            $schema['telephone'] = $contact['phone'];
        }
        if (!empty($contact['email'])) {
            $schema['email'] = $contact['email'];
        }

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    /**
     * Product JSON-LD (https://schema.org/Product).
     *
     * @param array<string, mixed> $product
     * @param array{
     *   product_url?: string,
     *   category_name?: string,
     *   brand_name?: string,
     * } $context
     */
    public function renderProductSchema(array $product, array $context = []): string
    {
        $images = [];
        if (!empty($product['images']) && is_array($product['images'])) {
            foreach ($product['images'] as $img) {
                if (!empty($img['url'])) {
                    $images[] = $this->absoluteUrl((string) $img['url']);
                }
            }
        }
        if (empty($images) && !empty($product['image'])) {
            $images[] = $this->absoluteUrl((string) $product['image']);
        }

        $descPlain = MetaHelper::stripHtml(
            (string) (
                $product['seo_desc'] ?? $product['seo_description'] ?? $product['desc']
                ?? $product['short_description'] ?? $product['short_desc'] ?? ''
            )
        );
        $desc = MetaHelper::excerpt($descPlain, 5000);

        $sku = trim((string) ($product['product_model'] ?? ''));
        if ($sku === '') {
            $sku = trim((string) ($product['slug'] ?? ''));
        }
        $slug = trim((string) ($product['slug'] ?? ''));

        $brandName = trim((string) ($context['brand_name'] ?? ''));
        if ($brandName === '') {
            $brandName = trim((string) Config::get('company_legal_name', ''));
        }
        if ($brandName === '') {
            $brandName = trim((string) Config::get('site_name', ''));
        }

        $productUrl = trim((string) ($context['product_url'] ?? ''));
        $categoryName = trim((string) ($context['category_name'] ?? ''));

        $name = trim((string) ($product['name'] ?? ''));
        if ($name === '') {
            $name = $sku !== '' ? $sku : ($slug !== '' ? $slug : 'Product');
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
        ];
        if ($sku !== '') {
            $schema['sku'] = $sku;
        }

        if ($desc !== '') {
            $schema['description'] = $desc;
        }

        if ($productUrl !== '') {
            $schema['url'] = $productUrl;
            $schema['@id'] = $productUrl;
        }

        if ($brandName !== '') {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $brandName,
            ];
            $logoPath = trim((string) Config::get('logo', ''));
            if ($logoPath !== '') {
                $schema['brand']['logo'] = $this->absoluteUrl($logoPath);
            }
        }

        if ($categoryName !== '') {
            $schema['category'] = $categoryName;
        }

        $manufacturer = $this->buildProductManufacturerJsonLd($product, $brandName);
        if ($manufacturer !== null) {
            $schema['manufacturer'] = $manufacturer;
        }

        $material = trim((string) ($product['material'] ?? $product['materials'] ?? ''));
        if ($material !== '') {
            $schema['material'] = $material;
        }

        $additionalProps = $this->buildProductAdditionalProperties($product);
        if ($additionalProps !== []) {
            $schema['additionalProperty'] = $additionalProps;
        }

        if (!empty($images)) {
            $schema['image'] = count($images) === 1 ? $images[0] : $images;
        }

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>|null
     */
    private function buildProductManufacturerJsonLd(array $product, string $brandName): ?array
    {
        $explicit = trim((string) ($product['manufacturer'] ?? ''));
        if ($explicit !== '') {
            return [
                '@type' => 'Organization',
                'name' => $explicit,
            ];
        }
        $orgName = trim((string) Config::get('company_legal_name', ''));
        if ($orgName === '') {
            $orgName = $brandName;
        }
        if ($orgName === '') {
            return null;
        }
        $out = [
            '@type' => 'Organization',
            'name' => $orgName,
        ];
        $base = rtrim($this->siteUrl, '/');
        if ($base !== '') {
            $out['url'] = $base;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $product
     * @return list<array<string, mixed>>
     */
    private function buildProductAdditionalProperties(array $product): array
    {
        $list = [];
        $specs = $product['specs'] ?? [];
        if (is_array($specs)) {
            foreach ($specs as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $propName = trim((string) ($row['label'] ?? $row['name'] ?? ''));
                $propValue = trim((string) ($row['value'] ?? ''));
                if ($propName === '' && $propValue === '') {
                    continue;
                }
                $list[] = [
                    '@type' => 'PropertyValue',
                    'name' => $propName !== '' ? $propName : 'Specification',
                    'value' => $propValue,
                ];
            }
        }

        $extra = $product['additionalProperty'] ?? $product['additional_properties'] ?? [];
        if (is_array($extra)) {
            foreach ($extra as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $propName = trim((string) ($row['name'] ?? $row['label'] ?? ''));
                $propValue = trim((string) ($row['value'] ?? ''));
                if ($propName === '' && $propValue === '') {
                    continue;
                }
                $list[] = [
                    '@type' => 'PropertyValue',
                    'name' => $propName !== '' ? $propName : 'Property',
                    'value' => $propValue,
                ];
            }
        }

        return $list;
    }

    /**
     * FAQPage JSON-LD from faq rows and/or legacy faq_json on the product record.
     *
     * @param array<int, array<string, mixed>> $faqs
     * @param array<string, mixed>|null $productFallback when $faqs is empty, decode faq_json from this product
     */
    public function renderFaqPageSchema(array $faqs, ?array $productFallback = null): string
    {
        $rows = $faqs;
        if ((!is_array($rows) || $rows === []) && $productFallback !== null) {
            $rows = $this->faqRowsFromProduct($productFallback);
        }
        if (!is_array($rows)) {
            $rows = [];
        }

        $main = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $q = trim((string) ($row['question'] ?? $row['q'] ?? ''));
            $aRaw = (string) ($row['answer'] ?? $row['a'] ?? '');
            $a = trim(MetaHelper::stripHtml($aRaw));
            if ($q === '' || $a === '') {
                continue;
            }
            $main[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $a,
                ],
            ];
        }
        if ($main === []) {
            return '';
        }
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $main,
        ];

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    /**
     * @param array<string, mixed> $product
     * @return array<int, array<string, mixed>>
     */
    private function faqRowsFromProduct(array $product): array
    {
        $raw = $product['faqs'] ?? [];
        if (is_array($raw) && $raw !== []) {
            return $raw;
        }
        $json = $product['faq_json'] ?? '';
        if (!is_string($json) || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * W3C datetime for sitemap &lt;lastmod&gt; from JSON row timestamps.
     *
     * @param array<string, mixed> $row
     */
    public static function resolveSitemapLastmod(array $row): string
    {
        foreach (['updated_at', 'published_at', 'date', 'created_at'] as $key) {
            $str = trim((string) ($row[$key] ?? ''));
            if ($str === '') {
                continue;
            }
            $ts = strtotime($str);
            if ($ts !== false) {
                return date('c', $ts);
            }
        }

        return date('c');
    }

    public static function formatSitemapPriority(float $priority): string
    {
        $n = max(0.0, min(1.0, $priority));

        return number_format($n, 2, '.', '');
    }

    /**
     * Persist sitemap.xml + image-sitemap.xml (same markup as dynamic routes).
     */
    public static function writeSitemapToDisk(): void
    {
        if (!\defined('ROOT_PATH')) {
            return;
        }
        $root = \constant('ROOT_PATH');
        file_put_contents($root . '/sitemap.xml', self::generateSitemap(), LOCK_EX);
        file_put_contents($root . '/image-sitemap.xml', self::generateImageSitemap(), LOCK_EX);
    }

    /**
     * @param array{loc: string, lastmod: string, changefreq: string, priority: float|int} $url
     */
    private static function appendSitemapUrlXml(string &$xml, array $url): void
    {
        $pr = self::formatSitemapPriority((float) ($url['priority'] ?? 0.5));
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars((string) ($url['loc'] ?? ''), ENT_XML1, 'UTF-8') . "</loc>\n";
        $xml .= '    <lastmod>' . htmlspecialchars((string) ($url['lastmod'] ?? date('c')), ENT_XML1, 'UTF-8') . "</lastmod>\n";
        $xml .= '    <changefreq>' . htmlspecialchars((string) ($url['changefreq'] ?? 'weekly'), ENT_XML1, 'UTF-8') . "</changefreq>\n";
        $xml .= '    <priority>' . $pr . "</priority>\n";
        $xml .= "  </url>\n";
    }

    public static function generateSitemap(): string
    {
        $siteUrl = rtrim(Config::get('site_url', ''), '/');
        $supportedLangs = Config::get('supported_langs', ['en', 'cn', 'es']);
        if (!is_array($supportedLangs)) {
            $supportedLangs = ['en'];
        }

        $urls = [];
        $now = date('c');

        $staticPages = [
            '' => ['priority' => 1.0, 'changefreq' => 'daily'],
            'products' => ['priority' => 0.95, 'changefreq' => 'daily'],
            'factory' => ['priority' => 0.75, 'changefreq' => 'weekly'],
            'about' => ['priority' => 0.8, 'changefreq' => 'weekly'],
            'cases' => ['priority' => 0.8, 'changefreq' => 'weekly'],
            'blog' => ['priority' => 0.85, 'changefreq' => 'weekly'],
            'contact' => ['priority' => 0.75, 'changefreq' => 'weekly'],
            'newsletter' => ['priority' => 0.5, 'changefreq' => 'monthly'],
        ];

        foreach ($supportedLangs as $lang) {
            $lang = trim((string) $lang);
            if ($lang === '') {
                continue;
            }

            foreach ($staticPages as $page => $meta) {
                $urls[] = [
                    'loc' => $siteUrl . '/' . $lang . ($page !== '' ? '/' . $page : ''),
                    'lastmod' => $now,
                    'changefreq' => $meta['changefreq'],
                    'priority' => $meta['priority'],
                ];
            }

            $cats = JsonStore::langData($lang, 'categories')->read();
            if (is_array($cats)) {
                foreach ($cats as $cat) {
                    if (!is_array($cat) || empty($cat['slug'])) {
                        continue;
                    }
                    $urls[] = [
                        'loc' => $siteUrl . '/' . $lang . '/products/' . rawurlencode((string) $cat['slug']),
                        'lastmod' => self::resolveSitemapLastmod($cat),
                        'changefreq' => 'weekly',
                        'priority' => 0.9,
                    ];
                }
            }

            $products = JsonStore::langData($lang, 'products')->read();
            if (is_array($products)) {
                foreach ($products as $item) {
                    if (!is_array($item) || empty($item['slug'])) {
                        continue;
                    }
                    if (!ProductPublishState::isPublicVisible($item)) {
                        continue;
                    }
                    $urls[] = [
                        'loc' => $siteUrl . '/' . $lang . '/product/' . rawurlencode((string) $item['slug']),
                        'lastmod' => self::resolveSitemapLastmod($item),
                        'changefreq' => 'weekly',
                        'priority' => 0.85,
                    ];
                }
            }

            foreach (['cases', 'blog'] as $type) {
                $store = JsonStore::langData($lang, $type)->read();
                $pathSeg = $type === 'cases' ? 'cases' : 'blog';
                if (!is_array($store)) {
                    continue;
                }
                foreach ($store as $item) {
                    if (!is_array($item) || empty($item['slug'])) {
                        continue;
                    }
                    if ($type === 'blog' && ($item['status'] ?? 'published') !== 'published') {
                        continue;
                    }
                    if ($type === 'cases' && isset($item['status']) && $item['status'] !== 'published') {
                        continue;
                    }
                    $urls[] = [
                        'loc' => $siteUrl . '/' . $lang . '/' . $pathSeg . '/' . rawurlencode((string) $item['slug']),
                        'lastmod' => self::resolveSitemapLastmod($item),
                        'changefreq' => 'monthly',
                        'priority' => 0.72,
                    ];
                }
            }

            $pages = JsonStore::langData($lang, 'pages')->read();
            if (is_array($pages)) {
                foreach ($pages as $item) {
                    if (!is_array($item) || empty($item['slug'])) {
                        continue;
                    }
                    $urls[] = [
                        'loc' => $siteUrl . '/' . $lang . '/page/' . rawurlencode((string) $item['slug']),
                        'lastmod' => self::resolveSitemapLastmod($item),
                        'changefreq' => 'monthly',
                        'priority' => 0.65,
                    ];
                }
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            self::appendSitemapUrlXml($xml, $url);
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Google image sitemap: product gallery (all product images) + blog featured images.
     */
    public static function generateImageSitemap(): string
    {
        $siteUrl = rtrim(Config::get('site_url', ''), '/');
        $supportedLangs = Config::get('supported_langs', ['en', 'cn', 'es']);
        if (!is_array($supportedLangs)) {
            $supportedLangs = ['en'];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($supportedLangs as $lang) {
            $lang = trim((string) $lang);
            if ($lang === '') {
                continue;
            }

            $productStore = JsonStore::langData($lang, 'products')->read();
            if (!is_array($productStore)) {
                $productStore = [];
            }
            foreach ($productStore as $item) {
                if (!is_array($item) || empty($item['slug'])) {
                    continue;
                }
                if (!ProductPublishState::isPublicVisible($item)) {
                    continue;
                }
                $pageUrl = $siteUrl . '/' . $lang . '/product/' . rawurlencode((string) $item['slug']);
                $seen = [];
                $imageBlocks = '';
                if (!empty($item['images']) && is_array($item['images'])) {
                    foreach ($item['images'] as $img) {
                        if (!is_array($img)) {
                            continue;
                        }
                        $u = trim((string) ($img['url'] ?? ''));
                        if ($u === '' || isset($seen[$u])) {
                            continue;
                        }
                        $seen[$u] = true;
                        $abs = self::absoluteImageLoc($siteUrl, $u);
                        if ($abs === '') {
                            continue;
                        }
                        $cap = (string) ($img['alt_text'] ?? $item['name'] ?? '');
                        $imageBlocks .= '    <image:image>' . "\n";
                        $imageBlocks .= '      <image:loc>' . htmlspecialchars($abs, ENT_XML1, 'UTF-8') . "</image:loc>\n";
                        if ($cap !== '') {
                            $imageBlocks .= '      <image:caption>' . htmlspecialchars($cap, ENT_XML1, 'UTF-8') . "</image:caption>\n";
                        }
                        $imageBlocks .= '    </image:image>' . "\n";
                    }
                }
                $main = trim((string) ($item['image'] ?? ''));
                if ($main !== '' && !isset($seen[$main])) {
                    $abs = self::absoluteImageLoc($siteUrl, $main);
                    if ($abs !== '') {
                        $imageBlocks .= '    <image:image>' . "\n";
                        $imageBlocks .= '      <image:loc>' . htmlspecialchars($abs, ENT_XML1, 'UTF-8') . "</image:loc>\n";
                        $imageBlocks .= '      <image:caption>' . htmlspecialchars((string) ($item['name'] ?? ''), ENT_XML1, 'UTF-8') . "</image:caption>\n";
                        $imageBlocks .= '    </image:image>' . "\n";
                    }
                }
                if ($imageBlocks === '') {
                    continue;
                }
                $lastmod = self::resolveSitemapLastmod($item);
                $xml .= "  <url>\n";
                $xml .= '    <loc>' . htmlspecialchars($pageUrl, ENT_XML1, 'UTF-8') . "</loc>\n";
                $xml .= '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1, 'UTF-8') . "</lastmod>\n";
                $xml .= '    <changefreq>weekly</changefreq>' . "\n";
                $xml .= '    <priority>' . self::formatSitemapPriority(0.75) . "</priority>\n";
                $xml .= $imageBlocks;
                $xml .= "  </url>\n";
            }

            $blogStore = JsonStore::langData($lang, 'blog')->read();
            if (!is_array($blogStore)) {
                $blogStore = [];
            }
            foreach ($blogStore as $post) {
                if (!is_array($post) || empty($post['slug'])) {
                    continue;
                }
                if (($post['status'] ?? 'published') !== 'published') {
                    continue;
                }
                $imgRaw = trim((string) ($post['image'] ?? ''));
                if ($imgRaw === '') {
                    continue;
                }
                $pageUrl = $siteUrl . '/' . $lang . '/blog/' . rawurlencode((string) $post['slug']);
                $abs = self::absoluteImageLoc($siteUrl, $imgRaw);
                if ($abs === '') {
                    continue;
                }
                $lastmod = self::resolveSitemapLastmod($post);
                $xml .= "  <url>\n";
                $xml .= '    <loc>' . htmlspecialchars($pageUrl, ENT_XML1, 'UTF-8') . "</loc>\n";
                $xml .= '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1, 'UTF-8') . "</lastmod>\n";
                $xml .= '    <changefreq>monthly</changefreq>' . "\n";
                $xml .= '    <priority>' . self::formatSitemapPriority(0.65) . "</priority>\n";
                $xml .= '    <image:image>' . "\n";
                $xml .= '      <image:loc>' . htmlspecialchars($abs, ENT_XML1, 'UTF-8') . "</image:loc>\n";
                $xml .= '      <image:caption>' . htmlspecialchars((string) ($post['title'] ?? ''), ENT_XML1, 'UTF-8') . "</image:caption>\n";
                $xml .= '    </image:image>' . "\n";
                $xml .= "  </url>\n";
            }
        }

        $xml .= '</urlset>';

        return $xml;
    }

    private static function absoluteImageLoc(string $siteUrl, string $pathOrUrl): string
    {
        $pathOrUrl = trim($pathOrUrl);
        if ($pathOrUrl === '') {
            return '';
        }
        if (strncmp($pathOrUrl, 'http://', 7) === 0 || strncmp($pathOrUrl, 'https://', 8) === 0) {
            return $pathOrUrl;
        }
        return $siteUrl . (strncmp($pathOrUrl, '/', 1) === 0 ? $pathOrUrl : '/' . $pathOrUrl);
    }

    private function getSeoData(string $page, string $slug, array $overrides): array
    {
        $store = JsonStore::langData($this->lang, 'seo');
        $seoDefaults = $store->read();
        $pageSeo = $seoDefaults[$page] ?? [];
        $pageSeoFallback = [];
        if ($this->lang !== 'en') {
            $fallbackSeoDefaults = JsonStore::langData('en', 'seo')->read();
            $pageSeoFallback = is_array($fallbackSeoDefaults) ? ($fallbackSeoDefaults[$page] ?? []) : [];
        }

        $entityFallback = $this->getEntitySeoFallbackFromDefaultLang($page, $slug);

        $brand = $this->siteName;
        $tagline = Config::get('site_tagline', 'Special Cable Manufacturer');

        $paginationQ = self::paginationQuerySuffix();
        $searchLike = self::hasSearchOrFilterNoIndex() || self::isSearchPathRequest();
        $robots = ($overrides['robots'] ?? null) !== null
            ? (string) $overrides['robots']
            : ($searchLike ? 'noindex, follow' : 'index, follow');

        $rawTitle = (string) (
            $overrides['seo_title']
            ?? $overrides['title']
            ?? $entityFallback['seo_title']
            ?? $pageSeo['title']
            ?? $pageSeoFallback['title']
            ?? ''
        );
        $rawDesc = (string) (
            $overrides['seo_desc']
            ?? $overrides['description']
            ?? $entityFallback['seo_desc']
            ?? $pageSeo['description']
            ?? $pageSeoFallback['description']
            ?? ''
        );

        $title = $this->buildTitleForPage($page, $rawTitle, $brand, $tagline);
        $description = $this->buildDescriptionForPage($page, $rawDesc, $overrides);

        if (!empty($overrides['canonical_rel'])) {
            $canonicalRel = (string) $overrides['canonical_rel'];
            $hreflangBase = preg_replace('/\?.*$/', '', $canonicalRel);
            $hreflangQuery = '';
            if (preg_match('/\?(.*)$/', $canonicalRel, $qm)) {
                $hreflangQuery = '?' . $qm[1];
            }
        } else {
            $hreflangBase = $this->buildCanonicalPath($page, $slug);
            $canonicalRel = $hreflangBase . $paginationQ;
            $hreflangQuery = $paginationQ;
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical_rel' => $canonicalRel,
            'hreflang_base' => $hreflangBase,
            'hreflang_query' => $hreflangQuery,
            'image' => $overrides['image'] ?? '',
            'og_type' => ($page === 'blog' && $slug !== '') ? 'article' : 'website',
            'robots' => $robots,
            'og_image_alt' => $this->resolveOgImageAltForSeoData($page, $slug, $overrides),
            'meta_author' => $this->resolveMetaAuthorForSeoData($page, $slug, $overrides),
            'meta_keywords' => trim((string) ($overrides['seo_keywords'] ?? $overrides['keywords'] ?? '')),
        ];
    }

    /**
     * Optional og:image:alt — overrides first, else main/first product image alt_text.
     */
    private function resolveOgImageAltForSeoData(string $page, string $slug, array $overrides): string
    {
        $fromOverride = trim((string) ($overrides['og_image_alt'] ?? $overrides['image_alt'] ?? ''));
        if ($fromOverride !== '') {
            return $fromOverride;
        }
        if ($page !== 'product' || $slug === '') {
            return '';
        }
        $rows = JsonStore::langData($this->lang, 'products')->read();
        if (!is_array($rows)) {
            return '';
        }
        foreach ($rows as $row) {
            if (!is_array($row) || ($row['slug'] ?? '') !== $slug) {
                continue;
            }
            $imgs = $row['images'] ?? [];
            if (is_array($imgs)) {
                foreach ($imgs as $img) {
                    if (!is_array($img) || empty($img['is_main'])) {
                        continue;
                    }
                    $alt = trim((string) ($img['alt_text'] ?? ''));
                    if ($alt !== '') {
                        return $alt;
                    }
                }
                foreach ($imgs as $img) {
                    if (!is_array($img)) {
                        continue;
                    }
                    $u = trim((string) ($img['url'] ?? ''));
                    $alt = trim((string) ($img['alt_text'] ?? ''));
                    if ($u !== '' && $alt !== '') {
                        return $alt;
                    }
                }
            }

            return trim((string) ($row['name'] ?? ''));
        }

        return '';
    }

    /**
     * Optional meta name="author" — overrides, site.json meta_author, or blog post author.
     */
    private function resolveMetaAuthorForSeoData(string $page, string $slug, array $overrides): string
    {
        $a = trim((string) ($overrides['meta_author'] ?? $overrides['author'] ?? ''));
        if ($a !== '') {
            return $a;
        }
        $a = trim((string) Config::get('meta_author', ''));
        if ($a !== '') {
            return $a;
        }
        if ($page !== 'blog' || $slug === '') {
            return '';
        }
        $rows = JsonStore::langData($this->lang, 'blog')->read();
        if (!is_array($rows)) {
            return '';
        }
        foreach ($rows as $row) {
            if (!is_array($row) || ($row['slug'] ?? '') !== $slug) {
                continue;
            }
            $author = trim((string) ($row['author'] ?? $row['author_name'] ?? ''));

            return $author;
        }

        return '';
    }

    private function buildTitleForPage(string $page, string $rawTitle, string $brand, string $tagline): string
    {
        if ($page === 'home') {
            if ($rawTitle !== '') {
                return MetaHelper::titleWithBrand($rawTitle, $brand);
            }
            return MetaHelper::titleWithBrand($tagline, $brand);
        }

        if (in_array($page, ['products', 'product', 'blog', 'about', 'contact', 'cases', 'factory', 'page', 'compare', 'search', 'products_category', 'newsletter'], true)) {
            if ($rawTitle !== '') {
                return MetaHelper::titleWithBrand($rawTitle, $brand);
            }
            $fallback = Config::get('default_meta_title', '');
            if ($fallback !== '') {
                return MetaHelper::titleWithBrand($fallback, $brand);
            }
            return $brand;
        }

        if ($rawTitle !== '') {
            return MetaHelper::titleWithBrand($rawTitle, $brand);
        }
        return $brand;
    }

    private function buildDescriptionForPage(string $page, string $rawDesc, array $overrides): string
    {
        if ($rawDesc !== '') {
            return MetaHelper::excerpt($rawDesc, 160, '…');
        }
        $globalDefaultDesc = (string) Config::get('default_meta_description', '');
        if ($globalDefaultDesc !== '') {
            return MetaHelper::excerpt($globalDefaultDesc, 160, '…');
        }

        $body = (string) ($overrides['body_text'] ?? $overrides['content_plain'] ?? '');
        if ($body !== '') {
            return MetaHelper::excerpt($body, 160, '…');
        }

        $htmlBody = (string) ($overrides['content_html'] ?? '');
        if ($htmlBody !== '') {
            return MetaHelper::descriptionFromHtml($htmlBody, 160);
        }

        return MetaHelper::excerpt($this->siteName . ' — ' . ucfirst(str_replace('_', ' ', $page)), 160, '…');
    }

    private function buildCanonicalPath(string $page, string $slug): string
    {
        $path = '/' . $this->lang;
        if ($page === 'home') {
            return $path;
        }
        if ($page === 'products_category' && $slug !== '') {
            return $path . '/products/' . rawurlencode($slug);
        }
        if ($page === 'product' && $slug !== '') {
            return $path . '/product/' . rawurlencode($slug);
        }
        if ($page === 'blog' && $slug !== '') {
            return $path . '/blog/' . rawurlencode($slug);
        }
        if ($page === 'cases' && $slug !== '') {
            return $path . '/cases/' . rawurlencode($slug);
        }
        if ($page === 'page' && $slug !== '') {
            return $path . '/page/' . rawurlencode($slug);
        }
        if ($page === 'compare') {
            return $path . '/compare';
        }
        if ($page === 'search') {
            return $path . '/search';
        }
        if ($page === 'newsletter') {
            return $path . '/newsletter';
        }

        return $path . '/' . $page;
    }

    private function extractPathWithoutLang(string $path): string
    {
        $pathOnly = preg_replace('/\?.*$/', '', $path);
        $pathOnly = preg_replace('#^/(' . implode('|', $this->supportedLangs) . ')#', '', $pathOnly);
        return $pathOnly ?: '';
    }

    private function getLocale(): string
    {
        $locale = Config::get('lang_config.' . $this->lang . '.locale', '');
        if ($locale !== '') {
            return $locale;
        }
        static $defaults = [
            'en' => 'en_US',
            'cn' => 'zh_CN',
            'es' => 'es_ES',
        ];
        return $defaults[$this->lang] ?? 'en_US';
    }

    private function absoluteUrl(string $pathOrUrl): string
    {
        if (strncmp($pathOrUrl, 'http://', 7) === 0 || strncmp($pathOrUrl, 'https://', 8) === 0) {
            return $pathOrUrl;
        }
        return $this->siteUrl . $pathOrUrl;
    }

    /**
     * Fallback to default language entity-level SEO when current language misses it.
     */
    private function getEntitySeoFallbackFromDefaultLang(string $page, string $slug): array
    {
        if ($this->lang === 'en' || $slug === '') {
            return [];
        }

        if ($page === 'product') {
            $rows = JsonStore::langData('en', 'products')->read();
            if (!is_array($rows)) {
                return [];
            }
            foreach ($rows as $row) {
                if (($row['slug'] ?? '') !== $slug) {
                    continue;
                }
                return [
                    'seo_title' => trim((string) ($row['seo_title'] ?? $row['name'] ?? '')),
                    'seo_desc' => trim((string) ($row['seo_desc'] ?? $row['seo_description'] ?? $row['desc'] ?? $row['short_description'] ?? $row['short_desc'] ?? '')),
                ];
            }
        }

        if ($page === 'blog') {
            $rows = JsonStore::langData('en', 'blog')->read();
            if (!is_array($rows)) {
                return [];
            }
            foreach ($rows as $row) {
                if (($row['slug'] ?? '') !== $slug) {
                    continue;
                }
                return [
                    'seo_title' => trim((string) ($row['seo_title'] ?? $row['title'] ?? '')),
                    'seo_desc' => trim((string) ($row['seo_desc'] ?? $row['desc'] ?? '')),
                ];
            }
        }

        return [];
    }
}
