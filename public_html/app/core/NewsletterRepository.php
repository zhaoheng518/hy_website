<?php

namespace App\Core;

/**
 * MySQL persistence for newsletter subscribers (see database.sql).
 */
final class NewsletterRepository
{
    private const TABLE = 'newsletter_subscribers';

    public static function isAvailable(): bool
    {
        try {
            Database::getInstance()->fetchColumn('SELECT COUNT(*) FROM ' . self::TABLE);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Upsert subscriber. On duplicate email, re-activate and widen notification flags.
     *
     * @param array{notify_product?: bool, notify_blog?: bool, notify_general?: bool, source?: string} $opts
     */
    public static function subscribe(string $email, string $lang, array $opts = []): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $lang = preg_replace('/[^a-z0-9_-]/i', '', substr(trim($lang), 0, 8)) ?: 'en';
        $np = !empty($opts['notify_product']) ? 1 : 0;
        $nb = !empty($opts['notify_blog']) ? 1 : 0;
        $ng = !empty($opts['notify_general']) ? 1 : 0;
        if ($np === 0 && $nb === 0 && $ng === 0) {
            $np = $nb = $ng = 1;
        }
        $source = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($opts['source'] ?? 'manual')));
        if ($source === '') {
            $source = 'manual';
        }
        $token = self::newToken();

        $sql = 'INSERT INTO ' . self::TABLE . ' (
            email, lang, notify_product, notify_blog, notify_general, source, unsubscribe_token, is_active,
            tags, interested_products, last_inquiry_product
        ) VALUES (
            :email, :lang, :np, :nb, :ng, :src, :tok, 1,
            CAST(:tags AS JSON), CAST(:prods AS JSON), NULL
        ) ON DUPLICATE KEY UPDATE
            lang = VALUES(lang),
            notify_product = GREATEST(notify_product, VALUES(notify_product)),
            notify_blog = GREATEST(notify_blog, VALUES(notify_blog)),
            notify_general = GREATEST(notify_general, VALUES(notify_general)),
            is_active = 1,
            updated_at = CURRENT_TIMESTAMP';

        try {
            Database::getInstance()->execute($sql, [
                'email' => $email,
                'lang' => $lang,
                'np' => $np,
                'nb' => $nb,
                'ng' => $ng,
                'src' => substr($source, 0, 32),
                'tok' => $token,
                'tags' => '[]',
                'prods' => '[]',
            ]);
            return true;
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] subscribe failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Subscribe from inquiry; if $productSlug is set, merge tag `product:{slug}` and interested_products.
     */
    public static function subscribeFromInquiry(string $email, string $lang, string $productSlug = ''): bool
    {
        $ok = self::subscribe($email, $lang, [
            'notify_product' => true,
            'notify_blog' => true,
            'notify_general' => true,
            'source' => 'inquiry',
        ]);
        if (!$ok) {
            return false;
        }
        $slug = self::sanitizeProductSlug($productSlug);
        if ($slug !== '') {
            self::appendInquiryProductInterest(strtolower(trim($email)), $slug);
        }

        return true;
    }

    /**
     * Merge product interest for an existing subscriber row (by email).
     */
    public static function appendInquiryProductInterest(string $email, string $productSlug): bool
    {
        $email = strtolower(trim($email));
        $slug = self::sanitizeProductSlug($productSlug);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $slug === '') {
            return false;
        }

        try {
            $db = Database::getInstance();
            $row = $db->fetch(
                'SELECT id, tags, interested_products FROM ' . self::TABLE . ' WHERE email = :e LIMIT 1',
                ['e' => $email]
            );
            if (!is_array($row)) {
                return false;
            }

            $tags = self::decodeJsonStringList($row['tags'] ?? null);
            $tag = 'product:' . $slug;
            if (!in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }

            $products = self::decodeJsonStringList($row['interested_products'] ?? null);
            $slugLower = strtolower($slug);
            $has = false;
            foreach ($products as $p) {
                if (strtolower((string) $p) === $slugLower) {
                    $has = true;
                    break;
                }
            }
            if (!$has) {
                $products[] = $slug;
            }

            $tagsJson = json_encode(array_values($tags), JSON_UNESCAPED_UNICODE);
            $prodsJson = json_encode(array_values($products), JSON_UNESCAPED_UNICODE);
            if ($tagsJson === false || $prodsJson === false) {
                return false;
            }

            $n = $db->execute(
                'UPDATE ' . self::TABLE . ' SET
                    tags = CAST(:t AS JSON),
                    interested_products = CAST(:p AS JSON),
                    last_inquiry_product = :slug,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id',
                [
                    'id' => (int) ($row['id'] ?? 0),
                    't' => $tagsJson,
                    'p' => $prodsJson,
                    'slug' => $slug,
                ]
            );

            return $n > 0;
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] appendInquiryProductInterest: ' . $e->getMessage());
            return false;
        }
    }

    public static function unsubscribeByToken(string $token): bool
    {
        $token = preg_replace('/[^a-f0-9]/i', '', $token);
        if (strlen($token) !== 64) {
            return false;
        }
        try {
            $n = Database::getInstance()->execute(
                'UPDATE ' . self::TABLE . ' SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE unsubscribe_token = :t AND is_active = 1',
                ['t' => $token]
            );
            return $n > 0;
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] unsubscribe failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 队列发送：按 id 取订阅者（含是否活跃）。
     *
     * @return array{id: int, email: string, lang: string, unsubscribe_token: string, is_active: int}|null
     */
    public static function findSubscriberById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        try {
            $row = Database::getInstance()->fetch(
                'SELECT id, email, lang, unsubscribe_token, is_active FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1',
                ['id' => $id]
            );
            if (!is_array($row)) {
                return null;
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'email' => (string) ($row['email'] ?? ''),
                'lang' => (string) ($row['lang'] ?? 'en'),
                'unsubscribe_token' => (string) ($row['unsubscribe_token'] ?? ''),
                'is_active' => (int) ($row['is_active'] ?? 0),
            ];
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] findSubscriberById: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 手动群发：notify_general=1 且活跃的订阅者。
     *
     * @return list<array{id: int, email: string, unsubscribe_token: string, lang: string}>
     */
    public static function fetchActiveBroadcastRecipients(int $limit = 100000): array
    {
        $limit = max(1, min(100000, $limit));
        try {
            return Database::getInstance()->fetchAll(
                'SELECT id, email, unsubscribe_token, lang FROM ' . self::TABLE
                    . ' WHERE is_active = 1 AND notify_general = 1 ORDER BY id ASC LIMIT ' . (int) $limit
            );
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] fetchActiveBroadcastRecipients: ' . $e->getMessage());

            return [];
        }
    }

    public static function countActiveBroadcastRecipients(): int
    {
        try {
            return (int) Database::getInstance()->fetchColumn(
                'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE is_active = 1 AND notify_general = 1'
            );
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] countActiveBroadcastRecipients: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * @return list<array{id: int, email: string, unsubscribe_token: string, lang: string}>
     */
    public static function fetchActiveBroadcastRecipientsPaged(int $limit, int $offset): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, min(10000000, $offset));
        try {
            return Database::getInstance()->fetchAll(
                'SELECT id, email, unsubscribe_token, lang FROM ' . self::TABLE
                    . ' WHERE is_active = 1 AND notify_general = 1 ORDER BY id ASC LIMIT ' . (int) $limit
                    . ' OFFSET ' . (int) $offset
            );
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] fetchActiveBroadcastRecipientsPaged: ' . $e->getMessage());

            return [];
        }
    }

    public static function fetchActiveRecipients(string $channel, int $limit = 500): array
    {
        $col = $channel === 'blog' ? 'notify_blog' : 'notify_product';
        if ($col !== 'notify_blog' && $col !== 'notify_product') {
            return [];
        }
        try {
            $sql = 'SELECT id, email, unsubscribe_token, lang FROM ' . self::TABLE
                . ' WHERE is_active = 1 AND ' . $col . ' = 1 ORDER BY id ASC LIMIT ' . max(1, min(100000, $limit));

            return Database::getInstance()->fetchAll($sql);
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] fetchActiveRecipients: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 产品发布通知：notify_product=1 的活跃订阅者，含兴趣字段用于与产品标签/ slug 匹配排序。
     *
     * @return list<array{
     *   id: int,
     *   email: string,
     *   unsubscribe_token: string,
     *   lang: string,
     *   tags: list<string>,
     *   interested_products: list<string>
     * }>
     */
    public static function fetchActiveProductRecipientsForPublish(int $limit = 100000): array
    {
        $limit = max(1, min(100000, $limit));
        try {
            $rows = Database::getInstance()->fetchAll(
                'SELECT id, email, unsubscribe_token, lang, tags, interested_products FROM ' . self::TABLE
                    . ' WHERE is_active = 1 AND notify_product = 1 ORDER BY id ASC LIMIT ' . (int) $limit
            );
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] fetchActiveProductRecipientsForPublish: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'email' => (string) ($row['email'] ?? ''),
                'unsubscribe_token' => (string) ($row['unsubscribe_token'] ?? ''),
                'lang' => (string) ($row['lang'] ?? ''),
                'tags' => self::decodeJsonStringList($row['tags'] ?? null),
                'interested_products' => self::decodeJsonStringList($row['interested_products'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * @return array{rows: list<array<string,mixed>>, total: int}
     */
    public static function adminList(int $page, int $perPage, string $q = '', ?bool $activeOnly = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $off = ($page - 1) * $perPage;
        $params = [];
        $where = '1=1';
        if ($q !== '') {
            $where .= ' AND email LIKE :q';
            $params['q'] = '%' . $q . '%';
        }
        if ($activeOnly !== null) {
            $where .= ' AND is_active = :act';
            $params['act'] = $activeOnly ? 1 : 0;
        }
        try {
            $db = Database::getInstance();
            $total = (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE ' . $where,
                $params
            );
            $sql = 'SELECT id, email, lang, notify_product, notify_blog, notify_general, source, is_active,'
                . ' tags, interested_products, last_inquiry_product, created_at, updated_at'
                . ' FROM ' . self::TABLE . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $off;
            $rows = $db->fetchAll($sql, $params);
            return ['rows' => $rows, 'total' => $total];
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] adminList: ' . $e->getMessage());
            return ['rows' => [], 'total' => 0];
        }
    }

    public static function adminSetActive(int $id, bool $active): bool
    {
        if ($id <= 0) {
            return false;
        }
        try {
            Database::getInstance()->execute(
                'UPDATE ' . self::TABLE . ' SET is_active = :a, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['a' => $active ? 1 : 0, 'id' => $id]
            );
            return true;
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] adminSetActive: ' . $e->getMessage());
            return false;
        }
    }

    public static function adminDelete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        try {
            Database::getInstance()->execute('DELETE FROM ' . self::TABLE . ' WHERE id = :id', ['id' => $id]);
            return true;
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] adminDelete: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Dashboard counts for newsletter subscribers.
     *
     * @return array{
     *   total: int,
     *   active: int,
     *   inactive: int
     * }
     */
    public static function getSubscriberStats(): array
    {
        $empty = ['total' => 0, 'active' => 0, 'inactive' => 0];
        try {
            $db = Database::getInstance();
            $total = (int) $db->fetchColumn('SELECT COUNT(*) FROM ' . self::TABLE);
            $active = (int) $db->fetchColumn('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE is_active = 1');
            $inactive = (int) $db->fetchColumn('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE is_active = 0');

            return [
                'total' => $total,
                'active' => $active,
                'inactive' => max(0, $inactive),
            ];
        } catch (\Throwable $e) {
            error_log('[NewsletterRepository] getSubscriberStats: ' . $e->getMessage());
            return $empty;
        }
    }

    private static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function sanitizeProductSlug(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }
        $s = preg_replace('/[^a-z0-9\-]/i', '', strtolower(str_replace(' ', '-', $s)));

        return substr($s, 0, 255);
    }

    /**
     * @return list<string>
     */
    private static function decodeJsonStringList(mixed $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        if (is_string($json)) {
            $decoded = json_decode($json, true);
        } elseif (is_array($json)) {
            $decoded = $json;
        } else {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $v) {
            if (is_string($v) && $v !== '') {
                $out[] = $v;
            }
        }

        return $out;
    }
}
