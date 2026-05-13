<?php

namespace App\Controllers;

use App\Core\JsonStore;
use App\Core\MetaHelper;
use App\Core\View;

class AboutController extends BaseController
{
    public function index(): void
    {
        $page = $this->findPage('about');

        $plain = MetaHelper::stripHtml($page['seo_description'] ?? $page['content'] ?? '');
        $seoHead = $this->seo->renderMeta('about', '', [
            'title' => $page['seo_title'] ?? $page['title'] ?? '',
            'description' => $page['seo_description'] ?? '',
            'content_plain' => $plain,
            'content_html' => $page['content'] ?? '',
        ]);
        $breadcrumbs = $this->seo->renderBreadcrumbs([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('about')],
        ]);
        $breadcrumbsSchema = $this->seo->renderBreadcrumbsSchema([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('about'), 'url' => View::langUrl($this->lang, 'about')],
        ]);

        $this->view->render('about', [
            'page' => $page,
            'seoHead' => $seoHead,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbsSchema' => $breadcrumbsSchema,
            'h1' => $page['title'] ?? $this->t('about'),
        ]);
    }

    private function findPage(string $slug): array
    {
        $store = new JsonStore(DATA_PATH . "/{$this->lang}/pages.json");
        $pages = $store->read();
        foreach ($pages as $p) {
            if (($p['slug'] ?? '') === $slug) {
                return $p;
            }
        }
        return [];
    }

    private function t(string $key): string
    {
        $translations = [
            'en' => ['home' => 'Home', 'about' => 'About Us'],
            'cn' => ['home' => '首页', 'about' => '关于我们'],
            'es' => ['home' => 'Inicio', 'about' => 'Sobre Nosotros'],
        ];
        return $translations[$this->lang][$key] ?? $key;
    }
}
