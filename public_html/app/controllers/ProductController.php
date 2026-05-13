<?php

namespace App\Controllers;

use App\Core\JsonStore;
use App\Core\MetaHelper;
use App\Core\ProductFileStore;
use App\Core\ProductPublishState;
use App\Core\StaticCache;
use App\Core\View;

class ProductController extends BaseController
{
    public function index(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: private, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        $categorySlug = $this->getQuery('category', '');

        if (!empty($categorySlug)) {
            $this->redirect('/' . $this->lang . '/products/' . rawurlencode($categorySlug), 301);
        }

        $products = $this->filterPublicProducts($this->getLangData('products'));
        $categories = JsonStore::langData($this->lang, 'categories')->read();

        $canonicalPath = '/' . $this->lang . '/products';
        $idxMeta = MetaHelper::buildProductsIndexMeta($this->t('products'), MetaHelper::stripHtml($this->t('products_desc')));
        $seoHead = $this->seo->renderMeta('products', '', $idxMeta['overrides']) . $idxMeta['head_suffix'];
        $breadcrumbs = $this->seo->renderBreadcrumbs([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('products'), 'url' => View::langUrl($this->lang, 'products')],
        ]);
        $breadcrumbsSchema = $this->seo->renderBreadcrumbsSchema([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('products'), 'url' => View::langUrl($this->lang, 'products')],
        ]);
        $collectionSchema = $this->seo->renderCollectionPageSchema($this->t('products'), $canonicalPath);

        $this->renderCached('/' . $this->lang . '/products', 'product_list', [
            'products' => $products,
            'allCategories' => $categories,
            'activeCategory' => null,
            'seoHead' => $seoHead,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbsSchema' => $breadcrumbsSchema,
            'extraSchema' => $collectionSchema,
            'h1' => $this->t('products'),
            'popularProducts' => array_slice($products, 0, 6),
        ]);
    }

    public function category(string $slug = ''): void
    {
        if (empty($slug)) {
            $this->redirect('/' . $this->lang . '/products');
        }
        $this->showCategory($slug);
    }

    private function showCategory(string $slug): void
    {
        $categories = JsonStore::langData($this->lang, 'categories')->read();
        $activeCategory = CategoryController::resolveLayer($this->lang, $slug);

        $allProducts = $this->filterPublicProducts($this->getLangData('products'));
        $filteredProducts = [];
        if ($activeCategory) {
            foreach ($allProducts as $p) {
                if (($p['category_id'] ?? '') === $slug) {
                    $filteredProducts[] = $p;
                }
            }
        } else {
            $productMatch = null;
            foreach ($allProducts as $p) {
                if (($p['slug'] ?? '') === $slug) {
                    $productMatch = $p;
                    break;
                }
            }
            if ($productMatch !== null) {
                $this->redirect('/' . $this->lang . '/product/' . rawurlencode($slug), 301);
            }
            $this->renderFront404();
        }

        $catMeta = MetaHelper::buildCategoryListingMeta($activeCategory);
        $seoHead = $this->seo->renderMeta('products_category', $slug, $catMeta['overrides']) . $catMeta['head_suffix'];
        $breadcrumbs = $this->seo->renderBreadcrumbs([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('products'), 'url' => View::langUrl($this->lang, 'products')],
            ['name' => $activeCategory['name'] ?? $slug],
        ]);
        $breadcrumbsSchema = $this->seo->renderBreadcrumbsSchema([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('products'), 'url' => View::langUrl($this->lang, 'products')],
            ['name' => $activeCategory['name'] ?? $slug, 'url' => View::langUrl($this->lang, 'products/' . $slug)],
        ]);
        $canonicalPath = '/' . $this->lang . '/products/' . rawurlencode($slug);
        $collectionSchema = $this->seo->renderCollectionPageSchema($activeCategory['name'] ?? $this->t('products'), $canonicalPath);

        $popularSlice = array_slice($filteredProducts, 0, 8);
        if ($popularSlice === []) {
            $popularSlice = array_slice($allProducts, 0, 8);
        }

        $this->renderCached('/' . $this->lang . '/products/' . $slug, 'product_list', [
            'products' => $filteredProducts,
            'allCategories' => $categories,
            'activeCategory' => $activeCategory,
            'seoHead' => $seoHead,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbsSchema' => $breadcrumbsSchema,
            'extraSchema' => $collectionSchema,
            'h1' => $activeCategory['name'] ?? $this->t('products'),
            'popularProducts' => $popularSlice,
        ]);
    }

    public function show(string $slug = ''): void
    {
        if (empty($slug)) {
            $this->redirect('/' . $this->lang . '/products');
        }

        if (!headers_sent()) {
            header('Cache-Control: private, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        // Module 11: fast path — read one shard instead of loading the full products array.
        // Falls back to full-array scan when shards haven't been built yet (first deploy).
        $product = ProductFileStore::getBySlug($this->lang, $slug);
        if ($product !== null && !ProductPublishState::isPublicVisible($product)) {
            $product = null; // shard found but product is not publicly visible
        }

        // Full-array fallback: also used to build $products for related-product resolution.
        $products = $this->filterPublicProducts($this->getLangData('products'));

        if ($product === null) {
            // Shard not available yet — locate product in the full array
            foreach ($products as $p) {
                if (isset($p['slug']) && $p['slug'] === $slug) {
                    $product = $p;
                    break;
                }
            }
        }

        if ($product === null) {
            $this->renderFront404();
        }

        $product = $this->normalizeProductB2bFields($product);

        $contactData = $this->getLangData('contact');
        $catSlug = trim((string) ($product['category_id'] ?? ''));
        $categoryLayer = CategoryController::resolveLayer($this->lang, $catSlug !== '' ? $catSlug : null);
        $catName = $categoryLayer !== null ? (string) ($categoryLayer['name'] ?? '') : '';

        $metaBundle = MetaHelper::buildProductDetailMeta($product, $categoryLayer);
        $metaOverrides = $metaBundle['overrides'];
        $canonicalRel = $this->normalizeProductCanonicalRel((string) ($product['canonical_url'] ?? ''));
        if ($canonicalRel !== null && $canonicalRel !== '') {
            $metaOverrides['canonical_rel'] = $canonicalRel;
        }

        $seoHead = $this->seo->renderMeta('product', $slug, $metaOverrides) . $metaBundle['head_suffix'];
        $seoHead .= $this->renderProductHeadExtras($product);

        $breadcrumbsItems = [
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('products'), 'url' => View::langUrl($this->lang, 'products')],
        ];
        if ($catSlug !== '' && $catName !== '') {
            $breadcrumbsItems[] = [
                'name' => $catName,
                'url' => View::langUrl($this->lang, 'products/' . $catSlug),
            ];
        }
        $breadcrumbsItems[] = ['name' => $product['name'] ?? ''];

        $breadcrumbs = $this->seo->renderBreadcrumbs($breadcrumbsItems);
        $breadcrumbsSchemaItems = [
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('products'), 'url' => View::langUrl($this->lang, 'products')],
        ];
        if ($catSlug !== '' && $catName !== '') {
            $breadcrumbsSchemaItems[] = [
                'name' => $catName,
                'url' => View::langUrl($this->lang, 'products/' . $catSlug),
            ];
        }
        $breadcrumbsSchemaItems[] = [
            'name' => $product['name'] ?? '',
            'url' => View::langUrl($this->lang, 'product/' . $slug),
        ];
        $breadcrumbsSchema = $this->seo->renderBreadcrumbsSchema($breadcrumbsSchemaItems);
        $productPageUrl = View::langUrl($this->lang, 'product/' . $slug);
        $productSchema = $this->seo->renderProductSchema($product, [
            'product_url' => $productPageUrl,
            'category_name' => $catName,
        ]);
        $faqs = $product['faqs'] ?? [];
        if (!is_array($faqs)) {
            $faqs = [];
        }
        $faqSchema = $this->seo->renderFaqPageSchema($faqs, $product);

        $related = $this->resolveRelatedProducts($products, $product, $slug);

        $blogPosts = JsonStore::langData($this->lang, 'blog')->read();
        $relatedPosts = [];
        if (is_array($blogPosts)) {
            foreach ($blogPosts as $post) {
                if (!empty($post['slug'])) {
                    $relatedPosts[] = $post;
                }
                if (count($relatedPosts) >= 4) {
                    break;
                }
            }
        }

        $datasheetExtras = $product['datasheet_files'] ?? [];
        if (!is_array($datasheetExtras)) {
            $datasheetExtras = [];
        }
        $downloadCenter = $product['download_center'] ?? [];
        if (!is_array($downloadCenter)) {
            $downloadCenter = [];
        }

        $this->renderCached('/' . $this->lang . '/product/' . $slug, 'product/detail', [
            'product' => $product,
            'productFaqs' => $faqs,
            'datasheetExtras' => $datasheetExtras,
            'downloadCenter' => $downloadCenter,
            'contactData' => $contactData,
            'seoHead' => $seoHead,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbsSchema' => $breadcrumbsSchema,
            'productSchema' => $productSchema,
            'faqSchema' => $faqSchema,
            'relatedProducts' => $related,
            'relatedPosts' => $relatedPosts,
            'categoryLink' => ($catSlug !== '') ? View::langUrl($this->lang, 'products/' . $catSlug) : View::langUrl($this->lang, 'products'),
            'categoryName' => $catName,
            'h1' => $product['name'] ?? '',
        ]);
    }

    /**
     * Module 12: Render a front-end template and lazily populate the static cache.
     *
     * Behaviour:
     *  - Static cache disabled or request has query params → plain render, no caching.
     *  - Static cache enabled, clean GET, no existing file → render with ob capture,
     *    echo output, then write to static_cache for future requests.
     *
     * @param string               $cacheUri  Canonical URI used as the cache key (e.g. /en/product/slug)
     * @param string               $template  View template name passed to View::render()
     * @param array<string, mixed> $vars      Template variables
     */
    private function renderCached(string $cacheUri, string $template, array $vars): void
    {
        $cacheable = StaticCache::isEnabled()
            && empty($_GET)
            && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';

        if (!$cacheable) {
            $this->view->render($template, $vars);
            return;
        }

        ob_start();
        $this->view->render($template, $vars);
        $html = ob_get_clean();
        echo $html;

        if (trim($html) !== '') {
            StaticCache::write($cacheUri, $html);
        }
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    private function filterPublicProducts(array $products): array
    {
        return array_values(array_filter($products, static function ($p) {
            return is_array($p) && ProductPublishState::isPublicVisible($p);
        }));
    }

    private function normalizeProductB2bFields(array $product): array
    {
        $faqs = $product['faqs'] ?? [];
        if ((!is_array($faqs) || $faqs === []) && !empty($product['faq_json'])) {
            $decoded = json_decode((string) $product['faq_json'], true);
            if (is_array($decoded)) {
                $out = [];
                foreach ($decoded as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $q = trim((string) ($row['question'] ?? $row['q'] ?? ''));
                    $a = trim((string) ($row['answer'] ?? $row['a'] ?? ''));
                    if ($q !== '' && $a !== '') {
                        $out[] = ['question' => $q, 'answer' => $a];
                    }
                }
                $product['faqs'] = $out;
            }
        }

        if (trim((string) ($product['short_description'] ?? '')) === ''
            && !empty($product['short_desc'])) {
            $product['short_description'] = $product['short_desc'];
        }

        $rel = $product['related_products'] ?? $product['related_product_slugs'] ?? [];
        if (is_string($rel) && trim($rel) !== '') {
            $rel = array_values(array_filter(array_map('trim', preg_split('/[\s,，]+/u', $rel) ?: [])));
        }
        if (is_array($rel) && $rel !== []) {
            $slugs = array_values(
                array_filter(array_map('strval', $rel), static fn ($s) => $s !== '')
            );
            $product['related_product_slugs'] = $slugs;
            $product['related_products'] = $slugs;
        }

        $customOpts = $product['custom_options'] ?? [];
        if (is_string($customOpts) && trim($customOpts) !== '') {
            $decodedCo = json_decode($customOpts, true);
            $customOpts = is_array($decodedCo) ? $decodedCo : [];
        }
        if (!is_array($customOpts)) {
            $customOpts = [];
        }
        $product['custom_options'] = $customOpts;

        if (empty($product['seo_description']) && !empty($product['seo_desc'])) {
            $product['seo_description'] = $product['seo_desc'];
        }

        return $product;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @param array<string, mixed> $product
     * @return array<int, array<string, mixed>>
     */
    private function resolveRelatedProducts(array $products, array $product, string $slug): array
    {
        $manualSlugs = $product['related_products'] ?? $product['related_product_slugs'] ?? [];
        if (!is_array($manualSlugs)) {
            $manualSlugs = [];
        }
        $manualSlugs = array_slice(
            array_values(array_filter(array_map('trim', $manualSlugs), static fn ($s) => $s !== '')),
            0,
            20
        );

        $bySlug = [];
        foreach ($products as $p) {
            $s = $p['slug'] ?? '';
            if ($s !== '') {
                $bySlug[$s] = $p;
            }
        }

        $related = [];
        foreach ($manualSlugs as $ms) {
            if ($ms === $slug || !isset($bySlug[$ms])) {
                continue;
            }
            $related[] = $bySlug[$ms];
            if (count($related) >= 6) {
                return $related;
            }
        }

        foreach ($products as $p) {
            if (($p['slug'] ?? '') === $slug) {
                continue;
            }
            if (($p['category_id'] ?? '') !== '' && ($p['category_id'] ?? '') === ($product['category_id'] ?? '')) {
                $related[] = $p;
            }
            if (count($related) >= 6) {
                break;
            }
        }
        if (count($related) < 4) {
            foreach ($products as $p) {
                if (($p['slug'] ?? '') === $slug || in_array($p['slug'] ?? '', array_column($related, 'slug'), true)) {
                    continue;
                }
                $related[] = $p;
                if (count($related) >= 6) {
                    break;
                }
            }
        }

        return $related;
    }

    private function normalizeProductCanonicalRel(string $canonicalUrl): ?string
    {
        $u = trim($canonicalUrl);
        if ($u === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $u)) {
            $parts = parse_url($u);
            $path = $parts['path'] ?? '';
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            if ($path === '') {
                return null;
            }
            $u = $path . $query;
        }
        if ($u === '' || ($u[0] ?? '') !== '/') {
            return null;
        }

        return $u;
    }

    /**
     * @param array<string, mixed> $product
     */
    private function renderProductHeadExtras(array $product): string
    {
        $html = '';
        $kw = trim((string) ($product['seo_keywords'] ?? ''));
        if ($kw !== '') {
            $html .= '<meta name="keywords" content="' . htmlspecialchars($kw, ENT_QUOTES, 'UTF-8') . "\">\n";
        }

        $raw = trim((string) ($product['tdk_tags'] ?? ''));
        if ($raw === '') {
            return $html;
        }

        $arr = json_decode($raw, true);
        if (!is_array($arr)) {
            return $html;
        }

        foreach ($arr as $row) {
            if (!is_array($row)) {
                continue;
            }
            $prop = trim((string) ($row['property'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $content = trim((string) ($row['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if ($prop !== '') {
                $html .= '<meta property="' . htmlspecialchars($prop, ENT_QUOTES, 'UTF-8')
                    . '" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . "\">\n";
            } elseif ($name !== '') {
                $html .= '<meta name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                    . '" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . "\">\n";
            }
        }

        return $html;
    }

    private function t(string $key): string
    {
        $translations = [
            'en' => [
                'home' => 'Home',
                'products' => 'Products',
                'products_desc' => 'Browse our industrial and B2B product catalog.',
                'about' => 'About Us',
                'cases' => 'Case Studies',
                'blog' => 'Blog',
                'contact' => 'Contact Us',
            ],
            'cn' => [
                'home' => '首页',
                'products' => '产品中心',
                'products_desc' => '浏览我们的工业与B2B产品目录。',
                'about' => '关于我们',
                'cases' => '客户案例',
                'blog' => '博客',
                'contact' => '联系我们',
            ],
            'es' => [
                'home' => 'Inicio',
                'products' => 'Productos',
                'products_desc' => 'Explore nuestro catálogo de productos B2B e industriales.',
                'about' => 'Sobre Nosotros',
                'cases' => 'Casos',
                'blog' => 'Blog',
                'contact' => 'Contáctenos',
            ],
        ];
        return $translations[$this->lang][$key] ?? $key;
    }
}
