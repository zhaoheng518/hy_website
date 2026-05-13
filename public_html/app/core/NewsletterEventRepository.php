<?php

declare(strict_types=1);

namespace App\Core;

/**
 * newsletter_events 表：Brevo Webhook 等事件持久化。
 *
 * @see database_migrations/newsletter_events.sql
 */
final class NewsletterEventRepository
{
    private const TABLE = 'newsletter_events';

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
     * @param array<string, mixed> $payload 将 JSON 编码存入 payload
     */
    public static function createEvent(
        ?int $jobId,
        ?int $subscriberId,
        ?string $providerMessageId,
        string $eventType,
        array $payload
    ): bool {
        if (!self::isAvailable()) {
            return false;
        }

        $eventType = mb_substr(preg_replace('/[^a-z0-9_\-]/i', '', strtolower(trim($eventType))) ?? '', 0, 50, 'UTF-8');
        if ($eventType === '') {
            $eventType = 'unknown';
        }

        $mid = null;
        if ($providerMessageId !== null && trim($providerMessageId) !== '') {
            $mid = mb_substr(trim($providerMessageId), 0, 255, 'UTF-8');
        }

        $jid = $jobId !== null && $jobId > 0 ? $jobId : null;
        $sid = $subscriberId !== null && $subscriberId > 0 ? $subscriberId : null;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = '{}';
        }

        try {
            $newId = Database::getInstance()->insert(
                'INSERT INTO ' . self::TABLE . ' (job_id, provider_message_id, subscriber_id, event_type, payload) VALUES (:jid, :mid, :sid, :et, :pl)',
                [
                    'jid' => $jid,
                    'mid' => $mid,
                    'sid' => $sid,
                    'et' => $eventType,
                    'pl' => $json,
                ]
            );

            return $newId > 0;
        } catch (\Throwable $e) {
            error_log('[NewsletterEventRepository] createEvent: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function findByMessageId(string $messageId): array
    {
        if (!self::isAvailable()) {
            return [];
        }
        $m = mb_substr(trim($messageId), 0, 255, 'UTF-8');
        if ($m === '') {
            return [];
        }

        try {
            $rows = Database::getInstance()->fetchAll(
                'SELECT id, job_id, provider_message_id, subscriber_id, event_type, payload, created_at FROM ' . self::TABLE
                    . ' WHERE provider_message_id = :m ORDER BY id DESC LIMIT 500',
                ['m' => $m]
            );

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log('[NewsletterEventRepository] findByMessageId: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listRecent(int $limit = 100): array
    {
        if (!self::isAvailable()) {
            return [];
        }
        $limit = max(1, min(500, $limit));

        try {
            $rows = Database::getInstance()->fetchAll(
                'SELECT id, job_id, provider_message_id, subscriber_id, event_type, payload, created_at FROM ' . self::TABLE
                    . ' ORDER BY id DESC LIMIT ' . (int) $limit
            );

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log('[NewsletterEventRepository] listRecent: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function listPaginated(int $page, int $perPage, ?string $eventTypeFilter = null): array
    {
        if (!self::isAvailable()) {
            return ['rows' => [], 'total' => 0];
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $params = [];
        $where = '1=1';
        if ($eventTypeFilter !== null && $eventTypeFilter !== '') {
            $ft = mb_substr(preg_replace('/[^a-z0-9_\-]/i', '', strtolower(trim($eventTypeFilter))) ?? '', 0, 50, 'UTF-8');
            if ($ft !== '') {
                $where .= ' AND event_type = :ft';
                $params['ft'] = $ft;
            }
        }

        try {
            $db = Database::getInstance();
            $total = (int) $db->fetchColumn('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE ' . $where, $params);
            $sql = 'SELECT id, job_id, provider_message_id, subscriber_id, event_type, payload, created_at FROM ' . self::TABLE
                . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
            $rows = $db->fetchAll($sql, $params);

            return ['rows' => is_array($rows) ? $rows : [], 'total' => $total];
        } catch (\Throwable $e) {
            error_log('[NewsletterEventRepository] listPaginated: ' . $e->getMessage());

            return ['rows' => [], 'total' => 0];
        }
    }

    /**
     * 最近 N 天内五类事件计数（用于后台概览）。
     *
     * @return array{
     *   delivered: int,
     *   opened: int,
     *   clicked: int,
     *   bounced: int,
     *   unsubscribed: int
     * }
     */
    public static function getStatsSummaryLastDays(int $days = 30): array
    {
        $empty = [
            'delivered' => 0,
            'opened' => 0,
            'clicked' => 0,
            'bounced' => 0,
            'unsubscribed' => 0,
        ];
        if (!self::isAvailable()) {
            return $empty;
        }
        $days = max(1, min(365, $days));

        try {
            $row = Database::getInstance()->fetch(
                'SELECT '
                    . ' COALESCE(SUM(CASE WHEN event_type = :d THEN 1 ELSE 0 END), 0) AS c_del,'
                    . ' COALESCE(SUM(CASE WHEN event_type = :o THEN 1 ELSE 0 END), 0) AS c_opn,'
                    . ' COALESCE(SUM(CASE WHEN event_type = :c THEN 1 ELSE 0 END), 0) AS c_clk,'
                    . ' COALESCE(SUM(CASE WHEN event_type IN (:b1, :b2, :b3) THEN 1 ELSE 0 END), 0) AS c_bnc,'
                    . ' COALESCE(SUM(CASE WHEN event_type = :u THEN 1 ELSE 0 END), 0) AS c_uns'
                    . ' FROM ' . self::TABLE
                    . ' WHERE created_at >= DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)',
                [
                    'd' => 'delivered',
                    'o' => 'opened',
                    'c' => 'clicked',
                    'b1' => 'bounced',
                    'b2' => 'spam',
                    'b3' => 'invalid',
                    'u' => 'unsubscribed',
                ]
            );
            if (!is_array($row)) {
                return $empty;
            }

            return [
                'delivered' => (int) ($row['c_del'] ?? 0),
                'opened' => (int) ($row['c_opn'] ?? 0),
                'clicked' => (int) ($row['c_clk'] ?? 0),
                'bounced' => (int) ($row['c_bnc'] ?? 0),
                'unsubscribed' => (int) ($row['c_uns'] ?? 0),
            ];
        } catch (\Throwable $e) {
            error_log('[NewsletterEventRepository] getStatsSummaryLastDays: ' . $e->getMessage());

            return $empty;
        }
    }

    public static function deleteOlderThanDays(int $days): int
    {
        if (!self::isAvailable()) {
            return 0;
        }
        $days = max(1, min(3650, $days));

        try {
            return Database::getInstance()->execute(
                'DELETE FROM ' . self::TABLE . ' WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)'
            );
        } catch (\Throwable $e) {
            error_log('[NewsletterEventRepository] deleteOlderThanDays: ' . $e->getMessage());

            return 0;
        }
    }
}
