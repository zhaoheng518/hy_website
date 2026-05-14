<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Services\AdminMenuService;

/**
 * 后台侧边栏菜单配置：/admin/menu-settings
 */
class AdminMenuSettingsController extends BaseController
{
    private function service(): AdminMenuService
    {
        return new AdminMenuService(Database::getInstance());
    }

    /** 管理页列表 */
    public function index(): void
    {
        Auth::requireCan('settings', 'read');
        $svc = $this->service();
        $rows = $svc->fetchAllRows();
        $flatOptions = $this->flatParentOptions($svc, $rows);

        $this->view->render('menu_settings/index', [
            'rows' => $rows,
            'parentOptions' => $flatOptions,
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'menu_settings',
            'pageTitle' => '侧边栏菜单',
            'breadcrumbs' => [
                ['label' => '系统', 'url' => '/admin/settings'],
                ['label' => '侧边栏菜单', 'url' => '/admin/menu-settings'],
            ],
            'success' => $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['setting_error'] ?? '',
            'tableMissing' => !$svc->tableExists(),
        ]);
        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    /**
     * AJAX：批量保存 sort_order。
     * POST JSON: { "_csrf":"...", "orders": [ {"id":1,"sort_order":10}, ... ] }
     */
    public function saveOrder(): void
    {
        Auth::requireCan('settings', 'write');
        header('Content-Type: application/json; charset=utf-8');
        $raw = (string) file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data) || !Auth::validateCsrfToken((string) ($data['_csrf'] ?? ''))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $orders = $data['orders'] ?? [];
        if (!is_array($orders)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Bad payload'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $this->service()->updateSortOrders($orders);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /**
     * AJAX：切换 is_visible。
     * POST JSON: { "_csrf":"...", "id": 1, "is_visible": 1 }
     */
    public function toggleVisible(): void
    {
        Auth::requireCan('settings', 'write');
        header('Content-Type: application/json; charset=utf-8');
        $raw = (string) file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data) || !Auth::validateCsrfToken((string) ($data['_csrf'] ?? ''))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $id = (int) ($data['id'] ?? 0);
        $vis = !empty($data['is_visible']);
        if ($id < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Bad id'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $this->service()->updateVisible($id, $vis);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /**
     * 保存单条（新增 id=0 或更新）。
     */
    public function saveItem(): void
    {
        Auth::requireCan('settings', 'write');
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Token 无效';
            $this->redirect('/admin/menu-settings');
            exit;
        }
        $svc = $this->service();
        $id = (int) $this->getPost('id', 0);
        $payload = [
            'parent_id' => ($this->getPost('parent_id', '') === '' || $this->getPost('parent_id', '') === '0')
                ? null
                : (int) $this->getPost('parent_id', ''),
            'title' => $this->getPost('title', ''),
            'icon' => $this->getPost('icon', 'default'),
            'route' => $this->getPost('route', ''),
            'sort_order' => (int) $this->getPost('sort_order', 0),
            'is_visible' => $this->getPost('is_visible', '0') === '1',
            'is_collapsible' => $this->getPost('is_collapsible', '0') === '1',
            'active_key' => $this->getPost('active_key', ''),
            'permission_key' => $this->getPost('permission_key', ''),
        ];
        if (trim((string) $payload['title']) === '') {
            $_SESSION['setting_error'] = '标题不能为空';
            $this->redirect('/admin/menu-settings');
            exit;
        }
        try {
            if ($id > 0) {
                if (!$svc->isValidParent($id, $payload['parent_id'] !== null ? (int) $payload['parent_id'] : null)) {
                    throw new \RuntimeException('父级选择无效（不能选自己或子级）');
                }
                $svc->updateRow($id, $payload);
                $_SESSION['setting_success'] = '菜单已更新';
            } else {
                $svc->insertRow($payload);
                $_SESSION['setting_success'] = '菜单已新增';
            }
        } catch (\Throwable $e) {
            $_SESSION['setting_error'] = $e->getMessage();
        }
        $this->redirect('/admin/menu-settings');
    }

    /**
     * 删除菜单（无子级才可删）。
     */
    public function deleteItem(): void
    {
        Auth::requireCan('settings', 'write');
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Token 无效';
            $this->redirect('/admin/menu-settings');
            exit;
        }
        $id = (int) $this->getPost('id', 0);
        if ($id < 1) {
            $_SESSION['setting_error'] = '无效 ID';
            $this->redirect('/admin/menu-settings');
            exit;
        }
        try {
            $this->service()->deleteRow($id);
            $_SESSION['setting_success'] = '已删除';
        } catch (\Throwable $e) {
            $_SESSION['setting_error'] = $e->getMessage();
        }
        $this->redirect('/admin/menu-settings');
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{id:int,label:string}>
     */
    private function flatParentOptions(AdminMenuService $svc, array $rows): array
    {
        $tree = $svc->buildTree($rows);
        $out = [['id' => 0, 'label' => '（顶级）']];
        $walk = function (array $nodes, int $depth) use (&$walk, &$out): void {
            foreach ($nodes as $n) {
                $id = (int) ($n['id'] ?? 0);
                $title = (string) ($n['title'] ?? '');
                $pad = str_repeat('—', max(0, $depth));
                $out[] = ['id' => $id, 'label' => ($pad !== '' ? $pad . ' ' : '') . $title];
                if (!empty($n['children']) && is_array($n['children'])) {
                    $walk($n['children'], $depth + 1);
                }
            }
        };
        $walk($tree, 0);

        return $out;
    }
}
