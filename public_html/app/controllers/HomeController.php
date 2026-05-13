<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\JsonStore;
use App\Core\MetaHelper;
use App\Core\View;

class HomeController extends BaseController
{
    public function index(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: private, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        $data = $this->getLangData('home');

        $blogStore = JsonStore::langData($this->lang, 'blog');
        $blogData = $blogStore->read();
        $posts = $blogData['posts'] ?? $blogData;
        if (!is_array($posts)) {
            $posts = [];
        }
        usort($posts, function ($a, $b) {
            return strcmp($b['created_at'] ?? $b['date'] ?? '', $a['created_at'] ?? $a['date'] ?? '');
        });
        $posts = array_slice($posts, 0, 3);

        $categoriesStore = JsonStore::langData($this->lang, 'categories');
        $allCategories = $categoriesStore->read();

        $selectedSlugs = $data['products']['selected_categories'] ?? [];
        $productItems = [];
        if (!empty($selectedSlugs)) {
            $catBySlug = [];
            foreach ($allCategories as $cat) {
                if (isset($cat['slug'])) {
                    $catBySlug[$cat['slug']] = $cat;
                }
            }
            foreach ($selectedSlugs as $slug) {
                if (isset($catBySlug[$slug])) {
                    $cat = $catBySlug[$slug];
                    $productItems[] = [
                        'name' => $cat['name'] ?? '',
                        'slug' => $cat['slug'] ?? '',
                        'desc' => $cat['description'] ?? '',
                        'image' => $cat['image'] ?? '',
                        'link' => View::langUrl($this->lang) . '/products/' . $cat['slug'],
                        'menu_thumbnail' => $cat['image'] ?? '',
                        'menu_subtitle' => $cat['description'] ?? '',
                        'show_in_menu' => true,
                    ];
                }
            }
        } else {
            $legacyItems = $data['products']['items'] ?? [];
            foreach ($legacyItems as $item) {
                $itemLink = View::langUrl($this->lang) . '/products';
                if (!empty($item['slug'])) {
                    $itemLink = View::langUrl($this->lang) . '/products/' . $item['slug'];
                } elseif (!empty($item['link'])) {
                    $itemLink = $item['link'];
                }
                $productItems[] = array_merge($item, ['link' => $itemLink]);
            }
        }

        $data['products']['items'] = $productItems;

        $bannerTitle = $data['banner']['title'] ?? '';
        $homePlain = MetaHelper::stripHtml(($data['banner']['subtitle'] ?? '') . ' ' . ($data['products']['subtitle'] ?? ''));
        $hpSeo = is_array($data['seo_home'] ?? null) ? $data['seo_home'] : [];
        $metaTitle = trim((string) ($hpSeo['meta_title'] ?? ''));
        $metaDesc = trim((string) ($hpSeo['meta_description'] ?? ''));
        $metaKw = trim((string) ($hpSeo['meta_keywords'] ?? ''));
        $seoOverrides = ['content_plain' => $homePlain];
        if ($metaTitle !== '') {
            $seoOverrides['seo_title'] = $metaTitle;
        } else {
            $seoOverrides['title'] = $bannerTitle;
        }
        if ($metaDesc !== '') {
            $seoOverrides['seo_desc'] = $metaDesc;
        }
        if ($metaKw !== '') {
            $seoOverrides['seo_keywords'] = $metaKw;
        }
        $seoHead = $this->seo->renderMeta('home', '', $seoOverrides);
        $orgSchema = $this->seo->renderOrganizationSchema();
        $webSiteSchema = $this->seo->renderWebSiteSchema();

        $this->view->render('home', [
            'data' => $data,
            'posts' => $posts,
            'megaMenuItems' => $productItems,
            'csrfToken' => Auth::generateCsrfToken(),
            'seoHead' => $seoHead,
            'orgSchema' => $orgSchema,
            'webSiteSchema' => $webSiteSchema,
            'h1' => $data['banner']['title'] ?? Config::get('site_name', 'Home'),
        ]);
    }
}
