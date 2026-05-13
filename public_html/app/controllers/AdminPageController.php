<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\JsonStore;
use App\Core\RichTextSanitizer;
use App\Core\View;

class AdminPageController extends BaseController
{
    private array $supportedLangs;

    public function __construct(string $lang, bool $isAdmin = false)
    {
        parent::__construct($lang, $isAdmin);
        $this->supportedLangs = Config::get('supported_langs', ['en', 'cn', 'es']);
    }

    public function index(): void
    {
        Auth::requireCan('pages');

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        $store = new JsonStore(DATA_PATH . "/{$editLang}/pages.json");
        $pages = $store->read();

        $this->view->render('page/index', [
            'pages' => $pages,
            'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs,
            'adminUser' => Auth::user(),
            'activeMenu' => 'pages',
            'pageTitle' => '页面管理',
            'breadcrumbs' => [['label' => '页面', 'url' => '/admin/pages'], ['label' => '列表']],
        ]);
    }

    /** CMS hub: /admin/page */
    public function cmsIndex(): void
    {
        Auth::requireCan('pages');

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        $store = new JsonStore(DATA_PATH . "/{$editLang}/pages.json");
        $pages = $store->read();

        $this->view->render('page/index', [
            'pages' => $pages,
            'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs,
            'adminUser' => Auth::user(),
            'activeMenu' => 'page_cms',
            'pageTitle' => '页面 CMS',
            'breadcrumbs' => [['label' => '页面 CMS', 'url' => '/admin/page']],
        ]);
    }

    public function create(): void
    {
        Auth::requireCan('pages');

        if ($this->isPost()) {
            $this->handleSave(false);
            return;
        }

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        $this->view->render('page/edit', [
            'page' => ['title' => '', 'slug' => '', 'subtitle' => '', 'featured_image' => '', 'content' => '', 'seo_title' => '', 'seo_description' => '', 'faqs' => []],
            'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs,
            'csrfToken' => Auth::generateCsrfToken(),
            'isEdit' => false,
            'adminUser' => Auth::user(),
        ]);
    }

    public function edit(string $slug = ''): void
    {
        Auth::requireCan('pages');

        if (empty($slug)) {
            $this->redirect('/admin/pages');
        }

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        if ($this->isPost()) {
            $this->handleSave(true, $slug, $editLang);
            return;
        }

        $store = new JsonStore(DATA_PATH . "/{$editLang}/pages.json");
        $pages = $store->read();
        $page = null;

        foreach ($pages as $p) {
            if (($p['slug'] ?? '') === $slug) {
                $page = $p;
                break;
            }
        }

        if ($page === null) {
            $_SESSION['setting_error'] = '页面未找到。';
            $this->redirect('/admin/pages?lang=' . $editLang);
        }

        $this->view->render('page/edit', [
            'page' => $page,
            'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs,
            'csrfToken' => Auth::generateCsrfToken(),
            'isEdit' => true,
            'adminUser' => Auth::user(),
            'activeMenu' => 'pages',
            'pageTitle' => '编辑页面',
        ]);
    }

    private function handleSave(bool $isEdit, string $origSlug = '', string $editLang = 'en'): void
    {
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Token无效';
            $this->redirect('/admin/pages');
        }

        $editLang = $this->getPost('edit_lang', $editLang);
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        $title = trim($this->getPost('title', ''));
        if ($title === '') {
            $_SESSION['setting_error'] = '标题不能为空';
            $this->redirect('/admin/pages/create?lang=' . $editLang);
        }

        $slug = trim($this->getPost('slug', ''));
        $slug = $slug ? View::slugify($slug) : View::slugify($title);
        // Cross-type global uniqueness — auto-append suffix to prevent SEO collisions
        $slug = \App\Core\SlugRegistry::makeUniqueSlug($slug, 'page', $isEdit ? $origSlug : '');

        $faqs = $this->parsePageFaqs($this->getPost('faqs_json', '[]'));

        $data = [
            'title' => $title,
            'slug' => $slug,
            'subtitle' => trim($this->getPost('subtitle', '')),
            'featured_image' => trim($this->getPost('featured_image', '')),
            'content' => RichTextSanitizer::sanitize(trim($this->getPost('content', ''))),
            'seo_title' => trim($this->getPost('seo_title', '')),
            'seo_description' => trim($this->getPost('seo_description', '')),
            'faqs' => $faqs,
        ];

        $store = new JsonStore(DATA_PATH . "/{$editLang}/pages.json");

        if ($isEdit) {
            $store->update(function ($pages) use ($origSlug, $data) {
                foreach ($pages as &$p) {
                    if (($p['slug'] ?? '') === $origSlug) {
                        $p = $data;
                        break;
                    }
                }
                unset($p);
                return $pages;
            });
        } else {
            $store->update(function ($pages) use ($data) {
                $pages[] = $data;
                return $pages;
            });
        }

        // Register 301 redirect when slug changes on edit
        if ($isEdit && $slug !== $origSlug) {
            \App\Core\SlugRegistry::registerRedirect($origSlug, $slug, 'page', 301);
        }
        $_SESSION['setting_success'] = $isEdit ? '页面已更新' : '页面已创建';
        $this->redirect('/admin/pages?lang=' . $editLang);
    }

    public function delete(string $slug = ''): void
    {
        Auth::requireCan('pages');

        if (empty($slug)) {
            $this->redirect('/admin/pages');
        }

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        if ($this->isPost()) {
            if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
                $this->redirect('/admin/pages?lang=' . $editLang);
            }

            $store = new JsonStore(DATA_PATH . "/{$editLang}/pages.json");
            $store->update(function ($pages) use ($slug) {
                return array_values(array_filter($pages, function ($p) use ($slug) {
                    return ($p['slug'] ?? '') !== $slug;
                }));
            });
            $_SESSION['setting_success'] = '页面已删除';
        }

        $this->redirect('/admin/pages?lang=' . $editLang);
    }

    private function parsePageFaqs(string $raw): array
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
        return array_slice($out, 0, 40);
    }
}
