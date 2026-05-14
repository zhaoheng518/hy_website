<?php

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Config;
use App\Core\JsonStore;
use App\Core\MetaHelper;
use App\Core\NotFoundLogger;
use App\Core\View;
use App\Seo\SeoEngine;

abstract class BaseController
{
    protected string $lang;
    protected bool $isAdmin;
    protected View $view;
    protected SeoEngine $seo;

    public function __construct(string $lang, bool $isAdmin = false)
    {
        $this->lang = $lang;
        $this->isAdmin = $isAdmin;
        $this->view = new View($lang, $isAdmin);
        $this->seo = new SeoEngine($lang);
    }

    protected function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $data ?? [];
    }

    protected function jsonSuccess(array $data = [], int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function jsonError(string $message, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        header('Location: ' . $url, true, $code);
        exit;
    }

    protected function getPost(string $key, $default = '')
    {
        return $_POST[$key] ?? $default;
    }

    protected function getQuery(string $key, $default = '')
    {
        return $_GET[$key] ?? $default;
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    protected function validateRequired(array $data, array $fields): ?string
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                return "Field '{$field}' is required.";
            }
        }
        return null;
    }

    protected function sanitizeString(string $value): string
    {
        $value = trim($value);
        $value = stripslashes($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        return $value;
    }

    protected function getLangData(string $type): array
    {
        $store = JsonStore::langData($this->lang, $type);
        return $store->read();
    }

    protected function getGlobalData(string $name): array
    {
        $store = JsonStore::globalData($name);
        return $store->read();
    }

    protected function renderFront404(): void
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
            error_log('[BaseController::renderFront404] NotFoundLogger failed: ' . $e->getMessage());
        }

        $view = new View($this->lang, false);
        $siteName = Config::get('site_name', 'Site');
        $seo = new SeoEngine($this->lang);
        $seoHead = '<title>' . htmlspecialchars(MetaHelper::titleWithBrand('404', $siteName), ENT_QUOTES, 'UTF-8') . "</title>\n";
        $seoHead .= '<meta name="robots" content="noindex, follow">' . "\n";
        $seoHead .= $seo->renderCanonical('/' . $this->lang);

        $reco = [];
        try {
            $products = JsonStore::langData($this->lang, 'products')->read();
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
