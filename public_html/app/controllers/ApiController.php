<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\NewsletterEventRepository;
use App\Core\NewsletterJobRepository;
use App\Core\UploadService;

class ApiController extends BaseController
{
    public function translate(): void
    {
        Auth::requireEditorApi();

        if (!$this->isPost()) {
            $this->jsonError('Method not allowed.', 405);
        }

        $data = $this->jsonInput();
        $text = trim($data['text'] ?? '');
        $targetLang = trim($data['targetLang'] ?? '');
        $sourceLang = trim($data['sourceLang'] ?? 'en');

        if (empty($text) || empty($targetLang)) {
            $this->jsonError('Missing required fields: text, targetLang.', 400);
        }

        $supportedLangs = \App\Core\Config::get('supported_langs', ['en', 'cn', 'es']);
        if (!in_array($targetLang, $supportedLangs, true)) {
            $this->jsonError('Unsupported target language.', 400);
        }

        usleep(random_int(300000, 800000));

        $translated = $this->mockTranslate($text, $targetLang, $sourceLang);

        $this->jsonSuccess(['translated' => $translated]);
    }

    public function upload(): void
    {
        Auth::requireEditorApi();

        if (!$this->isPost()) {
            $this->jsonError('Method not allowed.', 405);
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::validateCsrfToken($csrf)) {
            $this->jsonError('Invalid security token.', 403);
        }

        if (!isset($_FILES['file'])) {
            $this->jsonError('No valid file uploaded.', 400);
        }

        $replaceRaw = trim((string) ($_POST['replace'] ?? ''));
        $replaceWeb = $replaceRaw !== '' ? UploadService::normalizeWebPath($replaceRaw) : null;

        $type = strtolower(trim((string) ($_POST['type'] ?? $_GET['type'] ?? 'image')));
        if ($type === 'pdf') {
            $result = UploadService::process($_FILES['file'], [
                'bucket' => UploadService::BUCKET_DATASHEETS,
                'mode' => UploadService::MODE_PDF,
                'max_bytes' => UploadService::getDefaultPdfMaxBytes(),
                'replace_web_path' => $replaceWeb,
                'datasheet_style_name' => true,
            ]);
        } else {
            $result = UploadService::process($_FILES['file'], [
                'bucket' => UploadService::BUCKET_IMAGES,
                'mode' => UploadService::MODE_STRICT_IMAGE,
                'max_bytes' => UploadService::getMaxImageBytes(),
                'replace_web_path' => $replaceWeb,
            ]);
        }

        if (!$result['ok']) {
            $this->jsonError($result['error'] ?? 'Upload failed.', 400);
        }

        $this->jsonSuccess([
            'url' => $result['url'] ?? '',
            'name' => $result['basename'] ?? '',
            'web_path' => $result['web_path'] ?? '',
        ]);
    }

    /**
     * Brevo Webhook：落库 newsletter_events；可选密钥见 site.json brevo_webhook_secret。
     * URL：https://域名/api/brevo/webhook
     */
    public function brevoWebhook(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $secret = trim((string) Config::get('brevo_webhook_secret', ''));
        if ($secret !== '') {
            $hdr = trim((string) ($_SERVER['HTTP_X_BREVO_WEBHOOK_SECRET'] ?? $_SERVER['HTTP_X_MIB_WEBHOOK_SECRET'] ?? ''));
            if ($hdr === '' || !hash_equals($secret, $hdr)) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $raw = (string) file_get_contents('php://input');
        $decoded = json_decode($raw, true);

        $items = self::brevoFlattenWebhookPayload($decoded);
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            self::brevoProcessOneWebhookEvent($item, $raw);
        }

        http_response_code(200);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @param mixed $decoded
     * @return list<mixed>
     */
    private static function brevoFlattenWebhookPayload(mixed $decoded): array
    {
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['events']) && is_array($decoded['events'])) {
            return array_values($decoded['events']);
        }
        if (isset($decoded['items']) && is_array($decoded['items'])) {
            return array_values($decoded['items']);
        }

        $isList = array_keys($decoded) === range(0, count($decoded) - 1);
        if ($isList) {
            return $decoded;
        }

        return [$decoded];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function brevoProcessOneWebhookEvent(array $item, string $rawBodySnippet): void
    {
        $rawEvent = strtolower(trim((string) ($item['event'] ?? $item['type'] ?? $item['event_name'] ?? '')));
        $norm = self::brevoNormalizeEventType($rawEvent);
        $email = trim((string) ($item['email'] ?? $item['recipient'] ?? $item['to'] ?? ''));

        $msgId = self::brevoExtractMessageId($item);
        $msgIdStore = $msgId !== '' ? mb_substr($msgId, 0, 255, 'UTF-8') : null;

        $jobId = null;
        $subscriberId = null;
        if ($msgId !== '' && NewsletterJobRepository::isAvailable()) {
            $job = NewsletterJobRepository::findByProviderMessageId($msgId);
            if ($job !== null && ($job['id'] ?? 0) > 0) {
                $jobId = (int) $job['id'];
                $sid = (int) ($job['subscriber_id'] ?? 0);
                $subscriberId = $sid > 0 ? $sid : null;
            }
        }

        $payload = [
            'email' => $email,
            'raw_event' => $rawEvent,
            'normalized_event' => $norm,
            'message_id' => $msgId,
            'item' => $item,
            'body_snippet' => mb_substr($rawBodySnippet, 0, 2000, 'UTF-8'),
        ];

        error_log('[BrevoWebhook] event=' . $norm . ' email=' . $email . ' message_id=' . $msgId);

        $persist = Config::get('newsletter_advanced', false);
        $persist = $persist === true || $persist === 1 || $persist === '1' || $persist === 'true';
        if ($persist && NewsletterEventRepository::isAvailable()) {
            NewsletterEventRepository::createEvent(
                $jobId,
                $subscriberId,
                $msgIdStore,
                $norm,
                $payload
            );
        }
    }

    private static function brevoNormalizeEventType(string $raw): string
    {
        $e = preg_replace('/[^a-z0-9_]/', '', strtolower($raw)) ?? '';
        return match ($e) {
            'delivered', 'delivery' => 'delivered',
            'opened', 'open' => 'opened',
            'click', 'clicked' => 'clicked',
            'spam' => 'spam',
            'invalid' => 'invalid',
            'bounce', 'bounced', 'hardbounce', 'softbounce', 'hard_bounce', 'soft_bounce',
            'blocked', 'error' => 'bounced',
            'unsubscribed', 'unsubscribe' => 'unsubscribed',
            default => $e !== '' ? mb_substr($e, 0, 50, 'UTF-8') : 'unknown',
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function brevoExtractMessageId(array $item): string
    {
        $keys = ['message-id', 'messageId', 'message_id', 'MessageId', 'uuid', 'UUID'];
        foreach ($keys as $k) {
            if (!empty($item[$k]) && is_string($item[$k])) {
                return trim($item[$k]);
            }
        }
        if (!empty($item['message']) && is_array($item['message'])) {
            $m = $item['message'];
            foreach (['messageId', 'message-id', 'id'] as $k) {
                if (!empty($m[$k]) && is_string($m[$k])) {
                    return trim($m[$k]);
                }
            }
        }

        return '';
    }

    private function mockTranslate(string $text, string $targetLang, string $sourceLang): string
    {
        // --- OpenAI Integration Skeleton ---
        // $apiKey = getenv('OPENAI_API_KEY');
        // if ($apiKey) {
        //     $payload = json_encode([
        //         'model' => 'gpt-3.5-turbo',
        //         'messages' => [
        //             ['role' => 'system', 'content' => "Translate from {$sourceLang} to {$targetLang}. Return only the translation, no explanations."],
        //             ['role' => 'user', 'content' => $text],
        //         ],
        //         'temperature' => 0.3,
        //     ]);
        //     $ch = curl_init('https://api.openai.com/v1/chat/completions');
        //     curl_setopt_array($ch, [
        //         CURLOPT_POST => true,
        //         CURLOPT_POSTFIELDS => $payload,
        //         CURLOPT_HTTPHEADER => [
        //             'Content-Type: application/json',
        //             'Authorization: Bearer ' . $apiKey,
        //         ],
        //         CURLOPT_RETURNTRANSFER => true,
        //         CURLOPT_TIMEOUT => 30,
        //     ]);
        //     $response = curl_exec($ch);
        //     curl_close($ch);
        //     $result = json_decode($response, true);
        //     if (isset($result['choices'][0]['message']['content'])) {
        //         return trim($result['choices'][0]['message']['content']);
        //     }
        // }

        // --- DeepL Integration Skeleton ---
        // $apiKey = getenv('DEEPL_API_KEY');
        // if ($apiKey) {
        //     $ch = curl_init('https://api-free.deepl.com/v2/translate');
        //     curl_setopt_array($ch, [
        //         CURLOPT_POST => true,
        //         CURLOPT_POSTFIELDS => http_build_query([
        //             'auth_key' => $apiKey,
        //             'text' => $text,
        //             'source_lang' => strtoupper($sourceLang),
        //             'target_lang' => strtoupper($targetLang),
        //         ]),
        //         CURLOPT_RETURNTRANSFER => true,
        //         CURLOPT_TIMEOUT => 30,
        //     ]);
        //     $response = curl_exec($ch);
        //     curl_close($ch);
        //     $result = json_decode($response, true);
        //     if (isset($result['translations'][0]['text'])) {
        //         return $result['translations'][0]['text'];
        //     }
        // }

        $prefixes = [
            'cn' => '[中文]',
            'es' => '[ES]',
            'fr' => '[FR]',
            'de' => '[DE]',
            'ja' => '[JA]',
            'pt' => '[PT]',
            'it' => '[IT]',
            'ko' => '[KO]',
            'ru' => '[RU]',
            'ar' => '[AR]',
        ];

        $prefix = $prefixes[$targetLang] ?? '[' . strtoupper($targetLang) . ']';
        return $prefix . ' ' . $text;
    }
}
