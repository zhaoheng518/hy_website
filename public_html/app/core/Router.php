<?php

namespace App\Core;

class Router
{
    private array $supportedLangs;
    private string $defaultLang;
    private string $lang = '';
    private string $controller = '';
    private string $action = '';
    private array $params = [];
    private bool $isAdmin = false;
    /** @var string [Module 13] Configurable admin path segment (default: "admin") */
    private string $adminPath = 'admin';

    private static array $frontRouteMap = [
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
        'download'   => 'Download',   // Module 10: datasheet download tracking
    ];

    private static array $specialRoutes = [
        'sitemap.xml' => 'Sitemap',
        'robots.txt'  => 'Sitemap',
    ];

    private static array $adminRouteMap = [
        'login'      => ['AdminAuth', 'login'],
        'logout'     => ['AdminAuth', 'logout'],
        'home'       => ['AdminHome', 'index'],
        'products'   => ['AdminProduct', 'index'],
        'categories' => ['AdminCategory', 'index'],
        'pages'      => ['AdminPage', 'index'],
        'page'       => ['AdminPage', 'cmsIndex'],
        'inquiries'  => ['AdminInquiry', 'index'],
        'inquiry_export' => ['AdminInquiry', 'export'],
        'newsletter' => ['AdminNewsletter', 'index'],
        'settings'   => ['AdminSetting', 'index'],
        'seo'        => ['AdminSEO', 'index'],
        'media'      => ['AdminMedia', 'index'],
        'languages'  => ['AdminLanguage', 'index'],
        'sections'   => ['AdminSection', 'index'],
        'blog'       => ['AdminBlog', 'index'],
        'case'       => ['AdminCase', 'index'],
        'files'      => ['AdminFile', 'index'],
        'menu'       => ['AdminMenu', 'index'],
        'users'      => ['AdminUser', 'index'],
        'backup'     => ['AdminBackup', 'index'],
        'redirects'  => ['AdminRedirect', 'index'],
        '404monitor' => ['Admin404', 'index'],
        'downloads'  => ['AdminDownload', 'index'],  // Module 10: download stats
    ];

    public function __construct()
    {
        $this->supportedLangs = Config::get('supported_langs', ['en', 'cn', 'es']);
        $this->defaultLang    = Config::get('default_lang', 'en');
        // [Module 13] Read custom admin path; fall back to "admin" if empty/invalid
        $configured = strtolower(trim((string) Config::get('admin_path', 'admin')));
        $this->adminPath = ($configured !== '') ? $configured : 'admin';
    }

    public function dispatch(): void
    {
        $uri = $this->getCleanUri();

        // [Module 12] Serve pre-built static HTML when available — bypasses all routing overhead.
        // Falls through automatically on cache miss or when static_cache_enabled = false.
        if (StaticCache::tryServe($uri)) {
            return;
        }

        $this->applyDynamicRedirect($uri);

        if ($uri === 'sitemap.xml' || $uri === 'robots.txt' || $uri === 'image-sitemap.xml') {
            $this->handleSpecialRoute($uri);
            return;
        }

        $segments = $this->parseSegments($uri);

        if (!empty($segments) && $segments[0] === 'api') {
            $this->resolveApiRoute($segments);
            $this->executeController();
            return;
        }

        if ($this->isAdminRoute($segments)) {
            $this->resolveAdminRoute($segments);
        } else {
            $this->resolveFrontRoute($segments);
        }

        $this->executeController();
    }

    private function applyDynamicRedirect(string $uri): void
    {
        $path = '/' . ltrim($uri, '/');

        // [Module 13] Skip redirect logic for admin path (supports custom admin_path config)
        $adminPrefix = '/' . $this->adminPath;
        if ($path === $adminPrefix || strpos($path, $adminPrefix . '/') === 0) {
            return;
        }

        $map = JsonStore::globalData('redirects')->read();
        if (!is_array($map) || empty($map)) {
            return;
        }

        $target = null;
        $code   = 301; // default redirect code

        if (isset($map[$path]) && is_string($map[$path])) {
            // Legacy string-map format: {"/old": "/new"} — always 301
            $target = trim($map[$path]);
        } else {
            foreach ($map as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $from = '/' . ltrim(trim((string) ($row['from'] ?? '')), '/');
                if ($from !== $path) {
                    continue;
                }
                // Read optional code field: 301 | 302 | 410
                $rowCode = (int) ($row['code'] ?? 301);
                $code    = in_array($rowCode, [301, 302, 410], true) ? $rowCode : 301;
                $target  = isset($row['to']) ? trim((string) $row['to']) : null;
                break;
            }
        }

        // Handle 410 Gone — no Location header, resource permanently removed
        if ($code === 410) {
            http_response_code(410);
            header('X-Robots-Tag: noindex', true);
            exit;
        }

        if ($target === null || $target === '' || $target === $path) {
            return;
        }

        if (strncmp($target, 'http://', 7) !== 0 && strncmp($target, 'https://', 8) !== 0) {
            $target = '/' . ltrim($target, '/');
            if ($target !== '/') {
                $target = rtrim($target, '/');
            }
            $siteUrl = rtrim((string) Config::get('site_url', ''), '/');
            if ($siteUrl !== '') {
                $target = $siteUrl . $target;
            }
        }

        http_response_code($code);
        header('Location: ' . $target, true, $code);
        exit;
    }

    private function handleSpecialRoute(string $uri): void
    {
        $this->isAdmin = false;
        $this->lang = $this->defaultLang;

        if ($uri === 'sitemap.xml') {
            $this->controller = 'Sitemap';
            $this->action = 'index';
            $this->params = [];
        } elseif ($uri === 'image-sitemap.xml') {
            $this->controller = 'Sitemap';
            $this->action = 'images';
            $this->params = [];
        } elseif ($uri === 'robots.txt') {
            $this->controller = 'Sitemap';
            $this->action = 'robots';
            $this->params = [];
        }

        $this->executeController();
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function getController(): string
    {
        return $this->controller;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    private function getCleanUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if ($uri === null) {
            return '';
        }

        $uri = preg_replace('#/+#', '/', $uri);
        $uri = trim($uri, '/');

        if ($uri === 'index.php') {
            return '';
        }

        return $uri;
    }

    private function parseSegments(string $uri): array
    {
        if ($uri === '') {
            return [];
        }

        $segments = explode('/', $uri);

        return array_values(array_filter($segments, function ($segment) {
            return $segment !== '';
        }));
    }

    private function isAdminRoute(array $segments): bool
    {
        // [Module 13] Use configurable admin path instead of hardcoded "admin"
        return !empty($segments) && strtolower($segments[0]) === $this->adminPath;
    }

    private function resolveFrontRoute(array $segments): void
    {
        $this->isAdmin = false;

        if (empty($segments)) {
            $useBrowser = Config::get('root_redirect_use_browser_lang', false);
            $preferred = $useBrowser
                ? LanguageNegotiation::pickFromAcceptLanguage(
                    $this->supportedLangs,
                    $this->defaultLang
                )
                : $this->defaultLang;
            $this->redirect('/' . $preferred . '/', 302);
        }

        if (in_array($segments[0], $this->supportedLangs, true)) {
            $this->lang = array_shift($segments);
        } else {
            $target = '/' . $this->defaultLang . '/';
            if (!empty($segments)) {
                $target .= implode('/', $segments);
            }
            $this->redirect($target, 301);
        }

        if (empty($segments)) {
            $this->controller = 'Home';
            $this->action = 'index';
            $this->params = [];
            return;
        }

        $resource = strtolower($segments[0]);

        if (!isset(self::$frontRouteMap[$resource])) {
            $this->send404();
        }

        $this->controller = self::$frontRouteMap[$resource];
        $wasProductsPlural = (strtolower($resource) === 'products');
        $wasProductSingular = (strtolower($resource) === 'product');
        array_shift($segments);

        if (empty($segments)) {
            if ($this->controller === 'Product' && $wasProductSingular) {
                $this->redirect('/' . $this->lang . '/products', 301);
            }
            $this->action = 'index';
            $this->params = [];
            return;
        }

        if (strtolower($segments[0]) === 'submit' && $this->controller === 'Contact') {
            $this->action = 'submit';
            $this->params = [];
            return;
        }

        // Module 10: /{lang}/download/track — file download logging + redirect
        if ($this->controller === 'Download' && strtolower($segments[0]) === 'track') {
            $this->action = 'track';
            $this->params = [];
            return;
        }

        if ($this->controller === 'Newsletter') {
            if (!empty($segments) && strtolower($segments[0]) === 'submit') {
                $this->action = 'submit';
                $this->params = [];
                return;
            }
            if (count($segments) >= 2 && strtolower($segments[0]) === 'unsubscribe') {
                $this->action = 'unsubscribe';
                $this->params = [$segments[1] ?? ''];
                return;
            }
        }

        if ($wasProductsPlural && strtolower($segments[0]) === 'category') {
            array_shift($segments);
            if (!empty($segments[0])) {
                $this->redirect('/' . $this->lang . '/products/' . rawurlencode($segments[0]), 301);
            }
            $this->redirect('/' . $this->lang . '/products', 301);
        }

        if ($wasProductsPlural) {
            $this->action = 'category';
            $this->params = ['slug' => $segments[0]];
            return;
        }

        $this->action = 'show';
        $this->params = ['slug' => $segments[0]];
    }

    private function resolveApiRoute(array $segments): void
    {
        $this->isAdmin = true;
        $this->lang = $this->defaultLang;
        array_shift($segments);

        if (empty($segments)) {
            $this->send404();
        }

        $resource = strtolower($segments[0]);

        if ($resource === 'translate') {
            $this->controller = 'Api';
            $this->action = 'translate';
            $this->params = [];
        } elseif ($resource === 'upload') {
            $this->controller = 'Api';
            $this->action = 'upload';
            $this->params = [];
        } elseif ($resource === 'brevo' && isset($segments[1]) && strtolower((string) $segments[1]) === 'webhook') {
            $this->controller = 'Api';
            $this->action = 'brevoWebhook';
            $this->params = [];
        } else {
            $this->send404();
        }
    }

    private function resolveAdminRoute(array $segments): void
    {
        $this->isAdmin = true;
        $this->lang = $this->defaultLang;
        array_shift($segments);

        if (empty($segments)) {
            $this->controller = 'AdminDashboard';
            $this->action = 'index';
            $this->params = [];
            return;
        }

        $resource = strtolower($segments[0]);

        if (!isset(self::$adminRouteMap[$resource])) {
            $this->send404();
        }

        $routeInfo = self::$adminRouteMap[$resource];
        $this->controller = $routeInfo[0];
        $this->action = $routeInfo[1];
        array_shift($segments);

        if (!empty($segments)) {
            $this->action = $this->sanitizeAction(array_shift($segments));
            $this->params = $segments;
        }
    }

    private function sanitizeAction(string $action): string
    {
        $action = preg_replace('/[^a-zA-Z0-9_]/', '', $action);

        if ($action === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $action)) {
            return 'index';
        }

        return lcfirst($action);
    }

    private function executeController(): void
    {
        $controllerFile = APP_PATH . '/controllers/' . $this->controller . 'Controller.php';

        if (!file_exists($controllerFile)) {
            $this->send404();
        }

        require_once $controllerFile;

        $className = 'App\\Controllers\\' . $this->controller . 'Controller';

        if (!class_exists($className)) {
            $this->send404();
        }

        $controllerInstance = new $className($this->lang, $this->isAdmin);

        if (!method_exists($controllerInstance, $this->action)) {
            $this->send404();
        }

        call_user_func_array([$controllerInstance, $this->action], $this->params);
    }

    private function redirect(string $url, int $code = 302): void
    {
        $siteUrl = Config::get('site_url', '');
        $fullUrl = ($siteUrl !== '' && strncmp($url, 'http', 4) !== 0)
            ? rtrim($siteUrl, '/') . $url
            : $url;

        http_response_code($code);
        header('Location: ' . $fullUrl, true, $code);
        exit;
    }

    private function send404(): void
    {
        http_response_code(404);
        header('X-Robots-Tag: noindex, follow', true);

        // Log 404 hit — wrapped in try/catch so it never breaks the response
        try {
            $logUrl = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
            NotFoundLogger::log(
                $logUrl,
                (string) ($_SERVER['HTTP_REFERER']  ?? ''),
                ClientIp::get(),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
        } catch (\Throwable $e) {
            error_log('[Router::send404] NotFoundLogger failed: ' . $e->getMessage());
        }

        $lang = $this->defaultLang;
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        $uri = trim(preg_replace('#/+#', '/', $uri), '/');
        $parts = $uri === '' ? [] : explode('/', $uri);
        if (!empty($parts[0]) && in_array($parts[0], $this->supportedLangs, true)) {
            $lang = $parts[0];
        }

        $view = new View($lang, false);
        $siteName = Config::get('site_name', 'Site');
        $seo = new SEO($lang);
        $seoHead = '<title>' . htmlspecialchars(MetaHelper::titleWithBrand('404', $siteName), ENT_QUOTES, 'UTF-8') . '</title>' . "\n";
        $seoHead .= '<meta name="robots" content="noindex, follow">' . "\n";
        $seoHead .= $seo->renderCanonical('/' . $lang);

        $reco = [];
        try {
            $products = JsonStore::langData($lang, 'products')->read();
            $reco = array_slice($products, 0, 8);
        } catch (\Throwable $e) {
            $reco = [];
        }

        $view->render('404', [
            'seoHead' => $seoHead,
            'recommendedProducts' => $reco,
            'siteName' => $siteName,
        ]);

        exit;
    }
}
