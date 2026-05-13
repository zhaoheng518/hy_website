<!DOCTYPE html>
<?php
$langCode = (string) ($lang ?? 'en');
$htmlDir = $langCode === 'ar' ? 'rtl' : 'ltr';

$supportedLangs = \App\Core\Config::get('supported_langs', ['en', 'cn', 'es']);
if (!is_array($supportedLangs) || empty($supportedLangs)) {
    $supportedLangs = ['en'];
}
$defaultLang = (string) \App\Core\Config::get('default_lang', 'en');
$siteUrl = rtrim((string) \App\Core\Config::get('site_url', ''), '/');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = '/' . ltrim($requestPath, '/');
$segments = array_values(array_filter(explode('/', trim($requestPath, '/')), static function ($seg) {
    return $seg !== '';
}));

$pathWithoutLang = '';
if (!empty($segments) && in_array($segments[0], $supportedLangs, true)) {
    array_shift($segments);
}
if (!empty($segments)) {
    $pathWithoutLang = '/' . implode('/', $segments);
}

$canonicalHref = $siteUrl . $requestPath;
?>
<html lang="<?php echo htmlspecialchars($langCode, ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars($htmlDir, ENT_QUOTES, 'UTF-8'); ?>">
<head>
<?php require VIEW_PATH . '/layouts/main.php'; ?>
    <?php echo $orgSchema ?? ''; ?>
    <?php echo $webSiteSchema ?? ''; ?>
    <?php echo $breadcrumbsSchema ?? ''; ?>
    <?php echo $productSchema ?? ''; ?>
    <?php echo $faqSchema ?? ''; ?>
    <?php echo $articleSchema ?? ''; ?>
    <?php echo $extraSchema ?? ''; ?>
    <?php echo $localBusinessSchema ?? ''; ?>
    <?php
    $headScripts = \App\Core\Config::get('head_scripts', '');
    if (!empty($headScripts)) {
        echo $headScripts;
    }
    // DataLayer base push: page_lang + page_path (no PII). Must appear before GA4/GTM init.
    $_dl_base = [
        'page_lang' => $langCode,
        'page_path' => $requestPath,
    ];
    echo '<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push('
        . json_encode($_dl_base, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS)
        . ');</script>';
    $ga = trim((string) \App\Core\Config::get('google_analytics_id', ''));
    if ($ga !== '') {
        echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . htmlspecialchars($ga, ENT_QUOTES, 'UTF-8') . '"></script>';
        echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","' . htmlspecialchars($ga, ENT_QUOTES, 'UTF-8') . '");</script>';
    }
    $gtm = trim((string) \App\Core\Config::get('gtm_container_id', ''));
    if ($gtm !== '') {
        echo '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer","' . htmlspecialchars($gtm, ENT_QUOTES, 'UTF-8') . '");</script>';
    }
    $ads = (string) \App\Core\Config::get('google_ads_head', '');
    if ($ads !== '') {
        echo $ads;
    }
    $wa = (string) \App\Core\Config::get('whatsapp_widget_script', '');
    if ($wa !== '') {
        echo $wa;
    }
    $orgJson = trim((string) \App\Core\Config::get('schema_organization_json', ''));
    if ($orgJson !== '' && json_decode($orgJson) !== null) {
        echo '<script type="application/ld+json">' . $orgJson . '</script>';
    }
    ?>
</head>
<body>
<?php
$gtmBody = trim((string) \App\Core\Config::get('gtm_container_id', ''));
if ($gtmBody !== '') {
    echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . htmlspecialchars($gtmBody, ENT_QUOTES, 'UTF-8')
        . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
}
?>
<header class="site-header" id="site-header">
    <div class="container header-inner">
        <a href="<?php echo View::langUrl($lang ?? 'en'); ?>" class="site-logo">
            <?php echo htmlspecialchars($siteName ?? 'Stitch Tech', ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <div class="header-spacer"></div>
        <nav class="site-nav" id="main-nav">
            <div class="nav-item-mega" id="nav-products-trigger">
                <a href="<?php echo View::langUrl($lang ?? 'en'); ?>/products" class="nav-link nav-mega-trigger">
                    <?php echo $navLabels['products'] ?? 'Products'; ?>
                    <svg class="mega-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
                </a>
                <div class="mega-menu" id="mega-menu">
                    <div class="mega-menu-inner">
                        <div class="mega-grid">
                            <?php
                            $menuItems = array_filter($megaMenuItems ?? [], function($item) { return !empty($item['show_in_menu']); });
                            $menuItems = array_slice(array_values($menuItems), 0, 16);
                            foreach ($menuItems as $item):
                                $menuLink = View::langUrl($lang ?? 'en') . '/products';
                                if (!empty($item['slug'])) {
                                    $menuLink = View::langUrl($lang ?? 'en') . '/products/' . $item['slug'];
                                } elseif (!empty($item['link'])) {
                                    $menuLink = $item['link'];
                                }
                            ?>
                            <a href="<?php echo htmlspecialchars($menuLink, ENT_QUOTES, 'UTF-8'); ?>" class="mega-card">
                                <div class="mega-card-img">
                                    <?php if (!empty($item['menu_thumbnail'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['menu_thumbnail'], ENT_QUOTES, 'UTF-8'); ?>"
                                         alt="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                         loading="lazy">
                                    <?php else: ?>
                                    <div class="mega-card-placeholder">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/></svg>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mega-card-text">
                                    <strong class="mega-card-name"><?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span class="mega-card-sub"><?php echo htmlspecialchars($item['menu_subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <a href="<?php echo View::langUrl($lang ?? 'en'); ?>/factory" class="nav-link"><?php echo $navLabels['factory'] ?? 'Factory'; ?></a>
            <a href="<?php echo View::langUrl($lang ?? 'en'); ?>/cases" class="nav-link"><?php echo $navLabels['cases'] ?? 'Cases'; ?></a>
            <a href="<?php echo View::langUrl($lang ?? 'en'); ?>/about" class="nav-link"><?php echo $navLabels['about'] ?? 'About'; ?></a>
            <a href="<?php echo View::langUrl($lang ?? 'en', 'blog'); ?>" class="nav-link"><?php echo $navLabels['blog'] ?? 'Blog'; ?></a>
        </nav>
        <div class="header-actions">
            <a href="<?php echo View::langUrl($lang ?? 'en'); ?>/contact" class="nav-link nav-cta"><?php echo $navLabels['contact'] ?? 'Contact'; ?></a>
            <a href="<?php echo View::langUrl($lang ?? 'en', 'search'); ?>" class="search-toggle" aria-label="<?php echo htmlspecialchars($navLabels['search'] ?? 'Search', ENT_QUOTES, 'UTF-8'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            </a>
            <div class="lang-dropdown">
                <button class="lang-dropdown-trigger">
                    &#127760; <?php echo htmlspecialchars(View::getLangLabel($langCode), ENT_QUOTES, 'UTF-8'); ?>
                    <svg width="10" height="10" viewBox="0 0 12 12"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
                </button>
                <div class="lang-dropdown-menu">
                    <div class="lang-dropdown-menu-inner">
                        <?php foreach (View::getSupportedLangs() as $l): ?>
                        <a href="<?php echo View::langUrl($l); ?>"
                           class="lang-dropdown-item<?php echo ($lang ?? 'en') === $l ? ' active' : ''; ?>">
                            <?php echo htmlspecialchars(View::getLangLabel($l), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <button class="mobile-toggle" id="mobile-toggle" aria-label="Menu">&#9776;</button>
        </div>
    </div>
</header>

<main class="site-main">
    <?php echo $breadcrumbs ?? ''; ?>
    <?php echo $content; ?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col footer-brand">
                <div class="footer-logo"><?php echo htmlspecialchars($siteName ?? 'Stitch Tech', ENT_QUOTES, 'UTF-8'); ?></div>
                <p class="footer-desc"><?php echo htmlspecialchars($footerDesc ?? 'Professional B2B solutions for global businesses. Military-grade engineering trusted by defense contractors worldwide.', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="footer-socials">
                    <a href="#" class="social-link" aria-label="LinkedIn" target="_blank" rel="noopener">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    </a>
                    <a href="#" class="social-link" aria-label="YouTube" target="_blank" rel="noopener">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </a>
                    <a href="#" class="social-link" aria-label="X / Twitter" target="_blank" rel="noopener">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                </div>
            </div>
            <div class="footer-col">
                <h4><?php echo $navLabels['company'] ?? 'Company'; ?></h4>
                <ul class="footer-links">
                    <li><a href="<?php echo View::langUrl($lang ?? 'en'); ?>/about"><?php echo $navLabels['about'] ?? 'About Us'; ?></a></li>
                    <li><a href="<?php echo View::langUrl($lang ?? 'en'); ?>/factory"><?php echo $navLabels['factory'] ?? 'Factory'; ?></a></li>
                    <li><a href="<?php echo View::langUrl($lang ?? 'en'); ?>/cases"><?php echo $navLabels['cases'] ?? 'Applications'; ?></a></li>
                    <li><a href="<?php echo View::langUrl($lang ?? 'en', 'blog'); ?>"><?php echo $navLabels['blog'] ?? 'Blog'; ?></a></li>
                    <li><a href="<?php echo View::langUrl($lang ?? 'en'); ?>/contact"><?php echo $navLabels['contact'] ?? 'Contact Us'; ?></a></li>
                    <li><a href="<?php echo View::langUrl($lang ?? 'en'); ?>/newsletter"><?php echo ($lang ?? 'en') === 'cn' ? '邮件订阅' : (($lang ?? 'en') === 'es' ? 'Boletín' : 'Newsletter'); ?></a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4><?php echo $navLabels['products'] ?? 'Products'; ?></h4>
                <ul class="footer-links">
                    <?php
                    $footerCats = array_slice($footerCategories ?? [], 0, 6);
                    if (!empty($footerCats)):
                        foreach ($footerCats as $cat):
                    ?>
                    <li><a href="<?php echo View::langUrl($lang ?? 'en'); ?>/products/<?php echo htmlspecialchars($cat['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endforeach; else: ?>
                    <li><a href="<?php echo View::langUrl($lang ?? 'en'); ?>/products"><?php echo $navLabels['all_products'] ?? 'All Products'; ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-col">
                <h4><?php echo $navLabels['contact'] ?? 'Contact Us'; ?></h4>
                <ul class="footer-links footer-contact-list">
                    <?php if (!empty($footerContact['address'])): ?>
                    <li class="contact-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span><?php echo htmlspecialchars($footerContact['address'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($footerContact['email'])): ?>
                    <li class="contact-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 01-2.06 0L2 7"/></svg>
                        <a href="mailto:<?php echo htmlspecialchars($footerContact['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($footerContact['email'], ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($footerContact['phone'])): ?>
                    <li class="contact-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                        <a href="tel:<?php echo htmlspecialchars($footerContact['phone'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($footerContact['phone'], ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($footerContact['whatsapp'])): ?>
                    <li class="contact-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        <a href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/[^0-9]/', '', $footerContact['whatsapp']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($footerContact['whatsapp'], ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName ?? 'Stitch Tech', ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
            <div class="footer-legal">
                <a href="#"><?php echo $navLabels['privacy'] ?? 'Privacy Policy'; ?></a>
                <span class="footer-sep">|</span>
                <a href="#"><?php echo $navLabels['terms'] ?? 'Terms of Service'; ?></a>
            </div>
        </div>
    </div>
</footer>

<script src="<?php echo View::cacheBust('js/front.js'); ?>" defer></script>
<script src="<?php echo View::cacheBust('js/analytics.js'); ?>" defer></script>
<?php
$bodyScripts = \App\Core\Config::get('body_scripts', '');
if (!empty($bodyScripts)) echo $bodyScripts;
?>
</body>
</html>
