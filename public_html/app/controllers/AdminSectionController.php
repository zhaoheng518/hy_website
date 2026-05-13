<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\JsonStore;
use App\Core\UploadService;

class AdminSectionController extends BaseController
{
    private const VALID_TYPES = ['banner', 'products', 'cases', 'about', 'blog', 'contact'];
    private const TYPE_LABELS = [
        'banner' => '横幅',
        'products' => '产品',
        'cases' => '案例',
        'about' => '关于我们',
        'blog' => '博客',
        'contact' => '联系我们',
    ];
    private const IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    public function index(): void
    {
        Auth::requireCan('settings');

        $store = JsonStore::globalData('sections');
        $sections = $this->normalizeSections($store->read());

        $this->view->render('sections/index', [
            'sections' => $sections['sections'],
            'typeLabels' => self::TYPE_LABELS,
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'sections',
            'pageTitle' => '首页板块管理',
            'breadcrumbs' => [['label' => '首页板块', 'url' => '/admin/sections']],
            'flashSuccess' => $_SESSION['setting_success'] ?? '',
            'flashError' => $_SESSION['setting_error'] ?? '',
        ]);

        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    public function edit(string $type = ''): void
    {
        Auth::requireCan('settings');

        $type = trim(strtolower($type));
        if (!in_array($type, self::VALID_TYPES, true)) {
            $this->redirect('/admin/sections');
        }

        $store = JsonStore::globalData('sections');
        $data = $this->normalizeSections($store->read());
        $details = $this->normalizeDetails($data['details'] ?? []);
        $current = null;
        foreach ($data['sections'] as $row) {
            if (($row['type'] ?? '') === $type) {
                $current = $row;
                break;
            }
        }
        if ($current === null) {
            $current = ['type' => $type, 'enabled' => true, 'order' => 0];
        }

        $this->view->render('sections/edit', [
            'section' => $current,
            'sectionDetail' => $details[$type] ?? [],
            'typeLabels' => self::TYPE_LABELS,
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'sections',
            'pageTitle' => '编辑首页板块',
            'breadcrumbs' => [
                ['label' => '首页板块', 'url' => '/admin/sections'],
                ['label' => self::TYPE_LABELS[$type] ?? ucfirst($type), 'url' => '/admin/sections/edit/' . rawurlencode($type)],
            ],
            'flashSuccess' => $_SESSION['setting_success'] ?? '',
            'flashError' => $_SESSION['setting_error'] ?? '',
        ]);

        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    public function update(): void
    {
        Auth::requireCan('settings');

        if (!$this->isPost()) {
            $this->redirect('/admin/sections');
        }

        $csrf = $this->getPost('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $this->jsonError('Invalid security token.', 403);
        }

        $sectionsJson = $this->getPost('sections', '[]');
        $sections = json_decode($sectionsJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $_SESSION['form_error'] = 'Invalid section data.';
            $this->redirect('/admin/sections');
        }

        $sanitized = [];

        foreach ($sections as $section) {
            if (!isset($section['type']) || !in_array($section['type'], self::VALID_TYPES, true)) {
                continue;
            }
            $sanitized[] = [
                'type' => $section['type'],
                'enabled' => !empty($section['enabled']),
                'order' => (int) ($section['order'] ?? 0),
            ];
        }

        usort($sanitized, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        $store = JsonStore::globalData('sections');
        $store->write(['sections' => $sanitized]);

        if ($this->isAjax()) {
            $this->jsonSuccess(['message' => 'Sections updated successfully.']);
        }

        $this->redirect('/admin/sections');
    }

    public function save(string $type = ''): void
    {
        Auth::requireCan('settings');

        if (!$this->isPost()) {
            $this->redirect('/admin/sections');
        }

        $csrf = $this->getPost('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $_SESSION['setting_error'] = 'Token 无效';
            $this->redirect('/admin/sections');
        }

        $type = trim(strtolower($type !== '' ? $type : $this->getPost('type', '')));
        if (!in_array($type, self::VALID_TYPES, true)) {
            $_SESSION['setting_error'] = '无效板块类型';
            $this->redirect('/admin/sections');
        }

        $enabled = $this->getPost('enabled', '0') === '1';
        $order = max(0, (int) $this->getPost('order', 0));
        $detail = [
            'title' => trim($this->getPost('title', '')),
            'subtitle' => trim($this->getPost('subtitle', '')),
            'description' => trim($this->getPost('description', '')),
            'button_text' => trim($this->getPost('button_text', '')),
            'button_link' => trim($this->getPost('button_link', '')),
        ];

        $store = JsonStore::globalData('sections');
        $store->update(function (array $data) use ($type, $enabled, $order, $detail) {
            $normalized = $this->normalizeSections($data);
            $details = $this->normalizeDetails($normalized['details'] ?? []);

            $sections = $normalized['sections'];
            $found = false;
            foreach ($sections as &$row) {
                if (($row['type'] ?? '') === $type) {
                    $row['enabled'] = $enabled;
                    $row['order'] = $order;
                    $found = true;
                    break;
                }
            }
            unset($row);
            if (!$found) {
                $sections[] = ['type' => $type, 'enabled' => $enabled, 'order' => $order];
            }

            $details[$type] = array_merge($details[$type] ?? [], $detail);

            if (isset($_FILES['banner_image']) && ($_FILES['banner_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $path = $this->saveUploadedBanner($_FILES['banner_image']);
                if ($path !== '') {
                    $details[$type]['image'] = $path;
                }
            } elseif ($this->getPost('remove_image', '0') === '1') {
                $details[$type]['image'] = '';
            }

            usort($sections, fn(array $a, array $b): int => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));

            return [
                'sections' => $sections,
                'details' => $details,
            ];
        });

        $_SESSION['setting_success'] = '板块已保存';
        $this->redirect('/admin/sections/edit/' . rawurlencode($type));
    }

    public function toggle(): void
    {
        Auth::requireCan('settings');

        if (!$this->isPost()) {
            $this->jsonError('Method not allowed.', 405);
        }

        $csrf = $this->getPost('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $this->jsonError('Invalid security token.', 403);
        }

        $type = trim($this->getPost('type', ''));
        $enabled = $this->getPost('enabled', '0');

        if (!in_array($type, self::VALID_TYPES, true)) {
            $this->jsonError('Invalid section type.');
        }

        $store = JsonStore::globalData('sections');
        $store->update(function ($data) use ($type, $enabled) {
            if (!isset($data['sections'])) {
                $data['sections'] = [];
            }
            foreach ($data['sections'] as &$section) {
                if ($section['type'] === $type) {
                    $section['enabled'] = !empty($enabled);
                    break;
                }
            }
            unset($section);
            return $data;
        });

        $this->jsonSuccess(['message' => 'Section updated.']);
    }

    private function normalizeSections(array $data): array
    {
        $input = $data['sections'] ?? [];
        $details = $data['details'] ?? [];
        $normalized = [];

        foreach ($input as $row) {
            $type = trim((string) ($row['type'] ?? ''));
            if ($type === '' || !in_array($type, self::VALID_TYPES, true)) {
                continue;
            }
            $normalized[$type] = [
                'type' => $type,
                'enabled' => !empty($row['enabled']),
                'order' => (int) ($row['order'] ?? count($normalized)),
            ];
        }

        foreach (self::VALID_TYPES as $index => $type) {
            if (!isset($normalized[$type])) {
                $normalized[$type] = [
                    'type' => $type,
                    'enabled' => true,
                    'order' => $index,
                ];
            }
        }

        $sections = array_values($normalized);
        usort($sections, fn(array $a, array $b): int => $a['order'] <=> $b['order']);

        return [
            'sections' => $sections,
            'details' => $this->normalizeDetails(is_array($details) ? $details : []),
        ];
    }

    private function normalizeDetails(array $details): array
    {
        $result = [];
        foreach (self::VALID_TYPES as $type) {
            $row = isset($details[$type]) && is_array($details[$type]) ? $details[$type] : [];
            $result[$type] = [
                'title' => trim((string) ($row['title'] ?? '')),
                'subtitle' => trim((string) ($row['subtitle'] ?? '')),
                'description' => trim((string) ($row['description'] ?? '')),
                'button_text' => trim((string) ($row['button_text'] ?? '')),
                'button_link' => trim((string) ($row['button_link'] ?? '')),
                'image' => trim((string) ($row['image'] ?? '')),
            ];
        }
        return $result;
    }

    private function saveUploadedBanner(array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return '';
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, self::IMAGE_TYPES, true)) {
            return '';
        }

        $baseName = 'section_banner_' . date('YmdHis') . '_' . substr(uniqid('', true), -8) . '.' . $ext;
        $result = UploadService::process($file, [
            'bucket' => UploadService::BUCKET_IMAGES,
            'mode' => UploadService::MODE_STRICT_IMAGE,
            'max_bytes' => UploadService::getMaxImageBytes(),
            'custom_basename' => $baseName,
        ]);

        if (!$result['ok']) {
            return '';
        }

        return $result['web_path'] ?? '';
    }
}
