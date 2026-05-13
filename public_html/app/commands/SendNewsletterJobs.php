<?php

declare(strict_types=1);

namespace App\Commands;

use App\Core\Config;
use App\Core\NewsletterJobRepository;
use App\Core\NewsletterMailer;
use App\Core\NewsletterRepository;

/**
 * 邮件队列消费者（CLI）。
 *
 * 用法（在项目根目录）：
 *   php app/commands/SendNewsletterJobs.php
 *   php app/commands/SendNewsletterJobs.php --limit=100
 *
 * Cron：每 5 分钟执行一次，例如 crontab 中写（星号间隔按主机要求填写）：
 *   cd /path/to/public_html && php app/commands/SendNewsletterJobs.php >> app/data/newsletter_worker.log 2>&1
 */
final class SendNewsletterJobs
{
    private static bool $bootstrapped = false;

    public static function run(array $argv): int
    {
        self::bootstrap();

        if (!NewsletterJobRepository::isAvailable()) {
            fwrite(STDERR, "[SendNewsletterJobs] newsletter_jobs 不可用。\n");
            return 1;
        }
        if (!NewsletterRepository::isAvailable()) {
            fwrite(STDERR, "[SendNewsletterJobs] newsletter_subscribers 不可用。\n");
            return 1;
        }
        if (!NewsletterMailer::isConfigured()) {
            fwrite(STDERR, "[SendNewsletterJobs] Brevo 未配置（site.json: brevo_api_key / brevo_sender_email）。\n");
            return 1;
        }

        $lockPath = DATA_PATH . '/.newsletter_worker.lock';
        $lockHandle = @fopen($lockPath, 'c+');
        if ($lockHandle === false) {
            fwrite(STDERR, "[SendNewsletterJobs] cannot open lock file: {$lockPath}\n");
            return 1;
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fwrite(STDOUT, "[SendNewsletterJobs] another worker is running.\n");
            fclose($lockHandle);

            return 0;
        }

        try {
            $limit = self::parseLimit($argv);
            $jobs = NewsletterJobRepository::fetchPendingDue($limit);
            if ($jobs === []) {
                fwrite(STDOUT, "[SendNewsletterJobs] 无待发送任务。\n");

                return 0;
            }

            $sent = 0;
            $failed = 0;
            $skipped = 0;

            foreach ($jobs as $job) {
                $jobId = (int) ($job['id'] ?? 0);
                $subscriberId = (int) ($job['subscriber_id'] ?? 0);
                if ($jobId <= 0 || $subscriberId <= 0) {
                    ++$skipped;
                    continue;
                }

                $subscriber = NewsletterRepository::findSubscriberById($subscriberId);
                if ($subscriber === null || empty($subscriber['is_active'])) {
                    NewsletterJobRepository::markTerminalFailed(
                        $jobId,
                        $subscriber === null ? 'subscriber not found' : 'subscriber inactive'
                    );
                    ++$skipped;
                    continue;
                }

                if (!NewsletterJobRepository::tryMarkSending($jobId)) {
                    continue;
                }

                $sendResult = ['ok' => false, 'message_id' => null];
                try {
                    $sendResult = NewsletterMailer::sendForQueuedJobWithResult($job, [
                        'email' => $subscriber['email'],
                        'lang' => $subscriber['lang'],
                        'unsubscribe_token' => $subscriber['unsubscribe_token'],
                    ]);
                    $ok = !empty($sendResult['ok']);
                } catch (\Throwable $e) {
                    error_log('[SendNewsletterJobs] job ' . $jobId . ': ' . $e->getMessage());
                    $ok = false;
                }

                if ($ok) {
                    $mid = isset($sendResult['message_id']) ? (string) $sendResult['message_id'] : '';
                    if (NewsletterJobRepository::markSent($jobId, $mid !== '' ? $mid : null)) {
                        ++$sent;
                    } else {
                        error_log('[SendNewsletterJobs] markSent failed for job id=' . $jobId);
                    }
                } else {
                    if (NewsletterJobRepository::markFailed($jobId, 'Brevo send failed or rejected')) {
                        ++$failed;
                    }
                }
            }

            fwrite(STDOUT, sprintf(
                "[SendNewsletterJobs] 完成: sent=%d failed_attempts=%d skipped=%d batch_limit=%d\n",
                $sent,
                $failed,
                $skipped,
                $limit
            ));

            return 0;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private static function parseLimit(array $argv): int
    {
        $limit = 50;
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--limit=')) {
                $v = (int) substr($arg, 8);
                if ($v > 0) {
                    $limit = min(500, $v);
                }
                break;
            }
        }

        return $limit;
    }

    private static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        if (!defined('APP_PATH')) {
            define('APP_PATH', dirname(__DIR__));
        }
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(APP_PATH));
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
        if (!is_file($siteJson)) {
            fwrite(STDERR, "[SendNewsletterJobs] 缺少配置文件: {$siteJson}\n");
            exit(1);
        }
        Config::load($siteJson);

        self::$bootstrapped = true;
    }
}

if (PHP_SAPI === 'cli') {
    exit(SendNewsletterJobs::run($_SERVER['argv'] ?? []));
}
