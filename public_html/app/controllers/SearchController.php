<?php

namespace App\Controllers;

use App\Core\MetaHelper;
use App\Core\View;

class SearchController extends BaseController
{
    public function index(): void
    {
        $q = trim($this->getQuery('q', ''));
        if ($q === '') {
            $q = trim($this->getQuery('search', ''));
        }
        $canonicalRel = '/' . $this->lang . '/search' . ($q !== '' ? ('?q=' . rawurlencode($q)) : '');

        $seoHead = $this->seo->renderMeta('search', '', [
            'title' => $this->t('search_title'),
            'content_plain' => MetaHelper::stripHtml($this->t('search_desc')),
            'canonical_rel' => $canonicalRel,
            'robots' => 'noindex, follow',
        ]);

        $this->view->render('search', [
            'seoHead' => $seoHead,
            'query' => $q,
            'h1' => $this->t('search_title'),
        ]);
    }

    public function show(string $slug = ''): void
    {
        $this->redirect('/' . $this->lang . '/search');
    }

    private function t(string $key): string
    {
        $map = [
            'en' => ['search_title' => 'Search', 'search_desc' => 'Site search.'],
            'cn' => ['search_title' => '搜索', 'search_desc' => '站内搜索。'],
            'es' => ['search_title' => 'Buscar', 'search_desc' => 'Búsqueda en el sitio.'],
        ];
        return $map[$this->lang][$key] ?? $map['en'][$key] ?? $key;
    }
}
