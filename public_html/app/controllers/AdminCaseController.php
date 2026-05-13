<?php

namespace App\Controllers;

use App\Core\AdminListHelper;
use App\Core\Auth;
use App\Core\Config;
use App\Core\JsonStore;
use App\Core\RichTextSanitizer;
use App\Core\View;

class AdminCaseController extends BaseController
{
    private array $supportedLangs;

    public function __construct(string $lang, bool $isAdmin = false)
    {
        parent::__construct($lang, $isAdmin);
        $this->supportedLangs = Config::get('supported_langs', ['en', 'cn', 'es']);
    }

    public function index(): void
    {
        Auth::requireCan('cases');

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        $list = JsonStore::langData($editLang, 'cases')->read();
        if (!is_array($list)) {
            $list = [];
        }

        $q = trim($this->getQuery('q', ''));
        $status = trim($this->getQuery('status', ''));
        $sort = trim($this->getQuery('sort', 'desc')) === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $this->getQuery('page', 1));
        $perPage = max(1, min(100, (int) $this->getQuery('per_page', 20)));

        foreach ($list as &$row) {
            if (!isset($row['published_at']) && isset($row['date'])) {
                $row['published_at'] = $row['date'];
            }
        }
        unset($row);

        [$pageRows, $total] = AdminListHelper::process($list, [
            'q' => $q,
            'status' => $status,
            'sort' => $sort,
            'page' => $page,
            'per_page' => $perPage,
            'search_keys' => ['title', 'slug', 'desc', 'client_industry', 'products_used', 'seo_title'],
        ]);

        $this->view->render('case/index', [
            'cases' => $pageRows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
            'q' => $q,
            'status' => $status,
            'sort' => $sort,
            'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs,
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'cases_admin',
            'pageTitle' => '案例管理',
            'breadcrumbs' => [['label' => '案例', 'url' => '/admin/case'], ['label' => '列表']],
            'success' => $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['setting_error'] ?? '',
        ]);
        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    public function create(): void
    {
        Auth::requireCan('cases');

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        if ($this->isPost()) {
            $this->handleSave(false, '', $editLang);
            return;
        }

        $this->renderForm(null, $editLang, false);
    }

    public function edit(string $slug = ''): void
    {
        Auth::requireCan('cases');

        if ($slug === '') {
            $this->redirect('/admin/case');
        }

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        if ($this->isPost()) {
            $this->handleSave(true, $slug, $editLang);
            return;
        }

        $list = JsonStore::langData($editLang, 'cases')->read();
        $item = null;
        foreach ($list as $c) {
            if (($c['slug'] ?? '') === $slug) {
                $item = $c;
                break;
            }
        }
        if ($item === null) {
            $_SESSION['setting_error'] = '案例不存在';
            $this->redirect('/admin/case?lang=' . $editLang);
        }

        $this->renderForm($item, $editLang, true);
    }

    private function renderForm(?array $item, string $editLang, bool $isEdit): void
    {
        $products = JsonStore::langData($editLang, 'products')->read();
        if (!is_array($products)) {
            $products = [];
        }

        $this->view->render($isEdit ? 'case/edit' : 'case/create', [
            'caseItem' => $item ?? $this->emptyCase(),
            'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs,
            'productOptions' => $products,
            'csrfToken' => Auth::generateCsrfToken(),
            'isEdit' => $isEdit,
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'cases_admin',
            'pageTitle' => $isEdit ? '编辑案例' : '新建案例',
            'breadcrumbs' => [['label' => '案例', 'url' => '/admin/case?lang=' . $editLang], ['label' => $isEdit ? '编辑' : '新建']],
        ]);
    }

    private function emptyCase(): array
    {
        return [
            'title' => '',
            'slug' => '',
            'desc' => '',
            'content' => '',
            'image' => '',
            'gallery' => [],
            'seo_title' => '',
            'seo_desc' => '',
            'client_industry' => '',
            'products_used' => '',
            'related_products' => [],
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function handleSave(bool $isEdit, string $origSlug, string $editLang): void
    {
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Token 无效';
            $this->redirect('/admin/case?lang=' . $editLang);
        }

        $title = trim($this->getPost('title', ''));
        if ($title === '') {
            $_SESSION['setting_error'] = '标题必填';
            $this->redirect($isEdit ? '/admin/case/edit/' . rawurlencode($origSlug) . '?lang=' . $editLang : '/admin/case/create?lang=' . $editLang);
        }

        $slug = trim($this->getPost('slug', ''));
        if ($slug === '') {
            $slug = View::slugify($title);
        } else {
            $slug = View::slugify($slug);
        }

        $store = JsonStore::langData($editLang, 'cases');
        $list = $store->read();
        if (!is_array($list)) {
            $list = [];
        }

        $slug = $this->ensureUniqueCaseSlug($list, $slug, $isEdit ? $origSlug : '');

        $galleryRaw = trim($this->getPost('gallery_json', '[]'));
        $gallery = json_decode($galleryRaw, true);
        if (!is_array($gallery)) {
            $gallery = [];
        }
        $gallery = array_values(array_filter(array_map(function ($g) {
            $u = is_string($g) ? $g : ($g['url'] ?? '');
            return trim($u);
        }, $gallery)));

        $relRaw = trim($this->getPost('related_products', ''));
        $related = array_values(array_filter(array_map(function ($s) {
            return View::slugify(trim($s));
        }, preg_split('/[\s,，]+/u', $relRaw) ?: [])));

        $publishedAt = trim($this->getPost('published_at', date('Y-m-d H:i:s')));

        $data = [
            'title' => $title,
            'slug' => $slug,
            'desc' => trim($this->getPost('desc', '')),
            'content' => RichTextSanitizer::sanitize(trim($this->getPost('content', ''))),
            'image' => trim($this->getPost('image', '')),
            'gallery' => array_slice($gallery, 0, 30),
            'seo_title' => trim($this->getPost('seo_title', '')),
            'seo_desc' => trim($this->getPost('seo_desc', '')),
            'client_industry' => trim($this->getPost('client_industry', '')),
            'products_used' => trim($this->getPost('products_used', '')),
            'related_products' => array_slice($related, 0, 20),
            'status' => in_array($this->getPost('status', 'published'), ['draft', 'published'], true)
                ? $this->getPost('status', 'published') : 'published',
            'published_at' => $publishedAt,
            'date' => substr($publishedAt, 0, 10),
        ];

        if ($isEdit) {
            $store->update(function ($rows) use ($origSlug, $data) {
                foreach ($rows as &$r) {
                    if (($r['slug'] ?? '') === $origSlug) {
                        $r = array_merge($r, $data);
                        break;
                    }
                }
                unset($r);
                return $rows;
            });
        } else {
            $store->update(function ($rows) use ($data) {
                $rows[] = $data;
                return $rows;
            });
        }

        $_SESSION['setting_success'] = $isEdit ? '案例已更新' : '案例已创建';
        $this->redirect('/admin/case?lang=' . $editLang);
    }

    public function delete(string $slug = ''): void
    {
        Auth::requireCan('cases');

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        if ($slug === '' || !$this->isPost()) {
            $this->redirect('/admin/case?lang=' . $editLang);
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $this->redirect('/admin/case?lang=' . $editLang);
        }

        $store = JsonStore::langData($editLang, 'cases');
        $store->update(function ($rows) use ($slug) {
            return array_values(array_filter($rows, function ($r) use ($slug) {
                return ($r['slug'] ?? '') !== $slug;
            }));
        });

        $_SESSION['setting_success'] = '已删除';
        $this->redirect('/admin/case?lang=' . $editLang);
    }

    /**
     * @param array<int,array<string,mixed>> $list
     */
    private function ensureUniqueCaseSlug(array $list, string $slug, string $excludeSlug): string
    {
        $existing = [];
        foreach ($list as $r) {
            $s = (string) ($r['slug'] ?? '');
            if ($s !== '' && ($excludeSlug === '' || $s !== $excludeSlug)) {
                $existing[] = $s;
            }
        }
        $base = $slug;
        $n = 1;
        while (in_array($slug, $existing, true)) {
            $slug = $base . '-' . $n;
            $n++;
        }
        return $slug;
    }
}
