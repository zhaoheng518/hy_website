<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\JsonStore;
use App\Core\SEO;

class AdminSettingController extends BaseController
{
    public function index(): void
    {
        Auth::requireCan('settings');

        if ($this->isPost()) {
            $this->handleSave();
            return;
        }

        $csrfToken = Auth::generateCsrfToken();

        $this->view->render('settings', [
            'csrfToken' => $csrfToken,
            'adminUser' => Auth::user(),
            'success' => $_SESSION['setting_success'] ?? '',
            'error' => $_SESSION['setting_error'] ?? '',
        ]);

        unset($_SESSION['setting_success'], $_SESSION['setting_error']);
    }

    private function handleSave(): void
    {
        $csrf = $this->getPost('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $_SESSION['setting_error'] = 'Invalid security token.';
            $this->redirect('/admin/settings');
        }

        $tab = $this->getPost('tab', 'general');

        if ($tab === 'general') {
            $this->saveGeneral();
        } elseif ($tab === 'smtp') {
            $this->saveSmtp();
        } elseif ($tab === 'password') {
            $this->savePassword();
        } elseif ($tab === 'seo') {
            $this->saveSeo();
        } elseif ($tab === 'auto_reply') {
            $this->saveAutoReply();
        }
    }

    private function saveGeneral(): void
    {
        $store = JsonStore::globalData('site');
        $store->update(function ($config) {
            $config['site_name'] = trim($this->getPost('site_name', $config['site_name'] ?? ''));
            $config['site_url'] = rtrim(trim($this->getPost('site_url', $config['site_url'] ?? '')), '/');
            $config['logo'] = trim($this->getPost('logo', $config['logo'] ?? ''));
            $config['inquiry_email'] = trim($this->getPost('inquiry_email', $config['inquiry_email'] ?? ''));
            $config['admin_email'] = trim($this->getPost('admin_email', $config['admin_email'] ?? ''));
            $config['per_page'] = max(1, (int) $this->getPost('per_page', $config['per_page'] ?? 12));
            $config['phone'] = trim($this->getPost('phone', $config['phone'] ?? ''));
            $config['whatsapp'] = trim($this->getPost('whatsapp', $config['whatsapp'] ?? ''));
            $config['address'] = trim($this->getPost('address', $config['address'] ?? ''));
            $config['company_legal_name'] = trim($this->getPost('company_legal_name', $config['company_legal_name'] ?? ''));
            $config['company_intro'] = trim($this->getPost('company_intro', $config['company_intro'] ?? ''));
            $config['footer_html'] = trim($this->getPost('footer_html', $config['footer_html'] ?? ''));
            $config['social_linkedin'] = trim($this->getPost('social_linkedin', $config['social_linkedin'] ?? ''));
            $config['social_youtube'] = trim($this->getPost('social_youtube', $config['social_youtube'] ?? ''));
            $config['social_facebook'] = trim($this->getPost('social_facebook', $config['social_facebook'] ?? ''));
            // Keep tracking snippets raw to avoid breaking script tags.
            $config['head_scripts'] = (string) $this->getPost('head_scripts', $config['head_scripts'] ?? '');
            $config['body_scripts'] = (string) $this->getPost('body_scripts', $config['body_scripts'] ?? '');
            $dl = trim($this->getPost('default_lang', $config['default_lang'] ?? 'en'));
            $supported = $config['supported_langs'] ?? ['en', 'cn', 'es'];
            if (in_array($dl, $supported, true)) {
                $config['default_lang'] = $dl;
            }
            $config['multilang_enabled'] = $this->getPost('multilang_enabled', '0') === '1';
            return $config;
        });

        Config::reload(DATA_PATH . '/site.json');

        $_SESSION['setting_success'] = 'General settings saved.';
        $this->redirect('/admin/settings');
    }

    private function saveSmtp(): void
    {
        $store = JsonStore::globalData('site');
        $store->update(function ($config) {
            $config['smtp_host'] = trim($this->getPost('smtp_host', $config['smtp_host'] ?? ''));
            $config['smtp_port'] = (int) $this->getPost('smtp_port', $config['smtp_port'] ?? 587);
            $config['smtp_user'] = trim($this->getPost('smtp_user', $config['smtp_user'] ?? ''));
            $config['smtp_from'] = trim($this->getPost('smtp_from', $config['smtp_from'] ?? ''));
            $config['smtp_from_name'] = trim($this->getPost('smtp_from_name', $config['smtp_from_name'] ?? ''));

            $newPass = $this->getPost('smtp_pass', '');
            if ($newPass !== '' && $newPass !== '********') {
                $config['smtp_pass'] = $newPass;
            }

            return $config;
        });

        Config::reload(DATA_PATH . '/site.json');

        $_SESSION['setting_success'] = 'SMTP settings saved.';
        $this->redirect('/admin/settings');
    }

    private function savePassword(): void
    {
        $currentPassword = $this->getPost('current_password', '');
        $newPassword = $this->getPost('new_password', '');
        $confirmPassword = $this->getPost('confirm_password', '');

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['setting_error'] = 'All password fields are required.';
            $this->redirect('/admin/settings');
        }

        if (strlen($newPassword) < 8) {
            $_SESSION['setting_error'] = 'New password must be at least 8 characters.';
            $this->redirect('/admin/settings');
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['setting_error'] = 'New passwords do not match.';
            $this->redirect('/admin/settings');
        }

        if (!Auth::changePassword($currentPassword, $newPassword)) {
            $_SESSION['setting_error'] = 'Current password is incorrect.';
            $this->redirect('/admin/settings');
        }

        $_SESSION['setting_success'] = 'Password changed successfully.';
        $this->redirect('/admin/settings');
    }

    public function seo(): void
    {
        $this->redirect('/admin/seo');
    }

    private function saveSeo(): void
    {
        $store = JsonStore::globalData('site');
        $store->update(function ($config) {
            $config['default_meta_title'] = trim($this->getPost('default_meta_title', $config['default_meta_title'] ?? ''));
            $config['default_meta_description'] = trim($this->getPost('default_meta_description', $config['default_meta_description'] ?? ''));
            $config['favicon'] = trim($this->getPost('favicon', $config['favicon'] ?? ''));
            $config['head_scripts'] = $this->getPost('head_scripts', $config['head_scripts'] ?? '');
            $config['body_scripts'] = $this->getPost('body_scripts', $config['body_scripts'] ?? '');
            return $config;
        });

        Config::reload(DATA_PATH . '/site.json');

        $this->regenerateSitemap();

        $_SESSION['setting_success'] = 'SEO设置已保存，Sitemap已更新。';

        if ($this->isAjax()) {
            $this->jsonSuccess(['message' => 'SEO settings saved.']);
        } else {
            $referer = $_SERVER['HTTP_REFERER'] ?? '/admin/seo';
            $this->redirect($referer);
        }
    }

    private function regenerateSitemap(): void
    {
        $sitemapPath = ROOT_PATH . '/sitemap.xml';
        $xml = SEO::generateSitemap();
        file_put_contents($sitemapPath, $xml, LOCK_EX);

        $imgPath = ROOT_PATH . '/image-sitemap.xml';
        file_put_contents($imgPath, SEO::generateImageSitemap(), LOCK_EX);
    }

    // ── Module 9: Inquiry Auto-Reply ──────────────────────────────────────────

    /**
     * Save auto-reply email settings.
     * Stores config in site.json under keys:
     *   auto_reply_enabled, auto_reply_subject_{lang}, auto_reply_body_{lang}
     * Supported langs: en / cn / es
     */
    private function saveAutoReply(): void
    {
        $store = JsonStore::globalData('site');
        $store->update(function ($config) {
            $config['auto_reply_enabled'] = $this->getPost('auto_reply_enabled', '0') === '1';

            foreach (['en', 'cn', 'es'] as $lang) {
                $config['auto_reply_subject_' . $lang] = trim(
                    (string) $this->getPost('auto_reply_subject_' . $lang, $config['auto_reply_subject_' . $lang] ?? '')
                );
                // Body is raw HTML — strip dangerous tags but keep formatting
                $rawBody = (string) $this->getPost('auto_reply_body_' . $lang, $config['auto_reply_body_' . $lang] ?? '');
                $config['auto_reply_body_' . $lang] = trim($rawBody);
            }

            return $config;
        });

        Config::reload(DATA_PATH . '/site.json');

        $_SESSION['setting_success'] = '自动回复设置已保存。';
        $this->redirect('/admin/settings');
    }
}
