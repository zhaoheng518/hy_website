<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\NewsletterRepository;
use App\Core\View;

class NewsletterController extends BaseController
{
    public function index(): void
    {
        $msg = $_SESSION['newsletter_message'] ?? '';
        $msgType = $_SESSION['newsletter_message_type'] ?? '';
        unset($_SESSION['newsletter_message'], $_SESSION['newsletter_message_type']);

        $seoHead = $this->seo->renderMeta('newsletter', '', [
            'title' => $this->t('newsletter_title'),
            'seo_title' => $this->t('newsletter_title'),
            'content_plain' => $this->t('newsletter_intro'),
        ]);

        $breadcrumbs = $this->seo->renderBreadcrumbs([
            ['name' => $this->t('home'), 'url' => View::langUrl($this->lang)],
            ['name' => $this->t('newsletter_title')],
        ]);

        $this->view->render('newsletter/index', [
            'seoHead' => $seoHead,
            'breadcrumbs' => $breadcrumbs,
            'h1' => $this->t('newsletter_title'),
            'intro' => $this->t('newsletter_intro'),
            'message' => $msg,
            'messageType' => $msgType,
        ]);
    }

    public function submit(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/' . $this->lang . '/newsletter');
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['newsletter_message'] = $this->t('csrf');
            $_SESSION['newsletter_message_type'] = 'error';
            $this->redirect('/' . $this->lang . '/newsletter');
        }

        if (!empty(trim($this->getPost('website_url', '')))) {
            $_SESSION['newsletter_message'] = $this->t('ok');
            $_SESSION['newsletter_message_type'] = 'success';
            $this->redirect('/' . $this->lang . '/newsletter');
        }

        $email = trim($this->getPost('email', ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['newsletter_message'] = $this->t('email_invalid');
            $_SESSION['newsletter_message_type'] = 'error';
            $this->redirect('/' . $this->lang . '/newsletter');
        }

        $np = $this->getPost('notify_product', '1') === '1';
        $nb = $this->getPost('notify_blog', '1') === '1';
        $ng = $this->getPost('notify_general', '1') === '1';

        $ok = NewsletterRepository::subscribe($email, $this->lang, [
            'notify_product' => $np,
            'notify_blog' => $nb,
            'notify_general' => $ng,
            'source' => 'manual',
        ]);

        $_SESSION['newsletter_message'] = $ok ? $this->t('ok') : $this->t('fail');
        $_SESSION['newsletter_message_type'] = $ok ? 'success' : 'error';
        $this->redirect('/' . $this->lang . '/newsletter');
    }

    public function unsubscribe(string $token = ''): void
    {
        $token = trim($token);
        $ok = NewsletterRepository::unsubscribeByToken($token);
        $canon = '/' . $this->lang . '/newsletter/unsubscribe/' . rawurlencode($token);
        $seoHead = $this->seo->renderMeta('newsletter', '', [
            'title' => $this->t('unsub_title'),
            'seo_title' => $this->t('unsub_title'),
            'content_plain' => $ok ? $this->t('unsub_ok') : $this->t('unsub_bad'),
            'canonical_rel' => $canon,
            'robots' => 'noindex, follow',
        ]);
        $this->view->render('newsletter/unsubscribe', [
            'seoHead' => $seoHead,
            'h1' => $this->t('unsub_title'),
            'ok' => $ok,
        ]);
    }

    private function t(string $key): string
    {
        $pack = [
            'en' => [
                'home' => 'Home',
                'newsletter_title' => 'Email newsletter',
                'newsletter_intro' => 'Get product and blog updates by email. You can unsubscribe anytime.',
                'ok' => 'You are subscribed. Thank you!',
                'fail' => 'Subscription could not be saved. Please try again later.',
                'email_invalid' => 'Please enter a valid email address.',
                'csrf' => 'Security token expired. Please try again.',
                'unsub_title' => 'Unsubscribe',
                'unsub_ok' => 'You have been unsubscribed.',
                'unsub_bad' => 'This unsubscribe link is invalid or already used.',
            ],
            'cn' => [
                'home' => '首页',
                'newsletter_title' => '邮件订阅',
                'newsletter_intro' => '订阅产品与博客更新邮件，可随时退订。',
                'ok' => '订阅成功，感谢！',
                'fail' => '订阅未保存，请稍后重试。',
                'email_invalid' => '请输入有效的邮箱地址。',
                'csrf' => '安全令牌已过期，请重试。',
                'unsub_title' => '退订',
                'unsub_ok' => '您已成功退订。',
                'unsub_bad' => '退订链接无效或已使用。',
            ],
            'es' => [
                'home' => 'Inicio',
                'newsletter_title' => 'Boletín por correo',
                'newsletter_intro' => 'Reciba novedades de productos y blog. Puede darse de baja en cualquier momento.',
                'ok' => '¡Suscripción realizada. Gracias!',
                'fail' => 'No se pudo guardar la suscripción. Inténtelo más tarde.',
                'email_invalid' => 'Introduzca un correo válido.',
                'csrf' => 'Token de seguridad caducado.',
                'unsub_title' => 'Cancelar suscripción',
                'unsub_ok' => 'Se ha cancelado la suscripción.',
                'unsub_bad' => 'El enlace no es válido o ya se utilizó.',
            ],
        ];
        $lang = $this->lang;
        $m = $pack[$lang] ?? $pack['en'];

        return $m[$key] ?? $pack['en'][$key] ?? $key;
    }
}
