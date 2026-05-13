<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\JsonStore;
use App\Core\NotFoundLogger;

/**
 * Admin 404 Monitor
 *
 * Routes (all under /admin/404monitor):
 *   GET  /admin/404monitor              → index()
 *   POST /admin/404monitor/addRedirect  → addRedirect()
 *   POST /admin/404monitor/dismiss      → dismiss()
 *   POST /admin/404monitor/clear        → clear()
 */
class Admin404Controller extends BaseController
{
    // ── List ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        Auth::requireCan('settings');

        $entries = NotFoundLogger::readAll();

        // Simple search filter
        $q = trim((string) $this->getQuery('q', ''));
        if ($q !== '') {
            $qLower = strtolower($q);
            $entries = array_values(array_filter($entries, static function (array $e) use ($qLower): bool {
                return strpos(strtolower((string) ($e['url'] ?? '')), $qLower) !== false;
            }));
        }

        // Pagination
        $perPage     = 50;
        $currentPage = max(1, (int) $this->getQuery('page', '1'));
        $total       = count($entries);
        $totalPages  = max(1, (int) ceil($total / $perPage));
        $currentPage = min($currentPage, $totalPages);
        $paged       = array_slice($entries, ($currentPage - 1) * $perPage, $perPage);

        // Aggregate stats
        $totalHits   = 0;
        $allEntries  = NotFoundLogger::readAll();
        foreach ($allEntries as $e) {
            $totalHits += (int) ($e['count'] ?? 0);
        }

        $this->view->render('404monitor/index', [
            'csrfToken'    => Auth::generateCsrfToken(),
            'adminUser'    => Auth::user()['username'] ?? 'Admin',
            'activeMenu'   => '404monitor',
            'pageTitle'    => '404 监控',
            'breadcrumbs'  => [['label' => '404 监控', 'url' => '/admin/404monitor']],
            'success'      => $_SESSION['monitor404_success'] ?? '',
            'error'        => $_SESSION['monitor404_error']   ?? '',
            'entries'      => $paged,
            'totalEntries' => count($allEntries),
            'totalHits'    => $totalHits,
            'currentPage'  => $currentPage,
            'totalPages'   => $totalPages,
            'q'            => $q,
        ]);
        unset($_SESSION['monitor404_success'], $_SESSION['monitor404_error']);
    }

    // ── Add 301 redirect from a 404 URL ──────────────────────────────────────

    public function addRedirect(): void
    {
        Auth::requireCan('settings');

        if (!$this->isPost()) {
            $this->redirect('/admin/404monitor');
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['monitor404_error'] = 'Token 无效，请刷新后重试';
            $this->redirect('/admin/404monitor');
        }

        $from = $this->normalizePath($this->getPost('from', ''));
        $to   = trim($this->getPost('to', ''));

        if ($from === '' || $to === '') {
            $_SESSION['monitor404_error'] = '来源路径和目标路径不能为空';
            $this->redirect('/admin/404monitor');
        }

        if ($from === $to) {
            $_SESSION['monitor404_error'] = '来源路径和目标路径不能相同';
            $this->redirect('/admin/404monitor');
        }

        // Write to redirects.json (string-map format, compatible with AdminRedirectController)
        $store = JsonStore::globalData('redirects');
        $map   = $this->loadRedirectMap($store);
        $map[$from] = $to;
        ksort($map);

        try {
            $store->write($map);
        } catch (\Throwable $e) {
            error_log('[Admin404Controller] redirects write failed: ' . $e->getMessage());
            $_SESSION['monitor404_error'] = '写入重定向失败，请检查文件权限';
            $this->redirect('/admin/404monitor');
        }

        // Remove this URL from 404 log (it is now redirected)
        NotFoundLogger::dismiss($from);

        $_SESSION['monitor404_success'] = "已添加 301 重定向：{$from} → {$to}";
        $this->redirect('/admin/404monitor');
    }

    // ── Dismiss a single log entry ────────────────────────────────────────────

    public function dismiss(): void
    {
        Auth::requireCan('settings');

        if (!$this->isPost()) {
            $this->redirect('/admin/404monitor');
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['monitor404_error'] = 'Token 无效';
            $this->redirect('/admin/404monitor');
        }

        $url = trim($this->getPost('url', ''));
        if ($url !== '') {
            NotFoundLogger::dismiss($url);
        }

        $_SESSION['monitor404_success'] = '已忽略该记录';
        $this->redirect('/admin/404monitor');
    }

    // ── Clear entire log ──────────────────────────────────────────────────────

    public function clear(): void
    {
        Auth::requireCan('settings');

        if (!$this->isPost()) {
            $this->redirect('/admin/404monitor');
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['monitor404_error'] = 'Token 无效';
            $this->redirect('/admin/404monitor');
        }

        NotFoundLogger::clear();

        $_SESSION['monitor404_success'] = '404 日志已清空';
        $this->redirect('/admin/404monitor');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            $parts = parse_url($path);
            $path  = (string) ($parts['path'] ?? '');
        }
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    private function loadRedirectMap(JsonStore $store): array
    {
        try {
            $raw = $store->read();
        } catch (\Throwable $e) {
            return [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $map = [];
        foreach ($raw as $k => $v) {
            // String-map format: {"/old": "/new"}
            if (is_string($k) && is_string($v)) {
                $from = $this->normalizePath($k);
                if ($from !== '') {
                    $map[$from] = trim($v);
                }
                continue;
            }
            // Array format: [{"from": "/old", "to": "/new"}]
            if (is_array($v)) {
                $from = $this->normalizePath((string) ($v['from'] ?? ''));
                $to   = trim((string) ($v['to'] ?? ''));
                if ($from !== '' && $to !== '') {
                    $map[$from] = $to;
                }
            }
        }
        return $map;
    }
}
