<?php
$langCode = (string) ($lang ?? 'en');
$labels = [
    'en' => [
        'email' => 'Email address',
        'np' => 'Product updates',
        'nb' => 'Blog updates',
        'ng' => 'General announcements',
        'submit' => 'Subscribe',
        'privacy' => 'We only use your email for these notifications. You can unsubscribe anytime.',
    ],
    'cn' => [
        'email' => '邮箱地址',
        'np' => '产品更新',
        'nb' => '博客更新',
        'ng' => '一般通知',
        'submit' => '订阅',
        'privacy' => '我们仅将您的邮箱用于上述通知，可随时退订。',
    ],
    'es' => [
        'email' => 'Correo electrónico',
        'np' => 'Novedades de productos',
        'nb' => 'Novedades del blog',
        'ng' => 'Avisos generales',
        'submit' => 'Suscribirse',
        'privacy' => 'Solo usaremos su correo para estas notificaciones. Puede darse de baja en cualquier momento.',
    ],
];
$L = $labels[$langCode] ?? $labels['en'];
?>
<section class="page-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($h1 ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:560px;">
        <?php if (!empty($message)): ?>
        <div class="form-message <?php echo ($messageType ?? '') === 'success' ? 'success' : 'error'; ?>" style="margin-bottom:20px;">
            <?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>

        <p class="catalog-empty" style="margin-bottom:24px;text-align:left;"><?php echo htmlspecialchars($intro ?? '', ENT_QUOTES, 'UTF-8'); ?></p>

        <form method="post" action="<?php echo htmlspecialchars(View::langUrl($langCode, 'newsletter/submit'), ENT_QUOTES, 'UTF-8'); ?>" class="inquiry-form">
            <?php echo \App\Core\Auth::csrfField(); ?>
            <div style="position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;">
                <label for="website_url">Website</label>
                <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="nl_email"><?php echo htmlspecialchars($L['email'], ENT_QUOTES, 'UTF-8'); ?> *</label>
                <input type="email" id="nl_email" name="email" required maxlength="200" autocomplete="email">
            </div>

            <div class="form-group form-checkbox">
                <label style="font-weight:normal;display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="notify_product" value="1" checked>
                    <?php echo htmlspecialchars($L['np'], ENT_QUOTES, 'UTF-8'); ?>
                </label>
            </div>
            <div class="form-group form-checkbox">
                <label style="font-weight:normal;display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="notify_blog" value="1" checked>
                    <?php echo htmlspecialchars($L['nb'], ENT_QUOTES, 'UTF-8'); ?>
                </label>
            </div>
            <div class="form-group form-checkbox">
                <label style="font-weight:normal;display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="notify_general" value="1" checked>
                    <?php echo htmlspecialchars($L['ng'], ENT_QUOTES, 'UTF-8'); ?>
                </label>
            </div>

            <p style="font-size:13px;color:var(--c-text-light, #666);margin-bottom:16px;"><?php echo htmlspecialchars($L['privacy'], ENT_QUOTES, 'UTF-8'); ?></p>

            <button type="submit" class="btn btn-primary btn-block"><?php echo htmlspecialchars($L['submit'], ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
    </div>
</section>
