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

// DataLayer push for contact page (no PII)
$_dl_contact = [
    'page_type' => 'contact',
    'page_lang' => (string) ($lang ?? 'en'),
];
?>
<script>
window.dataLayer = window.dataLayer || [];
window.dataLayer.push(<?php echo json_encode($_dl_contact, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>);
</script>

<section class="page-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($contactData['title'] ?? 'Contact Us', ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="contact-grid">
            <div class="contact-info">
                <h2><?php echo htmlspecialchars($contactData['subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                <?php if (!empty($contactData['email'])): ?>
                <div class="contact-item">
                    <strong>Email:</strong>
                    <a href="mailto:<?php echo htmlspecialchars($contactData['email'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($contactData['email'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </div>
                <?php endif; ?>
                <?php if (!empty($contactData['phone'])): ?>
                <div class="contact-item">
                    <strong><?php echo $lang === 'cn' ? '电话' : ($lang === 'es' ? 'Teléfono' : 'Phone'); ?>:</strong>
                    <a href="tel:<?php echo htmlspecialchars($contactData['phone'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($contactData['phone'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </div>
                <?php endif; ?>
                <?php if (!empty($contactData['whatsapp'])): ?>
                <div class="contact-item">
                    <strong>WhatsApp:</strong>
                    <a href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/[^0-9]/', '', $contactData['whatsapp']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                        <?php echo htmlspecialchars($contactData['whatsapp'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </div>
                <?php endif; ?>
                <?php if (!empty($contactData['address'])): ?>
                <div class="contact-item">
                    <strong><?php echo $lang === 'cn' ? '地址' : ($lang === 'es' ? 'Dirección' : 'Address'); ?>:</strong>
                    <?php echo htmlspecialchars($contactData['address'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="contact-form-wrap">
                <?php if (!empty($formMessage)): ?>
                <div class="form-message <?php echo $formMessageType === 'success' ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($formMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>

                <?php require __DIR__ . '/product_inquiry.php'; ?>
            </div>
        </div>
    </div>
</section>
