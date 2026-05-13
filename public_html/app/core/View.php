<?php

namespace App\Core;

class View
{
    private string $lang;
    private bool $isAdmin;
    private array $sharedData = [];

    public function __construct(string $lang, bool $isAdmin = false)
    {
        if (!class_exists('View', false)) {
            class_alias('App\Core\View', 'View');
        }
        $this->lang = $lang;
        $this->isAdmin = $isAdmin;
    }

    public function render(string $template, array $data = [], bool $standalone = false): void
    {
        $templatePath = $this->resolveTemplate($template);

        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: {$templatePath}");
        }

        $data = array_merge($this->sharedData, $data);
        $data['lang'] = $this->lang;
        $data['siteName'] = Config::get('site_name', 'Stitch Tech');
        $data['siteUrl'] = Config::get('site_url', '');
        $data['isAdmin'] = $this->isAdmin;

        if ($this->isAdmin && !isset($data['unreadInquiries'])) {
            try {
                if (InquiryRepository::isAvailable()) {
                    $data['unreadInquiries'] = InquiryRepository::countUnread();
                } else {
                    $inq = JsonStore::globalData('inquiries')->read();
                    if (!is_array($inq)) {
                        $inq = [];
                    }
                    $data['unreadInquiries'] = count(array_filter($inq, function ($i) {
                        return empty($i['read']);
                    }));
                }
            } catch (\Throwable $e) {
                $data['unreadInquiries'] = 0;
            }
        }

        if (!$this->isAdmin && !isset($data['navLabels'])) {
            $data['navLabels'] = $this->getNavLabels($this->lang);
        }

        if (!$this->isAdmin && !isset($data['footerDesc'])) {
            $data['footerDesc'] = '';
        }

        if (!$this->isAdmin && !isset($data['footerContact'])) {
            $homeStore = new JsonStore(DATA_PATH . "/{$this->lang}/home.json");
            $homeData = $homeStore->read();
            $data['footerContact'] = $homeData['contact'] ?? [];
        }

        if (!$this->isAdmin && !isset($data['footerCategories'])) {
            $catStore = new JsonStore(DATA_PATH . "/{$this->lang}/categories.json");
            $data['footerCategories'] = $catStore->read();
        }

        if (!$this->isAdmin && !isset($data['megaMenuItems'])) {
            $homeStore = new JsonStore(DATA_PATH . "/{$this->lang}/home.json");
            $homeData = $homeStore->read();
            $data['megaMenuItems'] = $homeData['products']['items'] ?? [];
        }

        extract($data, EXTR_SKIP);

        if ($standalone) {
            require $templatePath;
            return;
        }

        $layout = 'layout';
        $layoutPath = $this->resolveTemplate($layout);

        if (!file_exists($layoutPath)) {
            throw new \RuntimeException("Layout template not found: {$layoutPath}");
        }

        ob_start();
        require $templatePath;
        $content = ob_get_clean();

        require $layoutPath;
    }

    public function renderPartial(string $template, array $data = []): string
    {
        $templatePath = $this->resolveTemplate($template);

        if (!file_exists($templatePath)) {
            return '';
        }

        $data = array_merge($this->sharedData, $data);
        $data['lang'] = $this->lang;

        extract($data, EXTR_SKIP);

        ob_start();
        require $templatePath;
        return ob_get_clean();
    }

    public function share(string $key, $value): void
    {
        $this->sharedData[$key] = $value;
    }

    private function resolveTemplate(string $template): string
    {
        $template = str_replace('.', '/', $template);
        $subDir = $this->isAdmin ? 'admin' : 'front';

        return VIEW_PATH . '/' . $subDir . '/' . $template . '.php';
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function langUrl(string $lang, string $path = ''): string
    {
        $siteUrl = Config::get('site_url', '');
        $base = rtrim($siteUrl, '/');
        $path = trim($path, '/');

        if ($path !== '') {
            return $base . '/' . $lang . '/' . $path;
        }

        return $base . '/' . $lang;
    }

    public static function getLangLabel(string $lang): string
    {
        $config = Config::get('lang_config.' . $lang . '.label', '');
        if ($config !== '') {
            return $config;
        }
        static $defaults = [
            'en' => 'English',
            'cn' => '中文',
            'es' => 'Español',
            'ru' => 'Русский',
            'ar' => 'العربية',
        ];
        return $defaults[$lang] ?? strtoupper($lang);
    }

    public static function getLangDirection(string $lang): string
    {
        $dir = strtolower((string) Config::get('lang_config.' . $lang . '.dir', 'ltr'));
        return $dir === 'rtl' ? 'rtl' : 'ltr';
    }

    public static function getSupportedLangs(): array
    {
        return Config::get('supported_langs', ['en', 'cn', 'es']);
    }

    private static function getNavLabels(string $lang): array
    {
        static $labels = [
            'en' => ['home' => 'Home', 'products' => 'Products', 'search' => 'Search', 'factory' => 'Factory', 'about' => 'About Us', 'cases' => 'Cases', 'blog' => 'Blog', 'contact' => 'Contact Us', 'learn_more' => 'Learn More', 'certifications' => 'Certifications & Qualifications', 'no_posts' => 'No articles yet.', 'your_name' => 'Your Name', 'your_email' => 'Your Email', 'company' => 'Company', 'phone_whatsapp' => 'Phone / WhatsApp', 'your_message' => 'Your Message', 'send_inquiry' => 'Send Inquiry', 'specs' => 'Specifications', 'details' => 'Details', 'download' => 'Download Datasheet', 'quote' => 'Request Quote', 'all_products' => 'All Products', 'no_products' => 'No products found in this category.', 'privacy' => 'Privacy Policy', 'terms' => 'Terms of Service'],
            'cn' => ['home' => '首页', 'products' => '产品中心', 'search' => '搜索', 'factory' => '工厂实力', 'about' => '关于我们', 'cases' => '客户案例', 'blog' => '博客', 'contact' => '联系我们', 'learn_more' => '了解更多', 'certifications' => '资质认证', 'no_posts' => '暂无文章', 'your_name' => '您的姓名', 'your_email' => '您的邮箱', 'company' => '公司', 'phone_whatsapp' => '电话/WhatsApp', 'your_message' => '您的留言', 'send_inquiry' => '发送询盘', 'specs' => '技术参数', 'details' => '产品详情', 'download' => '下载规格书', 'quote' => '获取报价', 'all_products' => '全部产品', 'no_products' => '该分类暂无产品。', 'privacy' => '隐私政策', 'terms' => '服务条款'],
            'es' => ['home' => 'Inicio', 'products' => 'Productos', 'search' => 'Buscar', 'factory' => 'Fábrica', 'about' => 'Sobre Nosotros', 'cases' => 'Casos', 'blog' => 'Blog', 'contact' => 'Contáctenos', 'learn_more' => 'Más información', 'certifications' => 'Certificaciones', 'no_posts' => 'Sin artículos aún.', 'your_name' => 'Su Nombre', 'your_email' => 'Su Email', 'company' => 'Empresa', 'phone_whatsapp' => 'Teléfono / WhatsApp', 'your_message' => 'Su Mensaje', 'send_inquiry' => 'Enviar Consulta', 'specs' => 'Especificaciones', 'details' => 'Detalles', 'download' => 'Descargar Ficha', 'quote' => 'Solicitar Cotización', 'all_products' => 'Todos los Productos', 'no_products' => 'No se encontraron productos en esta categoría.', 'privacy' => 'Política de Privacidad', 'terms' => 'Términos de Servicio'],
        ];
        return $labels[$lang] ?? $labels['en'];
    }

    public static function assetUrl(string $path): string
    {
        $siteUrl = Config::get('site_url', '');
        $base = rtrim($siteUrl, '/');
        return $base . '/app/assets/' . ltrim($path, '/');
    }

    public static function cacheBust(string $path): string
    {
        $fullPath = ROOT_PATH . '/app/assets/' . ltrim($path, '/');
        if (file_exists($fullPath)) {
            $mtime = filemtime($fullPath);
            $sep = (strpos($path, '?') !== false) ? '&' : '?';
            return '/app/assets/' . ltrim($path, '/') . $sep . 'v=' . $mtime;
        }
        return '/app/assets/' . ltrim($path, '/');
    }

    /**
     * Prefer minified front.css when front.min.css exists.
     */
    public static function frontStylesheetUrl(): string
    {
        $minPath = ROOT_PATH . '/app/assets/css/front.min.css';
        $srcPath = ROOT_PATH . '/app/assets/css/front.css';

        // Prefer minified CSS only when it is up to date.
        if (is_file($minPath) && (!is_file($srcPath) || filemtime($minPath) >= filemtime($srcPath))) {
            return self::cacheBust('css/front.min.css');
        }
        return self::cacheBust('css/front.css');
    }

    /**
     * Optional WebP &lt;source&gt; when a matching .webp file exists beside the image.
     */
    public static function responsiveImage(string $src, string $alt = '', array $attrs = []): string
    {
        $alt = trim($alt) !== '' ? $alt : 'Image';
        $class = isset($attrs['class']) ? (string) $attrs['class'] : '';
        $loading = isset($attrs['loading']) ? (string) $attrs['loading'] : 'lazy';
        $fetchpriority = isset($attrs['fetchpriority']) ? (string) $attrs['fetchpriority'] : '';

        $webpUrl = '';
        if ($src !== '' && !preg_match('#^https?://#i', $src)) {
            $rel = strncmp($src, '/', 1) === 0 ? $src : '/' . ltrim($src, '/');
            $full = ROOT_PATH . $rel;
            if (preg_match('/\.(jpe?g|png)$/i', $full)) {
                $webpFull = preg_replace('/\.(jpe?g|png)$/i', '.webp', $full);
                if (is_file($webpFull)) {
                    $webpUrl = preg_replace('/\.(jpe?g|png)$/i', '.webp', $rel);
                }
            }
        }

        $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
        $fetchAttr = $fetchpriority !== '' ? ' fetchpriority="' . htmlspecialchars($fetchpriority, ENT_QUOTES, 'UTF-8') . '"' : '';

        $skipKeys = ['class', 'loading', 'fetchpriority', 'sizes'];
        $extraAttrs = '';
        foreach ($attrs as $ak => $av) {
            if (in_array($ak, $skipKeys, true) || $av === '' || $av === null) {
                continue;
            }
            $extraAttrs .= ' ' . htmlspecialchars((string) $ak, ENT_QUOTES, 'UTF-8') . '="'
                . htmlspecialchars((string) $av, ENT_QUOTES, 'UTF-8') . '"';
        }

        $imgAttrs = ' src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"'
            . $classAttr
            . ' loading="' . htmlspecialchars($loading, ENT_QUOTES, 'UTF-8') . '" decoding="async"' . $fetchAttr . $extraAttrs;

        if ($webpUrl !== '') {
            return '<picture><source type="image/webp" srcset="' . htmlspecialchars($webpUrl, ENT_QUOTES, 'UTF-8')
                . '"><img' . $imgAttrs . '></picture>';
        }

        return '<img' . $imgAttrs . '>';
    }

    public static function uploadUrl(string $path): string
    {
        $siteUrl = Config::get('site_url', '');
        $base = rtrim($siteUrl, '/');
        return $base . '/uploads/' . ltrim($path, '/');
    }

    public static function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        if (function_exists('iconv')) {
            $converted = @iconv('utf-8', 'us-ascii//TRANSLIT', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        if ($text === '') {
            $text = 'page-' . substr(uniqid('', true), 0, 8);
        }

        return $text;
    }

    public static function truncate(string $text, int $length = 160, string $append = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . $append;
    }

    public static function formatTimeAgo(string $datetime): string
    {
        $now = time();
        $then = strtotime($datetime);
        $diff = $now - $then;

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . ' min ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        }
        if ($diff < 2592000) {
            return floor($diff / 86400) . ' days ago';
        }

        return date('Y-m-d', $then);
    }
}
