<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use ZipArchive;

class AdminBackupController extends BaseController
{
    public function index(): void
    {
        Auth::requireCan('settings');

        $this->view->render('backup/index', [
            'csrfToken' => Auth::generateCsrfToken(),
            'adminUser' => Auth::user()['username'] ?? 'Admin',
            'activeMenu' => 'backup',
            'pageTitle' => '一键备份中心',
            'breadcrumbs' => [['label' => '备份中心', 'url' => '/admin/backup']],
            'error' => $_SESSION['backup_error'] ?? '',
            'success' => $_SESSION['backup_success'] ?? '',
            'zipReady' => class_exists(ZipArchive::class),
        ]);

        unset($_SESSION['backup_error'], $_SESSION['backup_success']);
    }

    public function download(): void
    {
        Auth::requireCan('settings');

        if (!$this->isPost()) {
            $this->redirect('/admin/backup');
        }

        if (!Auth::consumeCsrfToken($this->getPost('_csrf', ''))) {
            $_SESSION['backup_error'] = 'Token 无效，请刷新后重试。';
            $this->redirect('/admin/backup');
        }

        if (!class_exists(ZipArchive::class)) {
            $_SESSION['backup_error'] = '服务器缺少 ZipArchive 扩展，无法打包备份。';
            $this->redirect('/admin/backup');
        }

        $tmpDir = DATA_PATH . '/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $stamp = date('Ymd_His');
        $zipPath = $tmpDir . '/full_backup_' . $stamp . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $_SESSION['backup_error'] = '无法创建备份压缩包。';
            $this->redirect('/admin/backup');
        }

        try {
            $this->addDataJsonFiles($zip);
            $this->addUploadsDirectory($zip);
            $this->addDatabaseSchema($zip);
            $zip->addFromString('backup_meta.txt', "Generated at: " . date('Y-m-d H:i:s') . "\n");
            $zip->close();
        } catch (\Throwable $e) {
            $zip->close();
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            $_SESSION['backup_error'] = '备份失败：' . $e->getMessage();
            $this->redirect('/admin/backup');
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
        header('Content-Length: ' . (string) filesize($zipPath));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    private function addDataJsonFiles(ZipArchive $zip): void
    {
        $root = rtrim(DATA_PATH, '/');
        if (!is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            $fullPath = $file->getPathname();
            $local = 'app_data/' . ltrim(str_replace($root, '', $fullPath), '/');
            $zip->addFile($fullPath, $local);
        }
    }

    private function addUploadsDirectory(ZipArchive $zip): void
    {
        $root = rtrim(UPLOAD_PATH, '/');
        if (!is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $fullPath = $file->getPathname();
            $local = 'uploads/' . ltrim(str_replace($root, '', $fullPath), '/');
            $zip->addFile($fullPath, $local);
        }
    }

    private function addDatabaseSchema(ZipArchive $zip): void
    {
        try {
            $pdo = Database::getInstance()->getConnection();
            $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($dbName === '') {
                return;
            }

            $tablesStmt = $pdo->prepare(
                'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db ORDER BY TABLE_NAME'
            );
            $tablesStmt->execute([':db' => $dbName]);
            $tables = $tablesStmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];

            if (empty($tables)) {
                return;
            }

            $sql = "-- Schema backup for {$dbName}\n";
            $sql .= '-- Generated at ' . date('Y-m-d H:i:s') . "\n\n";

            foreach ($tables as $table) {
                $tableName = (string) $table;
                $row = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $tableName) . '`')->fetch(\PDO::FETCH_ASSOC);
                if (!isset($row['Create Table'])) {
                    continue;
                }
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= $row['Create Table'] . ";\n\n";
            }

            if (trim($sql) !== '') {
                $zip->addFromString('database_schema.sql', $sql);
            }
        } catch (\Throwable $e) {
            $zip->addFromString('database_schema_error.txt', 'Database schema export skipped: ' . $e->getMessage());
        }
    }
}
