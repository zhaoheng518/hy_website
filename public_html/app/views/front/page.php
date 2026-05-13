<section class="page-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($page['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($page['subtitle'])): ?>
        <p class="page-subtitle" style="color:var(--c-text-light);margin-top:8px;"><?php echo htmlspecialchars($page['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($page['featured_image'])): ?>
<section class="section" style="padding-top:0;">
    <div class="container">
        <img src="<?php echo htmlspecialchars($page['featured_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width:100%;max-height:360px;object-fit:cover;border-radius:12px;">
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container container-narrow">
        <div class="product-rich-content">
            <?php echo \App\Core\RichTextSanitizer::sanitize((string) ($page['content'] ?? '')); ?>
        </div>
        <?php if (!empty($page['faqs']) && is_array($page['faqs'])): ?>
        <div class="faq-accordion" style="margin-top:32px;">
            <h2 style="margin-bottom:12px;">FAQ</h2>
            <?php foreach ($page['faqs'] as $fi => $faq): ?>
            <?php
            $fq = trim($faq['question'] ?? '');
            $fa = trim($faq['answer'] ?? '');
            if ($fq === '' || $fa === '') {
                continue;
            }
            ?>
            <details class="faq-item"<?php echo $fi === 0 ? ' open' : ''; ?>>
                <summary class="faq-summary"><?php echo htmlspecialchars($fq, ENT_QUOTES, 'UTF-8'); ?></summary>
                <div class="faq-answer"><?php echo nl2br(htmlspecialchars($fa, ENT_QUOTES, 'UTF-8')); ?></div>
            </details>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
