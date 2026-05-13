<?php
$page = $page ?? [];
$navLabels = $navLabels ?? [];
$lang = $lang ?? 'en';
?>

<section class="page-section">
    <div class="container">
        <h1 class="page-title"><?php echo htmlspecialchars($page['title'] ?? $navLabels['factory'] ?? 'Factory', ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($page['subtitle'])): ?>
        <p class="page-subtitle"><?php echo htmlspecialchars($page['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if (!empty($page['featured_image'])): ?>
        <div class="page-featured-image" style="margin-bottom:24px;">
            <img src="<?php echo htmlspecialchars($page['featured_image'], ENT_QUOTES, 'UTF-8'); ?>"
                 alt="<?php echo htmlspecialchars($page['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                 style="width:100%;max-height:400px;object-fit:cover;border-radius:8px;" loading="lazy">
        </div>
        <?php endif; ?>

        <?php if (!empty($page['content'])): ?>
        <div class="page-content rich-text">
            <?php echo $page['content']; ?>
        </div>
        <?php else: ?>
        <div class="page-content">
            <div class="section-empty">Content coming soon.</div>
        </div>
        <?php endif; ?>
    </div>
</section>
