<?php

namespace App\Controllers;

use App\Core\AdminListHelper;
use App\Core\Auth;
use App\Core\UploadService;
use App\Core\View;

class AdminFileController extends BaseController
{
    private const BUCKETS = ['datasheets' => 'datasheets', 'images' => 'images', 'downloads' => 'downloads'];

    public function index(): void
    {
        Auth::requireCan('media');

        $bucket = $this->getQuery('bucket', 'images');
        if (!isset(self::BUCKETS[$bucket])) {
            $bucket = 'images';
        }

        $q = trim($this->getQuery('q', ''));
        $page = max(1, (int) $this->getQuery('page', 1));
        $perPage = 30;

        $dir = UPLOAD_PATH . '/' . self::BUCKETS[$bucket];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $rows = [];
        foreach (glob($dir . '/*') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $base = basename($path);
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'doc', 'docx', 'zip', 'jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }
            $rows[] = [
                'name' => $base,
                'url' => '/uploads/' . self::BUCKETS[$bucket] . '/' . $base,
                'size' => filesize($path) ?: 0,
                'created_at' => date('Y-m-d H:i:s', filemtime($path) ?: time()),
                'bucket' => $bucket,
            ];
        }

        [$pageRows, $total] = AdminListHelper::process($rows, [
            'q' => $q,
            'page' => $page,
            'per_page' => $perPage,
            'search_keys' => ['name', 'url'],
            'sort' => 'desc',
        ]);

        $this->view->render('files/index', [
            'files' => $pageRows,
            'bucket' => $bucket,
            'buckets' => array_keys(self::BUCKETS),
            'q' => $q,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => (int) ceil($total / $perPage),
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'files',
            'pageTitle' => '文件管理',
            'breadcrumbs' => [['label' => '文件', 'url' => '/admin/files']],
        ]);
    }

    public function upload(): void
    {
        Auth::requireCan('media');

        if (!$this->isPost()) {
            $this->redirect('/admin/files');
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Token 无效';
            $this->redirect('/admin/files');
        }

        $bucket = $this->getPost('bucket', 'images');
        if (!isset(self::BUCKETS[$bucket])) {
            $bucket = 'images';
        }

        if (!isset($_FILES['file'])) {
            $_SESSION['setting_error'] = '上传失败';
            $this->redirect('/admin/files?bucket=' . $bucket);
        }

        $replaceName = basename(trim($this->getPost('replace_name', '')));
        $subdir = self::BUCKETS[$bucket];
        $replaceWeb = $replaceName !== '' && $replaceName !== '.' && strpos($replaceName, '..') === false
            ? 'uploads/' . $subdir . '/' . $replaceName
            : null;

        $maxBytes = $bucket === 'images'
            ? UploadService::getMaxImageBytes()
            : UploadService::getMaxAdminDocumentBytes();

        $result = UploadService::process($_FILES['file'], [
            'bucket' => $bucket,
            'mode' => UploadService::MODE_ADMIN_MIXED,
            'max_bytes' => $maxBytes,
            'replace_web_path' => $replaceWeb,
            'use_slug_filename' => true,
        ]);

        if (!$result['ok']) {
            $_SESSION['setting_error'] = $result['error'] ?? '上传失败';
            $this->redirect('/admin/files?bucket=' . $bucket);
        }

        $_SESSION['setting_success'] = '上传成功';
        $this->redirect('/admin/files?bucket=' . $bucket);
    }

    public function delete(): void
    {
        Auth::requireCan('media');

        if (!$this->isPost()) {
            $this->redirect('/admin/files');
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $this->redirect('/admin/files');
        }

        $bucket = $this->getPost('bucket', 'images');
        if (!isset(self::BUCKETS[$bucket])) {
            $bucket = 'images';
        }

        $name = basename($this->getPost('name', ''));
        if ($name === '' || strpos($name, '..') !== false) {
            $this->redirect('/admin/files?bucket=' . $bucket);
        }

        $webPath = 'uploads/' . self::BUCKETS[$bucket] . '/' . $name;
        if (!UploadService::deleteWebPath($webPath)) {
            $_SESSION['setting_error'] = '删除失败或文件不存在';
        } else {
            $_SESSION['setting_success'] = '已删除';
        }
        $this->redirect('/admin/files?bucket=' . $bucket);
    }
}
