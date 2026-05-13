<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\UploadService;

class AdminMediaController extends BaseController
{
    private array $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
    private int $maxSize = 2097152;

    public function index(): void
    {
        Auth::requireCan('media');

        $files = $this->listFiles();

        $this->view->render('media', [
            'files' => $files,
            'adminUser' => Auth::user(),
            'maxSize' => $this->formatBytes($this->maxSize),
            'error' => $_SESSION['media_error'] ?? '',
            'success' => $_SESSION['media_success'] ?? '',
        ]);

        unset($_SESSION['media_error'], $_SESSION['media_success']);
    }

    public function upload(): void
    {
        Auth::requireCan('media');

        if (!$this->isPost()) {
            $this->redirect('/admin/media');
        }

        $csrf = $this->getPost('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $_SESSION['media_error'] = 'Invalid security token.';
            $this->redirect('/admin/media');
        }

        if (!isset($_FILES['file'])) {
            $_SESSION['media_error'] = UploadService::getPhpUploadErrorMessage(UPLOAD_ERR_NO_FILE);
            $this->redirect('/admin/media');
        }

        $replaceRaw = trim($this->getPost('replace', ''));
        $replaceWeb = $replaceRaw !== '' ? UploadService::normalizeWebPath($replaceRaw) : null;

        $result = UploadService::process($_FILES['file'], [
            'bucket' => UploadService::BUCKET_IMAGES,
            'mode' => UploadService::MODE_STRICT_IMAGE,
            'max_bytes' => $this->maxSize,
            'replace_web_path' => $replaceWeb,
        ]);

        if (!$result['ok']) {
            $_SESSION['media_error'] = $result['error'] ?? 'Upload failed.';
            $this->redirect('/admin/media');
        }

        $newName = $result['basename'] ?? '';
        $_SESSION['media_success'] = 'File uploaded successfully: ' . $newName;
        $this->redirect('/admin/media');
    }

    public function delete(): void
    {
        Auth::requireCan('media');

        if (!$this->isPost()) {
            $this->redirect('/admin/media');
        }

        $csrf = $this->getPost('_csrf', '');
        if (!Auth::consumeCsrfToken($csrf)) {
            $_SESSION['media_error'] = 'Invalid security token.';
            $this->redirect('/admin/media');
        }

        $filename = trim($this->getPost('filename', ''));

        if (empty($filename)) {
            $_SESSION['media_error'] = 'No file specified.';
            $this->redirect('/admin/media');
        }

        $filename = basename($filename);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes, true)) {
            $_SESSION['media_error'] = 'Cannot delete this file type.';
            $this->redirect('/admin/media');
        }

        if (UploadService::deleteByBasenameAnyBucket($filename)) {
            $_SESSION['media_success'] = 'File deleted: ' . $filename;
        } else {
            $_SESSION['media_error'] = 'File not found or could not be deleted.';
        }

        $this->redirect('/admin/media');
    }

    private function listFiles(): array
    {
        $files = [];

        if (!is_dir(UPLOAD_PATH)) {
            return $files;
        }

        $items = scandir(UPLOAD_PATH);
        if ($items === false) {
            return $files;
        }

        $seen = [];
        $scanDirs = [
            UPLOAD_PATH . '/' . UploadService::BUCKET_IMAGES,
            UPLOAD_PATH,
        ];
        foreach ($scanDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $subItems = scandir($dir);
            if ($subItems === false) {
                continue;
            }
            foreach ($subItems as $item) {
                if ($item === '.' || $item === '..' || $item === '.htaccess') {
                    continue;
                }
                $filePath = $dir . '/' . $item;
                if (!is_file($filePath)) {
                    continue;
                }
                $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (!in_array($extension, $this->allowedTypes, true)) {
                    continue;
                }
                if (isset($seen[$item])) {
                    continue;
                }
                $seen[$item] = true;
                $imageInfo = @getimagesize($filePath);
                $fileSize = filesize($filePath);
                $base = rtrim(str_replace('\\', '/', UPLOAD_PATH), '/');
                $rel = ltrim(substr(str_replace('\\', '/', $filePath), strlen($base)), '/');
                $url = '/uploads/' . $rel;

                $files[] = [
                    'name' => $item,
                    'url' => $url,
                    'size' => $this->formatBytes($fileSize ?: 0),
                    'dimensions' => $imageInfo ? $imageInfo[0] . 'x' . $imageInfo[1] : 'N/A',
                    'modified' => date('Y-m-d H:i:s', filemtime($filePath) ?: time()),
                ];
            }
        }

        usort($files, function ($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });

        return $files;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    private function getUploadErrorMessage(int $code): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the server maximum size.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the form maximum size.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
        ];

        return $messages[$code] ?? 'Unknown upload error.';
    }
}
