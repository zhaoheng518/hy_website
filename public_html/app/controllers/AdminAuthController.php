<?php

namespace App\Controllers;

use App\Core\AdminSecurityHelper;
use App\Core\Auth;
use App\Core\Config;

class AdminAuthController extends BaseController
{
    public function login(): void
    {
        $adminPath = trim((string) Config::get('admin_path', 'admin')) ?: 'admin';

        if (Auth::check()) {
            $this->redirect('/' . $adminPath);
        }

        if ($this->isPost()) {
            $this->handleLogin();
            return;
        }

        $csrfToken      = Auth::generateCsrfToken();
        $lockedOut      = Auth::isLockedOut();
        $lockoutRemaining = Auth::getLockoutRemaining();

        // [Module 13] Generate CAPTCHA question for the view
        $captchaEnabled  = AdminSecurityHelper::isCaptchaEnabled();
        $captchaQuestion = $captchaEnabled ? AdminSecurityHelper::generateCaptcha() : '';

        $error = $_SESSION['login_error'] ?? '';
        unset($_SESSION['login_error']);

        $this->view->render('login', [
            'csrfToken'       => $csrfToken,
            'lockedOut'       => $lockedOut,
            'lockoutRemaining' => $lockoutRemaining,
            'error'           => $error,
            'captchaEnabled'  => $captchaEnabled,
            'captchaQuestion' => $captchaQuestion,
        ], true);
    }

    private function handleLogin(): void
    {
        $adminPath = trim((string) Config::get('admin_path', 'admin')) ?: 'admin';
        $loginUrl  = '/' . $adminPath . '/login';

        $csrf = $this->getPost('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $_SESSION['login_error'] = 'Invalid security token. Please try again.';
            $this->redirect($loginUrl);
        }

        if (Auth::isLockedOut()) {
            $_SESSION['login_error'] = 'Too many failed attempts. Please try again later.';
            $this->redirect($loginUrl);
        }

        // [Module 13] Validate CAPTCHA before credential check
        if (AdminSecurityHelper::isCaptchaEnabled()) {
            $captchaInput = trim($this->getPost('captcha', ''));
            if (!AdminSecurityHelper::validateCaptcha($captchaInput)) {
                $_SESSION['login_error'] = 'Incorrect CAPTCHA answer. Please try again.';
                $this->redirect($loginUrl);
            }
        }

        $username = trim($this->getPost('username', ''));
        $password = $this->getPost('password', '');

        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'Please enter both username and password.';
            $this->redirect($loginUrl);
        }

        if (Auth::login($username, $password)) {
            $this->redirect('/' . $adminPath);
        }

        $_SESSION['login_error'] = 'Invalid username or password.';
        $this->redirect($loginUrl);
    }

    public function logout(): void
    {
        Auth::logout();
        $adminPath = trim((string) Config::get('admin_path', 'admin')) ?: 'admin';
        $this->redirect('/' . $adminPath . '/login');
    }
}
