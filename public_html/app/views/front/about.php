<?php
$page = $page ?? [];
$navLabels = $navLabels ?? [];
$lang = $lang ?? 'en';
?>

<section class="page-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($h1 ?? 'About Us', ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($page['subtitle'])): ?>
        <p class="page-subtitle" style="color:var(--c-text-light);margin-top:8px;"><?php echo htmlspecialchars($page['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="about-grid about-grid-page">
            <div class="about-content">
                <p class="about-inline-title"><?php echo htmlspecialchars($page['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="about-text rich-text">
                    <?php if (!empty($page['content'])): ?>
                    <?php echo $page['content']; ?>
                    <?php else: ?>
                    <p>Learn more about our company, our mission, and our commitment to excellence.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($page['featured_image'])): ?>
            <div class="about-image">
                <img src="<?php echo htmlspecialchars($page['featured_image'], ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($page['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                     loading="lazy">
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section section-cta">
    <div class="container">
        <div class="cta-content">
            <h2><?php echo $lang === 'cn' ? '准备好合作了吗？' : ($lang === 'es' ? '¿Listo para colaborar?' : 'Ready to Partner?'); ?></h2>
            <p><?php echo $lang === 'cn' ? '联系我们开始您的B2B之旅' : ($lang === 'es' ? 'Contáctenos para comenzar su viaje B2B' : 'Contact us to start your B2B journey'); ?></p>
            <a href="<?php echo View::langUrl($lang, 'contact'); ?>" class="btn btn-hero">
                <?php echo $lang === 'cn' ? '联系我们' : ($lang === 'es' ? 'Contáctenos' : 'Get in Touch'); ?>
            </a>
        </div>
    </div>
</section>
