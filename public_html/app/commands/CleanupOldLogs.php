<?php

declare(strict_types=1);

namespace App\Commands;

use App\Core\Config;
use App\Core\NewsletterEventRepository;

/**
 * 定时清理：newsletter_events、询盘限流日志、询盘反垃圾 JSON 日志。
 *
 *   php app/commands/CleanupOldLogs.php
 */
final class CleanupOldLogs
{
    private const RATE_LIMIT_LOG = '.inquiry_rate_limit.log';
    private const SPAM_LOG = '.inquiry_spam.log';
    private const SPAM_KEYWORD_LOG = '.inquiry_spam_keywords.log';

    private static bool $bootstrapped = false;

    public static function run(array $argv): int
    {
        self::bootstrap();

        $deletedEvents = 0;
        if (NewsletterEventRepository::isAvailable()) {
            $deletedEvents = NewsletterEventRepository::deleteOlderThanDays(180);
        }

        $ratePruned = self::pruneJsonLinesByAge(DATA_PATH . '/' . self::RATE_LIMIT_LOG, 30);
        $spamTailed = self::tailLogFile(DATA_PATH . '/' . self::SPAM_LOG, 5000);
        $kwTailed = self::tailLogFile(DATA_PATH . '/' . self::SPAM_KEYWORD_LOG, 5000);

        fwrite(STDOUT, "cleanup completed\n");
        fwrite(STDOUT, sprintf(
            "details: newsletter_events_deleted=%d rate_log_pruned=%s spam_log_tailed=%s spam_kw_tailed=%s\n",
            $deletedEvents,
            $ratePruned ? 'yes' : 'skip',
            $spamTailed ? 'yes' : 'skip',
            $kwTailed ? 'yes' : 'skip'
        ));

        return 0;
    }

    private static function pruneJsonLinesByAge(string $path, int $keepDays): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }
        $cutoff = strtotime('-' . max(1, min(3650, $keepDays)) . ' days');
        if ($cutoff === false) {
            return false;
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return true;
        }

        $lines = preg_split('/\r\n|\n|\r/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($lines)) {
            return false;
        }

        $kept = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (!is_array($row)) {
                $kept[] = $line;
                continue;
            }
            $ts = strtotime((string) ($row['timestamp'] ?? ''));
            if ($ts === false) {
                $kept[] = $line;
                continue;
            }
            if ($ts >= $cutoff) {
                $kept[] = $line;
            }
        }

        $out = $kept === [] ? '' : implode("\n", $kept) . "\n";
        if (file_put_contents($path, $out, LOCK_EX) === false) {
            error_log('[CleanupOldLogs] failed writing ' . $path);

            return false;
        }

        return true;
    }

    private static function tailLogFile(string $path, int $maxLines): bool
    {
        $maxLines = max(100, min(100000, $maxLines));
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return false;
        }

        $lines = preg_split('/\r\n|\n|\r/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($lines) || count($lines) <= $maxLines) {
            return true;
        }

        $tail = array_slice($lines, -$maxLines);
        $out = implode("\n", $tail) . "\n";
        if (file_put_contents($path, $out, LOCK_EX) === false) {
            error_log('[CleanupOldLogs] failed tail write ' . $path);

            return false;
        }

        return true;
    }

    private static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        if (!defined('APP_PATH')) {
            define('APP_PATH', dirname(__DIR__));
        }
        if (!defined('DATA_PATH')) {
            define('DATA_PATH', APP_PATH . '/data');
        }

        spl_autoload_register(static function (string $class): void {
            $prefix = 'App\\';
            $baseDir = APP_PATH . '/';
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
            $relativeClass = substr($class, $len);
            $parts = explode('\\', $relativeClass);
            $fileName = array_pop($parts);
            $parts = array_map('strtolower', $parts);
            $file = $baseDir . implode('/', $parts) . '/' . $fileName . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });

        $siteJson = DATA_PATH . '/site.json';
        if (is_file($siteJson)) {
            Config::load($siteJson);
        }

        self::$bootstrapped = true;
    }
}

if (PHP_SAPI === 'cli') {
    exit(CleanupOldLogs::run($_SERVER['argv'] ?? []));
}
