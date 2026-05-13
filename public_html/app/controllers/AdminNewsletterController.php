<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\NewsletterEventRepository;
use App\Core\NewsletterJobRepository;
use App\Core\NewsletterRepository;

class AdminNewsletterController extends BaseController
{
    /**
     * 为 true 时开放：任务队列、手动群发、Webhook 事件页与库表写入；默认 false 保持轻量（投递日志用 Brevo）。
     * 配置：site.json → newsletter_advanced
     */
    private function isNewsletterAdvanced(): bool
    {
        $v = Config::get('newsletter_advanced', false);

        return $v === true || $v === 1 || $v === '1' || $v === 'true';
    }

    public function index(): void
    {
        Auth::requireCan('newsletter');

        $q = trim($this->getQuery('q', ''));
        $page = max(1, (int) $this->getQuery('page', 1));
        $perPage = 25;
        $status = trim($this->getQuery('status', ''));
        $activeOnly = $status === 'active' ? true : ($status === 'inactive' ? false : null);

        $data = NewsletterRepository::adminList($page, $perPage, $q, $activeOnly);
        $total = (int) ($data['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));

        $subStats = NewsletterRepository::getSubscriberStats();
        $advanced = $this->isNewsletterAdvanced();
        $sendStats = $advanced
            ? NewsletterJobRepository::getSendStats()
            : [
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'pending' => 0,
                'sending' => 0,
                'queued' => 0,
            ];

        $subTotal = (int) ($subStats['total'] ?? 0);
        $subInactive = (int) ($subStats['inactive'] ?? 0);
        $unsubscribeRate = $subTotal > 0 ? round(100 * $subInactive / $subTotal, 2) : 0.0;

        $this->view->render('newsletter/admin_index', [
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'newsletter',
            'pageTitle' => '邮件订阅',
            'breadcrumbs' => [['label' => '订阅', 'url' => '/admin/newsletter']],
            'rows' => $data['rows'],
            'total' => $total,
            'totalPages' => $totalPages,
            'page' => $page,
            'perPage' => $perPage,
            'q' => $q,
            'status' => $status,
            'success' => $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['setting_error'] ?? '',
            'statTotalSubscribers' => $subTotal,
            'statActiveSubscribers' => (int) ($subStats['active'] ?? 0),
            'statUnsubscribed' => $subInactive,
            'statUnsubscribeRate' => $unsubscribeRate,
            'statSendTotal' => (int) ($sendStats['total'] ?? 0),
            'statSendSuccess' => (int) ($sendStats['sent'] ?? 0),
            'statSendFailed' => (int) ($sendStats['failed'] ?? 0),
            'statSendPending' => (int) ($sendStats['queued'] ?? 0),
            'statSendPendingOnly' => (int) ($sendStats['pending'] ?? 0),
            'statSendSending' => (int) ($sendStats['sending'] ?? 0),
            'newsletterAdvanced' => $advanced,
        ]);
        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    public function toggle(): void
    {
        Auth::requireCan('newsletter', 'write');
        if (!$this->isPost() || !Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Invalid token';
            $this->redirect('/admin/newsletter');
        }
        $id = (int) $this->getPost('id', 0);
        $active = $this->getPost('active', '0') === '1';
        NewsletterRepository::adminSetActive($id, $active);
        $_SESSION['setting_success'] = '已更新';
        $this->redirect('/admin/newsletter');
    }

    public function delete(): void
    {
        Auth::requireCan('newsletter', 'write');
        if (!$this->isPost() || !Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Invalid token';
            $this->redirect('/admin/newsletter');
        }
        $id = (int) $this->getPost('id', 0);
        NewsletterRepository::adminDelete($id);
        $_SESSION['setting_success'] = '已删除';
        $this->redirect('/admin/newsletter');
    }

    /**
     * 队列任务历史、手动群发（需 newsletter 权限；与订阅列表共用 newsletter 菜单权限）。
     */
    public function jobs(): void
    {
        Auth::requireCan('newsletter');

        if (!$this->isNewsletterAdvanced()) {
            $_SESSION['setting_error'] = 'Newsletter 高级功能（队列/群发）未开启。投递与打开数据请在 Brevo 查看；需要时在 site.json 设置 newsletter_advanced 为 true。';
            $this->redirect('/admin/newsletter');
        }

        if (!NewsletterJobRepository::isAvailable()) {
            $_SESSION['setting_error'] = 'newsletter_jobs 表不可用，请检查数据库与迁移。';
            $this->redirect('/admin/newsletter');
        }

        $page = max(1, (int) $this->getQuery('page', 1));
        $perPage = 30;
        $st = trim($this->getQuery('job_status', ''));
        $filter = $st !== '' && in_array($st, ['pending', 'sending', 'sent', 'failed'], true) ? $st : null;

        $data = NewsletterJobRepository::listJobsPaginated($page, $perPage, $filter);
        $rows = $data['rows'] ?? [];
        $total = (int) ($data['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));

        $subStats = NewsletterRepository::getSubscriberStats();
        $sendStats = NewsletterJobRepository::getSendStats();

        $this->view->render('newsletter/jobs', [
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'newsletter',
            'pageTitle' => 'Newsletter 队列',
            'breadcrumbs' => [
                ['label' => '订阅', 'url' => '/admin/newsletter'],
                ['label' => '任务队列'],
            ],
            'jobRows' => $rows,
            'jobTotal' => $total,
            'jobPage' => $page,
            'jobPerPage' => $perPage,
            'jobTotalPages' => $totalPages,
            'jobStatusFilter' => $st,
            'statTotalSubscribers' => (int) ($subStats['total'] ?? 0),
            'statActiveSubscribers' => (int) ($subStats['active'] ?? 0),
            'statSendTotal' => (int) ($sendStats['total'] ?? 0),
            'statSendSuccess' => (int) ($sendStats['sent'] ?? 0),
            'statSendFailed' => (int) ($sendStats['failed'] ?? 0),
            'statSendPending' => (int) ($sendStats['queued'] ?? 0),
            'success' => $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['setting_error'] ?? '',
        ]);
        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    /**
     * POST：为所有「一般订阅」活跃订阅者入队一封 general 邮件。
     */
    public function broadcast(): void
    {
        Auth::requireCan('newsletter', 'write');

        if (!$this->isNewsletterAdvanced()) {
            $_SESSION['setting_error'] = '手动群发未开启（site.json newsletter_advanced）。';
            $this->redirect('/admin/newsletter');
        }

        if (!$this->isPost()) {
            $this->redirect('/admin/newsletter/jobs');
        }
        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['setting_error'] = 'Invalid token';
            $this->redirect('/admin/newsletter/jobs');
        }

        if (!NewsletterJobRepository::isAvailable() || !NewsletterRepository::isAvailable()) {
            $_SESSION['setting_error'] = '数据库表不可用';
            $this->redirect('/admin/newsletter/jobs');
        }

        $subject = mb_substr(trim($this->getPost('broadcast_subject', '')), 0, 512, 'UTF-8');
        $html = trim($this->getPost('broadcast_html', ''));
        $text = trim($this->getPost('broadcast_text', ''));

        if ($subject === '' || $html === '') {
            $_SESSION['setting_error'] = '请填写主题与 HTML 正文';
            $this->redirect('/admin/newsletter/jobs');
        }

        if ($text === '') {
            $text = trim(strip_tags($html));
        }

        $totalRecipients = NewsletterRepository::countActiveBroadcastRecipients();
        if ($totalRecipients <= 0) {
            $_SESSION['setting_error'] = '没有「一般订阅」的活跃订阅者（notify_general=1）';
            $this->redirect('/admin/newsletter/jobs');
        }

        $batchSize = 200;
        $created = 0;
        $failed = 0;
        for ($offset = 0; $offset < $totalRecipients; $offset += $batchSize) {
            $batch = NewsletterRepository::fetchActiveBroadcastRecipientsPaged($batchSize, $offset);
            if ($batch === []) {
                break;
            }
            foreach ($batch as $sub) {
                $sid = (int) ($sub['id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $jid = NewsletterJobRepository::createJob(
                    $sid,
                    $subject,
                    $html,
                    $text,
                    NewsletterJobRepository::TYPE_GENERAL,
                    null,
                    null
                );
                if ($jid > 0) {
                    ++$created;
                } else {
                    ++$failed;
                }
            }
        }

        $_SESSION['setting_success'] = sprintf(
            'Created %d jobs, failed %d (subscribers=%d, batch=%d)',
            $created,
            $failed,
            $totalRecipients,
            $batchSize
        );
        $this->redirect('/admin/newsletter/jobs');
    }

    /**
     * Webhook 事件列表（/admin/newsletter/events）。
     */
    public function events(): void
    {
        Auth::requireCan('newsletter');

        if (!$this->isNewsletterAdvanced()) {
            $_SESSION['setting_error'] = 'Webhook 事件库未开启（默认仅 Brevo 控制台查看）。开启请设置 site.json newsletter_advanced 为 true。';
            $this->redirect('/admin/newsletter');
        }

        if (!NewsletterEventRepository::isAvailable()) {
            $_SESSION['setting_error'] = 'newsletter_events 表不可用，请执行 database_migrations/newsletter_events.sql';
            $this->redirect('/admin/newsletter');
        }

        $page = max(1, (int) $this->getQuery('event_page', 1));
        $perPage = 30;
        $eventType = trim($this->getQuery('event_type', ''));
        $filter = $eventType !== '' ? $eventType : null;

        $data = NewsletterEventRepository::listPaginated($page, $perPage, $filter);
        $rows = $data['rows'] ?? [];
        $total = (int) ($data['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        $stats = NewsletterEventRepository::getStatsSummaryLastDays(30);

        $this->view->render('newsletter/events', [
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'newsletter',
            'pageTitle' => 'Newsletter 事件',
            'breadcrumbs' => [
                ['label' => '订阅', 'url' => '/admin/newsletter'],
                ['label' => 'Webhook 事件'],
            ],
            'eventRows' => $rows,
            'eventTotal' => $total,
            'eventPage' => $page,
            'eventPerPage' => $perPage,
            'eventTotalPages' => $totalPages,
            'eventTypeFilter' => $eventType,
            'statDelivered' => (int) ($stats['delivered'] ?? 0),
            'statOpened' => (int) ($stats['opened'] ?? 0),
            'statClicked' => (int) ($stats['clicked'] ?? 0),
            'statBounced' => (int) ($stats['bounced'] ?? 0),
            'statUnsubscribed' => (int) ($stats['unsubscribed'] ?? 0),
            'success' => $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['setting_error'] ?? '',
        ]);
        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }
}
