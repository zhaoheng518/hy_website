<?php
$langCode = (string) ($lang ?? 'en');
$body = [
    'en' => $ok ? 'You will no longer receive newsletter emails from us.' : 'If you need help, please contact us from the website.',
    'cn' => $ok ? '您将不再收到本站的订阅邮件。' : '如需帮助，请通过网站联系我们。',
    'es' => $ok ? 'Ya no recibirá correos del boletín.' : 'Si necesita ayuda, contáctenos desde el sitio web.',
];
$text = $body[$langCode] ?? $body['en'];
?>
<section class="page-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($h1 ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:560px;">
        <p class="<?php echo $ok ? 'form-message success' : 'form-message error'; ?>" style="display:block;padding:16px;border-radius:8px;">
            <?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <p style="margin-top:20px;">
            <a href="<?php echo htmlspecialchars(View::langUrl($langCode), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><?php echo $langCode === 'cn' ? '返回首页' : ($langCode === 'es' ? 'Volver al inicio' : 'Back to home'); ?></a>
        </p>
    </div>
</section>
