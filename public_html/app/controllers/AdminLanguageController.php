<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\JsonStore;

class AdminLanguageController extends BaseController
{
    private array $supportedLangs;

    public function __construct(string $lang, bool $isAdmin = false)
    {
        parent::__construct($lang, $isAdmin);
        $this->supportedLangs = Config::get('supported_langs', ['en', 'cn', 'es']);
    }

    public function index(): void
    {
        Auth::requireCan('languages');

        $editLang = $this->getQuery('lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        $dataType = $this->getQuery('type', 'home');
        $validTypes = ['home', 'products', 'cases', 'blog', 'contact', 'seo'];
        if (!in_array($dataType, $validTypes, true)) {
            $dataType = 'home';
        }

        $store = JsonStore::langData($editLang, $dataType);
        $data = $store->read();

        $csrfToken = Auth::generateCsrfToken();

        $this->view->render('languages', [
            'editLang' => $editLang,
            'dataType' => $dataType,
            'langData' => $data,
            'supportedLangs' => $this->supportedLangs,
            'validTypes' => $validTypes,
            'csrfToken' => $csrfToken,
            'adminUser' => Auth::user(),
            'success' => $_SESSION['lang_success'] ?? '',
            'error' => $_SESSION['lang_error'] ?? '',
        ]);

        unset($_SESSION['lang_success'], $_SESSION['lang_error']);
    }

    public function save(): void
    {
        Auth::requireCan('languages');

        if (!$this->isPost()) {
            $this->redirect('/admin/languages');
        }

        $csrf = $this->getPost('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $_SESSION['lang_error'] = 'Invalid security token.';
            $this->redirect('/admin/languages');
        }

        $editLang = $this->getPost('edit_lang', 'en');
        if (!in_array($editLang, $this->supportedLangs, true)) {
            $editLang = 'en';
        }

        $dataType = $this->getPost('data_type', 'home');
        $validTypes = ['home', 'products', 'cases', 'blog', 'contact', 'seo'];
        if (!in_array($dataType, $validTypes, true)) {
            $dataType = 'home';
        }

        $rawData = $this->getPost('json_data', '');
        $data = json_decode($rawData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $_SESSION['lang_error'] = 'Invalid JSON data: ' . json_last_error_msg();
            $this->redirect('/admin/languages?lang=' . $editLang . '&type=' . $dataType);
        }

        $store = JsonStore::langData($editLang, $dataType);
        $store->write($data);

        $_SESSION['lang_success'] = ucfirst($editLang) . ' ' . $dataType . ' data saved.';
        $this->redirect('/admin/languages?lang=' . $editLang . '&type=' . $dataType);
    }
}
