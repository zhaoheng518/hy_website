<?php

declare(strict_types=1);

namespace App\Commands;

use App\Core\Config;
use App\Core\SeoAuditService;

/**
 * CLI SEO Health Scanner
 *
 * Usage (from project root):
 *   php app/commands/SeoAudit.php
 *   php app/commands/SeoAudit.php --lang=en
 *   php app/commands/SeoAudit.php --type=product
 *   php app/commands/SeoAudit.php --lang=en --type=blog
 *   php app/commands/SeoAudit.php --format=json
 *   php app/commands/SeoAudit.php --lang=cn --type=product --format=json
 *   php app/commands/SeoAudit.php --limit=200
 *   php app/commands/SeoAudit.php --lang=en --type=product --limit=100 --format=json
 *
 * Exit codes:
 *   0  No issues found (or only INFO level)
 *   1  Warning-level issues found
 *   2  Critical-level issues found
 *   99 Bootstrap / config error
 */
final class SeoAudit
{
    private static bool $bootstrapped = false;

    public static function run(array $argv): int
    {
        self::bootstrap();

        $filterLang = self::parseArg($argv, '--lang=', '');
        $filterType = self::parseArg($argv, '--type=', '');
        $format     = self::parseArg($argv, '--format=', 'text');
        $limitRaw   = self::parseArg($argv, '--limit=', '500');
        $limit      = max(1, (int) $limitRaw);

        $langs = Config::get('supported_langs', ['en', 'cn', 'es']);

        // Validate filters
        if ($filterLang !== '' && !in_array($filterLang, $langs, true)) {
            fwrite(STDERR, "[SeoAudit] Unknown language '{$filterLang}'. Supported: " . implode(', ', $langs) . "\n");
            return 99;
        }
        $validTypes = ['product', 'blog', 'case', 'page'];
        if ($filterType !== '' && !in_array($filterType, $validTypes, true)) {
            fwrite(STDERR, "[SeoAudit] Unknown type '{$filterType}'. Supported: " . implode(', ', $validTypes) . "\n");
            return 99;
        }

        $service = new SeoAuditService(DATA_PATH, $langs);
        $result  = $service->run($filterLang, $filterType, $limit);

        if ($format === 'json') {
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        } else {
            self::printTextReport($result, $filterLang, $filterType);
        }

        $summary = $result['summary'] ?? [];
        if (($summary['critical'] ?? 0) > 0) {
            return 2;
        }
        if (($summary['warning'] ?? 0) > 0) {
            return 1;
        }
        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private static function printTextReport(array $result, string $filterLang, string $filterType): void
    {
        $summary    = $result['summary']     ?? [];
        $issues     = $result['issues']      ?? [];
        $totalPages = $result['total_pages'] ?? 0;
        $scannedAt  = $result['scanned_at']  ?? date('Y-m-d H:i:s');

        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                       SEO Health Audit Report                           ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "Generated       : {$scannedAt}\n";
        echo "Filter          : lang=" . ($filterLang ?: 'all') . "  type=" . ($filterType ?: 'all') . "\n";
        echo "Scanned Items   : {$totalPages}\n";
        echo "Total Issues    : " . ($summary['total']    ?? 0) . "\n";
        echo "  Critical      : " . ($summary['critical'] ?? 0) . "\n";
        echo "  Warning       : " . ($summary['warning']  ?? 0) . "\n";
        echo "  Info          : " . ($summary['info']     ?? 0) . "\n";
        echo "  Missing ALT   : " . ($summary['missing_alt']        ?? 0) . "\n";
        echo "  Meta ALT Fill : " . ($summary['media_missing_alt']  ?? 0) . "\n";
        echo "  Missing Dims  : " . ($summary['missing_dimensions']  ?? 0) . "\n";
        echo "  Non-WebP      : " . ($summary['non_webp_images']     ?? 0) . "\n";
        echo "  Broken Links  : " . ($summary['broken_links']        ?? 0) . "\n";
        echo "  Missing OG    : " . ($summary['missing_og_image']    ?? 0) . "\n";
        echo "  Slug Issues   : " . ($summary['slug_issues']         ?? 0) . "\n";
        echo "  Security Warn : " . ($summary['security_warnings']   ?? 0) . "\n";
        if (!empty($summary['robots_blocked'])) {
            echo "  !! ROBOTS BLOCK ALL IS ON — SITE MAY BE DEINDEXED !!\n";
        }
        echo "\n";

        // Group by severity for cleaner output
        $bySeverity = ['critical' => [], 'warning' => [], 'info' => []];
        foreach ($issues as $issue) {
            $sev = (string) ($issue['severity'] ?? 'info');
            if (!isset($bySeverity[$sev])) {
                $bySeverity[$sev] = [];
            }
            $bySeverity[$sev][] = $issue;
        }

        $labels = ['critical' => '[ CRITICAL ]', 'warning' => '[  WARNING ]', 'info' => '[   INFO   ]'];

        foreach (['critical', 'warning', 'info'] as $sev) {
            if (empty($bySeverity[$sev])) {
                continue;
            }
            $sevLabel = $labels[$sev];
            echo str_repeat('─', 78) . "\n";
            echo " {$sevLabel}  " . count($bySeverity[$sev]) . " issue(s)\n";
            echo str_repeat('─', 78) . "\n";

            foreach ($bySeverity[$sev] as $issue) {
                $type = (string) ($issue['type'] ?? '');
                $lang = (string) ($issue['lang'] ?? '');
                $url  = (string) ($issue['url']  ?? '');
                echo "\n  CHECK  : " . ($issue['check']  ?? '') . "\n";
                echo "  TARGET : [{$type}:{$lang}] {$url}\n";
                echo "  DETAIL : " . wordwrap((string) ($issue['detail'] ?? ''), 70, "\n           ", true) . "\n";
                echo "  FIX    : " . wordwrap((string) ($issue['fix']    ?? ''), 70, "\n           ", true) . "\n";
            }
            echo "\n";
        }

        if (empty($issues)) {
            echo "  ✓  No issues found — all checks passed!\n\n";
        }

        // Summary by type
        if (!empty($summary['by_type'])) {
            echo str_repeat('─', 78) . "\n";
            echo " Summary by page type:\n";
            foreach ($summary['by_type'] as $typ => $cnt) {
                printf("  %-12s %d issue(s)\n", $typ, $cnt);
            }
            echo "\n";
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private static function parseArg(array $argv, string $prefix, string $default): string
    {
        foreach ($argv as $arg) {
            if (strncmp($arg, $prefix, strlen($prefix)) === 0) {
                return substr($arg, strlen($prefix));
            }
        }
        return $default;
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
            $prefix  = 'App\\';
            $baseDir = APP_PATH . '/';
            $len     = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
            $relativeClass = substr($class, $len);
            $parts         = explode('\\', $relativeClass);
            $fileName      = array_pop($parts);
            $parts         = array_map('strtolower', $parts);
            $file          = $baseDir . implode('/', $parts) . '/' . $fileName . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });

        $siteJson = DATA_PATH . '/site.json';
        if (!is_file($siteJson)) {
            fwrite(STDERR, "[SeoAudit] Missing config: {$siteJson}\n");
            exit(99);
        }

        Config::load($siteJson);
        self::$bootstrapped = true;
    }
}

if (PHP_SAPI === 'cli') {
    exit(SeoAudit::run($_SERVER['argv'] ?? []));
}
