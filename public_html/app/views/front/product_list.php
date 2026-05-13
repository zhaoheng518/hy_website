<?php
$activeCategory = $activeCategory ?? null;
$allCategories = $allCategories ?? [];
$products = $products ?? [];
$popularProducts = $popularProducts ?? [];
$lang = $lang ?? 'en';
$navLabels = $navLabels ?? [];
$seoHead = $seoHead ?? '';

// DataLayer push for category/product-list page (no PII)
$_dl_category = [
    'page_type'       => 'category',
    'page_lang'       => (string) $lang,
    'category_slug'   => (string) ($activeCategory['slug'] ?? ''),
    'category_name'   => (string) ($activeCategory['name'] ?? ''),
];
?>
<script>
window.dataLayer = window.dataLayer || [];
window.dataLayer.push(<?php echo json_encode($_dl_category, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>);
</script>

<section class="catalog-page">
    <div class="container catalog-layout">
        <aside class="catalog-sidebar">
        <div class="sidebar-sticky">
            <p class="sidebar-title"><?php echo $navLabels['products'] ?? 'Products'; ?></p>
            <nav class="sidebar-nav" aria-label="<?php echo htmlspecialchars($navLabels['products'] ?? 'Products', ENT_QUOTES, 'UTF-8'); ?>">
                <a href="<?php echo View::langUrl($lang); ?>/products"
                   class="sidebar-link<?php echo !$activeCategory ? ' active' : ''; ?>">
                    <?php echo $navLabels['all_products'] ?? 'All Products'; ?>
                </a>
                <?php foreach ($allCategories as $cat): ?>
                <a href="<?php echo View::langUrl($lang); ?>/products/<?php echo htmlspecialchars($cat['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   class="sidebar-link<?php echo ($activeCategory['slug'] ?? '') === ($cat['slug'] ?? '') ? ' active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </aside>

    <main class="catalog-main">
        <div class="catalog-header">
            <h1 class="catalog-title">
                <?php echo $activeCategory ? htmlspecialchars($activeCategory['name'] ?? '', ENT_QUOTES, 'UTF-8') : ($navLabels['all_products'] ?? 'All Products'); ?>
            </h1>
            <?php if ($activeCategory && !empty($activeCategory['description'])): ?>
            <p class="catalog-desc"><?php echo htmlspecialchars($activeCategory['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <span class="catalog-count"><?php echo count($products); ?> <?php echo $navLabels['products'] ?? 'Products'; ?></span>
        </div>

        <?php if ($activeCategory && !empty($popularProducts)): ?>
        <section class="catalog-popular" aria-labelledby="popular-products-heading">
            <h2 id="popular-products-heading" class="catalog-subheading"><?php echo $lang === 'cn' ? '热门产品' : ($lang === 'es' ? 'Productos populares' : 'Popular products'); ?></h2>
            <div class="product-grid product-grid-compact">
                <?php foreach (array_slice($popularProducts, 0, 6) as $pp): ?>
                <a href="<?php echo View::langUrl($lang); ?>/product/<?php echo htmlspecialchars($pp['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="product-card">
                    <div class="product-card-img">
                        <?php
                        $pm = '';
                        if (!empty($pp['images'])) {
                            foreach ($pp['images'] as $im) {
                                if (!empty($im['is_main']) && !empty($im['url'])) {
                                    $pm = $im['url'];
                                    break;
                                }
                            }
                            if ($pm === '' && !empty($pp['images'][0]['url'])) {
                                $pm = $pp['images'][0]['url'];
                            }
                        }
                        if ($pm === '') {
                            $pm = $pp['image'] ?? '';
                        }
                        ?>
                        <?php if ($pm !== ''): ?>
                        <?php echo View::responsiveImage($pm, $pp['name'] ?? '', ['loading' => 'lazy']); ?>
                        <?php else: ?>
                        <div class="product-card-placeholder">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="product-card-body">
                        <h3 class="product-card-name"><?php echo htmlspecialchars($pp['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (empty($products)): ?>
        <div class="catalog-empty">
            <p><?php echo $navLabels['no_products'] ?? 'No products found in this category.'; ?></p>
        </div>
        <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
            <a href="<?php echo View::langUrl($lang); ?>/product/<?php echo htmlspecialchars($product['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="product-card">
                <div class="product-card-img">
                    <?php
                    $mainImg = '';
                    if (!empty($product['images'])) {
                        foreach ($product['images'] as $img) {
                            if (!empty($img['is_main']) && !empty($img['url'])) {
                                $mainImg = $img['url'];
                                break;
                            }
                        }
                        if (empty($mainImg) && !empty($product['images'][0]['url'])) {
                            $mainImg = $product['images'][0]['url'];
                        }
                    }
                    if (empty($mainImg)) {
                        $mainImg = $product['image'] ?? '';
                    }
                    ?>
                    <?php if (!empty($mainImg)): ?>
                    <?php echo View::responsiveImage($mainImg, $product['name'] ?? '', ['loading' => 'lazy']); ?>
                    <?php else: ?>
                    <div class="product-card-placeholder">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="product-card-body">
                    <h3 class="product-card-name"><?php echo htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                    <?php if (!empty($product['short_desc'])): ?>
                    <p class="product-card-desc"><?php echo htmlspecialchars($product['short_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <span class="product-card-cta"><?php echo $navLabels['learn_more'] ?? 'Read More'; ?> &rarr;</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
    </div>
</section>
