<?php

namespace App\Controllers;

use App\Core\JsonStore;
use App\Core\MetaHelper;
use App\Core\StaticCache;
use App\Core\View;

class BlogController extends BaseController
{
    public function index(): void
    {
        $posts = $this->getLangData('blog');
        if (is_array($posts)) {
            $posts = array_values(array_filter($posts, function ($p) {
                return ($p['status'] ?? 'published') === 'published';
            }));
        }

        $idxMeta = MetaHelper::buildBlogIndexMeta($this->t('blog'), MetaHelper::stripHtml($this->t('blog_desc')));
        $seoHead = $this->seo->renderMeta('blog', '', $idxMeta['overrides']) . $idxMeta['head_suffix'];
        $breadcrumbs = $this->seo->renderBreadcrumbs([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('blog'), 'url' => View::langUrl($this->lang, 'blog')],
        ]);
        $breadcrumbsSchema = $this->seo->renderBreadcrumbsSchema([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('blog'), 'url' => View::langUrl($this->lang, 'blog')],
        ]);

        $this->renderCached('/' . $this->lang . '/blog', 'blog', [
            'posts' => $posts,
            'seoHead' => $seoHead,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbsSchema' => $breadcrumbsSchema,
            'h1' => $this->t('blog'),
        ]);
    }

    public function show(string $slug = ''): void
    {
        if (empty($slug)) {
            $this->redirect('/' . $this->lang . '/blog');
        }

        $posts = $this->getLangData('blog');
        $post = null;

        foreach ($posts as $p) {
            if (isset($p['slug']) && $p['slug'] === $slug) {
                $post = $p;
                break;
            }
        }

        if ($post === null) {
            $this->renderFront404();
        }

        if (($post['status'] ?? 'published') !== 'published') {
            $this->renderFront404();
        }

        $canonicalPath = '/' . $this->lang . '/blog/' . rawurlencode($slug);
        $blogCatSlug = MetaHelper::firstNonEmpty(
            MetaHelper::strVal($post, 'category_slug'),
            MetaHelper::strVal($post, 'category_id'),
            MetaHelper::strVal($post, 'blog_category')
        );
        $blogCategoryLayer = MetaHelper::resolveBlogCategoryLayer(
            $this->lang,
            $blogCatSlug !== '' ? $blogCatSlug : null
        );
        $postMeta = MetaHelper::buildBlogPostMeta($post, $blogCategoryLayer);
        $seoHead = $this->seo->renderMeta('blog', $slug, $postMeta['overrides']) . $postMeta['head_suffix'];

        $breadcrumbs = $this->seo->renderBreadcrumbs([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('blog'), 'url' => View::langUrl($this->lang, 'blog')],
            ['name' => $post['title'] ?? ''],
        ]);
        $breadcrumbsSchema = $this->seo->renderBreadcrumbsSchema([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('blog'), 'url' => View::langUrl($this->lang, 'blog')],
            ['name' => $post['title'] ?? '', 'url' => View::langUrl($this->lang, 'blog/' . $slug)],
        ]);

        $articleSchema = $this->seo->renderArticleSchema($post, $canonicalPath);

        $relatedPosts = [];
        foreach ($posts as $p) {
            if (($p['slug'] ?? '') === $slug) {
                continue;
            }
            if (($p['status'] ?? 'published') !== 'published') {
                continue;
            }
            $relatedPosts[] = $p;
            if (count($relatedPosts) >= 4) {
                break;
            }
        }

        $allProducts = JsonStore::langData($this->lang, 'products')->read();
        if (!is_array($allProducts)) {
            $allProducts = [];
        }
        $relatedProducts = [];
        $want = isset($post['related_products']) && is_array($post['related_products']) ? $post['related_products'] : [];
        foreach ($allProducts as $prod) {
            if (in_array($prod['slug'] ?? '', $want, true)) {
                $relatedProducts[] = $prod;
            }
        }
        if (count($relatedProducts) < 4) {
            $have = array_column($relatedProducts, 'slug');
            foreach ($allProducts as $prod) {
                if (count($relatedProducts) >= 4) {
                    break;
                }
                $ps = $prod['slug'] ?? '';
                if ($ps === '' || in_array($ps, $have, true)) {
                    continue;
                }
                $relatedProducts[] = $prod;
                $have[] = $ps;
            }
        }
        $relatedProducts = array_slice($relatedProducts, 0, 8);

        $this->renderCached('/' . $this->lang . '/blog/' . $slug, 'blog_detail', [
            'post' => $post,
            'seoHead' => $seoHead,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbsSchema' => $breadcrumbsSchema,
            'articleSchema' => $articleSchema,
            'relatedPosts' => $relatedPosts,
            'relatedProducts' => $relatedProducts,
            'h1' => $post['title'] ?? '',
        ]);
    }

    /**
     * Module 12: Render a front-end template and lazily populate the static cache.
     *
     * Behaviour:
     *  - Static cache disabled or request has query params → plain render, no caching.
     *  - Static cache enabled, clean GET → render with ob capture, echo, write cache file.
     *
     * @param string               $cacheUri  Canonical URI used as the cache key (e.g. /en/blog/slug)
     * @param string               $template  View template name passed to View::render()
     * @param array<string, mixed> $vars      Template variables
     */
    private function renderCached(string $cacheUri, string $template, array $vars): void
    {
        $cacheable = StaticCache::isEnabled()
            && empty($_GET)
            && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';

        if (!$cacheable) {
            $this->view->render($template, $vars);
            return;
        }

        ob_start();
        $this->view->render($template, $vars);
        $html = ob_get_clean();
        echo $html;

        if (trim($html) !== '') {
            StaticCache::write($cacheUri, $html);
        }
    }

    private function t(string $key): string
    {
        $translations = [
            'en' => ['home' => 'Home', 'blog' => 'Blog', 'blog_desc' => 'Industry insights, technical articles, and company news.'],
            'cn' => ['home' => '首页', 'blog' => '博客', 'blog_desc' => '行业洞察、技术文章与公司动态。'],
            'es' => ['home' => 'Inicio', 'blog' => 'Blog', 'blog_desc' => 'Artículos técnicos y noticias del sector.'],
        ];
        return $translations[$this->lang][$key] ?? $key;
    }
}
