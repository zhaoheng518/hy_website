<?php
$relatedProducts = $relatedProducts ?? [];
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
        <h1><?php echo htmlspecialchars($case['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="detail-grid">
            <?php if (!empty($case['image'])): ?>
            <div class="detail-image">
                <img src="<?php echo htmlspecialchars($case['image'], ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($case['title'], ENT_QUOTES, 'UTF-8'); ?>"
                     loading="lazy">
            </div>
            <?php endif; ?>
            <div class="detail-content">
                <?php if (!empty($case['client_industry']) || !empty($case['products_used'])): ?>
                <p class="case-meta" style="color:var(--c-text-light);font-size:14px;margin-bottom:12px;">
                    <?php if (!empty($case['client_industry'])): ?>
                    <strong><?php echo $lang === 'cn' ? '行业' : ($lang === 'es' ? 'Sector' : 'Industry'); ?>:</strong>
                    <?php echo htmlspecialchars($case['client_industry'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                    <?php if (!empty($case['products_used'])): ?>
                    &nbsp;| <strong><?php echo $lang === 'cn' ? '产品' : ($lang === 'es' ? 'Productos' : 'Products'); ?>:</strong>
                    <?php echo htmlspecialchars($case['products_used'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
                <div class="detail-desc">
                    <?php echo nl2br(htmlspecialchars($case['desc'] ?? '', ENT_QUOTES, 'UTF-8')); ?>
                </div>
                <?php if (!empty($case['content'])): ?>
                <div class="product-rich-content" style="margin-top:20px;">
                    <?php echo \App\Core\RichTextSanitizer::sanitize((string) $case['content']); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($case['gallery']) && is_array($case['gallery'])): ?>
                <div class="case-gallery" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-top:20px;">
                    <?php foreach ($case['gallery'] as $gurl): ?>
                    <?php if (is_string($gurl) && $gurl !== ''): ?>
                    <a href="<?php echo htmlspecialchars($gurl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo htmlspecialchars($gurl, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" style="width:100%;height:100px;object-fit:cover;border-radius:8px;">
                    </a>
                    <?php elseif (is_array($gurl) && !empty($gurl['url'])): ?>
                    <a href="<?php echo htmlspecialchars($gurl['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo htmlspecialchars($gurl['url'], ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" style="width:100%;height:100px;object-fit:cover;border-radius:8px;">
                    </a>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($relatedProducts)): ?>
                <div style="margin-top:28px;">
                    <h2 style="font-size:1.1rem;margin-bottom:12px;"><?php echo $lang === 'cn' ? '相关产品' : ($lang === 'es' ? 'Productos' : 'Related products'); ?></h2>
                    <div class="product-grid product-grid-compact">
                        <?php foreach ($relatedProducts as $rp): ?>
                        <a href="<?php echo View::langUrl($lang, 'product/' . ($rp['slug'] ?? '')); ?>" class="product-card">
                            <div class="product-card-body">
                                <h3><?php echo htmlspecialchars($rp['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="detail-actions">
                    <a href="<?php echo View::langUrl($lang, 'contact'); ?>" class="btn btn-primary">
                        <?php echo $lang === 'cn' ? '咨询此案例' : ($lang === 'es' ? 'Consultar Caso' : 'Discuss This Case'); ?>
                    </a>
                    <a href="javascript:history.back()" class="btn btn-outline">
                        <?php echo $lang === 'cn' ? '返回' : ($lang === 'es' ? 'Volver' : 'Go Back'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
