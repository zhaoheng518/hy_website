<?php

namespace App\Controllers;

use App\Core\JsonStore;
use App\Core\MetaHelper;
use App\Core\View;

class CaseController extends BaseController
{
    public function index(): void
    {
        $page = $this->findPage('cases');
        $cases = $this->getLangData('cases');
        if (is_array($cases)) {
            $cases = array_values(array_filter($cases, function ($c) {
                return ($c['status'] ?? 'published') === 'published';
            }));
        }

        $seoHead = $this->seo->renderMeta('cases', '', [
            'title' => $page['seo_title'] ?? $page['title'] ?? '',
            'description' => $page['seo_description'] ?? '',
        ]);
        $breadcrumbs = $this->seo->renderBreadcrumbs([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('cases'), 'url' => View::langUrl($this->lang, 'cases')],
        ]);
        $breadcrumbsSchema = $this->seo->renderBreadcrumbsSchema([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('cases'), 'url' => View::langUrl($this->lang, 'cases')],
        ]);

        $this->view->render('cases', [
            'page' => $page,
            'cases' => $cases,
            'seoHead' => $seoHead,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbsSchema' => $breadcrumbsSchema,
            'h1' => $page['title'] ?? $this->t('cases'),
        ]);
    }

    public function show(string $slug = ''): void
    {
        if (empty($slug)) {
            $this->redirect('/' . $this->lang . '/cases');
        }

        $cases = $this->getLangData('cases');
        $case = null;

        foreach ($cases as $c) {
            if (isset($c['slug']) && $c['slug'] === $slug) {
                $case = $c;
                break;
            }
        }

        if ($case === null) {
            $this->renderFront404();
        }

        if (($case['status'] ?? 'published') !== 'published') {
            $this->renderFront404();
        }

        $plain = MetaHelper::stripHtml($case['seo_desc'] ?? $case['desc'] ?? '');
        $seoHead = $this->seo->renderMeta('cases', $slug, [
            'title' => $case['seo_title'] ?? $case['title'] ?? '',
            'description' => $case['seo_desc'] ?? $case['desc'] ?? '',
            'content_plain' => $plain,
        ]);

        $breadcrumbs = $this->seo->renderBreadcrumbs([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('cases'), 'url' => View::langUrl($this->lang, 'cases')],
            ['name' => $case['title'] ?? ''],
        ]);
        $breadcrumbsSchema = $this->seo->renderBreadcrumbsSchema([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('cases'), 'url' => View::langUrl($this->lang, 'cases')],
            ['name' => $case['title'] ?? '', 'url' => View::langUrl($this->lang, 'cases/' . $slug)],
        ]);

        $allProducts = JsonStore::langData($this->lang, 'products')->read();
        if (!is_array($allProducts)) {
            $allProducts = [];
        }
        $relatedProducts = [];
        $want = isset($case['related_products']) && is_array($case['related_products']) ? $case['related_products'] : [];
        foreach ($allProducts as $prod) {
            if (in_array($prod['slug'] ?? '', $want, true)) {
                $relatedProducts[] = $prod;
            }
        }
        $relatedProducts = array_slice($relatedProducts, 0, 8);

        $this->view->render('case_detail', [
            'case' => $case,
            'seoHead' => $seoHead,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbsSchema' => $breadcrumbsSchema,
            'h1' => $case['title'] ?? '',
            'relatedProducts' => $relatedProducts,
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
            'en' => ['home' => 'Home', 'cases' => 'Case Studies'],
            'cn' => ['home' => '首页', 'cases' => '客户案例'],
            'es' => ['home' => 'Inicio', 'cases' => 'Casos de Éxito'],
        ];
        return $translations[$this->lang][$key] ?? $key;
    }
}
