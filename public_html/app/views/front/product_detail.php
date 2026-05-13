<?php
$navLabels = $navLabels ?? [];

$images = $product['images'] ?? [];
if (empty($images) && !empty($product['image'])) {
    $images = [['url' => $product['image'], 'alt_text' => $product['name'] ?? '', 'is_main' => true]];
}
$mainImage = null;
$otherImages = [];
foreach ($images as $img) {
    if (!empty($img['is_main']) && $mainImage === null) {
        $mainImage = $img;
    } else {
        $otherImages[] = $img;
    }
}
if ($mainImage === null && !empty($images)) {
    $mainImage = $images[0];
    $otherImages = array_slice($images, 1);
}
$allImages = $mainImage ? array_merge([$mainImage], $otherImages) : [];
$relatedProducts = $relatedProducts ?? [];
$relatedPosts = $relatedPosts ?? [];
$categoryLink = $categoryLink ?? '';
$categoryName = $categoryName ?? '';
$productFaqs = $productFaqs ?? ($product['faqs'] ?? []);
if (!is_array($productFaqs)) {
    $productFaqs = [];
}
$datasheetExtras = $datasheetExtras ?? ($product['datasheet_files'] ?? []);
if (!is_array($datasheetExtras)) {
    $datasheetExtras = [];
}
$downloadCenter = $downloadCenter ?? ($product['download_center'] ?? []);
if (!is_array($downloadCenter)) {
    $downloadCenter = [];
}
$hasPrimarySheet = !empty($product['datasheet']);
$hasExtraSheets = !empty($datasheetExtras);
$hasDownloadCenter = false;
foreach ($downloadCenter as $dcRow) {
    if (trim((string) ($dcRow['url'] ?? '')) !== '') {
        $hasDownloadCenter = true;
        break;
    }
}
$shortLead = trim((string) ($product['short_description'] ?? $product['short_desc'] ?? ''));
$copts = $product['customizable_options'] ?? [];
if (!is_array($copts)) {
    $copts = [];
}
$customOpts = $product['custom_options'] ?? [];
if (!is_array($customOpts)) {
    $customOpts = [];
}
$b2bLabels = $lang === 'cn'
    ? [
        'structure' => '产品结构',
        'technical_specs' => '技术规格',
        'electrical' => '电气性能',
        'mechanical' => '机械性能',
        'environmental' => '环境性能',
        'applications' => '应用领域',
        'standards' => '标准与认证',
        'compliance' => '符合标准',
        'moq' => '最小起订量',
        'lead' => '交货期',
        'custom' => '可定制选项',
        'custom_options' => '选配项',
    ]
    : ($lang === 'es'
        ? [
            'structure' => 'Estructura del producto',
            'technical_specs' => 'Especificaciones técnicas',
            'electrical' => 'Características eléctricas',
            'mechanical' => 'Características mecánicas',
            'environmental' => 'Características ambientales',
            'applications' => 'Aplicaciones',
            'standards' => 'Normas y certificaciones',
            'compliance' => 'Normas',
            'moq' => 'MOQ',
            'lead' => 'Plazo de entrega',
            'custom' => 'Opciones personalizables',
            'custom_options' => 'Opciones configurables',
        ]
        : [
            'structure' => 'Product structure',
            'technical_specs' => 'Technical specifications',
            'electrical' => 'Electrical characteristics',
            'mechanical' => 'Mechanical characteristics',
            'environmental' => 'Environmental characteristics',
            'applications' => 'Applications',
            'standards' => 'Standards & certifications',
            'compliance' => 'Compliance & standards',
            'moq' => 'MOQ',
            'lead' => 'Lead time',
            'custom' => 'Customizable options',
            'custom_options' => 'Custom options',
        ]);
$b2bHtmlBlocks = [
    'product_structure' => $b2bLabels['structure'],
    'technical_specs' => $b2bLabels['technical_specs'],
    'electrical_characteristics' => $b2bLabels['electrical'],
    'mechanical_characteristics' => $b2bLabels['mechanical'],
    'environmental_characteristics' => $b2bLabels['environmental'],
    'applications' => $b2bLabels['applications'],
    'standards' => $b2bLabels['standards'],
    'compliance_standards' => $b2bLabels['compliance'],
];
?>
<?php require __DIR__ . '/../partials/data_layer_product.php'; ?>
<section class="page-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($product['product_model']) || !empty($product['product_series'])): ?>
        <p class="page-subtitle product-header-meta">
            <?php if (!empty($product['product_series'])): ?>
            <span class="product-meta-pill"><?php echo htmlspecialchars($product['product_series'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
            <?php if (!empty($product['product_model'])): ?>
            <span class="product-meta-pill product-meta-model"><?php echo htmlspecialchars($product['product_model'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </p>
        <?php endif; ?>
        <?php if ($categoryName !== '' && $categoryLink !== ''): ?>
        <p class="page-subtitle">
            <a href="<?php echo htmlspecialchars($categoryLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?></a>
        </p>
        <?php endif; ?>
    </div>
</section>

<section class="section product-detail-section">
    <div class="container">
        <div class="product-detail-top">
            <div class="product-gallery" id="product-gallery">
                <div class="gallery-main" id="gallery-main">
                    <?php if ($mainImage && !empty($mainImage['url'])): ?>
                    <?php
                    $mainAlt = $mainImage['alt_text'] ?? $product['name'] ?? '';
                    echo View::responsiveImage($mainImage['url'], $mainAlt, [
                        'id' => 'gallery-main-img',
                        'data-zoom' => $mainImage['url'],
                        'loading' => 'eager',
                        'fetchpriority' => 'high',
                    ]);
                    ?>
                    <?php else: ?>
                    <div class="gallery-main-placeholder" id="gallery-main-img">
                        <?php
                        $phName = (string) ($product['name'] ?? '?');
                        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
                            $phLetter = mb_strtoupper(mb_substr($phName, 0, 1, 'UTF-8'), 'UTF-8');
                        } else {
                            $phLetter = strtoupper(substr($phName, 0, 1));
                        }
                        ?>
                        <span><?php echo htmlspecialchars($phLetter, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (count($allImages) > 1): ?>
                <div class="gallery-thumbs" id="gallery-thumbs">
                    <?php foreach ($allImages as $i => $img): ?>
                    <div class="gallery-thumb<?php echo $i === 0 ? ' active' : ''; ?>"
                         data-index="<?php echo $i; ?>"
                         onclick="switchGalleryImage(<?php echo $i; ?>)">
                        <?php if (!empty($img['url'])): ?>
                        <?php
                        $tAlt = $img['alt_text'] ?? $product['name'] ?? '';
                        echo View::responsiveImage($img['url'], $tAlt, ['class' => '', 'loading' => 'lazy']);
                        ?>
                        <?php else: ?>
                        <div class="thumb-placeholder">&#128247;</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="product-info">
                <p class="product-info-title product-title-lead"><?php echo htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if ($shortLead !== ''): ?>
                <p class="product-info-short"><?php echo htmlspecialchars($shortLead, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <?php if (!empty($product['moq']) || !empty($product['lead_time'])): ?>
                <ul class="product-inquiry-meta">
                    <?php if (!empty($product['moq'])): ?>
                    <li><strong><?php echo htmlspecialchars($b2bLabels['moq'], ENT_QUOTES, 'UTF-8'); ?>:</strong>
                        <?php echo htmlspecialchars($product['moq'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($product['lead_time'])): ?>
                    <li><strong><?php echo htmlspecialchars($b2bLabels['lead'], ENT_QUOTES, 'UTF-8'); ?>:</strong>
                        <?php echo htmlspecialchars($product['lead_time'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                </ul>
                <?php endif; ?>

                <?php if (!empty($copts)): ?>
                <div class="product-custom-opts">
                    <h2 class="product-module-title"><?php echo htmlspecialchars($b2bLabels['custom'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <dl class="custom-opts-dl">
                        <?php foreach ($copts as $co): ?>
                        <?php
                        $cn = trim((string) ($co['name'] ?? ''));
                        $cv = trim((string) ($co['value'] ?? ''));
                        if ($cn === '' && $cv === '') {
                            continue;
                        }
                        ?>
                        <dt><?php echo htmlspecialchars($cn !== '' ? $cn : '—', ENT_QUOTES, 'UTF-8'); ?></dt>
                        <dd><?php echo htmlspecialchars($cv, ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php endforeach; ?>
                    </dl>
                </div>
                <?php endif; ?>

                <?php if (!empty($customOpts)): ?>
                <div class="product-custom-opts product-custom-options-extra">
                    <h2 class="product-module-title"><?php echo htmlspecialchars($b2bLabels['custom_options'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <dl class="custom-opts-dl">
                        <?php foreach ($customOpts as $co): ?>
                        <?php
                        $cn = trim((string) ($co['name'] ?? ''));
                        $cv = trim((string) ($co['value'] ?? ''));
                        if ($cn === '' && $cv === '') {
                            continue;
                        }
                        ?>
                        <dt><?php echo htmlspecialchars($cn !== '' ? $cn : '—', ENT_QUOTES, 'UTF-8'); ?></dt>
                        <dd><?php echo htmlspecialchars($cv, ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php endforeach; ?>
                    </dl>
                </div>
                <?php endif; ?>

                <?php if (!empty($product['specs'])): ?>
                <div class="product-specs-quick">
                    <h2><?php echo $navLabels['specs'] ?? 'Specifications'; ?></h2>
                    <div class="specs-table-wrap">
                    <table class="specs-table">
                        <?php foreach ($product['specs'] as $spec): ?>
                        <?php if (!empty($spec['label'])): ?>
                        <tr>
                            <th><?php echo htmlspecialchars($spec['label'], ENT_QUOTES, 'UTF-8'); ?></th>
                            <td><?php echo htmlspecialchars($spec['value'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </table>
                    </div>
                </div>
                <?php endif; ?>

                <div class="product-actions">
                    <a href="<?php echo View::langUrl($lang, 'contact'); ?>"
                       class="btn btn-primary">
                        <?php echo $navLabels['quote'] ?? 'Request Quote'; ?>
                    </a>
                </div>

                <div class="product-module product-module-compare" id="compare-module" data-slug="<?php echo htmlspecialchars($product['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <h2 class="product-module-title"><?php echo $lang === 'cn' ? '产品对比' : ($lang === 'es' ? 'Comparar' : 'Compare products'); ?></h2>
                    <p class="product-module-desc"><?php echo $lang === 'cn' ? '将本产品加入对比列表（最多4个），然后在对比页并排查看规格。' : ($lang === 'es' ? 'Añada hasta 4 productos y abra la página de comparación.' : 'Add up to 4 products, then open the comparison page.'); ?></p>
                    <button type="button" class="btn btn-outline" id="btn-add-compare"><?php echo $lang === 'cn' ? '加入对比' : ($lang === 'es' ? 'Añadir a comparar' : 'Add to compare'); ?></button>
                    <a href="<?php echo View::langUrl($lang, 'compare'); ?>" class="btn btn-primary" id="link-open-compare" style="margin-left:8px;"><?php echo $lang === 'cn' ? '打开对比页' : ($lang === 'es' ? 'Ver comparación' : 'Open compare'); ?></a>
                    <p class="compare-status" id="compare-status" style="margin-top:10px;font-size:14px;color:var(--c-text-light);"></p>
                </div>
            </div>
        </div>

        <?php if (!empty(trim((string) ($product['desc'] ?? '')))): ?>
        <div class="product-b2b-rich-block product-desc-main">
            <div class="product-rich-content">
                <?php echo \App\Core\RichTextSanitizer::sanitize((string) $product['desc']); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php foreach ($b2bHtmlBlocks as $fieldKey => $sectionTitle): ?>
        <?php
        $htmlChunk = trim((string) ($product[$fieldKey] ?? ''));
        if ($htmlChunk === '') {
            continue;
        }
        ?>
        <div class="product-b2b-rich-block">
            <h2 class="section-title product-b2b-rich-title"><?php echo htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="product-rich-content product-b2b-rich-inner">
                <?php echo \App\Core\RichTextSanitizer::sanitize($htmlChunk); ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($hasPrimarySheet || $hasExtraSheets || $hasDownloadCenter): ?>
        <section class="section product-module-datasheet" aria-labelledby="datasheet-module-title">
            <div class="container">
                <h2 id="datasheet-module-title" class="section-title"><?php echo $lang === 'cn' ? '资料下载' : ($lang === 'es' ? 'Descargas' : 'Downloads & datasheets'); ?></h2>
                <?php
                // Module 10: build a tracked download URL via /{lang}/download/track
                // $product['slug'] and $lang are always available in this template scope.
                $dlProductSlug = $product['slug'] ?? '';
                function buildDownloadTrackUrl(string $lang, string $fileUrl, string $productSlug, string $label): string {
                    return '/' . $lang . '/download/track'
                        . '?file='    . rawurlencode($fileUrl)
                        . '&product=' . rawurlencode($productSlug)
                        . '&label='   . rawurlencode($label);
                }
                ?>
                <ul class="datasheet-download-list">
                    <?php if ($hasPrimarySheet): ?>
                    <?php
                    $dsLabel     = $navLabels['download'] ?? 'Download Datasheet';
                    $dsRawUrl    = (string) $product['datasheet'];
                    $dsTrackUrl  = buildDownloadTrackUrl($lang, $dsRawUrl, $dlProductSlug, $dsLabel);
                    $dsFileName  = htmlspecialchars(rawurldecode(basename($dsRawUrl)), ENT_QUOTES, 'UTF-8');
                    ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($dsTrackUrl, ENT_QUOTES, 'UTF-8'); ?>"
                           class="datasheet-download-link"
                           data-file-name="<?php echo $dsFileName; ?>"
                           data-product-slug="<?php echo htmlspecialchars($dlProductSlug, ENT_QUOTES, 'UTF-8'); ?>"
                           target="_blank" rel="noopener">
                            <?php echo $dsLabel; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php foreach ($datasheetExtras as $df): ?>
                    <?php
                    $durl = trim($df['url'] ?? '');
                    if ($durl === '') {
                        continue;
                    }
                    $dlabel      = trim($df['label'] ?? '') ?: ($navLabels['download'] ?? 'Download');
                    $dTrackUrl   = buildDownloadTrackUrl($lang, $durl, $dlProductSlug, $dlabel);
                    $dFileName   = htmlspecialchars(rawurldecode(basename($durl)), ENT_QUOTES, 'UTF-8');
                    ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($dTrackUrl, ENT_QUOTES, 'UTF-8'); ?>"
                           class="datasheet-download-link"
                           data-file-name="<?php echo $dFileName; ?>"
                           data-product-slug="<?php echo htmlspecialchars($dlProductSlug, ENT_QUOTES, 'UTF-8'); ?>"
                           target="_blank" rel="noopener"><?php echo htmlspecialchars($dlabel, ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endforeach; ?>
                    <?php foreach ($downloadCenter as $dc): ?>
                    <?php
                    $dcUrl = trim((string) ($dc['url'] ?? ''));
                    if ($dcUrl === '') {
                        continue;
                    }
                    $dcLabel     = trim((string) ($dc['label'] ?? ''));
                    $dcTitle     = trim((string) ($dc['title'] ?? ''));
                    $dcLinkText  = $dcLabel !== '' ? $dcLabel : ($dcTitle !== '' ? $dcTitle : ($navLabels['download'] ?? 'Download'));
                    $dcTrackUrl  = buildDownloadTrackUrl($lang, $dcUrl, $dlProductSlug, $dcLinkText);
                    $dcFileName  = htmlspecialchars(rawurldecode(basename($dcUrl)), ENT_QUOTES, 'UTF-8');
                    ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($dcTrackUrl, ENT_QUOTES, 'UTF-8'); ?>"
                           class="datasheet-download-link"
                           data-file-name="<?php echo $dcFileName; ?>"
                           data-product-slug="<?php echo htmlspecialchars($dlProductSlug, ENT_QUOTES, 'UTF-8'); ?>"
                           target="_blank" rel="noopener"><?php echo htmlspecialchars($dcLinkText, ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($productFaqs)): ?>
        <section class="section product-module-faq" aria-labelledby="faq-module-title">
            <div class="container">
                <h2 id="faq-module-title" class="section-title"><?php echo $lang === 'cn' ? '常见问题' : ($lang === 'es' ? 'Preguntas frecuentes' : 'FAQ'); ?></h2>
                <div class="faq-accordion">
                    <?php foreach ($productFaqs as $fi => $faq): ?>
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
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($product['content'])): ?>
        <div class="product-detail-bottom">
            <div class="product-tabs" id="product-tabs">
                <button type="button" class="tab-btn active" onclick="switchProductTab('details', this)">
                    <?php echo $navLabels['details'] ?? 'Details'; ?>
                </button>
            </div>
            <div class="product-tab-content active" id="tab-details">
                <div class="product-rich-content">
                    <?php echo \App\Core\RichTextSanitizer::sanitize((string) ($product['content'] ?? '')); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($relatedProducts)): ?>
<section class="section section-related">
    <div class="container">
        <h2><?php echo $lang === 'cn' ? '相关产品' : ($lang === 'es' ? 'Productos relacionados' : 'Related products'); ?></h2>
        <div class="product-grid product-grid-compact">
            <?php foreach ($relatedProducts as $rp): ?>
            <a href="<?php echo View::langUrl($lang, 'product/' . ($rp['slug'] ?? '')); ?>" class="product-card">
                <div class="product-card-img">
                    <?php
                    $rim = '';
                    if (!empty($rp['images'][0]['url'])) {
                        $rim = $rp['images'][0]['url'];
                    } elseif (!empty($rp['image'])) {
                        $rim = $rp['image'];
                    }
                    ?>
                    <?php if ($rim !== ''): ?>
                    <?php echo View::responsiveImage($rim, $rp['name'] ?? '', ['loading' => 'lazy']); ?>
                    <?php else: ?>
                    <div class="product-card-placeholder"><span>&#9733;</span></div>
                    <?php endif; ?>
                </div>
                <div class="product-card-body">
                    <h3 class="product-card-name"><?php echo htmlspecialchars($rp['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($relatedPosts)): ?>
<section class="section section-related">
    <div class="container">
        <h2><?php echo $lang === 'cn' ? '相关文章' : ($lang === 'es' ? 'Artículos relacionados' : 'Related articles'); ?></h2>
        <ul class="related-links-list">
            <?php foreach ($relatedPosts as $rp): ?>
            <li><a href="<?php echo View::langUrl($lang, 'blog/' . ($rp['slug'] ?? '')); ?>"><?php echo htmlspecialchars($rp['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($contactData)): ?>
<section class="section section-cta">
    <div class="container">
        <div class="cta-content">
            <h2><?php echo $lang === 'cn' ? '需要更多信息？' : ($lang === 'es' ? '¿Necesita más información?' : 'Need More Information?'); ?></h2>
            <p><?php echo htmlspecialchars($contactData['subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            <a href="<?php echo View::langUrl($lang, 'contact'); ?>" class="btn btn-hero">
                <?php echo $navLabels['contact'] ?? 'Contact Us'; ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
var galleryImages = <?php echo json_encode($allImages, JSON_UNESCAPED_UNICODE); ?>;

function switchGalleryImage(index) {
    var img = galleryImages[index];
    if (!img || !img.url) return;
    var wrap = document.getElementById('gallery-main');
    if (!wrap) return;
    var mainImg = wrap.querySelector('img');
    if (mainImg) {
        mainImg.src = img.url;
        mainImg.alt = img.alt_text || '';
        mainImg.setAttribute('data-zoom', img.url);
    }
    document.querySelectorAll('.gallery-thumb').forEach(function(el, i) {
        el.classList.toggle('active', i === index);
    });
}

function switchProductTab(tabId, btn) {
    document.querySelectorAll('.product-tab-content').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelectorAll('.product-tabs .tab-btn').forEach(function(el) {
        el.classList.remove('active');
    });
    var tab = document.getElementById('tab-' + tabId);
    if (tab) tab.classList.add('active');
    if (btn) btn.classList.add('active');
}

(function() {
    var mod = document.getElementById('compare-module');
    if (!mod) return;
    var slug = mod.getAttribute('data-slug') || '';
    var lang = <?php echo json_encode($lang ?? 'en', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
    var KEY = 'stitch_compare_' + lang;
    var baseCompare = <?php echo json_encode(View::langUrl($lang ?? 'en', 'compare'), JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var lblList = <?php echo json_encode($lang === 'cn' ? '对比列表：' : ($lang === 'es' ? 'Lista: ' : 'In compare: '), JSON_HEX_TAG); ?>;
    function readList() {
        try {
            var raw = localStorage.getItem(KEY);
            var a = raw ? JSON.parse(raw) : [];
            return Array.isArray(a) ? a : [];
        } catch (e) { return []; }
    }
    function writeList(a) {
        var u = [];
        a.forEach(function(s) {
            s = (s || '').trim();
            if (s && u.indexOf(s) === -1) u.push(s);
        });
        localStorage.setItem(KEY, JSON.stringify(u.slice(0, 4)));
    }
    function refreshStatus() {
        var el = document.getElementById('compare-status');
        var link = document.getElementById('link-open-compare');
        if (!el || !link) return;
        var list = readList();
        link.href = baseCompare + (list.length ? ('?slugs=' + encodeURIComponent(list.join(','))) : '');
        el.textContent = lblList + (list.length ? list.join(', ') : '—');
    }
    var btn = document.getElementById('btn-add-compare');
    if (btn) {
        btn.addEventListener('click', function() {
            if (!slug) return;
            var list = readList();
            if (list.indexOf(slug) === -1) {
                list.push(slug);
            }
            writeList(list);
            refreshStatus();
        });
    }
    refreshStatus();
})();
</script>
