<?php

/**
 * Declarative URL → controller maps (front, admin first segment, special files).
 * Router loads this file once per request.
 *
 * @return array{
 *   front: array<string, string>,
 *   special: array<string, string>,
 *   admin: array<string, array{0: string, 1: string}>
 * }
 */
return [
    'front' => [
        'product'    => 'Product',
        'products'   => 'Product',
        'factory'    => 'Factory',
        'about'      => 'About',
        'cases'      => 'Case',
        'blog'       => 'Blog',
        'contact'    => 'Contact',
        'page'       => 'Page',
        'compare'    => 'Compare',
        'search'     => 'Search',
        'newsletter' => 'Newsletter',
        'download'   => 'Download',
    ],
    'special' => [
        'sitemap.xml' => 'Sitemap',
        'robots.txt'  => 'Sitemap',
    ],
    'admin' => [
        'login'          => ['AdminAuth', 'login'],
        'logout'         => ['AdminAuth', 'logout'],
        'home'           => ['AdminHome', 'index'],
        'products'       => ['AdminProduct', 'index'],
        'categories'     => ['AdminCategory', 'index'],
        'pages'          => ['AdminPage', 'index'],
        'page'           => ['AdminPage', 'cmsIndex'],
        'inquiries'      => ['AdminInquiry', 'index'],
        'inquiry_export' => ['AdminInquiry', 'export'],
        'newsletter'     => ['AdminNewsletter', 'index'],
        'settings'       => ['AdminSetting', 'index'],
        'seo'            => ['AdminSEO', 'index'],
        'media'          => ['AdminMedia', 'index'],
        'languages'      => ['AdminLanguage', 'index'],
        'sections'       => ['AdminSection', 'index'],
        'blog'           => ['AdminBlog', 'index'],
        'case'           => ['AdminCase', 'index'],
        'files'          => ['AdminFile', 'index'],
        'menu'           => ['AdminMenu', 'index'],
        'menu-settings'  => ['AdminMenuSettings', 'index'],
        'users'          => ['AdminUser', 'index'],
        'backup'         => ['AdminBackup', 'index'],
        'redirects'      => ['AdminRedirect', 'index'],
        '404monitor'     => ['Admin404', 'index'],
        'downloads'      => ['AdminDownload', 'index'],
    ],
];
