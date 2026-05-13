<?php

namespace App\Core;

/**
 * InquiryAutoReply — sends a confirmation email to the customer after an inquiry is saved.
 *
 * Design contracts:
 *  - ALL failures are logged only; they NEVER bubble up to the caller.
 *  - Config read from site.json via JsonStore (same source as Mailer).
 *  - Product name resolved from DB (product_translations) → product_source field → slug titlecase.
 *  - Supported template langs: en / cn / es; ru / ar fall back to en.
 *  - Admin can override subject & body per language in site.json (auto_reply_subject_*, auto_reply_body_*).
 *  - When no admin override, renders PHP template from app/views/email/inquiry_auto_reply_{lang}.php.
 *  - Feature is OFF by default (auto_reply_enabled = false).
 */
class InquiryAutoReply
{
    private const TEMPLATE_LANGS = ['en', 'cn', 'es'];
    private const LOG_FILE       = '/.auto_reply_mail.log';

    /**
     * Entry point — call this after an inquiry has been persisted.
     * Returns true on success, false on skip / failure (failure is also logged).
     */
    public static function send(array $inquiry): bool
    {
        $site = JsonStore::globalData('site')->read();
        if (!is_array($site)) {
            $site = [];
        }

        // Feature switch — default OFF; admin must explicitly enable in settings
        if (empty($site['auto_reply_enabled'])) {
            return false;
        }

        $to = trim((string) ($inquiry['email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::log('skip', 'invalid or empty customer email', $inquiry['id'] ?? '');
            return false;
        }

        $inquiryId   = (string) ($inquiry['id'] ?? '');
        $lang        = self::resolveLang((string) ($inquiry['lang'] ?? 'en'));
        $customerName = htmlspecialchars((string) ($inquiry['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $productName  = self::resolveProductName(
            (string) ($inquiry['product_slug'] ?? ''),
            (string) ($inquiry['product_source'] ?? ''),
            $lang
        );

        $subject = self::buildSubject($site, $lang, $customerName, $productName);
        $body    = self::buildBody($site, $lang, $customerName, $productName, $inquiryId);

        $mailer = new Mailer();
        try {
            $ok = $mailer->send($to, $subject, $body);
        } catch (\Throwable $e) {
            self::log('exception', $e->getMessage(), $inquiryId);
            return false;
        }

        if (!$ok) {
            self::log('send_failed', 'Mailer::send() returned false', $inquiryId);
            return false;
        }

        self::log('sent', 'auto-reply delivered to ' . $to, $inquiryId);
        return true;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Map unsupported / unknown langs to a supported template lang.
     * ru / ar → en fallback.
     */
    private static function resolveLang(string $lang): string
    {
        return in_array($lang, self::TEMPLATE_LANGS, true) ? $lang : 'en';
    }

    /**
     * Resolve a human-readable product name.
     * Priority: DB translated name → product_source field → slug titlecase → empty string.
     */
    private static function resolveProductName(string $slug, string $source, string $lang): string
    {
        if ($slug !== '') {
            try {
                $db  = Database::getInstance();
                $row = $db->fetch(
                    "SELECT pt.name
                     FROM products p
                     LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.lang = :lang
                     WHERE p.slug = :slug
                     LIMIT 1",
                    ['slug' => $slug, 'lang' => $lang]
                );
                if (is_array($row) && isset($row['name']) && (string) $row['name'] !== '') {
                    return htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8');
                }
            } catch (\Throwable $e) {
                // DB unavailable — continue to fallbacks
                error_log('[InquiryAutoReply] product name lookup failed: ' . $e->getMessage());
            }
        }

        if ($source !== '') {
            return htmlspecialchars($source, ENT_QUOTES, 'UTF-8');
        }

        if ($slug !== '') {
            return htmlspecialchars(ucwords(str_replace('-', ' ', $slug)), ENT_QUOTES, 'UTF-8');
        }

        return '';
    }

    private static function buildSubject(array $site, string $lang, string $customerName, string $productName): string
    {
        $key    = 'auto_reply_subject_' . $lang;
        $custom = trim((string) ($site[$key] ?? ''));
        if ($custom !== '') {
            return self::replaceVars($custom, $customerName, $productName, '', $site);
        }

        $siteName = (string) ($site['site_name'] ?? 'Us');
        $defaults = [
            'en' => "We received your inquiry – {$siteName}",
            'cn' => "我们已收到您的询盘 – {$siteName}",
            'es' => "Hemos recibido su consulta – {$siteName}",
        ];
        return $defaults[$lang] ?? $defaults['en'];
    }

    private static function buildBody(
        array  $site,
        string $lang,
        string $customerName,
        string $productName,
        string $inquiryId
    ): string {
        // 1) Admin custom body
        $key    = 'auto_reply_body_' . $lang;
        $custom = trim((string) ($site[$key] ?? ''));
        if ($custom !== '') {
            return self::replaceVars($custom, $customerName, $productName, $inquiryId, $site);
        }

        // 2) Built-in PHP template file
        $templateFile = self::templatePath($lang);
        if (is_file($templateFile)) {
            return self::renderTemplate($templateFile, [
                'customer_name' => $customerName,
                'product_name'  => $productName,
                'inquiry_id'    => $inquiryId,
                'site_name'     => htmlspecialchars((string) ($site['site_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'site_url'      => htmlspecialchars((string) ($site['site_url']  ?? ''), ENT_QUOTES, 'UTF-8'),
            ]);
        }

        // 3) Hard-coded last-resort fallback (no file dependency)
        return self::plainFallback($lang, $customerName, $site);
    }

    private static function templatePath(string $lang): string
    {
        $base = defined('APP_PATH')
            ? APP_PATH
            : dirname(__DIR__);
        return $base . '/views/email/inquiry_auto_reply_' . $lang . '.php';
    }

    /** Render a PHP template file with extracted variables — output-buffer safe. */
    private static function renderTemplate(string $file, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    /**
     * Replace supported placeholders in a custom admin template string.
     * Supported: {customer_name} {product_name} {inquiry_id} {site_name} {site_url}
     */
    private static function replaceVars(
        string $tpl,
        string $customerName,
        string $productName,
        string $inquiryId,
        array  $config
    ): string {
        $siteName = htmlspecialchars((string) ($config['site_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $siteUrl  = htmlspecialchars((string) ($config['site_url']  ?? ''), ENT_QUOTES, 'UTF-8');

        return str_replace(
            ['{customer_name}', '{product_name}', '{inquiry_id}', '{site_name}', '{site_url}'],
            [$customerName,     $productName,      $inquiryId,     $siteName,     $siteUrl],
            $tpl
        );
    }

    /** Minimal inline HTML fallback used when template file is missing. */
    private static function plainFallback(string $lang, string $customerName, array $config): string
    {
        $siteName = htmlspecialchars((string) ($config['site_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $siteUrl  = htmlspecialchars((string) ($config['site_url']  ?? ''), ENT_QUOTES, 'UTF-8');

        $bodies = [
            'en' => "<p>Dear {$customerName},</p>"
                  . "<p>Thank you for your inquiry. We have received your message and will get back to you as soon as possible.</p>"
                  . "<p>Best regards,<br><strong>{$siteName}</strong></p>",
            'cn' => "<p>尊敬的 {$customerName}，</p>"
                  . "<p>感谢您的询盘！我们已收到您的消息，将尽快与您取得联系。</p>"
                  . "<p>此致，<br><strong>{$siteName}</strong></p>",
            'es' => "<p>Estimado/a {$customerName},</p>"
                  . "<p>Gracias por su consulta. Hemos recibido su mensaje y nos pondremos en contacto a la brevedad.</p>"
                  . "<p>Atentamente,<br><strong>{$siteName}</strong></p>",
        ];

        $body = $bodies[$lang] ?? $bodies['en'];
        $footerLink = $siteUrl !== '' ? "<a href=\"{$siteUrl}\" style=\"color:#1a56db;\">{$siteUrl}</a>" : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto;padding:20px;">
{$body}
<hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
<p style="font-size:12px;color:#999;">{$footerLink}</p>
</body>
</html>
HTML;
    }

    /** Append one JSON line to the auto-reply log file. */
    private static function log(string $event, string $detail, string $inquiryId): void
    {
        $line = json_encode([
            'timestamp'  => date('c'),
            'event'      => $event,
            'inquiry_id' => $inquiryId,
            'detail'     => $detail,
        ], JSON_UNESCAPED_UNICODE) . "\n";

        if (defined('DATA_PATH') && is_dir(DATA_PATH)) {
            @file_put_contents(DATA_PATH . self::LOG_FILE, $line, FILE_APPEND | LOCK_EX);
        } else {
            error_log('[InquiryAutoReply] ' . $event . ' | inquiry=' . $inquiryId . ' | ' . $detail);
        }
    }
}
