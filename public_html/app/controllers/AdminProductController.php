<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\JsonStore;
use App\Core\NewsletterNotifier;
use App\Core\ProductFileStore;
use App\Core\ProductPublishState;
use App\Core\RichTextSanitizer;
use App\Core\SEO;
use App\Core\StaticCache;
use App\Core\UploadService;
use App\Core\View;
use App\Repositories\ProductRepository;
use App\Repositories\CategoryRepository;

class AdminProductController extends BaseController
{
    private array $supportedLangs;
    private const DEFAULT_LANG = 'en';
    private const GLOBAL_FIELDS = ['slug', 'category_id', 'specs', 'datasheet'];
    private const LOCALIZED_FIELDS = ['name', 'desc', 'short_desc', 'content', 'seo_title', 'seo_desc'];
    private const MAX_DATASHEET_SIZE = 10485760; // 10MB

    private ?ProductRepository $productRepo = null;
    private ?CategoryRepository $categoryRepo = null;

    public function __construct(string $lang, bool $isAdmin = false)
    {
        parent::__construct($lang, $isAdmin);
        $this->supportedLangs = Config::get('supported_langs', ['en', 'cn', 'es']);
        $this->initRepositories();
    }

    private function initRepositories(): void
    {
        $db = \App\Core\Database::getInstance();
        $this->productRepo = new ProductRepository($db);
        $this->productRepo->setSupportedLangs($this->supportedLangs);
        $this->productRepo->setDefaultLang(self::DEFAULT_LANG);

        $this->categoryRepo = new CategoryRepository($db);
        $this->categoryRepo->setSupportedLangs($this->supportedLangs);
        $this->categoryRepo->setDefaultLang(self::DEFAULT_LANG);
    }

    public function index(): void
    {
        Auth::requireCan('products');

        $editLang = $this->getQuery('lang', self::DEFAULT_LANG);
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = self::DEFAULT_LANG;
        }

        $search = trim($this->getQuery('search', ''));
        $page = max(1, (int) $this->getQuery('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        // 与 handleCreate / edit / delete 一致：产品数据存于 app/data/{lang}/products.json，而非仅 MySQL
        $productStore = JsonStore::langData($editLang, 'products');
        $allProducts = $productStore->read();
        if (!is_array($allProducts)) {
            $allProducts = [];
        }

        if ($search !== '') {
            $allProducts = $this->filterJsonProductsBySearch($allProducts, $search);
        }

        usort($allProducts, function ($a, $b) {
            $oa = (int) ($a['sort_order'] ?? 0);
            $ob = (int) ($b['sort_order'] ?? 0);
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        });

        $totalCount = count($allProducts);
        $products = array_slice($allProducts, $offset, $perPage);
        foreach ($products as &$p) {
            if (!array_key_exists('category_id', $p)) {
                $p['category_id'] = '';
            }
            if (!array_key_exists('is_active', $p)) {
                $p['is_active'] = 1;
            }
            if (!array_key_exists('sort_order', $p)) {
                $p['sort_order'] = 0;
            }
            if (!array_key_exists('id', $p)) {
                $p['id'] = 0;
            }
            if (!array_key_exists('slug', $p)) {
                $p['slug'] = '';
            }
            if (!array_key_exists('name', $p)) {
                $p['name'] = '';
            }
        }
        unset($p);

        $categories = JsonStore::langData($editLang, 'categories')->read();
        if (!is_array($categories)) {
            $categories = [];
        }
        $categoryMap = [];
        foreach ($categories as $cat) {
            $slug = (string) ($cat['slug'] ?? '');
            if ($slug !== '') {
                $categoryMap[$slug] = $cat['name'] ?? $slug;
            }
            if (isset($cat['id'])) {
                $categoryMap[(string) $cat['id']] = $cat['name'] ?? '';
            }
        }

        $unreadInquiries = $this->getUnreadInquiryCount();
        $adminUser = Auth::user();
        $adminEmail = $adminUser['email'] ?? '';

        $this->view->render('products/index', [
            'products' => $products,
            'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs,
            'search' => $search,
            'page' => $page,
            'perPage' => $perPage,
            'totalCount' => $totalCount,
            'totalPages' => ceil($totalCount / $perPage),
            'categoryMap' => $categoryMap,
            'unreadInquiries' => $unreadInquiries,
            'adminUser' => $adminUser['username'] ?? 'Admin',
            'adminEmail' => $adminEmail,
            'activeMenu' => 'products',
            'pageTitle' => '产品列表',
            'breadcrumbs' => [
                ['label' => '产品管理', 'url' => '/admin/products'],
                ['label' => '产品列表'],
            ],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    private function filterJsonProductsBySearch(array $products, string $needle): array
    {
        $needle = trim($needle);
        if ($needle === '') {
            return $products;
        }

        return array_values(array_filter($products, function ($p) use ($needle) {
            $name = (string) ($p['name'] ?? '');
            $slug = (string) ($p['slug'] ?? '');
            $model = (string) ($p['product_model'] ?? '');
            $series = (string) ($p['product_series'] ?? '');
            if (function_exists('mb_stripos')) {
                return mb_stripos($name, $needle, 0, 'UTF-8') !== false
                    || mb_stripos($slug, $needle, 0, 'UTF-8') !== false
                    || mb_stripos($model, $needle, 0, 'UTF-8') !== false
                    || mb_stripos($series, $needle, 0, 'UTF-8') !== false;
            }

            return stripos($name, $needle) !== false
                || stripos($slug, $needle) !== false
                || stripos($model, $needle) !== false
                || stripos($series, $needle) !== false;
        }));
    }

    private function getUnreadInquiryCount(): int
    {
        try {
            if (\App\Core\InquiryRepository::isAvailable()) {
                return \App\Core\InquiryRepository::countUnread();
            }
            $inq = JsonStore::globalData('inquiries')->read();
            if (!is_array($inq)) {
                return 0;
            }

            return count(array_filter($inq, function ($i) {
                return empty($i['read']);
            }));
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function create(): void
    {
        Auth::requireCan('products');

        if ($this->isPost()) {
            $this->handleCreate();
            return;
        }

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        $categories = JsonStore::langData($editLang, 'categories')->read();
        $isDefaultLang = ($editLang === self::DEFAULT_LANG);
        $enProduct = null;

        $this->view->render('product_form', [
            'product' => ['images' => [], 'specs' => []],
            'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs,
            'categories' => $categories,
            'csrfToken' => Auth::generateCsrfToken(),
            'isEdit' => false,
            'isDefaultLang' => $isDefaultLang,
            'enProduct' => $enProduct,
            'adminUser' => Auth::user(),
        ]);
    }

    private function handleCreate(): void
    {
        $csrf = $this->getPost('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $this->jsonError('Invalid security token.', 403);
        }

        $editLang = $this->getPost('edit_lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        $name = trim($this->getPost('name', ''));
        $error = $this->validateRequired(['name' => $name], ['name']);
        if ($error) {
            $_SESSION['form_error'] = $error;
            $this->redirect('/admin/products/create?lang=' . $editLang);
        }

        $slug = trim($this->getPost('slug', ''));
        if (empty($slug)) {
            $slug = View::slugify($name);
        } else {
            $slug = View::slugify($slug);
        }
        $slug = $this->ensureUniqueSlug($slug, $editLang);

        $isDefaultLang = ($editLang === self::DEFAULT_LANG);

        if ($isDefaultLang) {
            try {
                $datasheetPath = $this->processDatasheetUpload('');
            } catch (\RuntimeException $e) {
                $_SESSION['form_error'] = $e->getMessage();
                $this->redirect('/admin/products/create?lang=' . $editLang);
            }
            $newProduct = $this->buildFullProductData($slug, $datasheetPath);
        } else {
            $newProduct = $this->buildLocalizedProductData($slug);
        }
        $newProduct['name'] = $name;
        $newProduct['slug'] = $slug;
        $newProduct['id'] = $this->allocateProductId($editLang);

        $store = JsonStore::langData($editLang, 'products');
        $store->update(function ($products) use ($newProduct) {
            $products[] = $newProduct;
            return $products;
        });

        $this->syncSlugAcrossLanguages($slug);

        if ($isDefaultLang) {
            $this->syncGlobalFieldsAcrossLanguages($slug, $newProduct);
        }

        // 新增产品且为已发布（前台可见）时自动写入 newsletter_jobs
        $tagsForNotify = isset($newProduct['tags']) && is_array($newProduct['tags']) ? $newProduct['tags'] : [];
        if (ProductPublishState::isPublicVisible($newProduct)) {
            try {
                NewsletterNotifier::productPublished($editLang, $slug, $name, $tagsForNotify);
            } catch (\Throwable $e) {
                error_log('[Newsletter] new product publish notify: ' . $e->getMessage());
            }
        }

        // Module 11: sync shards after all cross-language writes are complete
        ProductFileStore::syncAllLangsFromJson($this->supportedLangs);

        $this->regenerateSitemap();

        // 新增产品后清除各语言产品列表页缓存
        foreach ($this->supportedLangs as $cacheLang) {
            StaticCache::invalidate('/' . $cacheLang . '/products');
        }

        $this->redirect('/admin/products?lang=' . $editLang);
    }

    public function edit(string $slug = ''): void
    {
        Auth::requireCan('products');

        if (empty($slug)) {
            $this->redirect('/admin/products');
        }

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        if ($this->isPost()) {
            $this->handleEdit($slug, $editLang);
            return;
        }

        $store = JsonStore::langData($editLang, 'products');
        $products = $store->read();
        $product = null;

        foreach ($products as $p) {
            if (isset($p['slug']) && $p['slug'] === $slug) {
                $product = $p;
                break;
            }
        }

        if ($product === null) {
            $_SESSION['form_error'] = 'Product not found.';
            $this->redirect('/admin/products?lang=' . $editLang);
        }

        if (!isset($product['images'])) {
            $product['images'] = !empty($product['image']) ? [['url' => $product['image'], 'alt_text' => $product['name'] ?? '', 'is_main' => true]] : [];
        }
        if (!isset($product['specs'])) {
            $product['specs'] = [];
        }

        $isDefaultLang = ($editLang === self::DEFAULT_LANG);
        $enProduct = null;

        if (!$isDefaultLang) {
            $enStore = JsonStore::langData(self::DEFAULT_LANG, 'products');
            $enProducts = $enStore->read();
            foreach ($enProducts as $p) {
                if (isset($p['slug']) && $p['slug'] === $slug) {
                    $enProduct = $p;
                    break;
                }
            }

            if ($enProduct !== null) {
                $enImages = $enProduct['images'] ?? [];
                $localAlts = [];
                foreach ($product['images'] ?? [] as $idx => $img) {
                    $localAlts[$idx] = $img['alt_text'] ?? '';
                }
                $mergedImages = [];
                foreach ($enImages as $idx => $enImg) {
                    $mergedImages[] = [
                        'url' => $enImg['url'] ?? '',
                        'alt_text' => $localAlts[$idx] ?? '',
                        'is_main' => !empty($enImg['is_main']),
                    ];
                }
                $product['images'] = $mergedImages;
                $product['category_id'] = $enProduct['category_id'] ?? $product['category_id'] ?? '';
                $product['specs'] = $enProduct['specs'] ?? $product['specs'] ?? [];
                $product['datasheet'] = $enProduct['datasheet'] ?? $product['datasheet'] ?? '';
                $product['datasheet_files'] = $enProduct['datasheet_files'] ?? $product['datasheet_files'] ?? [];
                $product['download_center'] = $enProduct['download_center'] ?? $product['download_center'] ?? [];
                $product['customizable_options'] = $enProduct['customizable_options'] ?? $product['customizable_options'] ?? [];
                $product['custom_options'] = $enProduct['custom_options'] ?? $product['custom_options'] ?? [];
                $product['product_model'] = $enProduct['product_model'] ?? $product['product_model'] ?? '';
                $product['product_series'] = $enProduct['product_series'] ?? $product['product_series'] ?? '';
                $product['moq'] = $enProduct['moq'] ?? $product['moq'] ?? '';
                $product['lead_time'] = $enProduct['lead_time'] ?? $product['lead_time'] ?? '';
                $product['image'] = $enProduct['image'] ?? $product['image'] ?? '';
            }
        }

        $categories = JsonStore::langData($editLang, 'categories')->read();

        $this->view->render('product_form', [
            'product' => $product,
            'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs,
            'categories' => $categories,
            'csrfToken' => Auth::generateCsrfToken(),
            'isEdit' => true,
            'isDefaultLang' => $isDefaultLang,
            'enProduct' => $enProduct,
            'adminUser' => Auth::user(),
        ]);
    }

    private function handleEdit(string $originalSlug, string $editLang): void
    {
        $csrf = $this->getPost('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $this->jsonError('Invalid security token.', 403);
        }

        $name = trim($this->getPost('name', ''));
        $error = $this->validateRequired(['name' => $name], ['name']);
        if ($error) {
            $_SESSION['form_error'] = $error;
            $this->redirect('/admin/products/edit/' . $originalSlug . '?lang=' . $editLang);
        }

        $isDefaultLang = ($editLang === self::DEFAULT_LANG);

        if ($isDefaultLang) {
            $prevStore = JsonStore::langData($editLang, 'products');
            $prevProducts = $prevStore->read();
            $prevProduct = null;
            $prevId = 0;
            foreach ($prevProducts as $p) {
                if (isset($p['slug']) && $p['slug'] === $originalSlug) {
                    $prevProduct = $p;
                    $prevId = (int) ($p['id'] ?? 0);
                    break;
                }
            }

            $newSlug = trim($this->getPost('slug', ''));
            if (empty($newSlug)) {
                $newSlug = View::slugify($name);
            } else {
                $newSlug = View::slugify($newSlug);
            }
            if ($newSlug !== $originalSlug) {
                $newSlug = $this->ensureUniqueSlug($newSlug, $editLang, $originalSlug);
            }

            try {
                $datasheetPath = $this->processDatasheetUpload((string) ($prevProduct['datasheet'] ?? ''));
            } catch (\RuntimeException $e) {
                $_SESSION['form_error'] = $e->getMessage();
                $this->redirect('/admin/products/edit/' . $originalSlug . '?lang=' . $editLang);
            }

            $updatedData = $this->buildFullProductData($newSlug, $datasheetPath);
            $updatedData['name'] = $name;
            $updatedData['slug'] = $newSlug;
            $updatedData['id'] = $prevId > 0 ? $prevId : $this->allocateProductId($editLang);

            $store = JsonStore::langData($editLang, 'products');
            $store->update(function ($products) use ($originalSlug, $updatedData) {
                foreach ($products as &$p) {
                    if (isset($p['slug']) && $p['slug'] === $originalSlug) {
                        $p = $updatedData;
                        break;
                    }
                }
                unset($p);
                return $products;
            });

            $this->syncGlobalFieldsAcrossLanguages($newSlug, $updatedData);

            // Register 301 redirects for all language variants when slug changes
            if ($newSlug !== $originalSlug) {
                \App\Core\SlugRegistry::registerRedirect($originalSlug, $newSlug, 'product', 301);
            }
        } else {
            $loc = $this->extractLocalizedProductUpdatesFromPost();

            $imagesJson = $this->getPost('images_json', '[]');
            $postedImages = json_decode($imagesJson, true);
            $altTexts = [];
            if (json_last_error() === JSON_ERROR_NONE && is_array($postedImages)) {
                foreach ($postedImages as $img) {
                    $altTexts[] = trim($img['alt_text'] ?? '');
                }
            }

            $store = JsonStore::langData($editLang, 'products');
            $store->update(function ($products) use ($originalSlug, $name, $loc, $altTexts) {
                foreach ($products as &$p) {
                    if (isset($p['slug']) && $p['slug'] === $originalSlug) {
                        $p['name'] = $name;
                        $p['desc'] = $loc['desc'];
                        $p['short_desc'] = $loc['short_desc'];
                        $p['short_description'] = $loc['short_description'];
                        $p['content'] = $loc['content'];
                        $p['seo_title'] = $loc['seo_title'];
                        $p['seo_desc'] = $loc['seo_desc'];
                        $p['seo_description'] = $loc['seo_description'];
                        $p['seo_keywords'] = $loc['seo_keywords'];
                        $p['canonical_url'] = $loc['canonical_url'];
                        $p['tdk_tags'] = $loc['tdk_tags'];
                        $p['product_structure'] = $loc['product_structure'];
                        $p['technical_specs'] = $loc['technical_specs'];
                        $p['electrical_characteristics'] = $loc['electrical_characteristics'];
                        $p['mechanical_characteristics'] = $loc['mechanical_characteristics'];
                        $p['environmental_characteristics'] = $loc['environmental_characteristics'];
                        $p['applications'] = $loc['applications'];
                        $p['standards'] = $loc['standards'];
                        $p['compliance_standards'] = $loc['compliance_standards'];
                        $p['faqs'] = $loc['faqs'];
                        $p['faq_json'] = $loc['faq_json'];

                        if (isset($p['images']) && is_array($p['images'])) {
                            foreach ($p['images'] as $idx => &$existingImg) {
                                if (isset($altTexts[$idx])) {
                                    $existingImg['alt_text'] = $altTexts[$idx];
                                }
                            }
                            unset($existingImg);
                        }
                        break;
                    }
                }
                unset($p);
                return $products;
            });
        }

        if ($isDefaultLang && is_array($prevProduct ?? null) && isset($updatedData, $newSlug)) {
            $prevSt = ProductPublishState::normalize($prevProduct['status'] ?? ProductPublishState::STATUS_PUBLISHED);
            $newSt = ProductPublishState::normalize($updatedData['status'] ?? ProductPublishState::STATUS_PUBLISHED);
            if ($prevSt !== ProductPublishState::STATUS_PUBLISHED && $newSt === ProductPublishState::STATUS_PUBLISHED) {
                $tagsForNotify = isset($updatedData['tags']) && is_array($updatedData['tags']) ? $updatedData['tags'] : [];
                try {
                    NewsletterNotifier::productPublished($editLang, $newSlug, $name, $tagsForNotify);
                } catch (\Throwable $e) {
                    error_log('[Newsletter] product publish notify: ' . $e->getMessage());
                }
            }
        }

        // Module 11: sync shards for all languages after all writes complete
        ProductFileStore::syncAllLangsFromJson($this->supportedLangs);

        $this->regenerateSitemap();

        // 清除该产品的静态缓存（所有语言），确保前台立即显示最新内容
        $slugToClear = isset($newSlug) ? $newSlug : $originalSlug;
        foreach ($this->supportedLangs as $cacheLang) {
            StaticCache::invalidate('/' . $cacheLang . '/product/' . $slugToClear);
            StaticCache::invalidate('/' . $cacheLang . '/products');
        }
        // 如果 slug 改变，同时清除旧 slug 的缓存
        if (isset($newSlug) && $newSlug !== $originalSlug) {
            foreach ($this->supportedLangs as $cacheLang) {
                StaticCache::invalidate('/' . $cacheLang . '/product/' . $originalSlug);
            }
        }

        $this->redirect('/admin/products?lang=' . $editLang);
    }

    private function buildFullProductData(string $slug, ?string $datasheetPath = null): array
    {
        $imagesJson = $this->getPost('images_json', '[]');
        $images = json_decode($imagesJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $images = [];
        }
        $sanitizedImages = [];
        $hasMain = false;
        foreach ($images as $img) {
            $isMain = !empty($img['is_main']);
            if ($isMain) $hasMain = true;
            $sanitizedImages[] = [
                'url' => trim($img['url'] ?? ''),
                'alt_text' => trim($img['alt_text'] ?? ''),
                'is_main' => $isMain,
            ];
        }
        if (!$hasMain && !empty($sanitizedImages)) {
            $sanitizedImages[0]['is_main'] = true;
        }

        $specsJson = $this->getPost('specs_json', '[]');
        $specs = json_decode($specsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $specs = [];
        }
        $sanitizedSpecs = [];
        foreach ($specs as $spec) {
            $label = trim($spec['label'] ?? '');
            $value = trim($spec['value'] ?? '');
            if ($label !== '' && $value !== '') {
                $sanitizedSpecs[] = ['label' => $label, 'value' => $value];
            }
        }

        $mainImage = '';
        foreach ($sanitizedImages as $img) {
            if (!empty($img['is_main']) && !empty($img['url'])) {
                $mainImage = $img['url'];
                break;
            }
        }
        if ($mainImage === '' && !empty($sanitizedImages[0]['url'])) {
            $mainImage = $sanitizedImages[0]['url'];
        }

        $tagsRaw = trim($this->getPost('tags_csv', ''));
        $tags = array_values(array_filter(array_map('trim', preg_split('/[,，]/u', $tagsRaw) ?: [])));

        $relRaw = trim($this->getPost('related_products', $this->getPost('related_product_slugs', '')));
        $relatedSlugs = array_values(array_filter(array_map(function ($s) {
            return View::slugify(trim($s));
        }, preg_split('/[\s,，]+/u', $relRaw) ?: [])));

        $cmpRaw = trim($this->getPost('compare_slugs', ''));
        $compareSlugs = array_values(array_filter(array_map(function ($s) {
            return View::slugify(trim($s));
        }, preg_split('/[\s,，]+/u', $cmpRaw) ?: [])));

        $faqs = $this->sanitizeFaqsFromPost();
        $shortDescUnified = trim($this->getPost('short_description', ''));

        return [
            'name' => trim($this->getPost('name', '')),
            'slug' => $slug,
            'category_id' => trim($this->getPost('category_id', '')),
            'product_model' => trim($this->getPost('product_model', '')),
            'product_series' => trim($this->getPost('product_series', '')),
            'desc' => RichTextSanitizer::sanitize(trim($this->getPost('desc', ''))),
            'short_desc' => $shortDescUnified,
            'short_description' => $shortDescUnified,
            'image' => $mainImage,
            'images' => $sanitizedImages,
            'specs' => $sanitizedSpecs,
            'faqs' => $faqs,
            'faq_json' => $faqs === [] ? '' : json_encode($faqs, JSON_UNESCAPED_UNICODE),
            'datasheet' => $datasheetPath ?? trim($this->getPost('datasheet', '')),
            'datasheet_files' => $this->sanitizeDatasheetFilesFromPost(),
            'download_center' => $this->sanitizeDownloadCenterFromPost(),
            'customizable_options' => $this->sanitizeCustomizableOptionsFromPost(),
            'custom_options' => $this->sanitizeCustomOptionsFromPost(),
            'moq' => trim($this->getPost('moq', '')),
            'lead_time' => trim($this->getPost('lead_time', '')),
            'content' => RichTextSanitizer::sanitize(trim($this->getPost('content', ''))),
            'product_structure' => RichTextSanitizer::sanitize(trim($this->getPost('product_structure', ''))),
            'technical_specs' => RichTextSanitizer::sanitize(trim($this->getPost('technical_specs', ''))),
            'electrical_characteristics' => RichTextSanitizer::sanitize(trim($this->getPost('electrical_characteristics', ''))),
            'mechanical_characteristics' => RichTextSanitizer::sanitize(trim($this->getPost('mechanical_characteristics', ''))),
            'environmental_characteristics' => RichTextSanitizer::sanitize(trim($this->getPost('environmental_characteristics', ''))),
            'applications' => RichTextSanitizer::sanitize(trim($this->getPost('applications', ''))),
            'standards' => RichTextSanitizer::sanitize(trim($this->getPost('standards', ''))),
            'compliance_standards' => RichTextSanitizer::sanitize(trim($this->getPost('compliance_standards', ''))),
            'seo_title' => trim($this->getPost('seo_title', '')),
            'seo_desc' => trim($this->getPost('seo_desc', '')),
            'seo_description' => trim($this->getPost('seo_desc', '')),
            'seo_keywords' => trim($this->getPost('seo_keywords', '')),
            'canonical_url' => trim($this->getPost('canonical_url', '')),
            'tdk_tags' => $this->sanitizeTdkTagsFromPost(),
            'tags' => array_slice($tags, 0, 40),
            'related_product_slugs' => array_slice($relatedSlugs, 0, 20),
            'related_products' => array_slice($relatedSlugs, 0, 20),
            'compare_slugs' => array_slice($compareSlugs, 0, 4),
            'application_industry' => trim($this->getPost('application_industry', '')),
            'is_featured' => $this->getPost('is_featured', '0') === '1',
            'is_hot' => $this->getPost('is_hot', '0') === '1',
            'status' => ProductPublishState::normalize($this->getPost('product_status', ProductPublishState::STATUS_PUBLISHED)),
        ];
    }

    private function buildLocalizedProductData(string $slug): array
    {
        $loc = $this->extractLocalizedProductUpdatesFromPost();

        return [
            'name' => trim($this->getPost('name', '')),
            'slug' => $slug,
            'category_id' => '',
            'desc' => $loc['desc'],
            'short_desc' => $loc['short_desc'],
            'short_description' => $loc['short_description'],
            'image' => '',
            'images' => [],
            'specs' => [],
            'faqs' => $loc['faqs'],
            'faq_json' => $loc['faq_json'],
            'datasheet' => '',
            'datasheet_files' => [],
            'content' => $loc['content'],
            'seo_title' => $loc['seo_title'],
            'seo_desc' => $loc['seo_desc'],
            'seo_description' => $loc['seo_description'],
            'seo_keywords' => $loc['seo_keywords'],
            'canonical_url' => $loc['canonical_url'],
            'tdk_tags' => $loc['tdk_tags'],
            'product_structure' => $loc['product_structure'],
            'technical_specs' => $loc['technical_specs'],
            'electrical_characteristics' => $loc['electrical_characteristics'],
            'mechanical_characteristics' => $loc['mechanical_characteristics'],
            'environmental_characteristics' => $loc['environmental_characteristics'],
            'applications' => $loc['applications'],
            'standards' => $loc['standards'],
            'compliance_standards' => $loc['compliance_standards'],
            'status' => ProductPublishState::normalize($this->getPost('product_status', ProductPublishState::STATUS_PUBLISHED)),
            'tags' => [],
        ];
    }

    private function sanitizeFaqsFromPost(): array
    {
        $raw = $this->getPost('faqs_json', '[]');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            $q = trim((string) ($row['question'] ?? $row['q'] ?? ''));
            $a = trim((string) ($row['answer'] ?? $row['a'] ?? ''));
            if ($q !== '' && $a !== '') {
                $out[] = ['question' => $q, 'answer' => $a];
            }
        }
        return $out;
    }

    private function sanitizeDatasheetFilesFromPost(): array
    {
        $raw = trim($this->getPost('datasheet_files_json', '[]'));
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($url !== '') {
                $out[] = ['label' => $label !== '' ? $label : 'Download', 'url' => $url];
            }
        }
        return array_slice($out, 0, 10);
    }

    /**
     * @return array<int, array{title: string, url: string, label: string}>
     */
    private function sanitizeDownloadCenterFromPost(): array
    {
        $raw = trim($this->getPost('download_center_json', '[]'));
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($url === '') {
                continue;
            }
            $out[] = [
                'title' => $title !== '' ? $title : ($label !== '' ? $label : 'Download'),
                'url' => $url,
                'label' => $label !== '' ? $label : ($title !== '' ? $title : 'Download'),
            ];
        }

        return array_slice($out, 0, 30);
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    private function sanitizeCustomizableOptionsFromPost(): array
    {
        $raw = trim($this->getPost('customizable_options_json', '[]'));
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            $name = trim((string) ($row['name'] ?? $row['label'] ?? $row['option'] ?? ''));
            $value = trim((string) ($row['value'] ?? $row['description'] ?? ''));
            if ($name === '' && $value === '') {
                continue;
            }
            $out[] = ['name' => $name !== '' ? $name : '—', 'value' => $value];
        }

        return array_slice($out, 0, 40);
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    private function sanitizeCustomOptionsFromPost(): array
    {
        $raw = trim($this->getPost('custom_options_json', '[]'));
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            $name = trim((string) ($row['name'] ?? $row['label'] ?? $row['option'] ?? ''));
            $value = trim((string) ($row['value'] ?? $row['description'] ?? ''));
            if ($name === '' && $value === '') {
                continue;
            }
            $out[] = ['name' => $name !== '' ? $name : '—', 'value' => $value];
        }

        return array_slice($out, 0, 40);
    }

    private function sanitizeTdkTagsFromPost(): string
    {
        $raw = trim($this->getPost('tdk_tags', ''));
        if ($raw === '') {
            return '';
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return '';
        }
        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $property = trim((string) ($row['property'] ?? ''));
            $content = trim((string) ($row['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if ($property !== '') {
                $out[] = ['property' => $property, 'content' => $content];
            } elseif ($name !== '') {
                $out[] = ['name' => $name, 'content' => $content];
            }
        }

        return $out === [] ? '' : json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractLocalizedProductUpdatesFromPost(): array
    {
        $faqs = $this->sanitizeFaqsFromPost();
        $shortDescUnified = trim($this->getPost('short_description', ''));

        return [
            'desc' => RichTextSanitizer::sanitize(trim($this->getPost('desc', ''))),
            'short_desc' => $shortDescUnified,
            'short_description' => $shortDescUnified,
            'content' => RichTextSanitizer::sanitize(trim($this->getPost('content', ''))),
            'seo_title' => trim($this->getPost('seo_title', '')),
            'seo_desc' => trim($this->getPost('seo_desc', '')),
            'seo_description' => trim($this->getPost('seo_desc', '')),
            'seo_keywords' => trim($this->getPost('seo_keywords', '')),
            'canonical_url' => trim($this->getPost('canonical_url', '')),
            'tdk_tags' => $this->sanitizeTdkTagsFromPost(),
            'product_structure' => RichTextSanitizer::sanitize(trim($this->getPost('product_structure', ''))),
            'technical_specs' => RichTextSanitizer::sanitize(trim($this->getPost('technical_specs', ''))),
            'electrical_characteristics' => RichTextSanitizer::sanitize(trim($this->getPost('electrical_characteristics', ''))),
            'mechanical_characteristics' => RichTextSanitizer::sanitize(trim($this->getPost('mechanical_characteristics', ''))),
            'environmental_characteristics' => RichTextSanitizer::sanitize(trim($this->getPost('environmental_characteristics', ''))),
            'applications' => RichTextSanitizer::sanitize(trim($this->getPost('applications', ''))),
            'standards' => RichTextSanitizer::sanitize(trim($this->getPost('standards', ''))),
            'compliance_standards' => RichTextSanitizer::sanitize(trim($this->getPost('compliance_standards', ''))),
            'faqs' => $faqs,
            'faq_json' => $faqs === [] ? '' : json_encode($faqs, JSON_UNESCAPED_UNICODE),
        ];
    }

    public function delete(string $slug = ''): void
    {
        Auth::requireCan('products');

        if (empty($slug)) {
            $this->redirect('/admin/products');
        }

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        if ($this->isPost()) {
            $csrf = $this->getPost('_csrf', '');
            if (!Auth::consumeCsrfToken($csrf)) {
                $this->jsonError('Invalid security token.', 403);
            }

            $store = JsonStore::langData($editLang, 'products');
            $store->update(function ($products) use ($slug) {
                return array_values(array_filter($products, function ($p) use ($slug) {
                    return !isset($p['slug']) || $p['slug'] !== $slug;
                }));
            });

            // Module 11: remove shard file and refresh index for this language
            ProductFileStore::removeShard($editLang, $slug);
            ProductFileStore::syncFromJson($editLang);

            $this->regenerateSitemap();

            // 删除产品后清除该产品及列表页静态缓存（所有语言）
            foreach ($this->supportedLangs as $cacheLang) {
                StaticCache::invalidate('/' . $cacheLang . '/product/' . $slug);
                StaticCache::invalidate('/' . $cacheLang . '/products');
            }
        }

        $this->redirect('/admin/products?lang=' . $editLang);
    }

    private function allocateProductId(string $lang): int
    {
        $products = JsonStore::langData($lang, 'products')->read();
        $max = 0;
        foreach ($products as $p) {
            if (!empty($p['id'])) {
                $max = max($max, (int) $p['id']);
            }
        }
        return $max + 1;
    }

    private function ensureUniqueSlug(string $slug, string $lang, string $excludeSlug = ''): string
    {
        // Cross-type global uniqueness check via SlugRegistry (covers products, blog, pages, categories, cases)
        return \App\Core\SlugRegistry::makeUniqueSlug($slug, 'product', $excludeSlug);
    }

    private function syncSlugAcrossLanguages(string $slug): void
    {
        foreach ($this->supportedLangs as $lang) {
            $store = JsonStore::langData($lang, 'products');
            $products = $store->read();

            $slugExists = false;
            foreach ($products as $p) {
                if (isset($p['slug']) && $p['slug'] === $slug) {
                    $slugExists = true;
                    break;
                }
            }

            if (!$slugExists) {
                $store->update(function ($products) use ($slug) {
                    $products[] = [
                        'name' => $slug,
                        'slug' => $slug,
                        'category_id' => '',
                        'product_model' => '',
                        'product_series' => '',
                        'desc' => '',
                        'short_desc' => '',
                        'short_description' => '',
                        'image' => '',
                        'images' => [],
                        'specs' => [],
                        'faqs' => [],
                        'faq_json' => '',
                        'datasheet' => '',
                        'datasheet_files' => [],
                        'download_center' => [],
                        'customizable_options' => [],
                        'custom_options' => [],
                        'moq' => '',
                        'lead_time' => '',
                        'content' => '',
                        'product_structure' => '',
                        'technical_specs' => '',
                        'electrical_characteristics' => '',
                        'mechanical_characteristics' => '',
                        'environmental_characteristics' => '',
                        'applications' => '',
                        'standards' => '',
                        'compliance_standards' => '',
                        'seo_title' => '',
                        'seo_desc' => '',
                        'seo_description' => '',
                        'seo_keywords' => '',
                        'canonical_url' => '',
                        'tdk_tags' => '',
                        'tags' => [],
                        'related_product_slugs' => [],
                        'related_products' => [],
                        'compare_slugs' => [],
                        'application_industry' => '',
                        'is_featured' => false,
                        'is_hot' => false,
                    ];
                    return $products;
                });
            }
        }
    }

    private function syncGlobalFieldsAcrossLanguages(string $slug, array $enProduct): void
    {
        $globalData = [
            'slug' => $enProduct['slug'] ?? $slug,
            'category_id' => $enProduct['category_id'] ?? '',
            'image' => $enProduct['image'] ?? '',
            'specs' => $enProduct['specs'] ?? [],
            'datasheet' => $enProduct['datasheet'] ?? '',
            'datasheet_files' => $enProduct['datasheet_files'] ?? [],
            'download_center' => $enProduct['download_center'] ?? [],
            'customizable_options' => $enProduct['customizable_options'] ?? [],
            'custom_options' => $enProduct['custom_options'] ?? [],
            'product_model' => $enProduct['product_model'] ?? '',
            'product_series' => $enProduct['product_series'] ?? '',
            'moq' => $enProduct['moq'] ?? '',
            'lead_time' => $enProduct['lead_time'] ?? '',
            'tags' => $enProduct['tags'] ?? [],
            'related_product_slugs' => $enProduct['related_product_slugs'] ?? $enProduct['related_products'] ?? [],
            'related_products' => $enProduct['related_products'] ?? $enProduct['related_product_slugs'] ?? [],
            'compare_slugs' => $enProduct['compare_slugs'] ?? [],
            'application_industry' => $enProduct['application_industry'] ?? '',
            'is_featured' => !empty($enProduct['is_featured']),
            'is_hot' => !empty($enProduct['is_hot']),
            'status' => ProductPublishState::normalize($enProduct['status'] ?? ProductPublishState::STATUS_PUBLISHED),
        ];

        $enImagesGlobal = [];
        foreach ($enProduct['images'] ?? [] as $img) {
            $enImagesGlobal[] = [
                'url' => $img['url'] ?? '',
                'is_main' => !empty($img['is_main']),
            ];
        }

        foreach ($this->supportedLangs as $lang) {
            if ($lang === self::DEFAULT_LANG) continue;

            $store = JsonStore::langData($lang, 'products');
            $store->update(function ($products) use ($slug, $globalData, $enImagesGlobal) {
                foreach ($products as &$p) {
                    if (isset($p['slug']) && $p['slug'] === $slug) {
                        $p['slug'] = $globalData['slug'];
                        $p['category_id'] = $globalData['category_id'];
                        $p['image'] = $globalData['image'];
                        $p['specs'] = $globalData['specs'];
                        $p['datasheet'] = $globalData['datasheet'];
                        $p['datasheet_files'] = $globalData['datasheet_files'];
                        $p['download_center'] = $globalData['download_center'];
                        $p['customizable_options'] = $globalData['customizable_options'];
                        $p['custom_options'] = $globalData['custom_options'];
                        $p['product_model'] = $globalData['product_model'];
                        $p['product_series'] = $globalData['product_series'];
                        $p['moq'] = $globalData['moq'];
                        $p['lead_time'] = $globalData['lead_time'];
                        $p['tags'] = $globalData['tags'];
                        $p['related_product_slugs'] = $globalData['related_product_slugs'];
                        $p['related_products'] = $globalData['related_products'];
                        $p['compare_slugs'] = $globalData['compare_slugs'];
                        $p['application_industry'] = $globalData['application_industry'];
                        $p['is_featured'] = $globalData['is_featured'];
                        $p['is_hot'] = $globalData['is_hot'];
                        $p['status'] = $globalData['status'];

                        $existingAlts = [];
                        if (isset($p['images']) && is_array($p['images'])) {
                            foreach ($p['images'] as $oldImg) {
                                $existingAlts[] = $oldImg['alt_text'] ?? '';
                            }
                        }

                        $syncedImages = [];
                        foreach ($enImagesGlobal as $idx => $globalImg) {
                            $syncedImages[] = [
                                'url' => $globalImg['url'],
                                'alt_text' => $existingAlts[$idx] ?? '',
                                'is_main' => $globalImg['is_main'],
                            ];
                        }
                        $p['images'] = $syncedImages;
                        break;
                    }
                }
                unset($p);
                return $products;
            });
        }
    }

    private function regenerateSitemap(): void
    {
        $sitemapPath = ROOT_PATH . '/sitemap.xml';
        $xml = SEO::generateSitemap();
        file_put_contents($sitemapPath, $xml, LOCK_EX);

        $imgPath = ROOT_PATH . '/image-sitemap.xml';
        $imgXml = SEO::generateImageSitemap();
        file_put_contents($imgPath, $imgXml, LOCK_EX);
    }

    private function processDatasheetUpload(string $currentPath = ''): string
    {
        if (!isset($_FILES['datasheet_file'])) {
            return $currentPath;
        }

        $file = $_FILES['datasheet_file'];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return $currentPath;
        }

        $replace = trim($currentPath) !== '' ? UploadService::normalizeWebPath($currentPath) : null;

        $result = UploadService::process($file, [
            'bucket' => UploadService::BUCKET_DATASHEETS,
            'mode' => UploadService::MODE_PDF,
            'max_bytes' => self::MAX_DATASHEET_SIZE,
            'replace_web_path' => $replace,
            'datasheet_style_name' => true,
        ]);

        if (!$result['ok']) {
            throw new \RuntimeException($result['error'] ?? 'Datasheet 上传失败，请重试。');
        }

        $newPath = $result['web_path'] ?? '';
        if ($newPath === '') {
            throw new \RuntimeException('Datasheet 保存失败。');
        }

        return $newPath;
    }
}
