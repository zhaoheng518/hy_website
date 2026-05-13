<?php

namespace App\Core;

/**
 * Enqueues newsletter emails (product / blog) into newsletter_jobs for async sending.
 */
final class NewsletterNotifier
{
    /** 无标签匹配的订阅者任务延后发送（秒），以便优先处理兴趣匹配队列 */
    private const PRODUCT_NON_MATCH_DELAY_SEC = 300;

    /**
     * 产品首次保存为已发布或从草稿变为已发布时调用：为 notify_product=1 的订阅者创建任务；
     * 与产品标签 / 询盘兴趣（interested_products、product:{slug}）匹配的订阅者优先（更早 send_at）。
     *
     * @param list<string> $productTags 产品 tags 字段（小写比对）
     */
    public static function productPublished(string $lang, string $slug, string $productName, array $productTags = []): void
    {
        self::enqueueProductJobs($lang, $slug, $productName, $productTags);
    }

    /**
     * 博客首次发布时调用：为 notify_blog=1 且 is_active=1 的订阅者各创建一条 newsletter_jobs（异步发送）。
     */
    public static function blogPublished(string $lang, string $slug, string $title): void
    {
        self::enqueueBlogJobs($lang, $slug, $title);
    }

    /**
     * @param list<string> $productTags
     */
    private static function enqueueProductJobs(string $lang, string $slug, string $productName, array $productTags = []): void
    {
        if (!NewsletterRepository::isAvailable() || !NewsletterJobRepository::isAvailable()) {
            return;
        }
        $recipients = NewsletterRepository::fetchActiveProductRecipientsForPublish(100000);
        if ($recipients === []) {
            return;
        }

        $slugNorm = strtolower(trim($slug));
        $tagSet = [];
        foreach ($productTags as $t) {
            $t = strtolower(trim((string) $t));
            if ($t !== '') {
                $tagSet[$t] = true;
            }
        }
        $productTagKey = strtolower('product:' . $slugNorm);

        usort($recipients, function (array $a, array $b) use ($slugNorm, $tagSet, $productTagKey): int {
            $ma = self::subscriberMatchesProductInterest($a, $slugNorm, $tagSet, $productTagKey);
            $mb = self::subscriberMatchesProductInterest($b, $slugNorm, $tagSet, $productTagKey);
            if ($ma !== $mb) {
                return $mb <=> $ma;
            }

            return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
        });

        $siteName = (string) Config::get('site_name', 'Site');
        $subject = sprintf('[%s] %s', $siteName, $productName !== '' ? $productName : 'Product update');
        $nowSend = date('Y-m-d H:i:s');
        $delayedSend = date('Y-m-d H:i:s', time() + self::PRODUCT_NON_MATCH_DELAY_SEC);

        foreach ($recipients as $row) {
            $sid = (int) ($row['id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $email = (string) ($row['email'] ?? '');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $rLang = (string) ($row['lang'] ?? $lang);
            if ($rLang === '') {
                $rLang = $lang;
            }
            $url = View::langUrl($rLang, 'product/' . rawurlencode($slug));
            $body = self::wrapHtml(
                $siteName,
                '<p>' . htmlspecialchars($productName !== '' ? $productName : $slug, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a></p>',
                self::unsubscribeUrl($rLang, (string) ($row['unsubscribe_token'] ?? ''))
            );
            $matched = self::subscriberMatchesProductInterest($row, $slugNorm, $tagSet, $productTagKey);
            $sendAt = $matched ? $nowSend : $delayedSend;
            try {
                NewsletterJobRepository::createJob(
                    $sid,
                    $subject,
                    $body,
                    self::htmlToPlainText($body),
                    NewsletterJobRepository::TYPE_PRODUCT_UPDATE,
                    $sendAt
                );
            } catch (\Throwable $e) {
                error_log('[NewsletterNotifier] enqueue product job subscriber=' . $sid . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * @param array{tags?: list<string>, interested_products?: list<string>} $row
     * @param array<string, bool> $productTagSet 小写标签 => true
     */
    private static function subscriberMatchesProductInterest(array $row, string $slugNorm, array $productTagSet, string $productTagKey): bool
    {
        foreach ($row['interested_products'] ?? [] as $p) {
            if (strtolower(trim((string) $p)) === $slugNorm) {
                return true;
            }
        }
        foreach ($row['tags'] ?? [] as $t) {
            $tl = strtolower(trim((string) $t));
            if ($tl === '') {
                continue;
            }
            if ($tl === $productTagKey) {
                return true;
            }
            if (isset($productTagSet[$tl])) {
                return true;
            }
        }

        return false;
    }

    private static function enqueueBlogJobs(string $lang, string $slug, string $title): void
    {
        if (!NewsletterRepository::isAvailable() || !NewsletterJobRepository::isAvailable()) {
            return;
        }
        $recipients = NewsletterRepository::fetchActiveRecipients('blog', 100000);
        if ($recipients === []) {
            return;
        }
        $siteName = (string) Config::get('site_name', 'Site');
        $subject = sprintf('[%s] %s', $siteName, $title !== '' ? $title : 'New article');

        foreach ($recipients as $row) {
            $sid = (int) ($row['id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $email = (string) ($row['email'] ?? '');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $rLang = (string) ($row['lang'] ?? $lang);
            if ($rLang === '') {
                $rLang = $lang;
            }
            $url = View::langUrl($rLang, 'blog/' . rawurlencode($slug));
            $body = self::wrapHtml(
                $siteName,
                '<p>' . htmlspecialchars($title !== '' ? $title : $slug, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a></p>',
                self::unsubscribeUrl($rLang, (string) ($row['unsubscribe_token'] ?? ''))
            );
            try {
                NewsletterJobRepository::createJob(
                    $sid,
                    $subject,
                    $body,
                    self::htmlToPlainText($body),
                    NewsletterJobRepository::TYPE_BLOG_POST,
                    null
                );
            } catch (\Throwable $e) {
                error_log('[NewsletterNotifier] enqueue blog job subscriber=' . $sid . ': ' . $e->getMessage());
            }
        }
    }

    private static function unsubscribeUrl(string $lang, string $token): string
    {
        $base = rtrim((string) Config::get('site_url', ''), '/');
        if ($base === '' || strlen($token) !== 64) {
            return '';
        }

        return $base . '/' . rawurlencode($lang) . '/newsletter/unsubscribe/' . $token;
    }

    private static function htmlToPlainText(string $html): string
    {
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/u', ' ', $plain);

        return trim($plain) !== '' ? trim($plain) : ' ';
    }

    private static function wrapHtml(string $siteName, string $innerHtml, string $unsub): string
    {
        $foot = $unsub !== ''
            ? '<p style="font-size:12px;color:#666;"><a href="' . htmlspecialchars($unsub, ENT_QUOTES, 'UTF-8') . '">Unsubscribe</a></p>'
            : '';
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#333;max-width:560px;">'
            . '<h2 style="color:#1a56db;">' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</h2>'
            . $innerHtml
            . $foot
            . '</body></html>';
    }
}
