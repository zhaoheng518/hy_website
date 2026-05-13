<?php
$recommendedProducts = $recommendedProducts ?? [];
$siteName = $siteName ?? 'Site';
$lang = $lang ?? 'en';
$navLabels = $navLabels ?? [];
?>

<section class="error-page-section">
    <div class="container error-page-inner">
        <h1><?php echo $lang === 'cn' ? '页面未找到' : ($lang === 'es' ? 'Página no encontrada' : 'Page not found'); ?></h1>
        <p class="error-page-lead"><?php echo $lang === 'cn' ? '您访问的页面不存在或已被移动。' : ($lang === 'es' ? 'La página solicitada no existe o se ha movido.' : 'The page you requested could not be found or has been moved.'); ?></p>
        <a href="<?php echo View::langUrl($lang); ?>" class="btn btn-primary btn-error-home">
            <?php echo $lang === 'cn' ? '返回首页' : ($lang === 'es' ? 'Volver al inicio' : 'Back to home'); ?>
        </a>
    </div>
</section>

<?php if (!empty($recommendedProducts)): ?>
<section class="section section-related">
    <div class="container">
        <h2><?php echo $lang === 'cn' ? '推荐产品' : ($lang === 'es' ? 'Productos recomendados' : 'Recommended products'); ?></h2>
        <div class="product-grid product-grid-compact">
            <?php foreach ($recommendedProducts as $rp): ?>
            <a href="<?php echo View::langUrl($lang, 'product/' . ($rp['slug'] ?? '')); ?>" class="product-card">
                <div class="product-card-img">
                    <?php
                    $pm = '';
                    if (!empty($rp['images'][0]['url'])) {
                        $pm = $rp['images'][0]['url'];
                    } elseif (!empty($rp['image'])) {
                        $pm = $rp['image'];
                    }
                    ?>
                    <?php if ($pm !== ''): ?>
                    <?php echo View::responsiveImage($pm, $rp['name'] ?? '', ['loading' => 'lazy']); ?>
                    <?php else: ?>
                    <div class="product-card-placeholder"><span>&#9733;</span></div>
                    <?php endif; ?>
                </div>
                <div class="product-card-body">
                    <h3 class="product-card-name"><?php echo htmlspecialchars($rp['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
