<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DownloadLogger;

/**
 * AdminDownloadController — Module 10: Download Stats
 *
 * Routes:
 *   GET /admin/downloads          → index()   (overview + recent list)
 *
 * Permission reuse: 'inquiries' read — same audience (sales/analytics viewers).
 */
class AdminDownloadController extends BaseController
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        Auth::requireCan('inquiries', 'read');

        $page   = max(1, (int) ($this->getDownloadQuery('page', '1')));
        $offset = ($page - 1) * self::PER_PAGE;

        // Build filters from GET parameters
        $filters = [
            'product_slug' => trim($this->getDownloadQuery('product_slug', '')),
            'file_name'    => trim($this->getDownloadQuery('file_name', '')),
            'utm_source'   => trim($this->getDownloadQuery('utm_source', '')),
            'utm_medium'   => trim($this->getDownloadQuery('utm_medium', '')),
            'date_from'    => trim($this->getDownloadQuery('date_from', '')),
            'date_to'      => trim($this->getDownloadQuery('date_to', '')),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($val) {
            return $val !== '';
        });

        // Get data with filters
        $byProduct = DownloadLogger::countByProduct(30);
        $byFile    = DownloadLogger::countByFile(10);
        $total     = empty($filters) ? DownloadLogger::countAll() : DownloadLogger::countWithFilters($filters);
        $recent    = DownloadLogger::recent(self::PER_PAGE, $offset, $filters);
        $pages     = $total > 0 ? (int) ceil($total / self::PER_PAGE) : 1;

        $this->view->render('downloads', [
            'adminUser'    => Auth::user(),
            'byProduct'    => $byProduct,
            'byFile'       => $byFile,
            'total'        => $total,
            'recent'       => $recent,
            'page'         => $page,
            'pages'        => $pages,
            'perPage'      => self::PER_PAGE,
            'filters'      => $filters,
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    protected function getDownloadQuery(string $key, string $default = ''): string
    {
        return isset($_GET[$key]) ? (string) $_GET[$key] : $default;
    }
}