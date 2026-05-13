<?php

namespace App\Controllers;

use App\Core\AdminListHelper;
use App\Core\Auth;
use App\Core\Config;
use App\Core\JsonStore;
use App\Core\NewsletterNotifier;
use App\Core\RichTextSanitizer;
use App\Core\View;

class AdminBlogController extends BaseController
{
    private array $supportedLangs;

    public function __construct(string $lang, bool $isAdmin = false)
    {
        parent::__construct($lang, $isAdmin);
        $this->supportedLangs = Config::get('supported_langs', ['en', 'cn', 'es']);
    }

    public function index(): void
    {
        Auth::requireCan('blog');

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        $store = JsonStore::langData($editLang, 'blog');
        $posts = $store->read();
        if (!is_array($posts)) {
            $posts = [];
        }

        $q = trim($this->getQuery('q', ''));
        $status = trim($this->getQuery('status', ''));
        $sort = trim($this->getQuery('sort', 'desc')) === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $this->getQuery('page', 1));
        $perPage = max(1, min(100, (int) $this->getQuery('per_page', 20)));

        [$pageRows, $total] = AdminListHelper::process($posts, [
            'q' => $q,
            'status' => $status,
            'sort' => $sort,
            'page' => $page,
            'per_page' => $perPage,
            'search_keys' => ['title', 'slug', 'desc', 'seo_title', 'seo_desc'],
        ]);

        $catStore = JsonStore::langData($editLang, 'blog_categories');
        $categories = $catStore->read();
        if (!is_array($categories)) {
            $categories = [];
        }

        $this->view->render('blog/index', [
            'posts' => $pageRows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
            'q' => $q,
            'status' => $status,
            'sort' => $sort,
            'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs,
            'categories' => $categories,
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'blog',
            'pageTitle' => '博客管理',
            'breadcrumbs' => [
                ['label' => '博客', 'url' => '/admin/blog'],
                ['label' => '列表'],
            ],
            'success' => $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['setting_error'] ?? '',
        ]);
        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    public function create(): void
    {
        Auth::requireCan('blog');

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
        Auth::requireCan('blog');

        if ($slug === '') {
            $this->redirect('/admin/blog');
        }

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        if ($this->isPost()) {
            $this->handleSave(true, $slug, $editLang);
            return;
        }

        $store = JsonStore::langData($editLang, 'blog');
        $posts = $store->read();
        $post = null;
        foreach ($posts as $p) {
            if (($p['slug'] ?? '') === $slug) {
                $post = $p;
                break;
            }
        }
        if ($post === null) {
            $_SESSION['setting_error'] = '文章不存在';
            $this->redirect('/admin/blog?lang=' . $editLang);
        }

        $this->renderForm($post, $editLang, true);
    }

    private function renderForm(?array $post, string $editLang, bool $isEdit): void
    {
        $catStore = JsonStore::langData($editLang, 'blog_categories');
        $categories = $catStore->read();
        if (!is_array($categories)) {
            $categories = [];
        }

        $products = JsonStore::langData($editLang, 'products')->read();
        if (!is_array($products)) {
            $products = [];
        }

        $this->view->render($isEdit ? 'blog/edit' : 'blog/create', [
            'post' => $post ?? $this->emptyPost(),
            'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs,
            'categories' => $categories,
            'productOptions' => $products,
            'csrfToken' => Auth::generateCsrfToken(),
            'isEdit' => $isEdit,
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'blog',
            'pageTitle' => $isEdit ? '编辑博客' : '新建博客',
            'breadcrumbs' => [
                ['label' => '博客', 'url' => '/admin/blog?lang=' . $editLang],
                ['label' => $isEdit ? '编辑' : '新建'],
            ],
            'success' => $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['setting_error'] ?? '',
        ]);
        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    private function emptyPost(): array
    {
        return [
            'title' => '',
            'slug' => '',
            'desc' => '',
            'content' => '',
            'image' => '',
            'date' => date('Y-m-d'),
            'published_at' => date('Y-m-d H:i:s'),
            'status' => 'draft',
            'seo_title' => '',
            'seo_desc' => '',
            'category_slug' => '',
            'tags' => [],
            'faqs' => [],
            'related_products' => [],
        ];
    }

    private function handleSave(bool $isEdit, string $origSlug, string $editLang): void
    {
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Token 无效';
            $this->redirect('/admin/blog?lang=' . $editLang);
        }

        $title = trim($this->getPost('title', ''));
        if ($title === '') {
            $_SESSION['setting_error'] = '标题必填';
            $this->redirect($isEdit ? '/admin/blog/edit/' . rawurlencode($origSlug) . '?lang=' . $editLang : '/admin/blog/create?lang=' . $editLang);
        }

        $slug = trim($this->getPost('slug', ''));
        if ($slug === '') {
            $slug = View::slugify($title);
        } else {
            $slug = View::slugify($slug);
        }

        $store = JsonStore::langData($editLang, 'blog');
        $posts = $store->read();
        if (!is_array($posts)) {
            $posts = [];
        }

        $exclude = $isEdit ? $origSlug : '';
        $slug = $this->ensureUniqueBlogSlug($posts, $slug, $exclude);

        $tagsRaw = trim($this->getPost('tags', ''));
        $tags = array_values(array_filter(array_map('trim', preg_split('/[,，]/u', $tagsRaw) ?: [])));

        $relatedRaw = trim($this->getPost('related_products', ''));
        $related = array_values(array_filter(array_map(function ($s) {
            return View::slugify(trim($s));
        }, preg_split('/[\s,，]+/u', $relatedRaw) ?: [])));

        $faqs = $this->parseFaqsJson($this->getPost('faqs_json', '[]'));

        $publishedAt = trim($this->getPost('published_at', ''));
        if ($publishedAt === '') {
            $publishedAt = trim($this->getPost('date', date('Y-m-d'))) . ' 12:00:00';
        }

        $data = [
            'title' => $title,
            'slug' => $slug,
            'desc' => trim($this->getPost('desc', '')),
            'content' => RichTextSanitizer::sanitize(trim($this->getPost('content', ''))),
            'image' => trim($this->getPost('image', '')),
            'date' => substr($publishedAt, 0, 10),
            'published_at' => $publishedAt,
            'status' => in_array($this->getPost('status', 'draft'), ['draft', 'published'], true)
                ? $this->getPost('status', 'draft') : 'draft',
            'seo_title' => trim($this->getPost('seo_title', '')),
            'seo_desc' => trim($this->getPost('seo_desc', '')),
            'category_slug' => trim($this->getPost('category_slug', '')),
            'tags' => $tags,
            'faqs' => $faqs,
            'related_products' => array_slice($related, 0, 20),
        ];

        $prevStatus = 'published';
        if ($isEdit) {
            foreach ($posts as $p) {
                if (($p['slug'] ?? '') === $origSlug) {
                    $raw = $p['status'] ?? 'published';
                    $prevStatus = in_array($raw, ['draft', 'published'], true) ? (string) $raw : 'published';
                    break;
                }
            }
        }

        if ($isEdit) {
            $store->update(function ($list) use ($origSlug, $data) {
                foreach ($list as &$p) {
                    if (($p['slug'] ?? '') === $origSlug) {
                        $p = array_merge($p, $data);
                        break;
                    }
                }
                unset($p);
                return $list;
            });
        } else {
            $store->update(function ($list) use ($data) {
                $list[] = $data;
                return $list;
            });
        }

        // Register 301 redirect when slug changes on edit
        if ($isEdit && $slug !== $origSlug) {
            \App\Core\SlugRegistry::registerRedirect($origSlug, $slug, 'blog', 301);
        }

        $newPublished = ($data['status'] ?? '') === 'published';
        $firstPublish = $newPublished && (!$isEdit || $prevStatus !== 'published');
        if ($firstPublish) {
            try {
                NewsletterNotifier::blogPublished($editLang, $slug, $title);
            } catch (\Throwable $e) {
                error_log('[Newsletter] blog notify: ' . $e->getMessage());
            }
        }

        $_SESSION['setting_success'] = $isEdit ? '博客已更新' : '博客已创建';
        $this->redirect('/admin/blog?lang=' . $editLang);
    }

    private function parseFaqsJson(string $raw): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            $q = trim((string) ($row['question'] ?? ''));
            $a = trim((string) ($row['answer'] ?? ''));
            if ($q !== '' && $a !== '') {
                $out[] = ['question' => $q, 'answer' => $a];
            }
        }
        return array_slice($out, 0, 30);
    }

    public function delete(string $slug = ''): void
    {
        Auth::requireCan('blog');

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        if ($slug === '' || !$this->isPost()) {
            $this->redirect('/admin/blog?lang=' . $editLang);
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $this->redirect('/admin/blog?lang=' . $editLang);
        }

        $store = JsonStore::langData($editLang, 'blog');
        $store->update(function ($list) use ($slug) {
            return array_values(array_filter($list, function ($p) use ($slug) {
                return ($p['slug'] ?? '') !== $slug;
            }));
        });

        $_SESSION['setting_success'] = '已删除';
        $this->redirect('/admin/blog?lang=' . $editLang);
    }

    /**
     * @param array<int,array<string,mixed>> $posts
     */
    private function ensureUniqueBlogSlug(array $posts, string $slug, string $excludeSlug): string
    {
        // Cross-type global uniqueness check via SlugRegistry
        return \App\Core\SlugRegistry::makeUniqueSlug($slug, 'blog', $excludeSlug);
    }
}
