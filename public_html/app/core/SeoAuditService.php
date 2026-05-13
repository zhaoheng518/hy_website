<?php

declare(strict_types=1);

namespace App\Core;

/**
 * SEO Health Scanner — static analysis against JSON data sources.
 *
 * Does NOT make any HTTP requests. Reads only from the JSON files under
 * app/data/{lang}/*.json and app/data/site.json.
 *
 * Compatible with PHP 7+, no Composer, no external dependencies.
 *
 * Usage:
 *   $service = new SeoAuditService(DATA_PATH, Config::get('supported_langs', ['en']));
 *   $result  = $service->run($filterLang, $filterType);
 */
final class SeoAuditService
{
    // ── Severity levels ───────────────────────────────────────────────────────
    const CRITICAL = 'critical';
    const WARNING  = 'warning';
    const INFO     = 'info';

    // ── Title / description soft limits (characters) ──────────────────────────
    const TITLE_MAX = 60;
    const DESC_MAX  = 160;

    private string $dataPath;
    private array  $supportedLangs;

    public function __construct(string $dataPath, array $supportedLangs)
    {
        $this->dataPath       = rtrim($dataPath, '/');
        $this->supportedLangs = array_values(array_filter(array_map('trim', $supportedLangs)));
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Run the audit and return a structured result array.
     *
     * @param string $filterLang  Empty = all supported languages
     * @param string $filterType  Empty = all types | product | blog | case | page
     * @return array{issues:array,summary:array,total_pages:int,scanned_at:string}
     */
    public function run(string $filterLang = '', string $filterType = ''): array
    {
        $issues     = [];
        $totalPages = 0;

        $langs = ($filterLang !== '' && in_array($filterLang, $this->supportedLangs, true))
            ? [$filterLang]
            : $this->supportedLangs;

        $validTypes = ['product', 'blog', 'case', 'page'];
        $types = ($filterType !== '' && in_array($filterType, $validTypes, true))
            ? [$filterType]
            : $validTypes;

        // Global checks (run once regardless of lang/type filter)
        if ($filterType === '' || $filterType === 'global') {
            foreach ($this->checkGlobalSchema() as $issue) {
                $issues[] = $issue;
            }
        }

        foreach ($langs as $lang) {
            // Build slug map for dead-link detection
            $allSlugs = $this->collectAllSlugs($lang);

            if (in_array('product', $types, true)) {
                [$pageIssues, $count] = $this->scanProducts($lang, $allSlugs);
                foreach ($pageIssues as $i) {
                    $issues[] = $i;
                }
                $totalPages += $count;
            }

            if (in_array('blog', $types, true)) {
                [$pageIssues, $count] = $this->scanBlog($lang, $allSlugs);
                foreach ($pageIssues as $i) {
                    $issues[] = $i;
                }
                $totalPages += $count;
            }

            if (in_array('case', $types, true)) {
                [$pageIssues, $count] = $this->scanCases($lang, $allSlugs);
                foreach ($pageIssues as $i) {
                    $issues[] = $i;
                }
                $totalPages += $count;
            }

            if (in_array('page', $types, true)) {
                [$pageIssues, $count] = $this->scanPages($lang, $allSlugs);
                foreach ($pageIssues as $i) {
                    $issues[] = $i;
                }
                $totalPages += $count;
            }
        }

        return [
            'issues'      => $issues,
            'summary'     => $this->buildSummary($issues),
            'total_pages' => $totalPages,
            'scanned_at'  => date('Y-m-d H:i:s'),
        ];
    }

    // =========================================================================
    // Per-type scanners
    // =========================================================================

    /** @return array{0:array,1:int} [issues, count] */
    private function scanProducts(string $lang, array $allSlugs): array
    {
        $items = $this->readJson("{$lang}/products.json");
        if (empty($items)) {
            return [[], 0];
        }

        $issues     = [];
        $seenTitles = [];
        $seenDescs  = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug    = (string) ($item['slug'] ?? '');
            $label   = '[product:' . $lang . '] ' . ($item['name'] ?? $slug ?: '?');
            $url     = '/' . $lang . '/product/' . $slug;
            $title   = trim((string) ($item['seo_title'] ?? ''));
            $desc    = trim((string) ($item['seo_desc'] ?? ''));
            $content = (string) ($item['content'] ?? '');
            $images  = is_array($item['images'] ?? null) ? (array) $item['images'] : [];

            foreach ($this->checkTitleDesc($title, $desc, $label, $url, 'product', $lang, $seenTitles, $seenDescs) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkH1($content, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkImageAlts($images, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkCanonical($slug, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkDeadLinks($content, $allSlugs, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
        }

        return [$issues, count($items)];
    }

    /** @return array{0:array,1:int} */
    private function scanBlog(string $lang, array $allSlugs): array
    {
        $items = $this->readJson("{$lang}/blog.json");
        if (empty($items)) {
            return [[], 0];
        }

        $issues     = [];
        $seenTitles = [];
        $seenDescs  = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug    = (string) ($item['slug'] ?? '');
            $label   = '[blog:' . $lang . '] ' . ($item['title'] ?? $slug ?: '?');
            $url     = '/' . $lang . '/blog/' . $slug;
            $title   = trim((string) ($item['seo_title'] ?? ''));
            $desc    = trim((string) ($item['seo_desc'] ?? ''));
            $content = (string) ($item['content'] ?? '');

            foreach ($this->checkTitleDesc($title, $desc, $label, $url, 'blog', $lang, $seenTitles, $seenDescs) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkH1($content, $label, $url, 'blog', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkCanonical($slug, $label, $url, 'blog', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkDeadLinks($content, $allSlugs, $label, $url, 'blog', $lang) as $i) {
                $issues[] = $i;
            }
        }

        return [$issues, count($items)];
    }

    /** @return array{0:array,1:int} */
    private function scanCases(string $lang, array $allSlugs): array
    {
        $items = $this->readJson("{$lang}/cases.json");
        if (empty($items)) {
            return [[], 0];
        }

        $issues     = [];
        $seenTitles = [];
        $seenDescs  = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug  = (string) ($item['slug'] ?? '');
            $label = '[case:' . $lang . '] ' . ($item['title'] ?? $slug ?: '?');
            $url   = '/' . $lang . '/cases/' . $slug;
            $title = trim((string) ($item['seo_title'] ?? ''));
            $desc  = trim((string) ($item['seo_desc'] ?? ''));

            foreach ($this->checkTitleDesc($title, $desc, $label, $url, 'case', $lang, $seenTitles, $seenDescs) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkCanonical($slug, $label, $url, 'case', $lang) as $i) {
                $issues[] = $i;
            }
        }

        return [$issues, count($items)];
    }

    /** @return array{0:array,1:int} */
    private function scanPages(string $lang, array $allSlugs): array
    {
        $items = $this->readJson("{$lang}/pages.json");
        if (empty($items)) {
            return [[], 0];
        }

        $issues     = [];
        $seenTitles = [];
        $seenDescs  = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug    = (string) ($item['slug'] ?? '');
            $label   = '[page:' . $lang . '] ' . ($item['title'] ?? $slug ?: '?');
            $url     = '/' . $lang . '/page/' . $slug;
            // pages.json uses seo_description (not seo_desc)
            $title   = trim((string) ($item['seo_title'] ?? ''));
            $desc    = trim((string) ($item['seo_description'] ?? $item['seo_desc'] ?? ''));
            $content = (string) ($item['content'] ?? '');

            foreach ($this->checkTitleDesc($title, $desc, $label, $url, 'page', $lang, $seenTitles, $seenDescs) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkH1($content, $label, $url, 'page', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkCanonical($slug, $label, $url, 'page', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkDeadLinks($content, $allSlugs, $label, $url, 'page', $lang) as $i) {
                $issues[] = $i;
            }
        }

        return [$issues, count($items)];
    }

    // =========================================================================
    // Global checks
    // =========================================================================

    private function checkGlobalSchema(): array
    {
        $issues   = [];
        $siteFile = $this->dataPath . '/site.json';
        if (!is_file($siteFile)) {
            return $issues;
        }

        $raw  = @file_get_contents($siteFile);
        $site = ($raw !== false) ? @json_decode($raw, true) : null;
        if (!is_array($site)) {
            return $issues;
        }

        $schema = trim((string) ($site['schema_organization_json'] ?? ''));

        if ($schema === '') {
            $issues[] = $this->makeIssue(
                self::INFO,
                'global',
                '',
                '/admin/seo',
                '缺失 Schema',
                'site.json 未配置 schema_organization_json（Organization JSON-LD）',
                '前往 SEO 总控，在 Schema 配置区域添加 Organization JSON-LD'
            );
            return $issues;
        }

        $parsed = @json_decode($schema, true);
        if (!is_array($parsed)) {
            $issues[] = $this->makeIssue(
                self::WARNING,
                'global',
                '',
                '/admin/seo',
                'Schema JSON 格式无效',
                'schema_organization_json 内容无法解析为合法 JSON',
                '在 SEO 总控中检查 Schema JSON 语法，确保格式正确'
            );
        }

        return $issues;
    }

    // =========================================================================
    // Individual check helpers
    // =========================================================================

    /**
     * Check title + description for: missing, duplicate, over-length.
     *
     * @param array<string,string> $seenTitles  Pass-by-reference dedup registry
     * @param array<string,string> $seenDescs   Pass-by-reference dedup registry
     */
    private function checkTitleDesc(
        string $title,
        string $desc,
        string $label,
        string $url,
        string $type,
        string $lang,
        array &$seenTitles,
        array &$seenDescs
    ): array {
        $issues = [];

        // ── Title ─────────────────────────────────────────────────────────────
        if ($title === '') {
            $issues[] = $this->makeIssue(
                self::CRITICAL, $type, $lang, $url,
                '缺失 Title',
                "{$label} 的 seo_title 字段为空",
                '编辑该内容，填写 SEO Title 字段'
            );
        } else {
            if (isset($seenTitles[$title])) {
                $issues[] = $this->makeIssue(
                    self::WARNING, $type, $lang, $url,
                    '重复 Title',
                    "{$label} 与 "{$seenTitles[$title]}" 使用了相同的 seo_title: "{$title}"",
                    '修改其中一个，确保每页 Title 唯一'
                );
            } else {
                $seenTitles[$title] = $label;
            }

            if (mb_strlen($title) > self::TITLE_MAX) {
                $issues[] = $this->makeIssue(
                    self::INFO, $type, $lang, $url,
                    'Title 过长',
                    "{$label} seo_title 共 " . mb_strlen($title) . " 字符（建议 ≤" . self::TITLE_MAX . "）",
                    '缩短 Title 以避免搜索结果截断'
                );
            }
        }

        // ── Description ───────────────────────────────────────────────────────
        if ($desc === '') {
            $issues[] = $this->makeIssue(
                self::CRITICAL, $type, $lang, $url,
                '缺失 Description',
                "{$label} 的 seo_desc / seo_description 字段为空",
                '编辑该内容，填写 Meta Description 字段'
            );
        } else {
            if (isset($seenDescs[$desc])) {
                $issues[] = $this->makeIssue(
                    self::WARNING, $type, $lang, $url,
                    '重复 Description',
                    "{$label} 与 "{$seenDescs[$desc]}" 使用了相同的 meta description",
                    '修改其中一个，确保每页 Description 唯一'
                );
            } else {
                $seenDescs[$desc] = $label;
            }

            if (mb_strlen($desc) > self::DESC_MAX) {
                $issues[] = $this->makeIssue(
                    self::INFO, $type, $lang, $url,
                    'Description 过长',
                    "{$label} meta description 共 " . mb_strlen($desc) . " 字符（建议 ≤" . self::DESC_MAX . "）",
                    '缩短 Description'
                );
            }
        }

        return $issues;
    }

    /**
     * Check H1 presence and uniqueness in HTML content string.
     * Uses regex to avoid DOMDocument overhead; safe for well-formed HTML snippets.
     */
    private function checkH1(string $html, string $label, string $url, string $type, string $lang): array
    {
        $issues = [];

        if (trim($html) === '') {
            $issues[] = $this->makeIssue(
                self::WARNING, $type, $lang, $url,
                '缺失 H1',
                "{$label} content 字段为空，无法检测 H1 标签",
                '确保内容中包含且仅包含一个 <h1> 标签'
            );
            return $issues;
        }

        $count = (int) preg_match_all('/<h1[\s>]/i', $html);

        if ($count === 0) {
            $issues[] = $this->makeIssue(
                self::WARNING, $type, $lang, $url,
                '缺失 H1',
                "{$label} 内容中未发现 <h1> 标签",
                '在内容开头添加一个 <h1> 作为页面主标题'
            );
        } elseif ($count > 1) {
            $issues[] = $this->makeIssue(
                self::CRITICAL, $type, $lang, $url,
                '多个 H1',
                "{$label} 内容中存在 {$count} 个 <h1> 标签",
                '每页只允许一个 <h1>，将其余标题改为 <h2> 或 <h3>'
            );
        }

        return $issues;
    }

    /**
     * Check product image array for missing alt_text on uploaded images.
     * Only flags images that have a non-empty URL (uploaded) but empty alt_text.
     */
    private function checkImageAlts(array $images, string $label, string $url, string $type, string $lang): array
    {
        $issues = [];

        foreach ($images as $idx => $img) {
            if (!is_array($img)) {
                continue;
            }
            $alt    = trim((string) ($img['alt_text'] ?? ''));
            $imgUrl = trim((string) ($img['url'] ?? ''));

            if ($imgUrl !== '' && $alt === '') {
                $issues[] = $this->makeIssue(
                    self::WARNING, $type, $lang, $url,
                    '图片缺失 ALT',
                    "{$label} 第 " . ($idx + 1) . " 张图片缺少 alt_text（图片：{$imgUrl}）",
                    '为每张图片填写描述性 alt 文字'
                );
            }
        }

        return $issues;
    }

    /**
     * A missing slug means no canonical URL can be generated.
     */
    private function checkCanonical(string $slug, string $label, string $url, string $type, string $lang): array
    {
        $issues = [];

        if ($slug === '') {
            $issues[] = $this->makeIssue(
                self::WARNING, $type, $lang, $url,
                '缺失 Canonical（slug 为空）',
                "{$label} slug 字段为空，系统无法生成 canonical URL",
                '为该内容设置唯一的 slug 标识'
            );
        }

        return $issues;
    }

    /**
     * Scan content HTML for internal links pointing to non-existent slugs.
     * Only checks relative paths matching /{lang}/{type}/{slug}.
     * Does NOT make HTTP requests.
     */
    private function checkDeadLinks(string $html, array $allSlugs, string $label, string $url, string $type, string $lang): array
    {
        $issues = [];

        if (trim($html) === '') {
            return $issues;
        }

        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);
        if (empty($matches[1])) {
            return $issues;
        }

        foreach ($matches[1] as $href) {
            $href = trim((string) $href);
            if ($href === '' || $href[0] === '#') {
                continue;
            }
            // Skip external URLs
            if (preg_match('#^https?://#i', $href)) {
                continue;
            }
            // Match /{lang}/{type}/{slug} pattern
            if (!preg_match('#^/([a-z]{2})/([a-z]+)/([^/?#]+)#', $href, $m)) {
                continue;
            }

            $hLang = $m[1];
            $hType = rtrim($m[2], 's'); // normalize "products" → "product", "cases" → "case"
            $hSlug = rawurldecode($m[3]);
            $key   = "{$hLang}/{$hType}/{$hSlug}";

            if (!isset($allSlugs[$key])) {
                $issues[] = $this->makeIssue(
                    self::CRITICAL, $type, $lang, $url,
                    '死链',
                    "{$label} 包含指向不存在页面的链接: {$href}",
                    '删除或修正该链接，确保目标页面存在'
                );
            }
        }

        return $issues;
    }

    // =========================================================================
    // Utility helpers
    // =========================================================================

    /**
     * Build a lookup map of all known slugs for dead-link detection.
     * Key format: "{lang}/{type}/{slug}"
     *
     * @return array<string,bool>
     */
    private function collectAllSlugs(string $lang): array
    {
        $map = [];
        $files = [
            'product' => 'products',
            'blog'    => 'blog',
            'case'    => 'cases',
            'page'    => 'pages',
        ];

        foreach ($files as $typeName => $fileName) {
            $items = $this->readJson("{$lang}/{$fileName}.json");
            foreach ($items as $item) {
                if (!is_array($item) || empty($item['slug'])) {
                    continue;
                }
                $map["{$lang}/{$typeName}/{$item['slug']}"] = true;
            }
        }

        return $map;
    }

    /** @return array<string,mixed> */
    private function makeIssue(
        string $severity,
        string $type,
        string $lang,
        string $url,
        string $check,
        string $detail,
        string $fix
    ): array {
        return [
            'severity' => $severity,
            'type'     => $type,
            'lang'     => $lang,
            'url'      => $url,
            'check'    => $check,
            'detail'   => $detail,
            'fix'      => $fix,
        ];
    }

    /** @return array<string,mixed> */
    private function buildSummary(array $issues): array
    {
        $summary = [
            'total'    => count($issues),
            'critical' => 0,
            'warning'  => 0,
            'info'     => 0,
            'by_type'  => [],
            'by_check' => [],
        ];

        foreach ($issues as $issue) {
            $sev = (string) ($issue['severity'] ?? '');
            $typ = (string) ($issue['type'] ?? '');
            $chk = (string) ($issue['check'] ?? '');

            if (isset($summary[$sev])) {
                $summary[$sev]++;
            }
            $summary['by_type'][$typ]  = ($summary['by_type'][$typ]  ?? 0) + 1;
            $summary['by_check'][$chk] = ($summary['by_check'][$chk] ?? 0) + 1;
        }

        return $summary;
    }

    private function readJson(string $relativePath): array
    {
        $path = $this->dataPath . '/' . ltrim($relativePath, '/');
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = @json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
