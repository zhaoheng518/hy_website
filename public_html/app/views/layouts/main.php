<?php
/**
 * Front <head> prefix: charset, viewport, $seoHead, default description fallback,
 * favicon, stylesheet, optional hreflang + canonical (only when not already in $seoHead).
 *
 * Expects variables from front/layout.php: $seoHead, $siteUrl, $supportedLangs, $defaultLang,
 * $pathWithoutLang, $canonicalHref (and standard extract scope).
 */
$__seoHead = (string) ($seoHead ?? '');
$__hasHreflangOrCanonical = (strpos($__seoHead, 'rel="canonical"') !== false
    || strpos($__seoHead, 'hreflang=') !== false);
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo $__seoHead; ?>
    <?php
    $defaultMetaDesc = \App\Core\Config::get('default_meta_description', '');
    if (!empty($defaultMetaDesc) && strpos($__seoHead, 'name="description"') === false):
    ?>
    <meta name="description" content="<?php echo htmlspecialchars((string) $defaultMetaDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php
    $favicon = \App\Core\Config::get('favicon', '');
    if (!empty($favicon)):
    ?>
    <link rel="icon" href="<?php echo htmlspecialchars((string) $favicon, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo View::frontStylesheetUrl(); ?>">
    <?php if (!$__hasHreflangOrCanonical): ?>
    <?php
    $hreflangQuery = \App\Core\SEO::paginationQuerySuffix();
    $hreflangPath = $pathWithoutLang === '' ? '/' : $pathWithoutLang;
    foreach (\App\Core\SEO::hreflangAlternateUrlSegments(is_array($supportedLangs) ? $supportedLangs : []) as $altLang):
        $altLang = trim((string) $altLang);
        if ($altLang === '') {
            continue;
        }
        $hreflang = \App\Core\SEO::alternateHreflangAttribute($altLang);
        $altPath = '/' . $altLang . $hreflangPath;
        $altUrl = $siteUrl . $altPath . $hreflangQuery;
    ?>
    <link rel="alternate" hreflang="<?php echo htmlspecialchars($hreflang, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($altUrl, ENT_QUOTES, 'UTF-8'); ?>" />
    <?php endforeach; ?>
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars($siteUrl . '/' . $defaultLang . $hreflangPath . $hreflangQuery, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalHref, ENT_QUOTES, 'UTF-8'); ?>" />
    <?php endif; ?>
