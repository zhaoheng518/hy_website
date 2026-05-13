<?php
$lang = $lang ?? 'en';
$q = $query ?? '';
$ph = $lang === 'cn' ? '输入关键词…' : ($lang === 'es' ? 'Buscar…' : 'Search…');
$btn = $lang === 'cn' ? '搜索' : ($lang === 'es' ? 'Buscar' : 'Search');
?>

<section class="page-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($h1 ?? 'Search', ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
</section>

<section class="section">
    <div class="container container-narrow">
        <form class="search-page-form" method="get" action="<?php echo View::langUrl($lang, 'search'); ?>">
            <div class="search-page-row">
                <input type="search" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($ph, ENT_QUOTES, 'UTF-8'); ?>" class="search-page-input" maxlength="120">
                <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($btn, ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </form>
        <p class="search-page-hint" style="margin-top:16px;color:var(--c-text-light);font-size:14px;">
            <?php echo $lang === 'cn' ? '产品目录请访问「产品中心」.' : ($lang === 'es' ? 'Visite «Productos» para el catálogo.' : 'Browse the product catalog for full listings.'); ?>
            <a href="<?php echo View::langUrl($lang, 'products'); ?>"><?php echo $lang === 'cn' ? '产品中心' : ($lang === 'es' ? 'Productos' : 'Products'); ?></a>
        </p>
    </div>
</section>
