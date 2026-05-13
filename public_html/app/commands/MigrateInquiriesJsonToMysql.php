<?php

declare(strict_types=1);

namespace App\Commands;

use App\Core\Config;
use App\Core\InquiryRepository;
use App\Core\JsonStore;

/**
 * 将 app/data/inquiries.json 导入 MySQL inquiries 表（按 external_id 去重）。
 *
 *   php app/commands/MigrateInquiriesJsonToMysql.php
 */
final class MigrateInquiriesJsonToMysql
{
    private static bool $bootstrapped = false;

    public static function run(array $argv): int
    {
        self::bootstrap();

        if (!InquiryRepository::isAvailable()) {
            fwrite(STDERR, "[MigrateInquiries] inquiries 表不可用或数据库未连接。\n");
            return 1;
        }

        $path = DATA_PATH . '/inquiries.json';
        if (!is_file($path)) {
            fwrite(STDOUT, "[MigrateInquiries] 无 {$path}，跳过。\n");
            return 0;
        }

        try {
            $rows = JsonStore::globalData('inquiries')->read();
        } catch (\Throwable $e) {
            fwrite(STDERR, '[MigrateInquiries] 读取 JSON 失败: ' . $e->getMessage() . "\n");
            return 1;
        }

        if (!is_array($rows) || $rows === []) {
            fwrite(STDOUT, "[MigrateInquiries] JSON 为空。\n");
            return 0;
        }

        $ok = 0;
        $fail = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (InquiryRepository::migrateFromJsonRecord($row)) {
                ++$ok;
            } else {
                ++$fail;
            }
        }

        fwrite(STDOUT, sprintf("[MigrateInquiries] 完成: 成功=%d 跳过或失败=%d\n", $ok, $fail));

        return 0;
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
            fwrite(STDERR, "[MigrateInquiries] 缺少 {$siteJson}\n");
            exit(1);
        }
        Config::load($siteJson);

        self::$bootstrapped = true;
    }
}

if (PHP_SAPI === 'cli') {
    exit(MigrateInquiriesJsonToMysql::run($_SERVER['argv'] ?? []));
}
