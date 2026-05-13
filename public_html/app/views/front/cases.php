<?php
$page = $page ?? [];
$navLabels = $navLabels ?? [];
$lang = $lang ?? 'en';
?>

<section class="page-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($h1 ?? 'Case Studies', ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($page['subtitle'])): ?>
        <p class="page-subtitle" style="color:var(--c-text-light);margin-top:8px;"><?php echo htmlspecialchars($page['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($cases)): ?>
        <p class="empty-state"><?php echo $lang === 'cn' ? '暂无案例' : ($lang === 'es' ? 'No hay casos' : 'No case studies available.'); ?></p>
        <?php else: ?>
        <div class="cases-grid cases-grid-full">
            <?php foreach ($cases as $case): ?>
            <a href="<?php echo View::langUrl($lang, 'cases/' . $case['slug']); ?>" class="case-card">
                <?php if (!empty($case['image'])): ?>
                <div class="case-card-img">
                    <img src="<?php echo htmlspecialchars($case['image'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($case['title'], ENT_QUOTES, 'UTF-8'); ?>"
                         loading="lazy">
                </div>
                <?php endif; ?>
                <div class="case-card-body">
                    <h3><?php echo htmlspecialchars($case['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars(View::truncate($case['desc'] ?? '', 150), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
