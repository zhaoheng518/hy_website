<?php

namespace App\Controllers;

use App\Core\JsonStore;
use App\Core\MetaHelper;
use App\Core\View;

class CompareController extends BaseController
{
    public function index(): void
    {
        $raw = trim($this->getQuery('slugs', ''));
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $parts = array_values(array_unique($parts));
        $parts = array_slice($parts, 0, 4);

        $products = [];
        if (!empty($parts)) {
            $all = JsonStore::langData($this->lang, 'products')->read();
            $bySlug = [];
            foreach ($all as $p) {
                if (!empty($p['slug'])) {
                    $bySlug[$p['slug']] = $p;
                }
            }
            foreach ($parts as $slug) {
                if (isset($bySlug[$slug])) {
                    $products[] = $bySlug[$slug];
                }
            }
        }

        $slugsParam = implode(',', array_column($products, 'slug'));
        $canonicalRel = '/' . $this->lang . '/compare' . ($slugsParam !== '' ? ('?slugs=' . rawurlencode($slugsParam)) : '');

        $seoHead = $this->seo->renderMeta('compare', '', [
            'title' => $this->t('compare_title'),
            'content_plain' => MetaHelper::stripHtml($this->t('compare_desc')),
            'canonical_rel' => $canonicalRel,
            'robots' => 'noindex, follow',
        ]);

        $this->view->render('compare', [
            'seoHead' => $seoHead,
            'compareProducts' => $products,
            'h1' => $this->t('compare_title'),
        ]);
    }

    public function show(string $slug = ''): void
    {
        $this->redirect('/' . $this->lang . '/compare');
    }

    private function t(string $key): string
    {
        $map = [
            'en' => [
                'compare_title' => 'Compare products',
                'compare_desc' => 'Side-by-side comparison of selected products.',
            ],
            'cn' => [
                'compare_title' => '产品对比',
                'compare_desc' => '并排对比所选产品。',
            ],
            'es' => [
                'compare_title' => 'Comparar productos',
                'compare_desc' => 'Comparación lado a lado de los productos seleccionados.',
            ],
        ];
        return $map[$this->lang][$key] ?? $map['en'][$key] ?? $key;
    }
}
