<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\JsonStore;
use App\Core\MetaHelper;
use App\Core\View;

class PageController extends BaseController
{
    public function show(string $slug = ''): void
    {
        if (empty($slug)) {
            $this->redirect('/' . $this->lang);
        }

        $store = new JsonStore(DATA_PATH . "/{$this->lang}/pages.json");
        $pages = $store->read();
        $page = null;

        foreach ($pages as $p) {
            if (isset($p['slug']) && $p['slug'] === $slug) {
                $page = $p;
                break;
            }
        }

        if ($page === null) {
            $this->renderFront404();
        }

        $seoTitle = $page['seo_title'] ?? '';
        $seoDesc = $page['seo_description'] ?? '';
        if ($seoTitle === '') {
            $seoTitle = ($page['title'] ?? '') . ' - ' . Config::get('site_name', '');
        }

        $plain = MetaHelper::stripHtml($seoDesc !== '' ? $seoDesc : ($page['content'] ?? ''));

        $seoHead = $this->seo->renderMeta('page', $slug, [
            'title' => $page['title'] ?? '',
            'seo_title' => $seoTitle,
            'description' => $seoDesc,
            'content_plain' => $plain,
            'content_html' => $page['content'] ?? '',
        ]);

        $breadcrumbs = $this->seo->renderBreadcrumbs([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $page['title'] ?? ''],
        ]);
        $breadcrumbsSchema = $this->seo->renderBreadcrumbsSchema([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $page['title'] ?? '', 'url' => View::langUrl($this->lang, 'page/' . $slug)],
        ]);

        $this->view->render('page', [
            'page' => $page,
            'seoHead' => $seoHead,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbsSchema' => $breadcrumbsSchema,
            'h1' => $page['title'] ?? '',
        ]);
    }

    private function t(string $key): string
    {
        $translations = [
            'en' => ['home' => 'Home'],
            'cn' => ['home' => '首页'],
            'es' => ['home' => 'Inicio'],
        ];
        return $translations[$this->lang][$key] ?? $key;
    }
}
