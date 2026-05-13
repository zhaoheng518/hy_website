<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\SEO;

class SitemapController extends BaseController
{
    public function index(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        $siteUrl = rtrim((string) Config::get('site_url', ''), '/');
        $langs = Config::get('supported_langs', ['en']);
        if ($siteUrl === '' || !is_array($langs) || empty($langs)) {
            http_response_code(500);
            echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
            exit;
        }

        echo SEO::generateSitemap();
        exit;
    }

    public function images(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo SEO::generateImageSitemap();
        exit;
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');

        $siteUrl = rtrim((string) Config::get('site_url', ''), '/');
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /login/',
            'Disallow: /search/',
            '',
            'Sitemap: ' . $siteUrl . '/sitemap.xml',
            'Sitemap: ' . $siteUrl . '/image-sitemap.xml',
        ];
        echo implode("\n", $lines) . "\n";
        exit;
    }
}
