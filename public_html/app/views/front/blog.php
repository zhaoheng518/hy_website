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
?>

<section class="page-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($h1 ?? 'Blog', ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($posts)): ?>
        <p class="empty-state"><?php echo $lang === 'cn' ? '暂无文章' : ($lang === 'es' ? 'No hay artículos' : 'No blog posts available.'); ?></p>
        <?php else: ?>
        <div class="blog-grid blog-grid-full">
            <?php foreach ($posts as $post): ?>
            <a href="<?php echo View::langUrl($lang, 'blog/' . $post['slug']); ?>" class="blog-card">
                <?php if (!empty($post['image'])): ?>
                <div class="blog-card-img">
                    <img src="<?php echo htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>"
                         loading="lazy">
                </div>
                <?php endif; ?>
                <div class="blog-card-body">
                    <span class="blog-date"><?php echo htmlspecialchars($post['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    <h3><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars(View::truncate($post['desc'] ?? '', 150), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
