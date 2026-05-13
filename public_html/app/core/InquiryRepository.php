<?php

declare(strict_types=1);

namespace App\Core;

/**
 * MySQL 询盘表 {@see database.sql `inquiries`}；不可用时前台回退到 JsonStore。
 */
final class InquiryRepository
{
    private const TABLE = 'inquiries';

    public static function isAvailable(): bool
    {
        try {
            Database::getInstance()->fetchColumn('SELECT COUNT(*) FROM ' . self::TABLE);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function countUnread(): int
    {
        try {
            return (int) Database::getInstance()->fetchColumn(
                'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE is_read = 0'
            );
        } catch (\Throwable $e) {
            error_log('[InquiryRepository] countUnread: ' . $e->getMessage());

            return 0;
        }
    }

    public static function countTotal(): int
    {
        try {
            return (int) Database::getInstance()->fetchColumn('SELECT COUNT(*) FROM ' . self::TABLE);
        } catch (\Throwable $e) {
            error_log('[InquiryRepository] countTotal: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * @param array<string, mixed> $row 与 ContactController 构造的询盘数组一致
     */
    public static function createFromContactForm(array $row): int
    {
        $externalId = self::sanitizeExternalId((string) ($row['id'] ?? ''));
        if ($externalId === '') {
            $externalId = uniqid('inq_', true);
        }

        $name = mb_substr(trim((string) ($row['name'] ?? '')), 0, 128, 'UTF-8');
        $email = mb_substr(trim((string) ($row['email'] ?? '')), 0, 256, 'UTF-8');
        $message = trim((string) ($row['message'] ?? ''));
        if ($name === '' || $email === '' || $message === '') {
            return 0;
        }

        try {
            $db = Database::getInstance();
            $id = $db->insert(
                'INSERT INTO ' . self::TABLE . ' (
                    external_id, name, company, email, phone, country,
                    product_slug, product_source, source_url,
                    utm_source, utm_medium, utm_campaign, utm_term, utm_content,
                    referrer, landing_page,
                    lang, message, status,
                    is_read, ip_address, user_agent
                ) VALUES (
                    :eid, :name, :company, :email, :phone, :country,
                    :pslug, :psrc, :surl,
                    :utm_src, :utm_med, :utm_cmp, :utm_trm, :utm_cnt,
                    :ref, :lp,
                    :lang, :msg, :status,
                    0, :ip, :ua
                )',
                [
                    'eid' => $externalId,
                    'name' => $name,
                    'company' => self::nullableStr($row['company'] ?? null, 256),
                    'email' => $email,
                    'phone' => self::nullableStr($row['phone'] ?? null, 64),
                    'country' => self::nullableStr($row['country'] ?? null, 128),
                    'pslug' => self::nullableStr($row['product_slug'] ?? null, 128),
                    'psrc' => self::nullableStr($row['product_source'] ?? null, 256),
                    'surl' => self::nullableStr($row['source_url'] ?? null, 512),
                    'utm_src' => self::nullableStr($row['utm_source']   ?? null, 256),
                    'utm_med' => self::nullableStr($row['utm_medium']   ?? null, 256),
                    'utm_cmp' => self::nullableStr($row['utm_campaign'] ?? null, 256),
                    'utm_trm' => self::nullableStr($row['utm_term']     ?? null, 256),
                    'utm_cnt' => self::nullableStr($row['utm_content']  ?? null, 256),
                    'ref'     => self::nullableStr($row['referrer']     ?? null, 512),
                    'lp'      => self::nullableStr($row['landing_page'] ?? null, 512),
                    'lang' => self::nullableStr($row['lang'] ?? null, 8),
                    'msg' => $message,
                    'status' => self::normalizeStatus((string) ($row['status'] ?? 'new')),
                    'ip' => self::nullableStr($row['ip'] ?? null, 45),
                    'ua' => self::nullableStr($row['user_agent'] ?? null, 512),
                ]
            );

            return $id > 0 ? $id : 0;
        } catch (\Throwable $e) {
            error_log('[InquiryRepository] createFromContactForm: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * 仪表盘最近询盘（id 倒序）。
     *
     * @return list<array<string, mixed>>
     */
    public static function listRecentForDashboard(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        try {
            $rows = Database::getInstance()->fetchAll(
                'SELECT * FROM ' . self::TABLE . ' ORDER BY id DESC LIMIT ' . (int) $limit
            );
        } catch (\Throwable $e) {
            error_log('[InquiryRepository] listRecentForDashboard: ' . $e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            if (is_array($r)) {
                $out[] = self::toLegacyShape($r);
            }
        }

        return $out;
    }

    /**
     * 后台列表：按 id 倒序，与旧 JSON 数组行为接近（新在后）。
     *
     * @return list<array<string, mixed>>
     */
    public static function listAllForAdmin(int $limit = 10000): array
    {
        $limit = max(1, min(50000, $limit));
        try {
            $rows = Database::getInstance()->fetchAll(
                'SELECT * FROM ' . self::TABLE . ' ORDER BY id DESC LIMIT ' . (int) $limit
            );
        } catch (\Throwable $e) {
            error_log('[InquiryRepository] listAllForAdmin: ' . $e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            if (is_array($r)) {
                $out[] = self::toLegacyShape($r);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByPublicRef(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        try {
            $db = Database::getInstance();
            if (str_starts_with($ref, 'inq_')) {
                $row = $db->fetch(
                    'SELECT * FROM ' . self::TABLE . ' WHERE external_id = :r LIMIT 1',
                    ['r' => $ref]
                );
            } elseif (ctype_digit($ref)) {
                $row = $db->fetch(
                    'SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1',
                    ['id' => (int) $ref]
                );
            } else {
                $row = $db->fetch(
                    'SELECT * FROM ' . self::TABLE . ' WHERE external_id = :r LIMIT 1',
                    ['r' => $ref]
                );
            }

            return is_array($row) ? self::toLegacyShape($row) : null;
        } catch (\Throwable $e) {
            error_log('[InquiryRepository] findByPublicRef: ' . $e->getMessage());

            return null;
        }
    }

    public static function markReadByPublicRef(string $ref): bool
    {
        $id = self::resolveNumericId($ref);
        if ($id <= 0) {
            return false;
        }
        try {
            Database::getInstance()->execute(
                'UPDATE ' . self::TABLE . ' SET is_read = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE id = :id',
                ['id' => $id]
            );

            return true;
        } catch (\Throwable $e) {
            error_log('[InquiryRepository] markReadByPublicRef: ' . $e->getMessage());

            return false;
        }
    }

    public static function updateStatusByPublicRef(string $ref, string $status): bool
    {
        $id = self::resolveNumericId($ref);
        if ($id <= 0) {
            return false;
        }
        $status = self::normalizeStatus($status);
        try {
            $n = Database::getInstance()->execute(
                'UPDATE ' . self::TABLE . ' SET status = :st, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['id' => $id, 'st' => $status]
            );

            return $n > 0;
        } catch (\Throwable $e) {
            error_log('[InquiryRepository] updateStatusByPublicRef: ' . $e->getMessage());

            return false;
        }
    }

    public static function deleteByPublicRef(string $ref): bool
    {
        $id = self::resolveNumericId($ref);
        if ($id <= 0) {
            return false;
        }
        try {
            $n = Database::getInstance()->execute('DELETE FROM ' . self::TABLE . ' WHERE id = :id', ['id' => $id]);

            return $n > 0;
        } catch (\Throwable $e) {
            error_log('[InquiryRepository] deleteByPublicRef: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * 从 JSON 迁移一行（跳过已存在 external_id）；保留已读状态与创建时间。
     *
     * @param array<string, mixed> $legacy JsonStore 中单条询盘
     */
    public static function importLegacyRow(array $legacy): bool
    {
        return self::migrateFromJsonRecord($legacy);
    }

    /**
     * @param array<string, mixed> $legacy
     */
    public static function migrateFromJsonRecord(array $legacy): bool
    {
        if (!self::isAvailable()) {
            return false;
        }
        $externalId = self::sanitizeExternalId((string) ($legacy['id'] ?? ''));
        if ($externalId === '') {
            return false;
        }

        try {
            $db = Database::getInstance();
            $exists = $db->fetchColumn(
                'SELECT id FROM ' . self::TABLE . ' WHERE external_id = :e LIMIT 1',
                ['e' => $externalId]
            );
            if ($exists) {
                return true;
            }

            $name = mb_substr(trim((string) ($legacy['name'] ?? '')), 0, 128, 'UTF-8');
            $email = mb_substr(trim((string) ($legacy['email'] ?? '')), 0, 256, 'UTF-8');
            $message = trim((string) ($legacy['message'] ?? ''));
            if ($name === '' || $email === '' || $message === '') {
                return false;
            }

            $isRead = !empty($legacy['read']) ? 1 : 0;
            $readAt = self::nullableStr($legacy['read_at'] ?? null, 32);
            $status = self::normalizeStatus((string) ($legacy['status'] ?? 'new'));
            $createdAt = self::nullableStr($legacy['created_at'] ?? null, 32);
            $createdSql = $createdAt !== null
                ? ':created_at'
                : 'CURRENT_TIMESTAMP';

            $params = [
                'eid' => $externalId,
                'name' => $name,
                'company' => self::nullableStr($legacy['company'] ?? null, 256),
                'email' => $email,
                'phone' => self::nullableStr($legacy['phone'] ?? null, 64),
                'country' => self::nullableStr($legacy['country'] ?? null, 128),
                'pslug' => self::nullableStr($legacy['product_slug'] ?? null, 128),
                'psrc' => self::nullableStr($legacy['product_source'] ?? null, 256),
                'surl' => self::nullableStr($legacy['source_url'] ?? null, 512),
                'utm_src' => self::nullableStr($legacy['utm_source']   ?? null, 256),
                'utm_med' => self::nullableStr($legacy['utm_medium']   ?? null, 256),
                'utm_cmp' => self::nullableStr($legacy['utm_campaign'] ?? null, 256),
                'utm_trm' => self::nullableStr($legacy['utm_term']     ?? null, 256),
                'utm_cnt' => self::nullableStr($legacy['utm_content']  ?? null, 256),
                'ref'     => self::nullableStr($legacy['referrer']     ?? null, 512),
                'lp'      => self::nullableStr($legacy['landing_page'] ?? null, 512),
                'lang' => self::nullableStr($legacy['lang'] ?? null, 8),
                'msg' => $message,
                'status' => $status,
                'ip' => self::nullableStr($legacy['ip'] ?? null, 45),
                'ua' => self::nullableStr($legacy['user_agent'] ?? null, 512),
                'is_read' => $isRead,
                'read_at' => $readAt,
            ];
            if ($createdAt !== null) {
                $params['created_at'] = $createdAt;
            }

            $sql = 'INSERT INTO ' . self::TABLE . ' (
                    external_id, name, company, email, phone, country,
                    product_slug, product_source, source_url,
                    utm_source, utm_medium, utm_campaign, utm_term, utm_content,
                    referrer, landing_page,
                    lang, message, status,
                    is_read, read_at, ip_address, user_agent, created_at
                ) VALUES (
                    :eid, :name, :company, :email, :phone, :country,
                    :pslug, :psrc, :surl,
                    :utm_src, :utm_med, :utm_cmp, :utm_trm, :utm_cnt,
                    :ref, :lp,
                    :lang, :msg, :status,
                    :is_read, :read_at, :ip, :ua, ' . $createdSql . '
                )';

            $id = $db->insert($sql, $params);

            return $id > 0;
        } catch (\Throwable $e) {
            error_log('[InquiryRepository] migrateFromJsonRecord: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @param array<string, mixed> $dbRow
     * @return array<string, mixed>
     */
    private static function toLegacyShape(array $dbRow): array
    {
        $pubId = trim((string) ($dbRow['external_id'] ?? ''));
        if ($pubId === '') {
            $pubId = (string) (int) ($dbRow['id'] ?? 0);
        }

        return [
            'id' => $pubId,
            'name' => (string) ($dbRow['name'] ?? ''),
            'email' => (string) ($dbRow['email'] ?? ''),
            'company' => (string) ($dbRow['company'] ?? ''),
            'phone' => (string) ($dbRow['phone'] ?? ''),
            'country' => (string) ($dbRow['country'] ?? ''),
            'product_slug' => (string) ($dbRow['product_slug'] ?? ''),
            'product_source' => (string) ($dbRow['product_source'] ?? ''),
            'source_url' => (string) ($dbRow['source_url'] ?? ''),
            'utm_source'   => (string) ($dbRow['utm_source']   ?? ''),
            'utm_medium'   => (string) ($dbRow['utm_medium']   ?? ''),
            'utm_campaign' => (string) ($dbRow['utm_campaign'] ?? ''),
            'utm_term'     => (string) ($dbRow['utm_term']     ?? ''),
            'utm_content'  => (string) ($dbRow['utm_content']  ?? ''),
            'referrer'     => (string) ($dbRow['referrer']     ?? ''),
            'landing_page' => (string) ($dbRow['landing_page'] ?? ''),
            'message' => (string) ($dbRow['message'] ?? ''),
            'lang' => (string) ($dbRow['lang'] ?? ''),
            'ip' => (string) ($dbRow['ip_address'] ?? ''),
            'user_agent' => (string) ($dbRow['user_agent'] ?? ''),
            'created_at' => (string) ($dbRow['created_at'] ?? ''),
            'read' => !empty($dbRow['is_read']),
            'read_at' => $dbRow['read_at'] ?? null,
            'status' => (string) ($dbRow['status'] ?? 'new'),
        ];
    }

    private static function resolveNumericId(string $ref): int
    {
        $raw = self::findRawByPublicRef($ref);
        if (!is_array($raw)) {
            return 0;
        }

        return (int) ($raw['id'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function findRawByPublicRef(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        try {
            $db = Database::getInstance();
            if (str_starts_with($ref, 'inq_')) {
                $row = $db->fetch(
                    'SELECT * FROM ' . self::TABLE . ' WHERE external_id = :r LIMIT 1',
                    ['r' => $ref]
                );
            } elseif (ctype_digit($ref)) {
                $row = $db->fetch(
                    'SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1',
                    ['id' => (int) $ref]
                );
            } else {
                $row = $db->fetch(
                    'SELECT * FROM ' . self::TABLE . ' WHERE external_id = :r LIMIT 1',
                    ['r' => $ref]
                );
            }

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function sanitizeExternalId(string $id): string
    {
        $id = trim($id);
        if (strlen($id) > 64) {
            $id = substr($id, 0, 64);
        }

        return $id;
    }

    private static function normalizeStatus(string $status): string
    {
        $status = strtolower(preg_replace('/[^a-z0-9_\-]/', '', trim($status)) ?? '');
        if ($status === 'replied') {
            return 'contacted';
        }
        if ($status === 'spam') {
            return 'closed';
        }
        $allowed = ['new', 'contacted', 'quoted', 'closed'];

        return in_array($status, $allowed, true) ? $status : 'new';
    }

    private static function nullableStr(mixed $v, int $maxLen): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }

        return mb_substr($s, 0, $maxLen, 'UTF-8');
    }
}
