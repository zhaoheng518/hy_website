<?php
$navLabels = [
    'en' => ['home' => 'Home', 'products' => 'Products', 'about' => 'About Us', 'cases' => 'Cases', 'blog' => 'Blog', 'contact' => 'Contact Us'],
    'cn' => ['home' => '首页', 'products' => '产品中心', 'about' => '关于我们', 'cases' => '客户案例', 'blog' => '博客', 'contact' => '联系我们'],
    'es' => ['home' => 'Inicio', 'products' => 'Productos', 'about' => 'Sobre Nosotros', 'cases' => 'Casos', 'blog' => 'Blog', 'contact' => 'Contáctenos'],
][$lang] ?? [];
$footerDesc = [
    'en' => 'Professional B2B solutions for global businesses.',
    'cn' => '为全球企业提供专业B2B解决方案。',
    'es' => 'Soluciones B2B profesionales para empresas globales.',
][$lang] ?? '';
$relatedPosts = $relatedPosts ?? [];
$relatedProducts = $relatedProducts ?? [];
?>

<article class="page-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($post['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($post['date'])): ?>
        <span class="blog-date blog-date-large"><?php echo htmlspecialchars($post['date'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
</article>

<article class="section">
    <div class="container container-narrow">
        <div class="blog-content">
            <?php if (!empty($post['image'])): ?>
            <div class="blog-featured-img">
                <?php echo View::responsiveImage($post['image'], $post['title'] ?? '', ['loading' => 'eager', 'fetchpriority' => 'high']); ?>
            </div>
            <?php endif; ?>

            <div class="blog-body">
                <?php
                $__blogContent = (isset($post['content']) && $post['content'] !== '')
                    ? \App\Core\RichTextSanitizer::sanitize($post['content'])
                    : nl2br(htmlspecialchars($post['desc'] ?? '', ENT_QUOTES, 'UTF-8'));
                echo $__blogContent;
                unset($__blogContent);
                ?>
            </div>

            <?php if (!empty($relatedProducts)): ?>
            <section class="blog-related" aria-labelledby="blog-related-products">
                <h2 id="blog-related-products"><?php echo $lang === 'cn' ? '相关产品' : ($lang === 'es' ? 'Productos relacionados' : 'Related products'); ?></h2>
                <ul class="related-links-list">
                    <?php foreach ($relatedProducts as $rp): ?>
                    <li><a href="<?php echo View::langUrl($lang, 'product/' . ($rp['slug'] ?? '')); ?>"><?php echo htmlspecialchars($rp['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <?php if (!empty($relatedPosts)): ?>
            <section class="blog-related" aria-labelledby="blog-related-posts">
                <h2 id="blog-related-posts"><?php echo $lang === 'cn' ? '相关文章' : ($lang === 'es' ? 'Artículos relacionados' : 'Related articles'); ?></h2>
                <ul class="related-links-list">
                    <?php foreach ($relatedPosts as $rp): ?>
                    <li><a href="<?php echo View::langUrl($lang, 'blog/' . ($rp['slug'] ?? '')); ?>"><?php echo htmlspecialchars($rp['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <div class="blog-footer">
                <a href="<?php echo View::langUrl($lang); ?>" class="btn btn-primary">
                    <?php echo $lang === 'cn' ? '返回首页' : ($lang === 'es' ? 'Inicio' : 'Home'); ?>
                </a>
            </div>
        </div>
    </div>
</article>
