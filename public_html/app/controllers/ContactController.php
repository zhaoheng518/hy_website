<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ClientIp;
use App\Core\Config;
use App\Core\InquiryAutoReply;
use App\Core\InquiryRepository;
use App\Core\JsonStore;
use App\Core\Mailer;
use App\Core\NewsletterRepository;
use App\Core\RateLimiter;
use App\Core\SEO;
use App\Core\SpamProtection;
use App\Core\View;

class ContactController extends BaseController
{
    /** @var list<string> */
    private const HONEYPOT_FIELDS = ['website_url', 'website'];
    private const CAPTCHA_TIMEOUT = 300;
    private const INQUIRY_THROTTLE = 60;
    private const SPAM_KEYWORD_LOG = '.inquiry_spam_keywords.log';

    /**
     * Substrings (case-insensitive, UTF-8) — if any appear in name / company / message, reject inquiry.
     *
     * @var list<string>
     */
    private const INQUIRY_SPAM_KEYWORDS = [
        '<script', '</script>', 'javascript:', 'onerror=', 'onclick=', 'onload=', '<iframe', 'data:text/html',
        'viagra', 'cialis', 'kamagra', 'levitra', 'xanax', 'tramadol', 'oxycodone',
        'online casino', 'casino bonus', 'slot machine', 'sports betting', 'poker online',
        'no credit check', 'payday loan', 'instant loan approval',
        'recover your crypto', 'bitcoin investment', 'send btc', 'double your crypto',
        'buy backlinks', 'cheap seo services', 'guest post service', 'link building service',
        'live cam', 'hookup tonight',
        'cryptocurrency giveaway', 'western union fee',
        '代开发票', '加微信', '网赌', '博彩', '刷单', '网贷', '推广引流', '棋牌', '老虎机',
    ];

    public function index(): void
    {
        $contactData = $this->getLangData('contact');

        $seoHead = $this->seo->renderMeta('contact');
        $breadcrumbs = $this->seo->renderBreadcrumbs([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('contact')],
        ]);
        $breadcrumbsSchema = $this->seo->renderBreadcrumbsSchema([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('contact'), 'url' => View::langUrl($this->lang, 'contact')],
        ]);

        $useTurnstile = $this->turnstileIsEnabled();
        $captcha = $useTurnstile ? ['question' => '', 'hash' => ''] : $this->generateCaptcha();

        $localBusinessSchema = $this->seo->renderLocalBusinessSchema($contactData);

        $formMessage = $_SESSION['form_message'] ?? null;
        $formMessageType = $_SESSION['form_message_type'] ?? '';
        $formData = $_SESSION['form_data'] ?? [];
        unset($_SESSION['form_message'], $_SESSION['form_message_type'], $_SESSION['form_data']);

        $this->view->render('contact', [
            'contactData' => $contactData,
            'seoHead' => $seoHead,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbsSchema' => $breadcrumbsSchema,
            'localBusinessSchema' => $localBusinessSchema,
            'captcha' => $captcha,
            'useTurnstile' => $useTurnstile,
            'turnstileSiteKey' => trim((string) Config::get('turnstile_site_key', '')),
            'inquiryFormAction' => '/' . $this->lang . '/contact/submit',
            'productSlugPrefill' => '',
            'h1' => $contactData['title'] ?? $this->t('contact'),
            'formMessage' => $formMessage,
            'formMessageType' => $formMessageType,
            'formData' => $formData,
        ]);
    }

    public function submit(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/' . $this->lang . '/contact');
        }

        $csrf = $this->getPost('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $this->setFormMessage($this->tMsg('csrf_error'), 'error');
            $this->redirect('/' . $this->lang . '/contact');
        }

        if ($this->honeypotFilled()) {
            $this->setFormMessage($this->tMsg('honeypot_reject'), 'error');
            $this->redirect('/' . $this->lang . '/contact');
        }

        if ($this->turnstileIsEnabled()) {
            if (!$this->verifyTurnstileToken(trim($this->getPost('cf-turnstile-response', '')))) {
                $this->setFormMessage($this->tMsg('turnstile_error'), 'error');
                $this->saveFormData();
                $this->redirect('/' . $this->lang . '/contact');
            }
        } elseif (!$this->validateCaptcha()) {
            $this->setFormMessage($this->tMsg('captcha_error'), 'error');
            $this->saveFormData();
            $this->redirect('/' . $this->lang . '/contact');
        }

        if ($this->isThrottled()) {
            $this->setFormMessage($this->tMsg('throttle_error'), 'error');
            $this->redirect('/' . $this->lang . '/contact');
        }

        $name = $this->sanitizeString(trim($this->getPost('name', '')));
        $email = trim($this->getPost('email', ''));
        $company = $this->sanitizeString(trim($this->getPost('company', '')));
        $phone = $this->sanitizeString(trim($this->getPost('phone', '')));
        $productSource = $this->sanitizeString(trim($this->getPost('product_source', '')));
        $productSlug = trim($this->getPost('product_slug', ''));
        if ($productSlug === '' && $productSource !== '') {
            $productSlug = preg_replace('/[^a-z0-9\-]/i', '', strtolower(str_replace(' ', '-', $productSource)));
        }
        $country = $this->sanitizeString(trim($this->getPost('country', '')));
        $sourceUrl = trim($this->getPost('source_url', ''));
        $message = $this->sanitizeString(trim($this->getPost('message', '')));

        // UTM attribution fields — JS-populated hidden inputs; sanitized server-side (no HTML, max length)
        $utmSource   = $this->sanitizeUtmValue($this->getPost('utm_source',   ''));
        $utmMedium   = $this->sanitizeUtmValue($this->getPost('utm_medium',   ''));
        $utmCampaign = $this->sanitizeUtmValue($this->getPost('utm_campaign', ''));
        $utmTerm     = $this->sanitizeUtmValue($this->getPost('utm_term',     ''));
        $utmContent  = $this->sanitizeUtmValue($this->getPost('utm_content',  ''));
        // landing_page: JS sessionStorage → hidden field; fallback empty
        $landingPage = $this->sanitizeUrl(trim($this->getPost('attr_landing_page', '')));
        // referrer: JS document.referrer → hidden field preferred (CDN-safe);
        //           server-side HTTP_REFERER as fallback (rule: never the *only* source)
        $jsReferrer     = $this->sanitizeUrl(trim($this->getPost('attr_referrer', '')));
        $serverReferrer = isset($_SERVER['HTTP_REFERER']) ? $this->sanitizeUrl((string) $_SERVER['HTTP_REFERER']) : '';
        $referrer       = $jsReferrer !== '' ? $jsReferrer : $serverReferrer;

        $error = $this->validateRequired(
            ['name' => $name, 'email' => $email, 'message' => $message],
            ['name', 'email', 'message']
        );
        if ($error) {
            $this->setFormMessage($error, 'error');
            $this->saveFormData();
            $this->redirect('/' . $this->lang . '/contact');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->setFormMessage($this->tMsg('email_error'), 'error');
            $this->saveFormData();
            $this->redirect('/' . $this->lang . '/contact');
        }

        if (mb_strlen($message) < 10) {
            $this->setFormMessage($this->tMsg('message_short'), 'error');
            $this->saveFormData();
            $this->redirect('/' . $this->lang . '/contact');
        }

        $spamKw = $this->findSpamKeywordHit($name, $company, $message);
        if ($spamKw !== null) {
            $this->logSpamKeywordHit($spamKw, $name, $company, $message);
            $this->setFormMessage($this->tMsg('spam_keyword_reject'), 'error');
            $this->saveFormData();
            $this->redirect('/' . $this->lang . '/contact');
        }

        $clientIp = $this->getClientIp();
        $ipqs = SpamProtection::evaluateInquiryClient(
            $clientIp,
            isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null,
            isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null
        );
        if (empty($ipqs['allowed'])) {
            $this->setFormMessage($this->tMsg('ip_reputation_reject'), 'error');
            $this->saveFormData();
            $this->redirect('/' . $this->lang . '/contact');
        }

        if (!RateLimiter::checkInquirySubmit($clientIp, $email)) {
            $this->setFormMessage($this->tMsg('too_many_requests'), 'error');
            $this->saveFormData();
            $this->redirect('/' . $this->lang . '/contact');
        }

        $inquiry = [
            'id' => uniqid('inq_', true),
            'name' => $name,
            'email' => $email,
            'company' => $company,
            'phone' => $phone,
            'country' => $country,
            'product_slug' => $productSlug,
            'product_source' => $productSource,
            'source_url' => $sourceUrl,
            'utm_source'   => $utmSource,
            'utm_medium'   => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_term'     => $utmTerm,
            'utm_content'  => $utmContent,
            'referrer'     => $referrer,
            'landing_page' => $landingPage,
            'message' => $message,
            'lang' => $this->lang,
            'ip' => $clientIp,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
            'read' => false,
            'read_at' => null,
            'status' => 'new',
        ];

        $saved = false;
        if (InquiryRepository::isAvailable()) {
            try {
                $saved = InquiryRepository::createFromContactForm($inquiry) > 0;
            } catch (\Throwable $e) {
                error_log('[ContactController] inquiry DB save: ' . $e->getMessage());
            }
        }
        if (!$saved) {
            $store = JsonStore::globalData('inquiries');
            $ok = $store->update(function ($inquiries) use ($inquiry) {
                if (!is_array($inquiries)) {
                    $inquiries = [];
                }
                $inquiries[] = $inquiry;

                return $inquiries;
            });
            if (!$ok) {
                $this->setFormMessage($this->tMsg('save_error'), 'error');
                $this->saveFormData();
                $this->redirect('/' . $this->lang . '/contact');
            }
        }

        RateLimiter::recordInquirySubmit($clientIp, $email);

        $this->setThrottle();

        if ($this->getPost('newsletter_opt_out', '') !== '1') {
            try {
                NewsletterRepository::subscribeFromInquiry($email, $this->lang, $productSlug);
            } catch (\Throwable $e) {
                error_log('[ContactController] newsletter subscribe: ' . $e->getMessage());
            }
        }

        try {
            $mailResult = Mailer::sendInquiryNotification($inquiry);
            if (!$mailResult) {
                error_log("Failed to send inquiry notification email for ID: {$inquiry['id']}");
            }
        } catch (\Throwable $e) {
            error_log("Inquiry mail exception for ID {$inquiry['id']}: " . $e->getMessage());
        }

        // Auto-reply to customer — runs after admin notification, failure never affects user flow
        try {
            InquiryAutoReply::send($inquiry);
        } catch (\Throwable $e) {
            error_log('[ContactController] auto_reply exception for ID ' . $inquiry['id'] . ': ' . $e->getMessage());
        }

        $this->setFormMessage($this->tMsg('success'), 'success');
        $this->redirect('/' . $this->lang . '/contact');
    }

    /**
     * Honeypot fields must stay empty; any value means bot — log, do not persist inquiry.
     */
    private function honeypotFilled(): bool
    {
        foreach (self::HONEYPOT_FIELDS as $field) {
            $raw = trim((string) $this->getPost($field, ''));
            if ($raw !== '') {
                $this->logSpamHoneypot($field, $raw);
                return true;
            }
        }

        return false;
    }

    private function logSpamHoneypot(string $fieldName, string $rawValue): void
    {
        if (!defined('DATA_PATH') || !is_dir(DATA_PATH)) {
            return;
        }

        $line = json_encode([
            'timestamp' => date('c'),
            'event' => 'honeypot_spam',
            'field' => $fieldName,
            'value_length' => strlen($rawValue),
            'ip' => $this->getClientIp(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
            'lang' => $this->lang,
            'request_uri' => substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 500),
        ], JSON_UNESCAPED_UNICODE) . "\n";

        @file_put_contents(DATA_PATH . '/.inquiry_spam.log', $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * @return array{field: string, keyword: string}|null
     */
    private function findSpamKeywordHit(string $name, string $company, string $message): ?array
    {
        $fields = [
            'name' => $name,
            'company' => $company,
            'message' => $message,
        ];

        foreach ($fields as $field => $haystack) {
            if ($haystack === '') {
                continue;
            }
            $lower = function_exists('mb_strtolower')
                ? mb_strtolower($haystack, 'UTF-8')
                : strtolower($haystack);

            foreach (self::INQUIRY_SPAM_KEYWORDS as $keyword) {
                $kw = trim($keyword);
                if ($kw === '') {
                    continue;
                }
                $kLower = function_exists('mb_strtolower')
                    ? mb_strtolower($kw, 'UTF-8')
                    : strtolower($kw);
                $found = function_exists('mb_strpos')
                    ? mb_strpos($lower, $kLower, 0, 'UTF-8')
                    : strpos($lower, $kLower);
                if ($found !== false) {
                    return ['field' => $field, 'keyword' => $kw];
                }
            }
        }

        return null;
    }

    /**
     * @param array{field: string, keyword: string} $hit
     */
    private function logSpamKeywordHit(array $hit, string $name, string $company, string $message): void
    {
        if (!defined('DATA_PATH') || !is_dir(DATA_PATH)) {
            return;
        }

        $line = json_encode([
            'timestamp' => date('c'),
            'event' => 'spam_keyword',
            'matched_field' => $hit['field'],
            'matched_keyword' => $hit['keyword'],
            'ip' => $this->getClientIp(),
            'lang' => $this->lang,
            'name_len' => function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name),
            'company_len' => function_exists('mb_strlen') ? mb_strlen($company, 'UTF-8') : strlen($company),
            'message_len' => function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
            'request_uri' => substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 500),
        ], JSON_UNESCAPED_UNICODE) . "\n";

        @file_put_contents(DATA_PATH . '/' . self::SPAM_KEYWORD_LOG, $line, FILE_APPEND | LOCK_EX);
    }

    private function turnstileIsEnabled(): bool
    {
        $siteKey = trim((string) Config::get('turnstile_site_key', ''));
        $secret = trim((string) Config::get('turnstile_secret_key', ''));

        return $siteKey !== '' && $secret !== '';
    }

    private function verifyTurnstileToken(string $token): bool
    {
        if (!$this->turnstileIsEnabled()) {
            return true;
        }
        if ($token === '') {
            return false;
        }
        $secret = (string) Config::get('turnstile_secret_key', '');
        $payload = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $this->getClientIp(),
        ], '', '&');
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $raw = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                ]);
                $raw = curl_exec($ch);
                curl_close($ch);
            }
        }
        if ($raw === false || $raw === '') {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $payload,
                    'timeout' => 15,
                ],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
        }
        if ($raw === false || $raw === '') {
            error_log('Turnstile siteverify: empty or failed response');

            return false;
        }
        $data = json_decode((string) $raw, true);

        return is_array($data) && !empty($data['success']);
    }

    private function validateCaptcha(): bool
    {
        $captchaAnswer = trim($this->getPost('captcha', ''));
        $captchaHash = trim($this->getPost('captcha_hash', ''));

        if (empty($captchaAnswer) || empty($captchaHash)) {
            return false;
        }

        if (!isset($_SESSION['captcha_hash'], $_SESSION['captcha_time'])) {
            return false;
        }

        if (time() - $_SESSION['captcha_time'] > self::CAPTCHA_TIMEOUT) {
            unset($_SESSION['captcha_hash'], $_SESSION['captcha_time']);
            return false;
        }

        $expectedHash = $_SESSION['captcha_hash'];
        unset($_SESSION['captcha_hash'], $_SESSION['captcha_time']);

        if (!hash_equals($expectedHash, $captchaHash)) {
            return false;
        }

        $computedHash = hash('sha256', trim($captchaAnswer) . '|' . session_id());

        return hash_equals($expectedHash, $computedHash);
    }

    private function isThrottled(): bool
    {
        $key = 'stitch_inquiry_' . hash('sha256', $this->getClientIp());
        $throttleFile = DATA_PATH . '/.throttle_' . $key;

        if (!file_exists($throttleFile)) {
            return false;
        }

        $data = json_decode(file_get_contents($throttleFile), true);
        if (!$data || !isset($data['until'])) {
            @unlink($throttleFile);
            return false;
        }

        if (time() > $data['until']) {
            @unlink($throttleFile);
            return false;
        }

        return true;
    }

    private function setThrottle(): void
    {
        $key = 'stitch_inquiry_' . hash('sha256', $this->getClientIp());
        $throttleFile = DATA_PATH . '/.throttle_' . $key;

        file_put_contents($throttleFile, json_encode([
            'until' => time() + self::INQUIRY_THROTTLE,
        ]), LOCK_EX);
    }

    private function getClientIp(): string
    {
        return ClientIp::get();
    }

    private function setFormMessage(string $message, string $type): void
    {
        $_SESSION['form_message'] = $message;
        $_SESSION['form_message_type'] = $type;
    }

    private function saveFormData(): void
    {
        $_SESSION['form_data'] = [
            'name' => $this->getPost('name', ''),
            'email' => $this->getPost('email', ''),
            'company' => $this->getPost('company', ''),
            'phone' => $this->getPost('phone', ''),
            'country' => $this->getPost('country', ''),
            'product_slug' => $this->getPost('product_slug', ''),
            'product_source' => $this->getPost('product_source', ''),
            'message' => $this->getPost('message', ''),
            'newsletter_opt_out' => $this->getPost('newsletter_opt_out', '') === '1' ? '1' : '',
        ];
    }

    /**
     * Sanitize a UTM parameter value.
     * Strips HTML tags and limits to 256 UTF-8 chars.
     * UTM values are never executed, just stored/displayed.
     */
    private function sanitizeUtmValue(string $value): string
    {
        $value = strip_tags(trim($value));
        return mb_substr($value, 0, 256, 'UTF-8');
    }

    /**
     * Sanitize a URL field (landing_page / referrer).
     * Strips HTML tags and limits to 512 UTF-8 chars.
     * Rejects javascript: and data: schemes.
     */
    private function sanitizeUrl(string $value): string
    {
        $value = strip_tags(trim($value));
        $value = mb_substr($value, 0, 512, 'UTF-8');
        $lower = strtolower($value);
        if (strncmp($lower, 'javascript:', 11) === 0 || strncmp($lower, 'data:', 5) === 0) {
            return '';
        }
        return $value;
    }

    private function generateCaptcha(): array
    {
        $a = random_int(1, 20);
        $b = random_int(1, 20);
        $hash = hash('sha256', (string) ($a + $b) . '|' . session_id());

        $_SESSION['captcha_hash'] = $hash;
        $_SESSION['captcha_time'] = time();

        $labels = [
            'en' => "What is {$a} + {$b}?",
            'cn' => "{$a} + {$b} = ?",
            'es' => "Cuanto es {$a} + {$b}?",
        ];

        return [
            'question' => $labels[$this->lang] ?? $labels['en'],
            'hash' => $hash,
        ];
    }

    private function t(string $key): string
    {
        $translations = [
            'en' => ['home' => 'Home', 'contact' => 'Contact Us'],
            'cn' => ['home' => '首页', 'contact' => '联系我们'],
            'es' => ['home' => 'Inicio', 'contact' => 'Contáctenos'],
        ];
        return $translations[$this->lang][$key] ?? $key;
    }

    private function tMsg(string $key): string
    {
        $messages = [
            'en' => [
                'success' => 'Thank you! Your inquiry has been submitted successfully. We will get back to you soon.',
                'csrf_error' => 'Security token expired. Please try again.',
                'turnstile_error' => 'Human verification failed. Please complete the security check and try again.',
                'captcha_error' => 'Incorrect answer to the security question. Please try again.',
                'throttle_error' => 'You have recently submitted an inquiry. Please wait a minute before trying again.',
                'too_many_requests' => 'Too many inquiries from your network. Please try again in a few minutes.',
                'honeypot_reject' => 'Your submission could not be processed. Please try again.',
                'ip_reputation_reject' => 'Your submission could not be completed from this connection. If you use a VPN, try turning it off or contact us another way.',
                'spam_keyword_reject' => 'Your message could not be sent because it contains disallowed content. Please revise and try again.',
                'email_error' => 'Please enter a valid email address.',
                'message_short' => 'Message must be at least 10 characters long.',
                'save_error' => 'We could not save your inquiry. Please try again in a moment or contact us by email.',
            ],
            'cn' => [
                'success' => '感谢您的询盘！我们已收到您的信息，将尽快与您联系。',
                'csrf_error' => '安全令牌已过期，请重试。',
                'turnstile_error' => '人机验证未通过，请完成安全验证后重试。',
                'captcha_error' => '验证码回答错误，请重试。',
                'throttle_error' => '您最近已提交过询盘，请稍后再试。',
                'too_many_requests' => '来自您网络的询盘过于频繁，请几分钟后再试。',
                'honeypot_reject' => '提交无法完成，请稍后重试。',
                'ip_reputation_reject' => '当前网络环境无法完成提交。若使用 VPN，请关闭后重试或通过其他方式联系我们。',
                'spam_keyword_reject' => '留言内容含有不允许的词汇，请修改后重新提交。',
                'email_error' => '请输入有效的邮箱地址。',
                'message_short' => '留言内容至少需要10个字符。',
                'save_error' => '询盘暂时无法保存，请稍后重试或通过邮件直接联系我们。',
            ],
            'es' => [
                'success' => '¡Gracias! Su consulta ha sido enviada exitosamente. Nos pondremos en contacto pronto.',
                'csrf_error' => 'Token de seguridad expirado. Por favor, intente de nuevo.',
                'turnstile_error' => 'La verificación humana falló. Complete la comprobación de seguridad e inténtelo de nuevo.',
                'captcha_error' => 'Respuesta incorrecta a la pregunta de seguridad. Por favor, intente de nuevo.',
                'throttle_error' => 'Ha enviado una consulta recientemente. Espere un minuto antes de intentar de nuevo.',
                'too_many_requests' => 'Demasiadas consultas desde su red. Inténtelo de nuevo en unos minutos.',
                'honeypot_reject' => 'No se pudo procesar el envío. Inténtelo de nuevo.',
                'ip_reputation_reject' => 'No se pudo completar el envío desde esta conexión. Si usa VPN, desactívela o contáctenos por otro medio.',
                'spam_keyword_reject' => 'El mensaje contiene contenido no permitido. Revíselo e inténtelo de nuevo.',
                'email_error' => 'Por favor, ingrese una dirección de correo electrónico válida.',
                'message_short' => 'El mensaje debe tener al menos 10 caracteres.',
                'save_error' => 'No pudimos guardar su consulta. Inténtelo de nuevo en unos momentos o contáctenos por correo.',
            ],
        ];
        return $messages[$this->lang][$key] ?? $messages['en'][$key] ?? $key;
    }
}
