<?php

namespace App\Core;

/**
 * Brevo (Sendinblue) Transactional API 发送订阅类邮件。
 *
 * 在 site.json（或 Config）中配置：
 * - brevo_api_key：SMTP & API → API keys → v3 密钥
 * - brevo_sender_email：发件人邮箱（须在 Brevo 发件人域中验证）
 * - brevo_sender_name：可选，默认 site_name / smtp_from_name
 *
 * 能力：HTML + 纯文本、按订阅者语言追加退订说明与链接、按任务类型打标签（产品/博客/一般）。
 */
final class NewsletterMailer
{
    private const BREVO_ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    private const HTTP_TIMEOUT_SEC = 45;

    /**
     * @var array<string, array{0: string, 1: string}> [退订链接文案, 说明句]
     */
    private const FOOTER_I18N = [
        'en' => ['Unsubscribe', 'You are receiving this because you opted in to email updates from us.'],
        'cn' => ['退订', '您收到本邮件是因为您曾订阅我们的邮件通知。'],
        'es' => ['Darse de baja', 'Recibe este correo porque se suscribió a nuestras actualizaciones por email.'],
        'ru' => ['Отписаться', 'Вы получили это письмо, так как подписались на рассылку.'],
        'ar' => ['إلغاء الاشتراك', 'وصلتك هذه الرسالة لأنك اشتركت في التحديثات عبر البريد.'],
    ];

    public static function isConfigured(): bool
    {
        $s = self::resolveSettings();

        return $s['api_key'] !== ''
            && $s['sender_email'] !== ''
            && filter_var($s['sender_email'], FILTER_VALIDATE_EMAIL);
    }

    /**
     * 发送一封事务邮件（HTML + 纯文本），可选按语言插入退订页脚、按类型附加 Brevo tags。
     *
     * @param array{
     *   lang?: string,
     *   unsubscribe_url?: string,
     *   type?: string,
     *   force_unsubscribe_footer?: bool
     * } $options
     *   type: {@see NewsletterJobRepository::TYPE_PRODUCT_UPDATE} | blog_post | general
     */
    public static function send(
        string $to,
        string $subject,
        string $htmlContent,
        string $textContent,
        array $options = []
    ): bool {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = mb_substr(trim($subject), 0, 998, 'UTF-8');
        if ($subject === '') {
            return false;
        }

        if (!self::isConfigured()) {
            error_log('[NewsletterMailer] Brevo not configured (brevo_api_key / brevo_sender_email)');
            return false;
        }

        $lang = self::normalizeLang((string) ($options['lang'] ?? 'en'));
        $unsub = trim((string) ($options['unsubscribe_url'] ?? ''));
        $forceFooter = !empty($options['force_unsubscribe_footer']);
        $type = self::sanitizeMailType((string) ($options['type'] ?? NewsletterJobRepository::TYPE_GENERAL));

        [$htmlContent, $textContent] = self::appendUnsubscribeIfNeeded(
            $htmlContent,
            $textContent,
            $lang,
            $unsub,
            $forceFooter
        );

        $s = self::resolveSettings();
        $payload = [
            'sender' => [
                'name' => $s['sender_name'],
                'email' => $s['sender_email'],
            ],
            'to' => [['email' => $to]],
            'subject' => $subject,
            'htmlContent' => $htmlContent === '' ? '<p></p>' : $htmlContent,
        ];
        if (trim($textContent) !== '') {
            $payload['textContent'] = $textContent;
        }

        $tags = self::brevoTagsForType($type);
        if ($tags !== []) {
            $payload['tags'] = $tags;
        }

        $idem = trim((string) ($options['idempotency_key'] ?? ''));
        $result = self::postBrevo($s['api_key'], $payload, $idem !== '' ? $idem : null);
        if (!$result['ok']) {
            error_log('[NewsletterMailer] Brevo HTTP ' . ($result['status'] ?? 0) . ' ' . ($result['error'] ?? ''));
            return false;
        }

        return true;
    }

    public static function sendProductUpdate(
        string $to,
        string $subject,
        string $htmlContent,
        string $textContent,
        string $subscriberLang,
        string $unsubscribeUrl
    ): bool {
        return self::send($to, $subject, $htmlContent, $textContent, [
            'lang' => $subscriberLang,
            'unsubscribe_url' => $unsubscribeUrl,
            'type' => NewsletterJobRepository::TYPE_PRODUCT_UPDATE,
        ]);
    }

    public static function sendBlogUpdate(
        string $to,
        string $subject,
        string $htmlContent,
        string $textContent,
        string $subscriberLang,
        string $unsubscribeUrl
    ): bool {
        return self::send($to, $subject, $htmlContent, $textContent, [
            'lang' => $subscriberLang,
            'unsubscribe_url' => $unsubscribeUrl,
            'type' => NewsletterJobRepository::TYPE_BLOG_POST,
        ]);
    }

    public static function sendGeneral(
        string $to,
        string $subject,
        string $htmlContent,
        string $textContent,
        string $subscriberLang,
        string $unsubscribeUrl
    ): bool {
        return self::send($to, $subject, $htmlContent, $textContent, [
            'lang' => $subscriberLang,
            'unsubscribe_url' => $unsubscribeUrl,
            'type' => NewsletterJobRepository::TYPE_GENERAL,
        ]);
    }

    /**
     * 与队列任务类型一致的分发入口（便于 worker 调用）。
     *
     * @param array<string, mixed> $job newsletter_jobs 一行（含 type、content_html、content_text、subscriber 侧语言与 token 需由调用方传入）
     * @param array{email: string, lang: string, unsubscribe_token: string} $subscriber
     */
    public static function sendForQueuedJob(array $job, array $subscriber): bool
    {
        return self::sendForQueuedJobWithResult($job, $subscriber)['ok'];
    }

    /**
     * @param array<string, mixed> $job
     * @param array{email: string, lang: string, unsubscribe_token: string} $subscriber
     * @return array{ok: bool, message_id?: string|null, error?: string|null}
     */
    public static function sendForQueuedJobWithResult(array $job, array $subscriber): array
    {
        $to = trim((string) ($subscriber['email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message_id' => null, 'error' => 'invalid recipient'];
        }

        $lang = (string) ($subscriber['lang'] ?? 'en');
        $token = (string) ($subscriber['unsubscribe_token'] ?? '');
        $unsub = self::buildUnsubscribeUrl($lang, $token);

        $subject = mb_substr(trim((string) ($job['subject'] ?? '')), 0, 998, 'UTF-8');
        $html = (string) ($job['content_html'] ?? '');
        $text = (string) ($job['content_text'] ?? '');
        $type = self::sanitizeMailType((string) ($job['type'] ?? NewsletterJobRepository::TYPE_GENERAL));

        if ($subject === '') {
            return ['ok' => false, 'message_id' => null, 'error' => 'empty subject'];
        }

        if (!self::isConfigured()) {
            error_log('[NewsletterMailer] Brevo not configured (brevo_api_key / brevo_sender_email)');

            return ['ok' => false, 'message_id' => null, 'error' => 'not configured'];
        }

        [$html, $text] = self::appendUnsubscribeIfNeeded($html, $text, $lang, $unsub, false);

        $s = self::resolveSettings();
        $payload = [
            'sender' => [
                'name' => $s['sender_name'],
                'email' => $s['sender_email'],
            ],
            'to' => [['email' => $to]],
            'subject' => $subject,
            'htmlContent' => $html === '' ? '<p></p>' : $html,
        ];
        if (trim($text) !== '') {
            $payload['textContent'] = $text;
        }

        $tags = self::brevoTagsForType($type);
        if ($tags !== []) {
            $payload['tags'] = $tags;
        }

        $jobId = (int) ($job['id'] ?? 0);
        $retry = (int) ($job['retry_count'] ?? 0);
        $idem = 'newsletter-job-' . $jobId . '-rc' . $retry;

        $result = self::postBrevo($s['api_key'], $payload, $idem);
        if (!$result['ok']) {
            error_log('[NewsletterMailer] Brevo HTTP ' . ($result['status'] ?? 0) . ' ' . ($result['error'] ?? ''));

            return ['ok' => false, 'message_id' => null, 'error' => (string) ($result['error'] ?? 'send failed')];
        }

        return ['ok' => true, 'message_id' => $result['message_id'] ?? null, 'error' => null];
    }

    public static function buildUnsubscribeUrl(string $lang, string $token): string
    {
        $base = rtrim((string) Config::get('site_url', ''), '/');
        $lang = self::normalizeLang($lang);
        $token = preg_replace('/[^a-f0-9]/i', '', $token);
        if ($base === '' || strlen($token) !== 64) {
            return '';
        }

        return $base . '/' . rawurlencode($lang) . '/newsletter/unsubscribe/' . $token;
    }

    /**
     * @return array{0: string, 1: string} [html, text]
     */
    public static function appendUnsubscribeIfNeeded(
        string $html,
        string $text,
        string $lang,
        string $unsubscribeUrl,
        bool $force = false
    ): array {
        $unsubscribeUrl = trim($unsubscribeUrl);
        if ($unsubscribeUrl === '') {
            return [$html, $text];
        }

        $lang = self::normalizeLang($lang);
        [$unlinkLabel, $disclaimer] = self::footerCopy($lang);

        $already = self::bodyContainsUnsubscribeHint($html, $text, $unsubscribeUrl);
        if ($already && !$force) {
            return [$html, $text];
        }

        $safeUrl = htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($unlinkLabel, ENT_QUOTES, 'UTF-8');
        $safeDisc = htmlspecialchars($disclaimer, ENT_QUOTES, 'UTF-8');

        $htmlBlock = '<p style="font-size:12px;color:#666;margin-top:24px;border-top:1px solid #eee;padding-top:12px;">'
            . $safeDisc
            . ' <a href="' . $safeUrl . '">' . $safeLabel . '</a></p>';
        $htmlOut = self::injectHtmlBeforeBodyClose($html, $htmlBlock);

        $textOut = rtrim($text) . "\n\n---\n" . $disclaimer . "\n" . $unlinkLabel . ': ' . $unsubscribeUrl . "\n";

        return [$htmlOut, $textOut];
    }

    private static function injectHtmlBeforeBodyClose(string $html, string $inject): string
    {
        if (stripos($html, '</body>') !== false) {
            $replaced = preg_replace('/<\/body>/i', $inject . '</body>', $html, 1);

            return is_string($replaced) ? $replaced : $html . $inject;
        }

        return $html . $inject;
    }

    /**
     * @return array{api_key: string, sender_email: string, sender_name: string}
     */
    private static function resolveSettings(): array
    {
        $site = [];
        if (class_exists(JsonStore::class)) {
            $read = JsonStore::globalData('site')->read();
            if (is_array($read)) {
                $site = $read;
            }
        }

        $apiKey = trim((string) ($site['brevo_api_key'] ?? Config::get('brevo_api_key', '')));
        $senderEmail = trim((string) ($site['brevo_sender_email'] ?? $site['smtp_from'] ?? Config::get('brevo_sender_email', Config::get('smtp_from', ''))));
        $senderName = trim((string) ($site['brevo_sender_name'] ?? $site['smtp_from_name'] ?? Config::get('brevo_sender_name', Config::get('smtp_from_name', (string) Config::get('site_name', 'Site')))));

        if ($senderName === '') {
            $senderName = (string) Config::get('site_name', 'Site');
        }

        return [
            'api_key' => $apiKey,
            'sender_email' => $senderEmail,
            'sender_name' => $senderName,
        ];
    }

    private static function normalizeLang(string $lang): string
    {
        $lang = strtolower(preg_replace('/[^a-z0-9_-]/i', '', trim($lang)) ?: 'en');
        if ($lang === '') {
            return 'en';
        }
        if (strlen($lang) > 8) {
            return substr($lang, 0, 8);
        }

        return $lang;
    }

    /** @return array{0: string, 1: string} */
    private static function footerCopy(string $lang): array
    {
        $short = strtolower(substr($lang, 0, 2));
        if ($short === 'zh') {
            $short = 'cn';
        }
        $row = self::FOOTER_I18N[$short] ?? self::FOOTER_I18N['en'];

        return [$row[0], $row[1]];
    }

    private static function bodyContainsUnsubscribeHint(string $html, string $text, string $unsubscribeUrl): bool
    {
        if (stripos($html, 'newsletter/unsubscribe') !== false || stripos($text, 'newsletter/unsubscribe') !== false) {
            return true;
        }
        if (stripos($html, $unsubscribeUrl) !== false || stripos($text, $unsubscribeUrl) !== false) {
            return true;
        }
        $esc = htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8');
        if ($esc !== $unsubscribeUrl && stripos($html, $esc) !== false) {
            return true;
        }

        return false;
    }

    private static function sanitizeMailType(string $type): string
    {
        $allowed = [
            NewsletterJobRepository::TYPE_PRODUCT_UPDATE,
            NewsletterJobRepository::TYPE_BLOG_POST,
            NewsletterJobRepository::TYPE_GENERAL,
        ];
        $type = preg_replace('/[^a-z0-9_\-]/i', '', strtolower(trim($type))) ?? '';
        if (!in_array($type, $allowed, true)) {
            return NewsletterJobRepository::TYPE_GENERAL;
        }

        return $type;
    }

    /** @return list<string> */
    private static function brevoTagsForType(string $type): array
    {
        return match ($type) {
            NewsletterJobRepository::TYPE_PRODUCT_UPDATE => ['newsletter', 'product_update'],
            NewsletterJobRepository::TYPE_BLOG_POST => ['newsletter', 'blog_post'],
            default => ['newsletter', 'general'],
        };
    }

    /**
     * @return array{ok: bool, status?: int, error?: string, message_id?: string|null}
     */
    private static function postBrevo(string $apiKey, array $payload, ?string $idempotencyKey = null): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return ['ok' => false, 'error' => 'json_encode failed'];
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $apiKey,
        ];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . preg_replace('/[^\x20-\x7E]/', '', substr($idempotencyKey, 0, 120));
        }

        if (function_exists('curl_init')) {
            $ch = curl_init(self::BREVO_ENDPOINT);
            if ($ch === false) {
                return ['ok' => false, 'error' => 'curl_init failed'];
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_TIMEOUT => self::HTTP_TIMEOUT_SEC,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            curl_close($ch);
            if ($body === false) {
                return ['ok' => false, 'status' => $status, 'error' => $cerr];
            }
            if ($status >= 200 && $status < 300) {
                return ['ok' => true, 'status' => $status, 'message_id' => self::parseBrevoMessageId((string) $body)];
            }

            return ['ok' => false, 'status' => $status, 'error' => mb_substr((string) $body, 0, 500, 'UTF-8')];
        }

        $headerBlock = implode("\r\n", $headers);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerBlock . "\r\n",
                'content' => $json,
                'timeout' => self::HTTP_TIMEOUT_SEC,
            ],
        ]);
        $body = @file_get_contents(self::BREVO_ENDPOINT, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        if ($body !== false && $status >= 200 && $status < 300) {
            return ['ok' => true, 'status' => $status, 'message_id' => self::parseBrevoMessageId((string) $body)];
        }

        return ['ok' => false, 'status' => $status, 'error' => $body !== false ? mb_substr((string) $body, 0, 500, 'UTF-8') : 'request failed'];
    }

    private static function parseBrevoMessageId(string $body): ?string
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }
        $mid = $data['messageId'] ?? $data['message_id'] ?? null;

        return is_string($mid) && $mid !== '' ? mb_substr($mid, 0, 128, 'UTF-8') : null;
    }
}
