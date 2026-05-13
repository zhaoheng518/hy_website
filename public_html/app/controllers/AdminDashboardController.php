<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\InquiryRepository;
use App\Core\JsonStore;

class AdminDashboardController extends BaseController
{
    public function index(): void
    {
        Auth::requireCan('dashboard');

        $defLang = Config::get('default_lang', 'en');

        if (InquiryRepository::isAvailable()) {
            $inquiries = [];
            $recentInquiries = InquiryRepository::listRecentForDashboard(5);
        } else {
            $inquiryStore = JsonStore::globalData('inquiries');
            $inquiries = $inquiryStore->read();
            if (!is_array($inquiries)) {
                $inquiries = [];
            }
            $recentInquiries = array_slice(array_reverse($inquiries), 0, 5);
        }

        $productStore = JsonStore::langData($defLang, 'products');
        $products = $productStore->read();
        if (!is_array($products)) {
            $products = [];
        }

        $caseStore = JsonStore::langData('en', 'cases');
        $cases = $caseStore->read();
        if (!is_array($cases)) {
            $cases = [];
        }

        $blogStore = JsonStore::langData('en', 'blog');
        $blogPosts = $blogStore->read();
        if (!is_array($blogPosts)) {
            $blogPosts = [];
        }

        $sectionStore = JsonStore::globalData('sections');
        $sections = $sectionStore->read();

        $totalProducts = 0;
        foreach (Config::get('supported_langs', ['en', 'cn', 'es']) as $lng) {
            $p = JsonStore::langData($lng, 'products')->read();
            if (is_array($p)) {
                $totalProducts += count($p);
            }
        }

        $publishedPosts = array_values(array_filter($blogPosts, function ($p) {
            return ($p['status'] ?? 'published') === 'published';
        }));
        usort($publishedPosts, function ($a, $b) {
            return strcmp($b['published_at'] ?? $b['date'] ?? '', $a['published_at'] ?? $a['date'] ?? '');
        });
        $recentBlogs = array_slice($publishedPosts, 0, 5);

        $hotProducts = [];
        $haveHot = [];
        foreach ($products as $p) {
            if (!empty($p['is_hot'])) {
                $hotProducts[] = $p;
                $haveHot[$p['slug'] ?? ''] = true;
                if (count($hotProducts) >= 6) {
                    break;
                }
            }
        }
        if (count($hotProducts) < 6) {
            foreach ($products as $p) {
                if (count($hotProducts) >= 6) {
                    break;
                }
                $s = $p['slug'] ?? '';
                if ($s === '' || !empty($haveHot[$s])) {
                    continue;
                }
                $hotProducts[] = $p;
                $haveHot[$s] = true;
            }
        }
        $hotProducts = array_slice($hotProducts, 0, 6);

        if (InquiryRepository::isAvailable()) {
            $totalInq = InquiryRepository::countTotal();
            $unreadInq = InquiryRepository::countUnread();
        } else {
            $totalInq = count($inquiries);
            $unreadInq = count(array_filter($inquiries, function ($i) {
                return empty($i['read']);
            }));
        }

        $stats = [
            'total_inquiries' => $totalInq,
            'unread_inquiries' => $unreadInq,
            'total_products' => $totalProducts,
            'total_cases' => is_array($cases) ? count($cases) : 0,
            'total_posts' => count($blogPosts),
            'active_sections' => count(array_filter($sections['sections'] ?? [], function ($s) {
                return !empty($s['enabled']);
            })),
        ];

        $this->view->render('dashboard/index', [
            'stats' => $stats,
            'recentInquiries' => $recentInquiries,
            'recentBlogs' => $recentBlogs,
            'hotProducts' => $hotProducts,
            'defaultLang' => $defLang,
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'dashboard',
            'pageTitle' => '仪表盘',
        ]);
    }
}
