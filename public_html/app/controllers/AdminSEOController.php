<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\JsonStore;
use App\Core\SEO;
use App\Core\SeoAuditService;

class AdminSEOController extends BaseController
{
    // ── SEO Settings ──────────────────────────────────────────────────────────

    public function index(): void
    {
        Auth::requireCan('seo');

        if ($this->isPost()) {
            $this->handleSave();
            return;
        }

        $this->view->render('seo/index', [
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'seo',
            'pageTitle' => 'SEO 总控',
            'breadcrumbs' => [['label' => 'SEO', 'url' => '/admin/seo'], ['label' => '设置']],
            'success' => $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['setting_error'] ?? '',
        ]);
        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    private function handleSave(): void
    {
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Invalid token';
            $this->redirect('/admin/seo');
        }

        $store = JsonStore::globalData('site');
        $store->update(function ($config) {
            $config['default_meta_title'] = trim($this->getPost('default_meta_title', $config['default_meta_title'] ?? ''));
            $config['default_meta_description'] = trim($this->getPost('default_meta_description', $config['default_meta_description'] ?? ''));
            $config['site_name'] = trim($this->getPost('site_name', $config['site_name'] ?? ''));
            $config['favicon'] = trim($this->getPost('favicon', $config['favicon'] ?? ''));
            $config['default_og_image'] = trim($this->getPost('default_og_image', $config['default_og_image'] ?? ''));
            $config['head_scripts'] = $this->getPost('head_scripts', $config['head_scripts'] ?? '');
            $config['body_scripts'] = $this->getPost('body_scripts', $config['body_scripts'] ?? '');
            $config['robots_block_all'] = $this->getPost('robots_block_all', '0') === '1';
            $config['robots_txt_extra'] = $this->sanitizeRobotsTxtExtra($this->getPost('robots_txt_extra', $config['robots_txt_extra'] ?? ''));
            $config['sitemap_enabled'] = $this->getPost('sitemap_enabled', '0') === '1';
            $config['google_analytics_id'] = trim($this->getPost('google_analytics_id', $config['google_analytics_id'] ?? ''));
            $config['gtm_container_id'] = trim($this->getPost('gtm_container_id', $config['gtm_container_id'] ?? ''));
            $config['google_ads_head'] = trim($this->getPost('google_ads_head', $config['google_ads_head'] ?? ''));
            $config['whatsapp_widget_script'] = trim($this->getPost('whatsapp_widget_script', $config['whatsapp_widget_script'] ?? ''));
            $config['schema_organization_json'] = trim($this->getPost('schema_organization_json', $config['schema_organization_json'] ?? ''));
            $config['inquiry_ip_salt'] = trim($this->getPost('inquiry_ip_salt', $config['inquiry_ip_salt'] ?? ''));
            return $config;
        });

        Config::reload(DATA_PATH . '/site.json');

        if (Config::get('sitemap_enabled', true)) {
            $this->regenerateSitemap();
        }

        $_SESSION['setting_success'] = 'SEO 已保存';
        $this->redirect('/admin/seo');
    }

    private function regenerateSitemap(): void
    {
        SEO::writeSitemapToDisk();
    }

    private function sanitizeRobotsTxtExtra(string $value): string
    {
        $value = preg_replace('/<\?(?:php)?[\s\S]*?\?>/i', '', $value) ?? '';
        $value = strip_tags($value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = array_map('rtrim', explode("\n", $value));
        $value = trim(implode("\n", $lines));

        return mb_substr($value, 0, 4000, 'UTF-8');
    }

    // ── SEO Health Audit ──────────────────────────────────────────────────────

    /**
     * GET /admin/seo/audit[?lang=en&type=product]
     *
     * Runs the static SEO audit against all JSON data sources and renders the
     * results report.  No HTTP requests are made; analysis is pure in-memory.
     */
    public function audit(): void
    {
        Auth::requireCan('seo');

        $langs      = Config::get('supported_langs', ['en', 'cn', 'es']);
        $validTypes = ['product', 'blog', 'case', 'page'];

        $filterLang = trim((string) $this->getQuery('lang', ''));
        $filterType = trim((string) $this->getQuery('type', ''));

        if ($filterLang !== '' && !in_array($filterLang, $langs, true)) {
            $filterLang = '';
        }
        if ($filterType !== '' && !in_array($filterType, $validTypes, true)) {
            $filterType = '';
        }

        $limitParam  = max(1, min(2000, (int) $this->getQuery('limit', '500')));
        $service     = new SeoAuditService(DATA_PATH, $langs);
        $auditResult = $service->run($filterLang, $filterType, $limitParam);

        $this->view->render('seo/audit', [
            'adminUser'      => Auth::user()['username'] ?? 'Admin',
            'activeMenu'     => 'seo_audit',
            'pageTitle'      => 'SEO 健康检查',
            'breadcrumbs'    => [
                ['label' => 'SEO', 'url' => '/admin/seo'],
                ['label' => 'SEO 健康检查'],
            ],
            'auditResult'    => $auditResult,
            'filterLang'     => $filterLang,
            'filterType'     => $filterType,
            'supportedLangs' => $langs,
            'validTypes'     => $validTypes,
        ]);
    }

    /**
     * POST /admin/seo/audit/rescan
     *
     * Runs the SEO audit and saves the report to JSON file.
     */
    public function rescan(): void
    {
        Auth::requireCan('seo');

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Invalid token';
            $this->redirect('/admin/seo');
            return;
        }

        try {
            $langs   = Config::get('supported_langs', ['en', 'cn', 'es']);
            $service = new SeoAuditService(DATA_PATH, $langs);
            $result  = $service->run('', '', 500);

            // Save report with LOCK_EX
            $reportPath = DATA_PATH . '/seo_audit_report.json';
            $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            file_put_contents($reportPath, $json, LOCK_EX);

            $_SESSION['setting_success'] = 'SEO 健康检查已完成，共发现 ' . ($result['summary']['total'] ?? 0) . ' 个问题';
        } catch (\Exception $e) {
            error_log('[SEO Audit] Rescan failed: ' . $e->getMessage());
            $_SESSION['setting_error'] = '扫描失败，请稍后重试';
        }

        $this->redirect('/admin/seo');
    }

    /**
     * GET /admin/seo/export[?lang=en&type=product]
     *
     * Downloads the audit result as a plain-text report.
     */
    public function export(): void
    {
        Auth::requireCan('seo');

        $langs      = Config::get('supported_langs', ['en', 'cn', 'es']);
        $validTypes = ['product', 'blog', 'case', 'page'];

        $filterLang = trim((string) $this->getQuery('lang', ''));
        $filterType = trim((string) $this->getQuery('type', ''));

        if ($filterLang !== '' && !in_array($filterLang, $langs, true)) {
            $filterLang = '';
        }
        if ($filterType !== '' && !in_array($filterType, $validTypes, true)) {
            $filterType = '';
        }

        $limitParam = max(1, min(2000, (int) $this->getQuery('limit', '500')));
        $service    = new SeoAuditService(DATA_PATH, $langs);
        $result     = $service->run($filterLang, $filterType, $limitParam);

        $filename = 'seo_audit_' . date('Ymd_His') . '.txt';

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store');

        $summary = $result['summary'] ?? [];
        echo "SEO Health Audit Report\n";
        echo "Generated : " . ($result['scanned_at'] ?? date('Y-m-d H:i:s')) . "\n";
        echo "Filter    : lang=" . ($filterLang ?: 'all') . "  type=" . ($filterType ?: 'all') . "\n";
        echo "Pages     : " . ($result['total_pages'] ?? 0) . "\n";
        echo "Issues    : " . ($summary['total'] ?? 0)
            . "  (critical=" . ($summary['critical'] ?? 0)
            . "  warning=" . ($summary['warning'] ?? 0)
            . "  info=" . ($summary['info'] ?? 0) . ")\n";
        echo str_repeat('=', 80) . "\n\n";

        foreach ($result['issues'] ?? [] as $idx => $issue) {
            $no  = $idx + 1;
            $sev = strtoupper((string) ($issue['severity'] ?? ''));
            echo "[{$no}] [{$sev}] " . ($issue['check'] ?? '') . "\n";
            echo "  Type   : " . ($issue['type'] ?? '') . "  lang=" . ($issue['lang'] ?? '') . "\n";
            echo "  URL    : " . ($issue['url'] ?? '') . "\n";
            echo "  Detail : " . ($issue['detail'] ?? '') . "\n";
            echo "  Fix    : " . ($issue['fix'] ?? '') . "\n\n";
        }

        exit;
    }

    // ── Internal Links Management ──────────────────────────────────────────────

    /**
     * GET/POST /admin/seo/internal-links
     *
     * Show internal links management page, or handle add/toggle/delete actions.
     */
    public function internallinks(): void
    {
        Auth::requireCan('seo');

        if ($this->isPost()) {
            $action = trim((string) $this->getPost('_action', ''));
            if ($action === 'add') {
                $this->handleInternalLinksAdd();
                return;
            } elseif ($action === 'toggle') {
                $this->handleInternalLinksToggle();
                return;
            } elseif ($action === 'delete') {
                $this->handleInternalLinksDelete();
                return;
            }
        }

        $links = $this->readInternalLinks();

        $this->view->render('seo/internal-links', [
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'seo',
            'pageTitle' => '自动内链管理',
            'breadcrumbs' => [['label' => 'SEO', 'url' => '/admin/seo'], ['label' => '自动内链']],
            'links' => $links,
            'success' => $_SESSION['link_success'] ?? '',
            'error' => $_SESSION['link_error'] ?? '',
        ]);
        unset($_SESSION['link_success'], $_SESSION['link_error']);
    }

    private function handleInternalLinksAdd(): void
    {
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['link_error'] = 'Token 无效';
            $this->redirect('/admin/seo/internal-links');
        }

        $keyword = trim((string) $this->getPost('keyword', ''));
        $url = trim((string) $this->getPost('url', ''));
        $caseSensitive = $this->getPost('case_sensitive', '0') === '1';

        $error = $this->validateInternalLink($keyword, $url);
        if ($error !== '') {
            $_SESSION['link_error'] = $error;
            $this->redirect('/admin/seo/internal-links');
        }

        $links = $this->readInternalLinks();
        $links[] = [
            'keyword' => $keyword,
            'url' => $url,
            'enabled' => true,
            'case_sensitive' => $caseSensitive,
        ];

        if ($this->writeInternalLinks($links)) {
            $_SESSION['link_success'] = '内链规则已添加';
        } else {
            $_SESSION['link_error'] = '保存失败，请检查文件权限';
        }

        $this->redirect('/admin/seo/internal-links');
    }

    private function handleInternalLinksToggle(): void
    {
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['link_error'] = 'Token 无效';
            $this->redirect('/admin/seo/internal-links');
        }

        $index = (int) $this->getPost('index', '-1');
        if ($index < 0) {
            $_SESSION['link_error'] = '无效的索引';
            $this->redirect('/admin/seo/internal-links');
        }

        $links = $this->readInternalLinks();
        if (!isset($links[$index])) {
            $_SESSION['link_error'] = '规则不存在';
            $this->redirect('/admin/seo/internal-links');
        }

        $links[$index]['enabled'] = !$links[$index]['enabled'];

        if ($this->writeInternalLinks($links)) {
            $_SESSION['link_success'] = '状态已更新';
        } else {
            $_SESSION['link_error'] = '保存失败';
        }

        $this->redirect('/admin/seo/internal-links');
    }

    private function handleInternalLinksDelete(): void
    {
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['link_error'] = 'Token 无效';
            $this->redirect('/admin/seo/internal-links');
        }

        $index = (int) $this->getPost('index', '-1');
        if ($index < 0) {
            $_SESSION['link_error'] = '无效的索引';
            $this->redirect('/admin/seo/internal-links');
        }

        $links = $this->readInternalLinks();
        if (!isset($links[$index])) {
            $_SESSION['link_error'] = '规则不存在';
            $this->redirect('/admin/seo/internal-links');
        }

        array_splice($links, $index, 1);

        if ($this->writeInternalLinks($links)) {
            $_SESSION['link_success'] = '规则已删除';
        } else {
            $_SESSION['link_error'] = '删除失败';
        }

        $this->redirect('/admin/seo/internal-links');
    }

    /**
     * POST /admin/seo/internal-links/add
     *
     * Add a new internal link rule.
     */
    public function internallinksAdd(): void
    {
        Auth::requireCan('seo');

        if (!$this->isPost()) {
            $this->redirect('/admin/seo/internal-links');
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['link_error'] = 'Token 无效';
            $this->redirect('/admin/seo/internal-links');
        }

        $keyword = trim((string) $this->getPost('keyword', ''));
        $url = trim((string) $this->getPost('url', ''));
        $caseSensitive = $this->getPost('case_sensitive', '0') === '1';

        $error = $this->validateInternalLink($keyword, $url);
        if ($error !== '') {
            $_SESSION['link_error'] = $error;
            $this->redirect('/admin/seo/internal-links');
        }

        $links = $this->readInternalLinks();
        $links[] = [
            'keyword' => $keyword,
            'url' => $url,
            'enabled' => true,
            'case_sensitive' => $caseSensitive,
        ];

        if ($this->writeInternalLinks($links)) {
            $_SESSION['link_success'] = '内链规则已添加';
        } else {
            $_SESSION['link_error'] = '保存失败，请检查文件权限';
        }

        $this->redirect('/admin/seo/internal-links');
    }

    /**
     * POST /admin/seo/internal-links/toggle
     *
     * Toggle enabled status of an internal link rule.
     */
    public function internallinksToggle(): void
    {
        Auth::requireCan('seo');

        if (!$this->isPost()) {
            $this->redirect('/admin/seo/internal-links');
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['link_error'] = 'Token 无效';
            $this->redirect('/admin/seo/internal-links');
        }

        $index = (int) $this->getPost('index', '-1');
        if ($index < 0) {
            $_SESSION['link_error'] = '无效的索引';
            $this->redirect('/admin/seo/internal-links');
        }

        $links = $this->readInternalLinks();
        if (!isset($links[$index])) {
            $_SESSION['link_error'] = '规则不存在';
            $this->redirect('/admin/seo/internal-links');
        }

        $links[$index]['enabled'] = !$links[$index]['enabled'];

        if ($this->writeInternalLinks($links)) {
            $_SESSION['link_success'] = '状态已更新';
        } else {
            $_SESSION['link_error'] = '保存失败';
        }

        $this->redirect('/admin/seo/internal-links');
    }

    /**
     * POST /admin/seo/internal-links/delete
     *
     * Delete an internal link rule.
     */
    public function internallinksDelete(): void
    {
        Auth::requireCan('seo');

        if (!$this->isPost()) {
            $this->redirect('/admin/seo/internal-links');
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['link_error'] = 'Token 无效';
            $this->redirect('/admin/seo/internal-links');
        }

        $index = (int) $this->getPost('index', '-1');
        if ($index < 0) {
            $_SESSION['link_error'] = '无效的索引';
            $this->redirect('/admin/seo/internal-links');
        }

        $links = $this->readInternalLinks();
        if (!isset($links[$index])) {
            $_SESSION['link_error'] = '规则不存在';
            $this->redirect('/admin/seo/internal-links');
        }

        array_splice($links, $index, 1);

        if ($this->writeInternalLinks($links)) {
            $_SESSION['link_success'] = '规则已删除';
        } else {
            $_SESSION['link_error'] = '删除失败';
        }

        $this->redirect('/admin/seo/internal-links');
    }

    /**
     * Read internal links from JSON file.
     */
    private function readInternalLinks(): array
    {
        $file = DATA_PATH . '/internal_links.json';
        if (!file_exists($file)) {
            return [];
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        // Normalize each entry, limit to 200 rules
        $result = [];
        $count = 0;
        foreach ($data as $entry) {
            if ($count >= 200) {
                break;
            }
            if (!is_array($entry)) {
                continue;
            }
            $result[] = [
                'keyword' => trim((string) ($entry['keyword'] ?? '')),
                'url' => trim((string) ($entry['url'] ?? '')),
                'enabled' => (bool) ($entry['enabled'] ?? true),
                'case_sensitive' => (bool) ($entry['case_sensitive'] ?? false),
            ];
            $count++;
        }

        return $result;
    }

    /**
     * Write internal links to JSON file with LOCK_EX.
     */
    private function writeInternalLinks(array $links): bool
    {
        $file = DATA_PATH . '/internal_links.json';
        $json = json_encode($links, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($json === false) {
            error_log('[AdminSEOController] Failed to encode internal links');
            return false;
        }

        return file_put_contents($file, $json . "\n", LOCK_EX) !== false;
    }

    /**
     * Validate internal link keyword and URL.
     */
    private function validateInternalLink(string $keyword, string $url): string
    {
        if ($keyword === '') {
            return '关键词不能为空';
        }

        if ($url === '') {
            return '目标路径不能为空';
        }

        // Must start with /
        if ($url[0] !== '/') {
            return '目标路径必须以 / 开头（如 /en/product/slug）';
        }

        // Reject scheme-bearing URLs
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+\-.]*:#i', $url)) {
            return '禁止使用外部 URL 或危险协议（如 http://、javascript:）';
        }

        // Reject protocol-relative URLs
        if (strncmp($url, '//', 2) === 0) {
            return '禁止使用协议相对 URL（如 //evil.com）';
        }

        return '';
    }
}
