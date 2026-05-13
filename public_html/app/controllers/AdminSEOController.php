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
            $config['sitemap_enabled'] = $this->getPost('sitemap_enabled', '0') === '1';
            $config['google_analytics_id'] = trim($this->getPost('google_analytics_id', $config['google_analytics_id'] ?? ''));
            $config['gtm_container_id'] = trim($this->getPost('gtm_container_id', $config['gtm_container_id'] ?? ''));
            $config['google_ads_head'] = trim($this->getPost('google_ads_head', $config['google_ads_head'] ?? ''));
            $config['whatsapp_widget_script'] = trim($this->getPost('whatsapp_widget_script', $config['whatsapp_widget_script'] ?? ''));
            $config['schema_organization_json'] = trim($this->getPost('schema_organization_json', $config['schema_organization_json'] ?? ''));
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

        $service     = new SeoAuditService(DATA_PATH, $langs);
        $auditResult = $service->run($filterLang, $filterType);

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
     * GET /admin/seo/audit/export[?lang=en&type=product]
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

        $service = new SeoAuditService(DATA_PATH, $langs);
        $result  = $service->run($filterLang, $filterType);

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
}
