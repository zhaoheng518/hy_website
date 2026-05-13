<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\JsonStore;
use App\Core\StaticCache;

class AdminHomeController extends BaseController
{
    private array $validSections = ['seo_home', 'banner', 'products', 'factory', 'cases', 'about', 'blog', 'contact'];

    public function index(): void
    {
        Auth::requireCan('home');
        $this->redirect('/admin/home/banner');
    }

    public function seo_home(): void { $this->editSection('seo_home'); }
    public function banner(): void    { $this->editSection('banner'); }
    public function products(): void  { $this->editSection('products'); }
    public function factory(): void   { $this->editSection('factory'); }
    public function cases(): void     { $this->editSection('cases'); }
    public function about(): void     { $this->editSection('about'); }
    public function blog(): void      { $this->editSection('blog'); }
    public function contact(): void   { $this->editSection('contact'); }

    private function editSection(string $section): void
    {
        Auth::requireCan('home');

        if (!in_array($section, $this->validSections, true)) {
            $this->redirect('/admin/home/banner');
        }

        $editLang = $this->getQuery('lang', Config::get('default_lang', 'en'));
        $supportedLangs = Config::get('supported_langs', ['en']);

        if (!in_array($editLang, $supportedLangs, true)) {
            $editLang = Config::get('default_lang', 'en');
        }

        $store = new JsonStore(DATA_PATH . "/{$editLang}/home.json");
        $data = $store->read();

        $this->view->render('home_edit', [
            'section' => $section,
            'sectionData' => $data[$section] ?? [],
            'editLang' => $editLang,
            'supportedLangs' => $supportedLangs,
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user(),
            'activeMenu' => 'home',
        ]);
    }

    public function save(): void
    {
        Auth::requireCan('home');

        if (!$this->isPost()) {
            $this->redirect('/admin/home/banner');
        }

        $csrf = $this->getPost('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $_SESSION['form_error'] = 'Invalid security token.';
            $this->redirect('/admin/home/banner');
        }

        $section = trim($this->getPost('section', ''));
        if (!in_array($section, $this->validSections, true)) {
            $this->redirect('/admin/home/banner');
        }

        $editLang = $this->getPost('edit_lang', Config::get('default_lang', 'en'));
        $store = new JsonStore(DATA_PATH . "/{$editLang}/home.json");

        $store->update(function ($data) use ($section) {
            $method = 'save' . ucfirst($section);
            if (method_exists($this, $method)) {
                $data[$section] = $this->$method($data[$section] ?? []);
            }
            return $data;
        });

        // 清除该语言首页的静态缓存，确保前台立即显示最新内容
        StaticCache::invalidate('/' . $editLang);
        StaticCache::invalidate('/' . $editLang . '/');

        $_SESSION['form_success'] = sprintf(
            '内容已保存（语言：%s）。前台请访问对应语言路径（例如 /%s/）查看首页。',
            strtoupper($editLang),
            rawurlencode($editLang)
        );
        $this->redirect('/admin/home/' . $section . '?lang=' . $editLang);
    }

    private function saveSeo_home(array $current): array
    {
        return [
            'meta_title' => trim($this->getPost('meta_title', '')),
            'meta_description' => trim($this->getPost('meta_description', '')),
            'meta_keywords' => trim($this->getPost('meta_keywords', '')),
        ];
    }

    private function saveBanner(array $current): array
    {
        return [
            'title' => trim($this->getPost('title', '')),
            'subtitle' => trim($this->getPost('subtitle', '')),
            'cta_text' => trim($this->getPost('cta_text', '')),
            'cta_link' => trim($this->getPost('cta_link', '')),
            'background_image' => trim($this->getPost('background_image', $current['background_image'] ?? '')),
            'background_video' => trim($this->getPost('background_video', $current['background_video'] ?? '')),
        ];
    }

    private function saveProducts(array $current): array
    {
        $selectedJson = $this->getPost('selected_categories', '[]');
        $selectedSlugs = json_decode($selectedJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $selectedSlugs = $current['selected_categories'] ?? [];
        }
        $selectedSlugs = array_values(array_filter(array_map('trim', $selectedSlugs ?? [])));

        return [
            'title' => trim($this->getPost('title', $current['title'] ?? '')),
            'subtitle' => trim($this->getPost('subtitle', $current['subtitle'] ?? '')),
            'selected_categories' => $selectedSlugs,
        ];
    }

    private function saveFactory(array $current): array
    {
        $imagesJson = $this->getPost('images_json', '[]');
        $images = json_decode($imagesJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $images = $current['images'] ?? [];
        }
        return [
            'title' => trim($this->getPost('title', $current['title'] ?? '')),
            'subtitle' => trim($this->getPost('subtitle', $current['subtitle'] ?? '')),
            'content' => trim($this->getPost('content', '')),
            'images' => array_values(array_filter($images, function ($img) { return !empty(trim($img)); })),
        ];
    }

    private function saveCases(array $current): array
    {
        return [
            'title' => trim($this->getPost('title', $current['title'] ?? '')),
            'subtitle' => trim($this->getPost('subtitle', $current['subtitle'] ?? '')),
        ];
    }

    private function saveAbout(array $current): array
    {
        $certsJson = $this->getPost('certifications_json', '[]');
        $certs = json_decode($certsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $certs = $current['certifications'] ?? [];
        }
        $sanitizedCerts = [];
        foreach ($certs as $cert) {
            $sanitizedCerts[] = [
                'id' => $cert['id'] ?? uniqid('cert', true),
                'name' => trim($cert['name'] ?? ''),
                'image' => trim($cert['image'] ?? ''),
            ];
        }
        return [
            'title' => trim($this->getPost('title', $current['title'] ?? '')),
            'content' => trim($this->getPost('content', '')),
            'image' => trim($this->getPost('about_image', $current['image'] ?? '')),
            'certifications' => $sanitizedCerts,
        ];
    }

    private function saveBlog(array $current): array
    {
        return [
            'title' => trim($this->getPost('title', $current['title'] ?? '')),
            'subtitle' => trim($this->getPost('subtitle', $current['subtitle'] ?? '')),
        ];
    }

    private function saveContact(array $current): array
    {
        return [
            'title' => trim($this->getPost('title', $current['title'] ?? '')),
            'subtitle' => trim($this->getPost('subtitle', $current['subtitle'] ?? '')),
            'email' => trim($this->getPost('email', '')),
            'phone' => trim($this->getPost('phone', '')),
            'whatsapp' => trim($this->getPost('whatsapp', '')),
            'address' => trim($this->getPost('address', '')),
        ];
    }
}
