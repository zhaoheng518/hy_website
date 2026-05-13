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

        $page   = max(1, (int) ($this->getQuery('page', '1')));
        $offset = ($page - 1) * self::PER_PAGE;

        $byProduct = DownloadLogger::countByProduct(30);
        $total     = DownloadLogger::countAll();
        $recent    = DownloadLogger::recent(self::PER_PAGE, $offset);
        $pages     = $total > 0 ? (int) ceil($total / self::PER_PAGE) : 1;

        $this->view->render('downloads', [
            'adminUser'  => Auth::user(),
            'byProduct'  => $byProduct,
            'total'      => $total,
            'recent'     => $recent,
            'page'       => $page,
            'pages'      => $pages,
            'perPage'    => self::PER_PAGE,
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function getQuery(string $key, string $default = ''): string
    {
        return isset($_GET[$key]) ? (string) $_GET[$key] : $default;
    }
}
