<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\JsonStore;

class AdminMenuController extends BaseController
{
    public function index(): void
    {
        Auth::requireCan('menu');

        if ($this->isPost()) {
            $this->handleSave();
            return;
        }

        $store = JsonStore::globalData('menus');
        $menus = $store->read();
        if (!is_array($menus)) {
            $menus = ['header' => [], 'footer' => []];
        }

        $this->view->render('menu/index', [
            'menus' => $menus,
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'menu',
            'pageTitle' => '菜单管理',
            'breadcrumbs' => [['label' => '菜单', 'url' => '/admin/menu']],
            'success' => $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['setting_error'] ?? '',
        ]);
        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    private function handleSave(): void
    {
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Token 无效';
            $this->redirect('/admin/menu');
        }

        $raw = $this->getPost('menus_json', '');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $_SESSION['setting_error'] = 'JSON 无效';
            $this->redirect('/admin/menu');
        }

        $clean = ['header' => [], 'footer' => []];
        foreach (['header', 'footer'] as $zone) {
            if (!isset($data[$zone]) || !is_array($data[$zone])) {
                continue;
            }
            foreach ($data[$zone] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $clean[$zone][] = [
                    'label' => trim((string) ($item['label'] ?? '')),
                    'url' => trim((string) ($item['url'] ?? '')),
                    'sort' => (int) ($item['sort'] ?? 0),
                    'enabled' => !empty($item['enabled']),
                ];
            }
        }

        JsonStore::globalData('menus')->write($clean);
        $_SESSION['setting_success'] = '菜单已保存';
        $this->redirect('/admin/menu');
    }
}
