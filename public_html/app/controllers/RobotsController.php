<?php

namespace App\Controllers;

use App\Core\Config;

class RobotsController extends BaseController
{
    public function index(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');

        $siteUrl = rtrim(Config::get('site_url', ''), '/');

        if (Config::get('robots_block_all', false)) {
            echo "User-agent: *\n";
            echo "Disallow: /\n";
            exit;
        }

        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /admin/\n";
        echo "Disallow: /login/\n";
        echo "Disallow: /search/\n";
        echo "Disallow: /app/\n";
        echo "\n";
        echo "Sitemap: {$siteUrl}/sitemap.xml\n";
        echo "Sitemap: {$siteUrl}/image-sitemap.xml\n";

        exit;
    }
}
