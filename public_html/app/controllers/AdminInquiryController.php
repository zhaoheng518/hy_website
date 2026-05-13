<?php

namespace App\Controllers;

use App\Core\AdminListHelper;
use App\Core\Auth;
use App\Core\InquiryRepository;
use App\Core\JsonStore;

class AdminInquiryController extends BaseController
{
    private const STATUSES = ['new', 'contacted', 'quoted', 'closed'];
    private const EXPORT_COLUMNS = [
        'id' => 'ID',
        'name' => 'Name',
        'email' => 'Email',
        'company' => 'Company',
        'phone' => 'Phone',
        'country' => 'Country',
        'message' => 'Message',
        'source_url' => 'Source URL',
        'product_slug' => 'Product Slug',
        'status' => 'Status',
        'created_at' => 'Created At',
    ];

    public function index(): void
    {
        Auth::requireCan('inquiries');

        $all = $this->loadInquiryRows();

        $q = trim($this->getQuery('q', ''));
        $status = trim($this->getQuery('status', ''));
        $dateFrom = trim($this->getQuery('date_from', ''));
        $dateTo = trim($this->getQuery('date_to', ''));
        $sort = trim($this->getQuery('sort', 'desc')) === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $this->getQuery('page', 1));
        $perPage = max(1, min(100, (int) $this->getQuery('per_page', 25)));

        $normalized = [];
        foreach ($all as $row) {
            $row['_norm_status'] = $this->normalizeStatus((string) ($row['status'] ?? 'new'));
            $normalized[] = $row;
        }

        $filtered = $normalized;
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $filtered = array_values(array_filter($filtered, function ($i) use ($status) {
                return ($i['_norm_status'] ?? 'new') === $status;
            }));
        }

        [$pageRows, $total] = AdminListHelper::process($filtered, [
            'q' => $q,
            'sort' => $sort,
            'page' => $page,
            'per_page' => $perPage,
            'search_keys' => ['name', 'email', 'company', 'phone', 'message', 'source_url', 'product_slug', 'product_source', 'country'],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        foreach ($pageRows as &$r) {
            unset($r['_norm_status']);
        }
        unset($r);

        $counts = ['all' => count($all), 'new' => 0, 'contacted' => 0, 'quoted' => 0, 'closed' => 0];
        foreach ($all as $i) {
            $s = $this->normalizeStatus((string) ($i['status'] ?? 'new'));
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
        }

        $this->view->render('inquiry/index', [
            'inquiries' => $pageRows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
            'q' => $q,
            'currentFilter' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sort' => $sort,
            'counts' => $counts,
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'inquiries',
            'pageTitle' => '询盘管理',
            'breadcrumbs' => [['label' => '询盘', 'url' => '/admin/inquiries']],
            'success' => $_SESSION['form_success'] ?? $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['form_error'] ?? $_SESSION['setting_error'] ?? '',
        ]);
        unset($_SESSION['form_success'], $_SESSION['form_error'], $_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    public function show(string $id = ''): void
    {
        Auth::requireCan('inquiries', 'read');

        if ($id === '') {
            $this->redirect('/admin/inquiries');
        }

        $inquiry = null;
        if (InquiryRepository::isAvailable()) {
            $inquiry = InquiryRepository::findByPublicRef($id);
            if ($inquiry !== null && empty($inquiry['read'])) {
                InquiryRepository::markReadByPublicRef($id);
                $inquiry['read'] = true;
                $inquiry['read_at'] = $inquiry['read_at'] ?? date('Y-m-d H:i:s');
            }
        } else {
            $store = JsonStore::globalData('inquiries');
            $inquiries = $store->read();
            foreach ($inquiries as $i) {
                if (isset($i['id']) && $i['id'] === $id) {
                    $inquiry = $i;
                    break;
                }
            }

            if ($inquiry === null) {
                $_SESSION['form_error'] = '询盘未找到';
                $this->redirect('/admin/inquiries');
            }

            if (empty($inquiry['read'])) {
                $store->update(function ($list) use ($id) {
                    foreach ($list as &$row) {
                        if (isset($row['id']) && $row['id'] === $id) {
                            $row['read'] = true;
                            $row['read_at'] = date('Y-m-d H:i:s');
                            if (!isset($row['status'])) {
                                $row['status'] = 'new';
                            }
                            break;
                        }
                    }
                    unset($row);

                    return $list;
                });
                $inquiry['read'] = true;
            }
        }

        if ($inquiry === null) {
            $_SESSION['form_error'] = '询盘未找到';
            $this->redirect('/admin/inquiries');
        }

        $inquiry['display_status'] = $this->normalizeStatus((string) ($inquiry['status'] ?? 'new'));

        $this->view->render('inquiry/detail', [
            'inquiry' => $inquiry,
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'inquiries',
            'pageTitle' => '询盘详情',
            'breadcrumbs' => [
                ['label' => '询盘', 'url' => '/admin/inquiries'],
                ['label' => '详情'],
            ],
        ]);
    }

    public function updateStatus(string $id = ''): void
    {
        Auth::requireCan('inquiries', 'write');

        if ($id === '') {
            $this->jsonError('ID required');
        }

        $csrf = $this->getPost('_csrf', '') ?: $this->getQuery('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $this->jsonError('Invalid token', 403);
        }

        $status = (string) ($this->getPost('status', '') ?: $this->getQuery('status', ''));
        if (!in_array($status, self::STATUSES, true)) {
            $this->jsonError('Invalid status');
        }

        if (InquiryRepository::isAvailable()) {
            if (!InquiryRepository::updateStatusByPublicRef($id, $status)) {
                if ($this->isPost()) {
                    $_SESSION['form_error'] = '询盘未找到';
                    $this->redirect('/admin/inquiries');
                }
                $this->jsonError('Not found', 404);
            }
        } else {
            $store = JsonStore::globalData('inquiries');
            $store->update(function ($list) use ($id, $status) {
                foreach ($list as &$row) {
                    if (isset($row['id']) && $row['id'] === $id) {
                        $row['status'] = $status;
                        break;
                    }
                }
                unset($row);

                return $list;
            });
        }

        if ($this->isPost()) {
            $_SESSION['form_success'] = '状态已更新';
            $this->redirect('/admin/inquiries');
        }
        $this->jsonSuccess(['status' => $status]);
    }

    public function delete(string $id = ''): void
    {
        Auth::requireCan('inquiries', 'write');

        if ($id === '') {
            $this->redirect('/admin/inquiries');
        }

        if ($this->isPost()) {
            $csrf = $this->getPost('_csrf', '');
            if (!Auth::consumeCsrfToken($csrf)) {
                $this->jsonError('Invalid security token.', 403);
            }

            if (InquiryRepository::isAvailable()) {
                InquiryRepository::deleteByPublicRef($id);
            } else {
                $store = JsonStore::globalData('inquiries');
                $store->update(function ($list) use ($id) {
                    return array_values(array_filter($list, function ($i) use ($id) {
                        return !isset($i['id']) || $i['id'] !== $id;
                    }));
                });
            }
        }

        $this->redirect('/admin/inquiries');
    }

    public function export(): void
    {
        Auth::requireCan('inquiries', 'read');

        $csrf = $this->getQuery('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        $inquiries = array_reverse($this->loadInquiryRows());
        $filter = $this->getQuery('status', '');
        if ($filter !== '' && in_array($filter, self::STATUSES, true)) {
            $inquiries = array_values(array_filter($inquiries, function ($i) use ($filter) {
                return $this->normalizeStatus((string) ($i['status'] ?? 'new')) === $filter;
            }));
        }

        $q = trim($this->getQuery('q', ''));
        $dateFrom = trim($this->getQuery('date_from', ''));
        $dateTo = trim($this->getQuery('date_to', ''));
        [$inquiries] = AdminListHelper::process($inquiries, [
            'q' => $q,
            'sort' => 'desc',
            'page' => 1,
            'per_page' => max(1, count($inquiries)),
            'search_keys' => ['name', 'email', 'company', 'phone', 'message', 'source_url', 'product_slug', 'country'],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=inquiries_export_' . date('Ymd_His') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, array_values(self::EXPORT_COLUMNS));

        foreach ($inquiries as $i) {
            $row = [
                'id' => (string) ($i['id'] ?? ''),
                'name' => (string) ($i['name'] ?? ''),
                'email' => (string) ($i['email'] ?? ''),
                'company' => (string) ($i['company'] ?? ''),
                'phone' => (string) ($i['phone'] ?? ''),
                'country' => (string) ($i['country'] ?? ''),
                'message' => (string) ($i['message'] ?? ''),
                'source_url' => (string) ($i['source_url'] ?? ''),
                'product_slug' => (string) ($i['product_slug'] ?? $i['product_source'] ?? ''),
                'status' => $this->normalizeStatus((string) ($i['status'] ?? 'new')),
                'created_at' => (string) ($i['created_at'] ?? ''),
            ];
            fputcsv($out, [
                $row['id'],
                $row['name'],
                $row['email'],
                $row['company'],
                $row['phone'],
                $row['country'],
                $row['message'],
                $row['source_url'],
                $row['product_slug'],
                $row['status'],
                $row['created_at'],
            ]);
        }

        fclose($out);
        exit;
    }

    private function loadInquiryRows(): array
    {
        if (InquiryRepository::isAvailable()) {
            return InquiryRepository::listAllForAdmin();
        }

        $store = JsonStore::globalData('inquiries');
        $rows = $store->read();

        return is_array($rows) ? $rows : [];
    }

    private function normalizeStatus(string $status): string
    {
        if ($status === 'replied') {
            return 'contacted';
        }
        if ($status === 'spam') {
            return 'closed';
        }
        if (in_array($status, self::STATUSES, true)) {
            return $status;
        }
        return 'new';
    }
}
