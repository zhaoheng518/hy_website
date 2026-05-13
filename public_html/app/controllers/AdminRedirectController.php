<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\JsonStore;

class AdminRedirectController extends BaseController
{
    public function index(): void
    {
        Auth::requireCan('settings');

        if ($this->isPost()) {
            $this->handlePost();
            return;
        }

        $redirects = $this->loadRedirectMap();
        $rows = [];
        foreach ($redirects as $from => $to) {
            $rows[] = ['from' => $from, 'to' => $to];
        }

        $this->view->render('redirects/index', [
            'redirects' => $rows,
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'redirects',
            'pageTitle' => '301 重定向管理',
            'breadcrumbs' => [['label' => '301 重定向', 'url' => '/admin/redirects']],
            'success' => $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['setting_error'] ?? '',
        ]);
        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    private function handlePost(): void
    {
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Token 无效';
            $this->redirect('/admin/redirects');
        }

        $action = trim($this->getPost('action', 'add'));
        $store = JsonStore::globalData('redirects');
        $map = $this->loadRedirectMap();

        if ($action === 'delete') {
            $from = $this->normalizePath((string) $this->getPost('from', ''));
            if ($from !== '' && isset($map[$from])) {
                unset($map[$from]);
            }
            $store->write($map);
            $_SESSION['setting_success'] = '重定向已删除';
            $this->redirect('/admin/redirects');
        }

        if ($action === 'update') {
            $oldFrom = $this->normalizePath((string) $this->getPost('old_from', ''));
            $from = $this->normalizePath((string) $this->getPost('from', ''));
            $to = trim((string) $this->getPost('to', ''));

            if ($oldFrom === '' || $from === '' || $to === '') {
                $_SESSION['setting_error'] = '旧路径和新路径都不能为空';
                $this->redirect('/admin/redirects');
            }
            if ($from === $to) {
                $_SESSION['setting_error'] = '旧路径和新路径不能相同';
                $this->redirect('/admin/redirects');
            }

            if ($oldFrom !== $from) {
                unset($map[$oldFrom]);
            }
            $map[$from] = $to;
            ksort($map);
            $store->write($map);
            $_SESSION['setting_success'] = '重定向已更新';
            $this->redirect('/admin/redirects');
        }

        $from = $this->normalizePath((string) $this->getPost('from', ''));
        $to = trim((string) $this->getPost('to', ''));
        if ($from === '' || $to === '') {
            $_SESSION['setting_error'] = '旧路径和新路径都不能为空';
            $this->redirect('/admin/redirects');
        }
        if ($from === $to) {
            $_SESSION['setting_error'] = '旧路径和新路径不能相同';
            $this->redirect('/admin/redirects');
        }

        $map[$from] = $to;
        ksort($map);
        $store->write($map);
        $_SESSION['setting_success'] = '重定向已保存';
        $this->redirect('/admin/redirects');
    }

    private function loadRedirectMap(): array
    {
        $raw = JsonStore::globalData('redirects')->read();
        if (!is_array($raw)) {
            return [];
        }

        $map = [];
        foreach ($raw as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $from = $this->normalizePath($k);
                if ($from !== '') {
                    $map[$from] = trim($v);
                }
                continue;
            }
            if (is_array($v)) {
                $from = $this->normalizePath((string) ($v['from'] ?? ''));
                $to = trim((string) ($v['to'] ?? ''));
                if ($from !== '' && $to !== '') {
                    $map[$from] = $to;
                }
            }
        }

        return $map;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            $parts = parse_url($path);
            $path = $parts['path'] ?? '';
        }
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return $path;
    }
}
