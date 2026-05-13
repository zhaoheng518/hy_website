<?php

namespace App\Core;

/**
 * Sliding-window rate limits for inquiry submits: same IP and same email (both must pass).
 * State: app/data/.inquiry_rate_by_ip.json — v2 shape: { "v": 2, "ip": { "hash": [unix,...] }, "email": { "hash": [...] } }.
 * Legacy file (flat { "hash": [...] } }) is treated as IP-only buckets on read.
 * Entries older than {@see RETENTION_SECONDS} are stripped and empty buckets removed on each lock (bounds JSON growth).
 * Rejections: app/data/.inquiry_rate_limit.log (JSON lines).
 */
final class RateLimiter
{
    private const WINDOW_SECONDS = 300;

    /** 状态文件内仅保留该时间内的打点；更旧的时间戳与空桶删除，避免 ip/email 键无限累积 */
    private const RETENTION_SECONDS = 86400;

    private const IP_MAX_SUBMITS = 3;
    private const EMAIL_MAX_SUBMITS = 2;

    private const STATE_BASENAME = '.inquiry_rate_by_ip.json';
    private const LOG_BASENAME = '.inquiry_rate_limit.log';

    /**
     * True if this IP and this email may submit (IP: max 3 / 5 min; email: max 2 / 5 min).
     * Empty email skips the email bucket (IP-only).
     *
     * @param string $ip    客户端 IP（建议 {@see ClientIp::get()}）
     * @param string $email 原始邮箱；与 IP 各自独立计数，两者均通过才返回 true
     */
    public static function checkInquirySubmit(string $ip, string $email = ''): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            $ip = '0.0.0.0';
        }

        $now = time();
        $result = self::withStateLock(function (array $norm) use ($ip, $email, $now): array {
            $ipKey = self::ipKey($ip);
            $ipList = self::pruneTimestamps($norm['ip'][$ipKey] ?? [], $now);
            if (count($ipList) >= self::IP_MAX_SUBMITS) {
                self::logRejection($now, $ip, count($ipList), 'ip', null);
                return ['ok' => false, 'state' => $norm];
            }

            $em = self::normalizeEmail($email);
            if ($em !== '') {
                $emailKey = self::emailKey($em);
                $emailList = self::pruneTimestamps($norm['email'][$emailKey] ?? [], $now);
                if (count($emailList) >= self::EMAIL_MAX_SUBMITS) {
                    self::logRejection($now, $ip, count($emailList), 'email', substr(hash('sha256', $em), 0, 16));
                    return ['ok' => false, 'state' => $norm];
                }
            }

            return ['ok' => true, 'state' => $norm];
        });

        return $result['ok'] ?? true;
    }

    /**
     * Record a successful inquiry save for IP and email (after JsonStore::update succeeds).
     */
    public static function recordInquirySubmit(string $ip, string $email = ''): void
    {
        $ip = trim($ip);
        if ($ip === '') {
            $ip = '0.0.0.0';
        }

        $now = time();
        try {
            self::withStateLock(function (array $norm) use ($ip, $email, $now): array {
                $ipKey = self::ipKey($ip);
                $ipList = self::pruneTimestamps($norm['ip'][$ipKey] ?? [], $now);
                $ipList[] = $now;
                sort($ipList, SORT_NUMERIC);
                $norm['ip'][$ipKey] = $ipList;

                $em = self::normalizeEmail($email);
                if ($em !== '') {
                    $emailKey = self::emailKey($em);
                    $emailList = self::pruneTimestamps($norm['email'][$emailKey] ?? [], $now);
                    $emailList[] = $now;
                    sort($emailList, SORT_NUMERIC);
                    $norm['email'][$emailKey] = $emailList;
                }

                return ['ok' => true, 'state' => $norm, 'write' => true];
            });
        } catch (\Throwable $e) {
            error_log('[RateLimiter] recordInquirySubmit: ' . $e->getMessage());
        }
    }

    private static function ipKey(string $ip): string
    {
        return hash('sha256', $ip);
    }

    private static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private static function emailKey(string $normalizedEmail): string
    {
        return hash('sha256', $normalizedEmail);
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{ip: array<string, list<int>>, email: array<string, list<int>>}
     */
    private static function migrateState(array $raw): array
    {
        if (isset($raw['ip']) && is_array($raw['ip'])) {
            $email = isset($raw['email']) && is_array($raw['email']) ? self::coerceBucket($raw['email']) : [];

            return ['ip' => self::coerceBucket($raw['ip']), 'email' => $email];
        }

        $ip = [];
        foreach ($raw as $k => $v) {
            if ($k === 'v' || $k === 'ip' || $k === 'email') {
                continue;
            }
            if (is_array($v)) {
                $ip[(string) $k] = self::coerceTimestampList($v);
            }
        }

        return ['ip' => $ip, 'email' => []];
    }

    /**
     * @param array<string, mixed> $bucket
     * @return array<string, list<int>>
     */
    private static function coerceBucket(array $bucket): array
    {
        $out = [];
        foreach ($bucket as $k => $v) {
            if (!is_array($v)) {
                continue;
            }
            $out[(string) $k] = self::coerceTimestampList($v);
        }

        return $out;
    }

    /**
     * @param list<mixed> $list
     * @return list<int>
     */
    private static function coerceTimestampList(array $list): array
    {
        $out = [];
        foreach ($list as $t) {
            $out[] = (int) $t;
        }

        return array_values($out);
    }

    /**
     * @param array{ip: array<string, list<int>>, email: array<string, list<int>>} $norm
     * @return array{v: int, ip: array<string, list<int>>, email: array<string, list<int>>}
     */
    private static function toDiskState(array $norm): array
    {
        return [
            'v' => 2,
            'ip' => $norm['ip'],
            'email' => $norm['email'],
        ];
    }

    /**
     * @param list<int|float> $timestamps
     * @return list<int>
     */
    private static function pruneTimestamps(array $timestamps, int $now): array
    {
        $cutoff = $now - self::WINDOW_SECONDS;
        $out = [];
        foreach ($timestamps as $t) {
            $ti = (int) $t;
            if ($ti >= $cutoff) {
                $out[] = $ti;
            }
        }

        return array_values($out);
    }

    /**
     * 删除超过 {@see RETENTION_SECONDS} 的时间戳；若桶为空则去掉该键（IP / email 两侧均处理，防止 JSON 无限增长）。
     *
     * @param array{ip: array<string, list<int>>, email: array<string, list<int>>} $norm
     * @return array{0: array{ip: array<string, list<int>>, email: array<string, list<int>>}, 1: bool} [state, changed]
     */
    private static function pruneStaleState(array $norm, int $now): array
    {
        $cutoff = $now - self::RETENTION_SECONDS;
        $changed = false;

        foreach (['ip', 'email'] as $dim) {
            if (!isset($norm[$dim]) || !is_array($norm[$dim])) {
                continue;
            }
            foreach ($norm[$dim] as $key => $list) {
                if (!is_array($list)) {
                    unset($norm[$dim][$key]);
                    $changed = true;
                    continue;
                }
                $kept = [];
                foreach ($list as $t) {
                    $ti = (int) $t;
                    if ($ti >= $cutoff) {
                        $kept[] = $ti;
                    }
                }
                if (count($kept) !== count($list)) {
                    $changed = true;
                }
                if ($kept === []) {
                    unset($norm[$dim][$key]);
                    $changed = true;
                } else {
                    $norm[$dim][$key] = array_values($kept);
                }
            }
        }

        return [$norm, $changed];
    }

    private static function logRejection(int $unixTs, string $ip, int $countInWindow, string $dimension, ?string $emailFingerprint): void
    {
        $dir = defined('DATA_PATH') ? DATA_PATH : '';
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $payload = [
            'timestamp' => date('c', $unixTs),
            'ip' => $ip,
            'count' => $countInWindow,
            'dimension' => $dimension,
            'reason' => 'too_many_requests',
        ];
        if ($emailFingerprint !== null && $emailFingerprint !== '') {
            $payload['email_fingerprint'] = $emailFingerprint;
        }

        $line = json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";

        @file_put_contents($dir . '/' . self::LOG_BASENAME, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param callable(array{ip: array<string, list<int>>, email: array<string, list<int>>}):array $fn
     * @return array{ok: bool}
     */
    private static function withStateLock(callable $fn): array
    {
        $dir = defined('DATA_PATH') ? DATA_PATH : '';
        if ($dir === '' || !is_dir($dir)) {
            return ['ok' => true];
        }

        $path = $dir . '/' . self::STATE_BASENAME;
        $fh = @fopen($path, 'c+');
        if ($fh === false) {
            error_log('[RateLimiter] cannot open state file: ' . $path);

            return ['ok' => true];
        }

        $locked = false;
        $start = microtime(true);
        while (microtime(true) - $start < 5.0) {
            if (flock($fh, LOCK_EX | LOCK_NB)) {
                $locked = true;
                break;
            }
            usleep(50000);
        }

        if (!$locked) {
            if (!flock($fh, LOCK_EX)) {
                fclose($fh);
                error_log('[RateLimiter] lock timeout: ' . $path);

                return ['ok' => true];
            }
        }

        try {
            rewind($fh);
            $raw = stream_get_contents($fh);
            $decoded = [];
            if (is_string($raw) && trim($raw) !== '') {
                $tmp = json_decode($raw, true);
                if (is_array($tmp)) {
                    $decoded = $tmp;
                }
            }

            $now = time();
            $norm = self::migrateState($decoded);
            [$norm, $purgedStale] = self::pruneStaleState($norm, $now);
            $out = $fn($norm);
            $newNorm = $out['state'] ?? $norm;
            $mustWrite = !empty($out['write']) || $purgedStale;
            if ($mustWrite) {
                rewind($fh);
                ftruncate($fh, 0);
                $disk = self::toDiskState($newNorm);
                $encoded = json_encode($disk, JSON_UNESCAPED_UNICODE);
                if ($encoded !== false) {
                    fwrite($fh, $encoded);
                    fflush($fh);
                }
            }

            return ['ok' => (bool) ($out['ok'] ?? true)];
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}
