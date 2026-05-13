<?php

namespace App\Controllers;

use App\Core\JsonStore;
use App\Core\MetaHelper;
use App\Core\View;

class FactoryController extends BaseController
{
    public function index(): void
    {
        $page = $this->findPage('factory');

        $plain = MetaHelper::stripHtml($page['seo_description'] ?? $page['content'] ?? '');
        $seoHead = $this->seo->renderMeta('factory', '', [
            'title' => $page['seo_title'] ?? $page['title'] ?? '',
            'description' => $page['seo_description'] ?? '',
            'content_plain' => $plain,
            'content_html' => $page['content'] ?? '',
        ]);
        $breadcrumbs = $this->seo->renderBreadcrumbs([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('factory')],
        ]);
        $breadcrumbsSchema = $this->seo->renderBreadcrumbsSchema([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('factory'), 'url' => View::langUrl($this->lang, 'factory')],
        ]);

        $this->view->render('factory', [
            'page' => $page,
            'seoHead' => $seoHead,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbsSchema' => $breadcrumbsSchema,
            'h1' => $page['title'] ?? $this->t('factory'),
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
            'en' => ['home' => 'Home', 'factory' => 'Factory'],
            'cn' => ['home' => '首页', 'factory' => '工厂实力'],
            'es' => ['home' => 'Inicio', 'factory' => 'Fábrica'],
        ];
        return $translations[$this->lang][$key] ?? $key;
    }
}
