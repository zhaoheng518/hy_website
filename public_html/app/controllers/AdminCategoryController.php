<?php
namespace App\Controllers;
use App\Core\Auth;
use App\Core\JsonStore;
use App\Core\View;

class AdminCategoryController extends BaseController
{
    private array $supportedLangs;

    public function __construct(string $lang, bool $isAdmin = false)
    {
        parent::__construct($lang, $isAdmin);
        $this->supportedLangs = \App\Core\Config::get('supported_langs', ['en', 'cn', 'es']);
    }

    public function index(): void
    {
        Auth::requireCan('categories');
        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) $editLang = 'en';
        $categories = JsonStore::langData($editLang, 'categories')->read();
        $this->view->render('categories', [
            'categories' => $categories, 'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs, 'adminUser' => Auth::user(),
        ]);
    }

    public function create(): void
    {
        Auth::requireCan('categories');
        if ($this->isPost()) { $this->handleSave(false); return; }
        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) $editLang = 'en';
        $this->view->render('category_form', [
            'category' => ['name'=>'','slug'=>'','description'=>'','image'=>'','banner_image'=>'','sort_order'=>0,'seo_title'=>'','seo_description'=>'','featured_product_slugs'=>[],'faqs'=>[]],
            'editLang' => $editLang, 'supportedLangs' => $this->supportedLangs,
            'csrfToken' => Auth::generateCsrfToken(), 'isEdit' => false, 'adminUser' => Auth::user(),
        ]);
    }

    public function edit(string $slug = ''): void
    {
        Auth::requireCan('categories');
        if (empty($slug)) $this->redirect('/admin/categories');
        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) $editLang = 'en';
        if ($this->isPost()) { $this->handleSave(true, $slug, $editLang); return; }
        $categories = JsonStore::langData($editLang, 'categories')->read();
        $category = null;
        foreach ($categories as $c) { if (($c['slug'] ?? '') === $slug) { $category = $c; break; } }
        if (!$category) { $_SESSION['setting_error'] = '分类未找到。'; $this->redirect('/admin/categories?lang='.$editLang); }
        $this->view->render('category_form', [
            'category' => $category, 'editLang' => $editLang,
            'supportedLangs' => $this->supportedLangs, 'csrfToken' => Auth::generateCsrfToken(),
            'isEdit' => true, 'adminUser' => Auth::user(),
        ]);
    }

    private function handleSave(bool $isEdit, string $origSlug = '', string $editLang = 'en'): void
    {
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) { $_SESSION['setting_error']='Token无效'; $this->redirect('/admin/categories'); }
        $editLang = $this->getPost('edit_lang', $editLang);
        if (!in_array($editLang, $this->supportedLangs, true)) $editLang = 'en';
        $name = trim($this->getPost('name', ''));
        if ($name === '') { $_SESSION['setting_error']='名称不能为空'; $this->redirect('/admin/categories/create?lang='.$editLang); }
        $slug = trim($this->getPost('slug', ''));
        $slug = $slug ? View::slugify($slug) : View::slugify($name);
        // Cross-type global uniqueness — auto-append suffix to prevent SEO collisions
        $slug = \App\Core\SlugRegistry::makeUniqueSlug($slug, 'category', $isEdit ? $origSlug : '');
        $store = JsonStore::langData($editLang, 'categories');
        $cats = $store->read();
        $maxId = 0;
        foreach ($cats as $c) {
            $maxId = max($maxId, (int) ($c['id'] ?? 0));
        }
        $nextCatId = $maxId + 1;
        $fp = trim($this->getPost('featured_product_slugs', ''));
        $feat = array_values(array_filter(array_map(function ($s) {
            return View::slugify(trim($s));
        }, preg_split('/[\s,，]+/u', $fp) ?: [])));

        $faqsRaw = $this->getPost('faqs_json', '[]');
        $faqs = json_decode($faqsRaw, true);
        if (!is_array($faqs)) {
            $faqs = [];
        }
        $faqsOut = [];
        foreach ($faqs as $row) {
            $q = trim((string) ($row['question'] ?? ''));
            $a = trim((string) ($row['answer'] ?? ''));
            if ($q !== '' && $a !== '') {
                $faqsOut[] = ['question' => $q, 'answer' => $a];
            }
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => trim($this->getPost('description', '')),
            'image' => trim($this->getPost('image', '')),
            'banner_image' => trim($this->getPost('banner_image', '')),
            'sort_order' => (int) $this->getPost('sort_order', 0),
            'seo_title' => trim($this->getPost('seo_title', '')),
            'seo_description' => trim($this->getPost('seo_description', '')),
            'featured_product_slugs' => array_slice($feat, 0, 20),
            'faqs' => array_slice($faqsOut, 0, 30),
        ];
        if ($isEdit) {
            $store->update(function ($cats) use ($origSlug, $data) {
                foreach ($cats as &$c) {
                    if (($c['slug'] ?? '') === $origSlug) {
                        $existingId = (int) ($c['id'] ?? 0);
                        $data['id'] = $existingId > 0 ? $existingId : $nextCatId;
                        $c = array_merge($c, $data);
                        break;
                    }
                }
                unset($c);
                return $cats;
            });
        } else {
            $data['id'] = $nextCatId;
            $store->update(function ($cats) use ($data) {
                $cats[] = $data;
                return $cats;
            });
        }
        // Register 301 redirect when slug changes on edit
        if ($isEdit && $slug !== $origSlug) {
            \App\Core\SlugRegistry::registerRedirect($origSlug, $slug, 'category', 301);
        }
        $_SESSION['setting_success'] = $isEdit ? '分类已更新' : '分类已创建';
        $this->redirect('/admin/categories?lang='.$editLang);
    }

    public function delete(string $slug = ''): void
    {
        Auth::requireCan('categories');
        if (empty($slug)) $this->redirect('/admin/categories');
        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) $editLang = 'en';
        if ($this->isPost()) {
            if (!Auth::consumeCsrfToken($this->getPost('_csrf',''))) $this->redirect('/admin/categories?lang='.$editLang);
            $store = JsonStore::langData($editLang, 'categories');
            $store->update(function($cats) use ($slug) {
                return array_values(array_filter($cats, fn($c) => ($c['slug']??'') !== $slug));
            });
            $_SESSION['setting_success'] = '分类已删除';
        }
        $this->redirect('/admin/categories?lang='.$editLang);
    }
}
