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
    const TITLE_MIN = 30;
    const TITLE_MAX = 60;
    const DESC_MIN  = 80;
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
    public function run(string $filterLang = '', string $filterType = '', int $limit = 500): array
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
                [$pageIssues, $count] = $this->scanProducts($lang, $allSlugs, $limit);
                foreach ($pageIssues as $i) {
                    $issues[] = $i;
                }
                $totalPages += $count;
            }

            if (in_array('blog', $types, true)) {
                [$pageIssues, $count] = $this->scanBlog($lang, $allSlugs, $limit);
                foreach ($pageIssues as $i) {
                    $issues[] = $i;
                }
                $totalPages += $count;
            }

            if (in_array('case', $types, true)) {
                [$pageIssues, $count] = $this->scanCases($lang, $allSlugs, $limit);
                foreach ($pageIssues as $i) {
                    $issues[] = $i;
                }
                $totalPages += $count;
            }

            if (in_array('page', $types, true)) {
                [$pageIssues, $count] = $this->scanPages($lang, $allSlugs, $limit);
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
    private function scanProducts(string $lang, array $allSlugs, int $limit = 500): array
    {
        $items = $this->readJson("{$lang}/products.json");
        if (empty($items)) {
            return [[], 0];
        }
        $items = array_slice($items, 0, $limit);

        $issues     = [];
        $seenTitles = [];
        $seenDescs  = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug      = (string) ($item['slug'] ?? '');
            $label     = '[product:' . $lang . '] ' . ($item['name'] ?? $slug ?: '?');
            $url       = '/' . $lang . '/product/' . $slug;
            $title     = trim((string) ($item['seo_title'] ?? ''));
            $desc      = trim((string) ($item['seo_desc'] ?? ''));
            $content   = (string) ($item['content'] ?? '');
            $images    = is_array($item['images'] ?? null) ? (array) $item['images'] : [];
            $category  = (string) ($item['category'] ?? '');
            $status    = (string) ($item['status'] ?? 'published');

            foreach ($this->checkTitleDesc($title, $desc, $label, $url, 'product', $lang, $seenTitles, $seenDescs) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkH1($content, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkImageAlts($images, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkImageMediaMeta($images, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkCanonical($slug, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkDeadLinks($content, $allSlugs, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkSlug($slug, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkOgImage($item, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkCategory($category, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkPublishStatus($status, $slug, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkContentImageAlts($content, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkExternalLinks($content, $label, $url, 'product', $lang) as $i) {
                $issues[] = $i;
            }
        }

        return [$issues, count($items)];
    }

    /** @return array{0:array,1:int} */
    private function scanBlog(string $lang, array $allSlugs, int $limit = 500): array
    {
        $items = $this->readJson("{$lang}/blog.json");
        if (empty($items)) {
            return [[], 0];
        }
        $items = array_slice($items, 0, $limit);

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
            foreach ($this->checkSlug($slug, $label, $url, 'blog', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkOgImage($item, $label, $url, 'blog', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkContentImageAlts($content, $label, $url, 'blog', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkExternalLinks($content, $label, $url, 'blog', $lang) as $i) {
                $issues[] = $i;
            }
        }

        return [$issues, count($items)];
    }

    /** @return array{0:array,1:int} */
    private function scanCases(string $lang, array $allSlugs, int $limit = 500): array
    {
        $items = $this->readJson("{$lang}/cases.json");
        if (empty($items)) {
            return [[], 0];
        }
        $items = array_slice($items, 0, $limit);

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
            foreach ($this->checkSlug($slug, $label, $url, 'case', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkOgImage($item, $label, $url, 'case', $lang) as $i) {
                $issues[] = $i;
            }
        }

        return [$issues, count($items)];
    }

    /** @return array{0:array,1:int} */
    private function scanPages(string $lang, array $allSlugs, int $limit = 500): array
    {
        $items = $this->readJson("{$lang}/pages.json");
        if (empty($items)) {
            return [[], 0];
        }
        $items = array_slice($items, 0, $limit);

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
            foreach ($this->checkSlug($slug, $label, $url, 'page', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkOgImage($item, $label, $url, 'page', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkContentImageAlts($content, $label, $url, 'page', $lang) as $i) {
                $issues[] = $i;
            }
            foreach ($this->checkExternalLinks($content, $label, $url, 'page', $lang) as $i) {
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

        // ── robots_block_all ─────────────────────────────────────────────────
        if (!empty($site['robots_block_all'])) {
            $issues[] = $this->makeIssue(
                self::CRITICAL,
                'global',
                '',
                '/admin/seo',
                '全站 Noindex 已开启',
                'site.json 的 robots_block_all 为 true，搜索引擎将无法索引整站',
                '立即前往 SEO 总控关闭 robots_block_all，或确认是否故意屏蔽索引'
            );
        }

        // ── default_og_image ─────────────────────────────────────────────────
        $ogImage = trim((string) ($site['default_og_image'] ?? ''));
        if ($ogImage === '') {
            $issues[] = $this->makeIssue(
                self::INFO,
                'global',
                '',
                '/admin/seo',
                '缺失全局 OG 图片',
                'site.json 未设置 default_og_image，无特色图片的页面分享时将无图展示',
                '前往 SEO 总控，上传一张全局 OG 图片（建议尺寸 1200×630px）'
            );
        }

        // ── schema_organization_json ─────────────────────────────────────────
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
                    "{$label} 与 \"{$seenTitles[$title]}\" 使用了相同的 seo_title: \"{$title}\"",
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
            } elseif (mb_strlen($title) < self::TITLE_MIN) {
                $issues[] = $this->makeIssue(
                    self::WARNING, $type, $lang, $url,
                    'Title 过短',
                    "{$label} seo_title 共 " . mb_strlen($title) . " 字符（建议 ≥" . self::TITLE_MIN . "）",
                    '扩充 Title，建议在 ' . self::TITLE_MIN . '–' . self::TITLE_MAX . ' 字符之间'
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
                    "{$label} 与 \"{$seenDescs[$desc]}\" 使用了相同的 meta description",
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
            } elseif (mb_strlen($desc) < self::DESC_MIN) {
                $issues[] = $this->makeIssue(
                    self::WARNING, $type, $lang, $url,
                    'Description 过短',
                    "{$label} meta description 共 " . mb_strlen($desc) . " 字符（建议 ≥" . self::DESC_MIN . "）",
                    '扩充 Description，建议在 ' . self::DESC_MIN . '–' . self::DESC_MAX . ' 字符之间'
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
            // H1 is likely rendered by the view template, not in Quill content
            $issues[] = $this->makeIssue(
                self::INFO, $type, $lang, $url,
                'H1 可能由模板层渲染',
                "{$label} content 字段为空，H1 可能由视图模板直接输出（非 Quill 内容）",
                '如需在富文本中添加 H1，请在编辑器中添加；模板层 H1 无需操作'
            );
            return $issues;
        }

        $count = (int) preg_match_all('/<h1[\s>]/i', $html);

        if ($count === 0) {
            $issues[] = $this->makeIssue(
                self::WARNING, $type, $lang, $url,
                '缺失 H1',
                "{$label} 富文本内容中未发现 <h1> 标签",
                '在富文本内容开头添加一个 <h1> 作为主标题，或确认 H1 已由模板层输出'
            );
        } elseif ($count > 1) {
            $issues[] = $this->makeIssue(
                self::CRITICAL, $type, $lang, $url,
                '多个 H1',
                "{$label} 富文本内容中存在 {$count} 个 <h1> 标签",
                '每页只允许一个 <h1>，将其余标题改为 <h2> 或 <h3>'
            );
        }

        // ── H-tag hierarchy check ─────────────────────────────────────────────
        preg_match_all('/<(h[1-6])[\s>]/i', $html, $hMatches);
        if (!empty($hMatches[1])) {
            $hNums = array_map(function ($h) { return (int) substr($h, 1); }, $hMatches[1]);
            $prev  = 0;
            foreach ($hNums as $n) {
                if ($prev > 0 && $n > $prev + 1) {
                    $issues[] = $this->makeIssue(
                        self::INFO, $type, $lang, $url,
                        'H 标签跳级',
                        "{$label} 富文本中存在 H 标签跳级：h{$prev} → h{$n}",
                        '保持 H 标签层级连续（h1→h2→h3），避免跳过中间层级'
                    );
                    break; // Only report first jump per item to avoid noise
                }
                $prev = $n;
            }
        }

        return $issues;
    }

    /**
     * Check product image array for missing alt_text on uploaded images.
     *
     * When alt_text is empty the MediaMetaStore is consulted (single readAll()
     * call; in-process cached).  If a metadata alt exists the issue is downgraded
     * to INFO (fallback is active, SEO is acceptable).  Only when both sources are
     * empty is a WARNING reported.
     */
    private function checkImageAlts(array $images, string $label, string $url, string $type, string $lang): array
    {
        $issues = [];
        $allMeta = MediaMetaStore::readAll();

        foreach ($images as $idx => $img) {
            if (!is_array($img)) {
                continue;
            }
            $alt    = trim((string) ($img['alt_text'] ?? ''));
            $imgUrl = trim((string) ($img['url'] ?? ''));

            if ($imgUrl === '' || $alt !== '') {
                continue;
            }

            $num        = $idx + 1;
            $normalized = MediaMetaStore::normalizeWebPath($imgUrl);
            $metaAlt    = '';

            if ($normalized !== '' && array_key_exists($normalized, $allMeta)) {
                $metaAlt = trim(strip_tags((string) ($allMeta[$normalized]['alt'] ?? '')));
            }

            if ($metaAlt !== '') {
                $issues[] = $this->makeIssue(
                    self::INFO, $type, $lang, $url,
                    '图片 ALT 由 Media Metadata 补全',
                    "{$label} 第 {$num} 张图片 alt_text 为空，已由 media_metadata 自动补全（图片：{$imgUrl}）",
                    '建议直接在图片的 alt_text 字段填写描述，以减少对 media_metadata 的依赖'
                );
            } else {
                $issues[] = $this->makeIssue(
                    self::WARNING, $type, $lang, $url,
                    '图片缺失 ALT',
                    "{$label} 第 {$num} 张图片缺少 alt_text（图片：{$imgUrl}）",
                    '为每张图片填写描述性 alt 文字'
                );
            }
        }

        return $issues;
    }

    /**
     * Check product image array against MediaMetaStore for dimension and WebP gaps.
     *
     * Rules (supplement 4): only report when a metadata entry EXISTS for the image.
     * Images without any metadata entry are silently skipped to avoid mass false
     * positives on un-scanned uploads.
     *
     *   missing_dimensions — metadata entry exists but width = 0
     *   non_webp_images    — metadata entry exists, jpg/png, webp = false
     */
    private function checkImageMediaMeta(array $images, string $label, string $url, string $type, string $lang): array
    {
        $issues  = [];
        $allMeta = MediaMetaStore::readAll();

        foreach ($images as $idx => $img) {
            if (!is_array($img)) {
                continue;
            }
            $imgUrl    = trim((string) ($img['url'] ?? ''));
            if ($imgUrl === '') {
                continue;
            }
            $normalized = MediaMetaStore::normalizeWebPath($imgUrl);
            if ($normalized === '' || !array_key_exists($normalized, $allMeta)) {
                continue;
            }

            $meta = array_merge(MediaMetaStore::defaultEntry(), $allMeta[$normalized]);
            $num  = $idx + 1;

            if ((int) ($meta['width'] ?? 0) === 0) {
                $issues[] = $this->makeIssue(
                    self::WARNING, $type, $lang, $url,
                    '图片缺失尺寸',
                    "{$label} 第 {$num} 张图片未记录宽高尺寸（图片：{$imgUrl}）",
                    '在 Media SEO 中心对该图片执行扫描，系统将自动填充宽高数据以降低 CLS'
                );
            }

            $ext = strtolower(pathinfo($imgUrl, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'], true) && empty($meta['webp'])) {
                $issues[] = $this->makeIssue(
                    self::WARNING, $type, $lang, $url,
                    '图片未转 WebP',
                    "{$label} 第 {$num} 张图片（{$imgUrl}）尚未生成 WebP 版本",
                    '通过上传流程重新上传该图片，或在 Media SEO 中心手动触发 WebP 转换'
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

        // Match href values including empty ones (changed + to *)
        preg_match_all('/href=["\']([^"\']*)["\']|href=([^\s>]+)/i', $html, $matches);
        $hrefs = array_merge($matches[1] ?? [], array_filter($matches[2] ?? [], 'strlen'));
        if (empty($hrefs)) {
            return $issues;
        }

        foreach ($hrefs as $href) {
            $href = trim((string) $href);

            // ── Empty href ────────────────────────────────────────────────────
            if ($href === '') {
                $issues[] = $this->makeIssue(
                    self::WARNING, $type, $lang, $url,
                    '空链接',
                    "{$label} 富文本中存在 href 为空的链接",
                    '删除或修正空链接的 href 属性'
                );
                continue;
            }

            // ── Dangerous protocols ───────────────────────────────────────────
            if (preg_match('#^(javascript|vbscript|data):#i', $href)) {
                $proto = strtolower(explode(':', $href)[0]);
                $issues[] = $this->makeIssue(
                    self::CRITICAL, $type, $lang, $url,
                    '危险链接协议',
                    "{$label} 富文本中存在危险协议链接 \"{$proto}:\"，存在 XSS 安全风险",
                    '立即删除所有 javascript:/vbscript:/data: 协议的链接'
                );
                continue;
            }

            // ── Bare anchor ───────────────────────────────────────────────────
            if ($href === '#') {
                $issues[] = $this->makeIssue(
                    self::INFO, $type, $lang, $url,
                    '锚点占位链接',
                    "{$label} 富文本中存在 href=\"#\" 的无效锚点链接",
                    '为锚点链接设置有效目标 ID，或删除无意义的 "#" 链接'
                );
                continue;
            }

            // Skip valid fragment links (e.g. #section-id)
            if ($href[0] === '#') {
                continue;
            }

            // Skip external URLs (handled by checkExternalLinks)
            if (preg_match('#^https?://#i', $href)) {
                continue;
            }

            // ── Internal dead-link check ──────────────────────────────────────
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
    // Additional check helpers (incremental additions)
    // =========================================================================

    /**
     * Validate slug format: only [a-z0-9-] allowed; flag uppercase, trailing
     * slash, consecutive hyphens, and any other special characters.
     */
    private function checkSlug(string $slug, string $label, string $url, string $type, string $lang): array
    {
        $issues = [];
        if ($slug === '') {
            return $issues; // already caught by checkCanonical
        }

        if (preg_match('/[A-Z]/', $slug)) {
            $issues[] = $this->makeIssue(
                self::WARNING, $type, $lang, $url,
                'Slug 含大写字母',
                "{$label} slug \"{$slug}\" 含有大写字母，可能导致 URL 大小写不一致",
                '将 slug 修改为全小写'
            );
        }

        if (substr($slug, -1) === '/') {
            $issues[] = $this->makeIssue(
                self::WARNING, $type, $lang, $url,
                'Slug 含尾部斜杠',
                "{$label} slug \"{$slug}\" 以 \"/\" 结尾，可能导致 canonical 错误",
                '删除 slug 末尾的 "/"'
            );
        }

        if (strpos($slug, '--') !== false) {
            $issues[] = $this->makeIssue(
                self::INFO, $type, $lang, $url,
                'Slug 含连续连字符',
                "{$label} slug \"{$slug}\" 包含连续连字符 \"--\"",
                '将连续连字符替换为单个连字符'
            );
        }

        // Flag characters outside [a-zA-Z0-9/-] (uppercase and slash already caught above)
        if (preg_match('/[^a-zA-Z0-9\/\-]/', $slug)) {
            $issues[] = $this->makeIssue(
                self::WARNING, $type, $lang, $url,
                'Slug 含特殊字符',
                "{$label} slug \"{$slug}\" 包含 [a-z0-9-] 以外的字符（如下划线、空格、点等）",
                'Slug 仅允许使用小写字母、数字和连字符'
            );
        }

        return $issues;
    }

    /**
     * Check that an OG/social image exists for the item.
     * Products: images[] array or image field.
     * Blog/case: image field.
     * Page: featured_image field.
     *
     * @param array<string,mixed> $item
     */
    private function checkOgImage(array $item, string $label, string $url, string $type, string $lang): array
    {
        $issues   = [];
        $hasImage = false;

        if ($type === 'product') {
            if (trim((string) ($item['image'] ?? '')) !== '') {
                $hasImage = true;
            }
            if (!$hasImage && !empty($item['images']) && is_array($item['images'])) {
                foreach ($item['images'] as $img) {
                    if (is_array($img) && trim((string) ($img['url'] ?? '')) !== '') {
                        $hasImage = true;
                        break;
                    }
                }
            }
        } elseif (in_array($type, ['blog', 'case'], true)) {
            $hasImage = trim((string) ($item['image'] ?? '')) !== '';
        } elseif ($type === 'page') {
            $hasImage = trim((string) ($item['featured_image'] ?? '')) !== '';
        }

        if (!$hasImage) {
            $issues[] = $this->makeIssue(
                self::WARNING, $type, $lang, $url,
                '缺失 OG 图片',
                "{$label} 未设置特色图片，社交分享时将使用全局默认图或无图",
                '为该内容上传一张特色图片（建议尺寸 1200×630px）'
            );
        }

        return $issues;
    }

    /**
     * Scan <img> tags inside Quill HTML content for missing or low-quality alt attributes.
     * Flags: no alt attribute, alt="", alt="image", alt="img", alt="photo", alt="picture".
     */
    private function checkContentImageAlts(string $html, string $label, string $url, string $type, string $lang): array
    {
        $issues = [];
        if (trim($html) === '') {
            return $issues;
        }

        preg_match_all('/<img\b[^>]*>/i', $html, $imgTags);
        if (empty($imgTags[0])) {
            return $issues;
        }

        $lowQualityAlts = ['', 'image', 'img', 'photo', 'picture'];

        foreach ($imgTags[0] as $idx => $imgTag) {
            $num = $idx + 1;

            if (!preg_match('/\balt\s*=/i', $imgTag)) {
                $issues[] = $this->makeIssue(
                    self::WARNING, $type, $lang, $url,
                    '富文本图片缺失 ALT',
                    "{$label} 富文本第 {$num} 张图片缺少 alt 属性",
                    '为图片添加描述性 alt 属性（如 "ETFE cable cross section"）'
                );
                continue;
            }

            if (preg_match('/\balt\s*=\s*["\']([^"\']*)["\']|\balt\s*=\s*(\S*)/i', $imgTag, $altMatch)) {
                $altValue = strtolower(trim($altMatch[1] ?? $altMatch[2] ?? ''));
                if (in_array($altValue, $lowQualityAlts, true)) {
                    $displayAlt = ($altValue === '') ? '（空字符串）' : "\"{$altValue}\"";
                    $issues[] = $this->makeIssue(
                        self::WARNING, $type, $lang, $url,
                        '富文本图片低质量 ALT',
                        "{$label} 富文本第 {$num} 张图片 alt 值 {$displayAlt} 无描述意义",
                        '将 alt 替换为描述图片内容的具体文字'
                    );
                }
            }
        }

        return $issues;
    }

    /**
     * Check external links with target="_blank" for missing rel="noopener noreferrer".
     * Only flags external (http/https) anchor tags.
     */
    private function checkExternalLinks(string $html, string $label, string $url, string $type, string $lang): array
    {
        $issues = [];
        if (trim($html) === '') {
            return $issues;
        }

        preg_match_all('/<a\b[^>]+>/i', $html, $anchorTags);
        if (empty($anchorTags[0])) {
            return $issues;
        }

        foreach ($anchorTags[0] as $tag) {
            // Only care about external links
            if (!preg_match('/\bhref\s*=\s*["\']([^"\']*)["\']|\bhref\s*=\s*(\S+)/i', $tag, $hrefMatch)) {
                continue;
            }
            $href = trim($hrefMatch[1] ?? $hrefMatch[2] ?? '');
            if (!preg_match('#^https?://#i', $href)) {
                continue;
            }

            // Only warn when target="_blank" is present
            if (!preg_match('/\btarget\s*=\s*["\']_blank["\']/i', $tag)) {
                continue;
            }

            $rel = '';
            if (preg_match('/\brel\s*=\s*["\']([^"\']*)["\']|\brel\s*=\s*(\S+)/i', $tag, $relMatch)) {
                $rel = strtolower(trim($relMatch[1] ?? $relMatch[2] ?? ''));
            }

            $hasNoopener   = strpos($rel, 'noopener') !== false;
            $hasNoreferrer = strpos($rel, 'noreferrer') !== false;

            if (!$hasNoopener || !$hasNoreferrer) {
                $missing = [];
                if (!$hasNoopener)   { $missing[] = 'noopener'; }
                if (!$hasNoreferrer) { $missing[] = 'noreferrer'; }
                $issues[] = $this->makeIssue(
                    self::WARNING, $type, $lang, $url,
                    '外链安全警告',
                    "{$label} 含有 target=\"_blank\" 外链但缺少 rel=\"" . implode(' ', $missing) . "\"（链接：{$href}）",
                    '为所有 target="_blank" 外链添加 rel="noopener noreferrer" 以防止 tabnapping 攻击'
                );
            }
        }

        return $issues;
    }

    /**
     * Check that product has a category assigned.
     */
    private function checkCategory(string $category, string $label, string $url, string $type, string $lang): array
    {
        $issues = [];

        if ($category === '') {
            $issues[] = $this->makeIssue(
                self::WARNING, $type, $lang, $url,
                '产品缺失分类',
                "{$label} 未分配分类（category 字段为空）",
                '为产品选择一个分类，便于用户导航和搜索过滤'
            );
        }

        return $issues;
    }

    /**
     * Check that unpublished products are not in sitemap.
     * Non-published products with valid slug could still appear in sitemap.
     */
    private function checkPublishStatus(string $status, string $slug, string $label, string $url, string $type, string $lang): array
    {
        $issues = [];

        // Only warn if slug exists (product could appear in sitemap) but status is not published
        if ($slug !== '' && !in_array($status, ['published', ''], true)) {
            $issues[] = $this->makeIssue(
                self::WARNING, $type, $lang, $url,
                '产品状态异常',
                "{$label} 状态为 \"{$status}\"，但具有有效 slug，可能被收录到 sitemap 中",
                '确认产品状态：如需隐藏则设置 status 为 draft；如需发布则改为 published'
            );
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
            'total'              => count($issues),
            'critical'           => 0,
            'warning'            => 0,
            'info'               => 0,
            'by_type'            => [],
            'by_check'           => [],
            // ── Specialised counters ──────────────────────────────────────────
            'missing_alt'        => 0,
            'media_missing_alt'  => 0,
            'missing_dimensions' => 0,
            'non_webp_images'    => 0,
            'broken_links'       => 0,
            'missing_og_image'   => 0,
            'slug_issues'        => 0,
            'security_warnings'  => 0,
            'robots_blocked'     => false,
        ];

        // '图片 ALT 由 Media Metadata 补全' is INFO and must NOT count toward missing_alt.
        $altChecks    = ['图片缺失 ALT', '富文本图片缺失 ALT', '富文本图片低质量 ALT'];
        $brokenChecks = ['死链', '危险链接协议', '空链接'];
        $slugChecks   = ['Slug 含大写字母', 'Slug 含尾部斜杠', 'Slug 含连续连字符', 'Slug 含特殊字符'];

        foreach ($issues as $issue) {
            $sev = (string) ($issue['severity'] ?? '');
            $typ = (string) ($issue['type'] ?? '');
            $chk = (string) ($issue['check'] ?? '');

            if (isset($summary[$sev])) {
                $summary[$sev]++;
            }
            $summary['by_type'][$typ]  = ($summary['by_type'][$typ]  ?? 0) + 1;
            $summary['by_check'][$chk] = ($summary['by_check'][$chk] ?? 0) + 1;

            if (in_array($chk, $altChecks, true)) {
                $summary['missing_alt']++;
            }
            if ($chk === '图片 ALT 由 Media Metadata 补全') {
                $summary['media_missing_alt']++;
            }
            if ($chk === '图片缺失尺寸') {
                $summary['missing_dimensions']++;
            }
            if ($chk === '图片未转 WebP') {
                $summary['non_webp_images']++;
            }
            if (in_array($chk, $brokenChecks, true)) {
                $summary['broken_links']++;
            }
            if ($chk === '缺失 OG 图片') {
                $summary['missing_og_image']++;
            }
            if (in_array($chk, $slugChecks, true)) {
                $summary['slug_issues']++;
            }
            if ($chk === '外链安全警告') {
                $summary['security_warnings']++;
            }
            if ($chk === '全站 Noindex 已开启') {
                $summary['robots_blocked'] = true;
            }
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
