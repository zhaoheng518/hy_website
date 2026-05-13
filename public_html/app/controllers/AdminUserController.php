<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\JsonStore;
use App\Repositories\AuthRepository;

class AdminUserController extends BaseController
{
    public function index(): void
    {
        Auth::requireCan('users', 'read');

        if ($this->isPost() && $this->getPost('action', '') === 'save_permissions') {
            Auth::requireCan('users', 'write');
            $this->savePermissions();

            return;
        }

        $users = [];
        $permMap = [];
        try {
            $db = Database::getInstance();
            $repo = new AuthRepository($db);
            $users = $repo->listUsers();
        } catch (\Throwable $e) {
            $_SESSION['setting_error'] = '无法读取用户表: ' . $e->getMessage();
        }

        try {
            $permMap = JsonStore::globalData('admin_permissions')->read();
            if (!is_array($permMap)) {
                $permMap = [];
            }
        } catch (\Throwable $e) {
            $permMap = [];
        }

        $logs = [];
        try {
            $logs = array_reverse(array_slice(JsonStore::globalData('login_log')->read(), -100));
            if (!is_array($logs)) {
                $logs = [];
            }
        } catch (\Throwable $e) {
            $logs = [];
        }

        $this->view->render('users/index', [
            'users' => $users,
            'permMap' => $permMap,
            'loginLogs' => $logs,
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'users',
            'pageTitle' => '账号与权限',
            'breadcrumbs' => [['label' => '用户', 'url' => '/admin/users']],
            'success' => $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['setting_error'] ?? '',
            'canManageUsers' => Auth::can('users', 'write'),
        ]);
        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    public function create(): void
    {
        Auth::requireCan('users', 'write');

        if (!$this->isPost()) {
            $this->redirect('/admin/users');
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Token';
            $this->redirect('/admin/users');
        }

        $username = trim($this->getPost('username', ''));
        $email = trim($this->getPost('email', ''));
        $password = $this->getPost('password', '');
        $role = trim($this->getPost('role', 'editor'));
        $allowedRoles = ['super_admin', 'admin', 'editor', 'viewer'];

        if ($username === '' || $email === '' || strlen($password) < 8) {
            $_SESSION['setting_error'] = '用户名/邮箱/密码(≥8)必填';
            $this->redirect('/admin/users');
        }

        if (!in_array($role, $allowedRoles, true)) {
            $role = 'editor';
        }

        if ($role === 'super_admin' && !Auth::isSuperAdmin()) {
            $_SESSION['setting_error'] = '仅 super_admin 可创建该角色';
            $this->redirect('/admin/users');
        }

        try {
            $db = Database::getInstance();
            $repo = new AuthRepository($db);
            $repo->createUser([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'is_active' => 1,
            ]);
            $_SESSION['setting_success'] = '用户已创建';
        } catch (\Throwable $e) {
            $_SESSION['setting_error'] = $e->getMessage();
        }

        $this->redirect('/admin/users');
    }

    public function delete(string $id = ''): void
    {
        Auth::requireCan('users', 'write');

        $uid = (int) $id;
        $selfId = (int) (Auth::user()['id'] ?? 0);
        if ($uid <= 0 || $uid === $selfId) {
            $this->redirect('/admin/users');
        }

        if (!$this->isPost() || !Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $this->redirect('/admin/users');
        }

        try {
            $db = Database::getInstance();
            $repo = new AuthRepository($db);
            $target = $repo->getUserById($uid);
            if ($target && ($target['role'] ?? '') === 'super_admin' && !Auth::isSuperAdmin()) {
                $_SESSION['setting_error'] = '无权删除 super_admin';

                $this->redirect('/admin/users');
            }
            $repo->delete($uid);
            $_SESSION['setting_success'] = '已删除';
        } catch (\Throwable $e) {
            $_SESSION['setting_error'] = $e->getMessage();
        }

        $this->redirect('/admin/users');
    }

    private function savePermissions(): void
    {
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Token';
            $this->redirect('/admin/users');
        }

        $raw = $this->getPost('permissions_json', '{}');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $_SESSION['setting_error'] = 'JSON 无效';
            $this->redirect('/admin/users');
        }

        JsonStore::globalData('admin_permissions')->write($data);
        $_SESSION['setting_success'] = '权限已保存';
        $this->redirect('/admin/users');
    }
}
