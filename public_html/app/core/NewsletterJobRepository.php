<?php

namespace App\Core;

/**
 * MySQL queue for per-subscriber newsletter sends (see database.sql `newsletter_jobs`).
 * 失败自动重试：第 1/2/3 次失败后分别间隔 10 分钟、30 分钟、2 小时回到 pending；超过 max_retry 则永久 failed。
 */
final class NewsletterJobRepository
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public const TYPE_PRODUCT_UPDATE = 'product_update';
    public const TYPE_BLOG_POST = 'blog_post';
    public const TYPE_GENERAL = 'general';

    public const DEFAULT_MAX_RETRY = 3;

    private const TABLE = 'newsletter_jobs';

    /** 第 n 次失败后的重试间隔（秒）：第 1 次失败→10 分钟，第 2 次→30 分钟，第 3 次→2 小时 */
    private const RETRY_DELAY_AFTER_FAIL = [
        1 => 600,
        2 => 1800,
        3 => 7200,
    ];

    /** @var list<string> */
    private const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SENDING,
        self::STATUS_SENT,
        self::STATUS_FAILED,
    ];

    private static function selectColumnsSql(): string
    {
        return 'id, subscriber_id, subject, content_html, content_text, type, status, send_at, sent_at, error_message,'
            . ' retry_count, max_retry, next_retry_at, last_retry_at, provider_message_id, created_at';
    }

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
     * 创建任务（pending，send_at 起可被抓取）
     *
     * @param string|null $sendAt 'Y-m-d H:i:s' 或 null 表示立即可发送
     * @param int|null $maxRetry 失败自动重试上限，默认 {@see DEFAULT_MAX_RETRY}
     * @return int 新任务 id，失败返回 0
     */
    public static function createJob(
        int $subscriberId,
        string $subject,
        string $contentHtml,
        string $contentText,
        string $type,
        ?string $sendAt = null,
        ?int $maxRetry = null
    ): int {
        if ($subscriberId <= 0) {
            return 0;
        }
        $subject = mb_substr(trim($subject), 0, 512, 'UTF-8');
        if ($subject === '') {
            return 0;
        }
        $type = self::sanitizeType($type);
        $contentHtml = $contentHtml === '' ? '<p></p>' : $contentHtml;
        $contentText = mb_substr($contentText, 0, 65000, 'UTF-8');

        $sendAtSql = $sendAt !== null && $sendAt !== '' ? $sendAt : date('Y-m-d H:i:s');
        $mr = $maxRetry !== null ? max(0, min(255, $maxRetry)) : self::DEFAULT_MAX_RETRY;

        try {
            $id = Database::getInstance()->insert(
                'INSERT INTO ' . self::TABLE . ' (
                    subscriber_id, subject, content_html, content_text, type, status, send_at, sent_at, error_message,
                    retry_count, max_retry, next_retry_at, last_retry_at, provider_message_id
                ) VALUES (
                    :sid, :subj, :html, :text, :typ, :st, :send_at, NULL, NULL,
                    0, :mr, NULL, NULL, NULL
                )',
                [
                    'sid' => $subscriberId,
                    'subj' => $subject,
                    'html' => $contentHtml,
                    'text' => $contentText,
                    'typ' => $type,
                    'st' => self::STATUS_PENDING,
                    'send_at' => $sendAtSql,
                    'mr' => $mr,
                ]
            );

            return $id > 0 ? $id : 0;
        } catch (\Throwable $e) {
            error_log('[NewsletterJobRepository] createJob: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 获取待发送任务：pending 且 send_at <= 当前时间，按 id 升序
     *
     * @return list<array<string, mixed>>
     */
    public static function fetchPendingDue(int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        try {
            return Database::getInstance()->fetchAll(
                'SELECT ' . self::selectColumnsSql()
                    . ' FROM ' . self::TABLE
                    . ' WHERE status = :st AND send_at <= NOW() ORDER BY id ASC LIMIT ' . (int) $limit,
                ['st' => self::STATUS_PENDING]
            );
        } catch (\Throwable $e) {
            error_log('[NewsletterJobRepository] fetchPendingDue: ' . $e->getMessage());
            return [];
        }
    }

    /** @deprecated 使用 fetchPendingDue */
    public static function listPendingDue(int $limit = 50): array
    {
        return self::fetchPendingDue($limit);
    }

    /**
     * 抢占任务：pending → sending（发送前调用）
     */
    public static function tryMarkSending(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        try {
            $n = Database::getInstance()->execute(
                'UPDATE ' . self::TABLE . ' SET status = :sending'
                    . ' WHERE id = :id AND status = :pending',
                [
                    'id' => $id,
                    'pending' => self::STATUS_PENDING,
                    'sending' => self::STATUS_SENDING,
                ]
            );

            return $n > 0;
        } catch (\Throwable $e) {
            error_log('[NewsletterJobRepository] tryMarkSending: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 标记发送成功：sending → sent，写入 sent_at、可选 provider_message_id，并重置重试相关字段
     */
    public static function markSent(int $id, ?string $providerMessageId = null): bool
    {
        if ($id <= 0) {
            return false;
        }
        $mid = null;
        if ($providerMessageId !== null && trim($providerMessageId) !== '') {
            $mid = mb_substr(trim($providerMessageId), 0, 128, 'UTF-8');
        }
        try {
            if ($mid !== null) {
                $n = Database::getInstance()->execute(
                    'UPDATE ' . self::TABLE . ' SET status = :sent, sent_at = CURRENT_TIMESTAMP, error_message = NULL,'
                        . ' retry_count = 0, next_retry_at = NULL, last_retry_at = NULL, provider_message_id = :mid'
                        . ' WHERE id = :id AND status = :sending',
                    [
                        'id' => $id,
                        'sending' => self::STATUS_SENDING,
                        'sent' => self::STATUS_SENT,
                        'mid' => $mid,
                    ]
                );
            } else {
                $n = Database::getInstance()->execute(
                    'UPDATE ' . self::TABLE . ' SET status = :sent, sent_at = CURRENT_TIMESTAMP, error_message = NULL,'
                        . ' retry_count = 0, next_retry_at = NULL, last_retry_at = NULL'
                        . ' WHERE id = :id AND status = :sending',
                    [
                        'id' => $id,
                        'sending' => self::STATUS_SENDING,
                        'sent' => self::STATUS_SENT,
                    ]
                );
            }

            return $n > 0;
        } catch (\Throwable $e) {
            error_log('[NewsletterJobRepository] markSent: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 永久失败（不重试）：用于订阅者缺失/停用或不可恢复错误。
     */
    public static function markTerminalFailed(int $id, string $errorMessage): bool
    {
        if ($id <= 0) {
            return false;
        }
        $err = mb_substr(trim($errorMessage), 0, 65000, 'UTF-8');
        if ($err === '') {
            $err = 'aborted';
        }
        try {
            $n = Database::getInstance()->execute(
                'UPDATE ' . self::TABLE . ' SET status = :failed, error_message = :err, next_retry_at = NULL, last_retry_at = CURRENT_TIMESTAMP'
                    . ' WHERE id = :id AND (status = :pending OR status = :sending)',
                [
                    'id' => $id,
                    'pending' => self::STATUS_PENDING,
                    'sending' => self::STATUS_SENDING,
                    'failed' => self::STATUS_FAILED,
                    'err' => $err,
                ]
            );

            return $n > 0;
        } catch (\Throwable $e) {
            error_log('[NewsletterJobRepository] markTerminalFailed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 标记发送失败：若未超过 max_retry 则回到 pending 并设置 send_at / next_retry_at；
     * 否则标记为 failed。重试间隔：第 1 次失败 10 分钟、第 2 次 30 分钟、第 3 次 2 小时。
     */
    public static function markFailed(int $id, string $errorMessage): bool
    {
        if ($id <= 0) {
            return false;
        }
        $err = mb_substr(trim($errorMessage), 0, 65000, 'UTF-8');
        if ($err === '') {
            $err = 'unknown error';
        }

        $db = Database::getInstance();
        $startedHere = $db->beginTransaction();
        try {
            $row = $db->fetch(
                'SELECT id, retry_count, max_retry FROM ' . self::TABLE
                    . ' WHERE id = :id AND status = :sending'
                    . ($startedHere ? ' FOR UPDATE' : ''),
                ['id' => $id, 'sending' => self::STATUS_SENDING]
            );
            if (!is_array($row)) {
                if ($startedHere) {
                    $db->rollback();
                }
                return false;
            }

            $rc = (int) ($row['retry_count'] ?? 0);
            $mr = (int) ($row['max_retry'] ?? self::DEFAULT_MAX_RETRY);
            if ($mr <= 0) {
                $mr = self::DEFAULT_MAX_RETRY;
            }
            $newRc = $rc + 1;

            if ($newRc > $mr) {
                $n = $db->execute(
                    'UPDATE ' . self::TABLE . ' SET status = :failed, error_message = :err, retry_count = :nrc,'
                        . ' next_retry_at = NULL, last_retry_at = CURRENT_TIMESTAMP'
                        . ' WHERE id = :id AND status = :sending',
                    [
                        'id' => $id,
                        'sending' => self::STATUS_SENDING,
                        'failed' => self::STATUS_FAILED,
                        'err' => $err,
                        'nrc' => $newRc,
                    ]
                );
            } else {
                $delay = self::retryDelaySeconds($newRc);
                $nextAt = date('Y-m-d H:i:s', time() + $delay);
                $n = $db->execute(
                    'UPDATE ' . self::TABLE . ' SET status = :pending, error_message = :err, retry_count = :nrc,'
                        . ' send_at = :next, next_retry_at = :next2, last_retry_at = CURRENT_TIMESTAMP'
                        . ' WHERE id = :id AND status = :sending',
                    [
                        'id' => $id,
                        'sending' => self::STATUS_SENDING,
                        'pending' => self::STATUS_PENDING,
                        'err' => $err,
                        'nrc' => $newRc,
                        'next' => $nextAt,
                        'next2' => $nextAt,
                    ]
                );
            }

            if ($startedHere) {
                $db->commit();
            }

            return $n > 0;
        } catch (\Throwable $e) {
            if ($startedHere) {
                $db->rollback();
            }
            error_log('[NewsletterJobRepository] markFailed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 当前为第几次失败（1-based）对应的重试延迟秒数
     */
    private static function retryDelaySeconds(int $failureCount): int
    {
        return self::RETRY_DELAY_AFTER_FAIL[$failureCount] ?? self::RETRY_DELAY_AFTER_FAIL[3];
    }

    public static function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        try {
            $row = Database::getInstance()->fetch(
                'SELECT ' . self::selectColumnsSql() . ' FROM ' . self::TABLE . ' WHERE id = :id',
                ['id' => $id]
            );

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            error_log('[NewsletterJobRepository] findById: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 按 Brevo 返回的 messageId 查找已发送任务（用于 Webhook 关联）。
     *
     * @return array{id: int, subscriber_id: int}|null
     */
    public static function findByProviderMessageId(string $rawMessageId): ?array
    {
        $raw = trim($rawMessageId);
        if ($raw === '') {
            return null;
        }
        $stripped = trim($raw, "<> \t\r\n");
        $candidates = array_values(array_unique(array_filter([
            mb_substr($raw, 0, 128, 'UTF-8'),
            mb_substr($stripped, 0, 128, 'UTF-8'),
        ])));

        foreach ($candidates as $m) {
            if ($m === '') {
                continue;
            }
            try {
                $row = Database::getInstance()->fetch(
                    'SELECT id, subscriber_id FROM ' . self::TABLE . ' WHERE provider_message_id = :m LIMIT 1',
                    ['m' => $m]
                );
                if (is_array($row)) {
                    return [
                        'id' => (int) ($row['id'] ?? 0),
                        'subscriber_id' => (int) ($row['subscriber_id'] ?? 0),
                    ];
                }
            } catch (\Throwable $e) {
                error_log('[NewsletterJobRepository] findByProviderMessageId: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listByStatus(string $status, int $limit = 100, int $offset = 0): array
    {
        if (!self::isValidStatus($status)) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        try {
            return Database::getInstance()->fetchAll(
                'SELECT ' . self::selectColumnsSql() . ' FROM ' . self::TABLE
                    . ' WHERE status = :st ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
                ['st' => $status]
            );
        } catch (\Throwable $e) {
            error_log('[NewsletterJobRepository] listByStatus: ' . $e->getMessage());
            return [];
        }
    }

    public static function countByStatus(string $status): int
    {
        if (!self::isValidStatus($status)) {
            return 0;
        }
        try {
            return (int) Database::getInstance()->fetchColumn(
                'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE status = :st',
                ['st' => $status]
            );
        } catch (\Throwable $e) {
            error_log('[NewsletterJobRepository] countByStatus: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 队列任务统计（用于后台订阅/发送看板）。
     *
     * @return array{
     *   total: int,
     *   sent: int,
     *   failed: int,
     *   pending: int,
     *   sending: int,
     *   queued: int
     * }
     */
    public static function getSendStats(): array
    {
        $empty = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'pending' => 0,
            'sending' => 0,
            'queued' => 0,
        ];
        try {
            $row = Database::getInstance()->fetch(
                'SELECT COUNT(*) AS total,'
                    . ' COALESCE(SUM(CASE WHEN status = :st_sent THEN 1 ELSE 0 END), 0) AS n_sent,'
                    . ' COALESCE(SUM(CASE WHEN status = :st_fail THEN 1 ELSE 0 END), 0) AS n_failed,'
                    . ' COALESCE(SUM(CASE WHEN status = :st_pend THEN 1 ELSE 0 END), 0) AS n_pending,'
                    . ' COALESCE(SUM(CASE WHEN status = :st_send THEN 1 ELSE 0 END), 0) AS n_sending'
                    . ' FROM ' . self::TABLE,
                [
                    'st_sent' => self::STATUS_SENT,
                    'st_fail' => self::STATUS_FAILED,
                    'st_pend' => self::STATUS_PENDING,
                    'st_send' => self::STATUS_SENDING,
                ]
            );
            if (!is_array($row)) {
                return $empty;
            }
            $total = (int) ($row['total'] ?? 0);
            $sent = (int) ($row['n_sent'] ?? 0);
            $failed = (int) ($row['n_failed'] ?? 0);
            $pending = (int) ($row['n_pending'] ?? 0);
            $sending = (int) ($row['n_sending'] ?? 0);

            return [
                'total' => $total,
                'sent' => $sent,
                'failed' => $failed,
                'pending' => $pending,
                'sending' => $sending,
                'queued' => $pending + $sending,
            ];
        } catch (\Throwable $e) {
            error_log('[NewsletterJobRepository] getSendStats: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * 后台任务历史分页。
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function listJobsPaginated(int $page, int $perPage, ?string $statusFilter = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where = '1=1';
        if ($statusFilter !== null && $statusFilter !== '' && self::isValidStatus($statusFilter)) {
            $where .= ' AND status = :fst';
            $params['fst'] = $statusFilter;
        }
        $empty = ['rows' => [], 'total' => 0];
        try {
            $db = Database::getInstance();
            $total = (int) $db->fetchColumn('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE ' . $where, $params);
            $sql = 'SELECT ' . self::selectColumnsSql() . ' FROM ' . self::TABLE
                . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
            $rows = $db->fetchAll($sql, $params);

            return ['rows' => $rows, 'total' => $total];
        } catch (\Throwable $e) {
            error_log('[NewsletterJobRepository] listJobsPaginated: ' . $e->getMessage());

            return $empty;
        }
    }

    private static function sanitizeType(string $type): string
    {
        $type = preg_replace('/[^a-z0-9_\-]/i', '', strtolower(trim($type)));
        if ($type === '') {
            return self::TYPE_GENERAL;
        }

        return substr($type, 0, 32);
    }

    private static function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }
}
