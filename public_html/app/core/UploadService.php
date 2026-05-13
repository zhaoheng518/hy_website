<?php

namespace App\Core;

/**
 * Centralized uploads: validation, storage under uploads/{bucket}/, delete, replace.
 * Legacy files directly under uploads/ (no subfolder) remain valid URLs; delete resolves both.
 */
final class UploadService
{
    public const BUCKET_IMAGES = 'images';
    public const BUCKET_DATASHEETS = 'datasheets';
    public const BUCKET_DOWNLOADS = 'downloads';

    public const MODE_STRICT_IMAGE = 'strict_image';
    public const MODE_PDF = 'pdf';
    public const MODE_ADMIN_MIXED = 'admin_mixed';

    private const BUCKET_SUBDIRS = [
        self::BUCKET_IMAGES => 'images',
        self::BUCKET_DATASHEETS => 'datasheets',
        self::BUCKET_DOWNLOADS => 'downloads',
    ];

    /** Extensions allowed for MODE_ADMIN_MIXED per bucket. SVG removed (XSS risk). */
    private const ADMIN_MIXED_EXTENSIONS = [
        self::BUCKET_IMAGES     => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        self::BUCKET_DATASHEETS => ['pdf', 'doc', 'docx', 'zip', 'jpg', 'jpeg', 'png', 'webp', 'gif'],
        self::BUCKET_DOWNLOADS  => ['pdf', 'doc', 'docx', 'zip', 'jpg', 'jpeg', 'png', 'webp', 'gif'],
    ];

    /**
     * Extensions that are NEVER allowed regardless of mode or bucket.
     * Covers: PHP variants, server-side scripts, HTML/JS (XSS), config override, archives with code.
     * This is an absolute deny list checked before any allowlist.
     */
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'pl', 'py', 'rb', 'cgi', 'sh', 'bash', 'ksh', 'zsh',
        'asp', 'aspx', 'jsx', 'ts', 'tsx',
        'js', 'mjs', 'cjs',
        'html', 'htm', 'shtml', 'xhtml',
        'svg', 'xml',
        'htaccess', 'htpasswd',
        'exe', 'dll', 'so', 'bin', 'com', 'bat', 'cmd', 'vbs', 'wsf', 'jar',
        'jsp', 'jspx', 'cfm', 'cfml',
    ];

    public static function getMaxImageBytes(): int
    {
        $v = Config::get('upload_max_size', 2097152);

        return max(1024, (int) $v);
    }

    public static function getDefaultPdfMaxBytes(): int
    {
        return 10485760;
    }

    /** Larger cap for admin file manager (non-image buckets); legacy had no strict PHP-side limit. */
    public static function getMaxAdminDocumentBytes(): int
    {
        return 52428800;
    }

    public static function getPhpUploadErrorMessage(int $code): string
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

    /**
     * Normalize to relative path starting with uploads/ (no leading slash).
     */
    public static function normalizeWebPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'uploads/')) {
            return $path;
        }
        if ($path !== '' && strpos($path, '/') === false) {
            return 'uploads/' . $path;
        }

        return $path;
    }

    /**
     * Absolute filesystem path for an existing file under uploads/, or null if unsafe / missing.
     */
    public static function absoluteFromWebPath(string $webPath): ?string
    {
        $rel = self::normalizeWebPath($webPath);
        if ($rel === '' || !str_starts_with($rel, 'uploads/')) {
            return null;
        }
        $abs = ROOT_PATH . '/' . $rel;
        if (!is_file($abs)) {
            return null;
        }
        $uploadRoot = realpath(UPLOAD_PATH);
        $real = realpath($abs);
        if ($uploadRoot === false || $real === false) {
            return null;
        }
        $normRoot = str_replace('\\', '/', $uploadRoot);
        $normFile = str_replace('\\', '/', $real);

        if ($normFile === $normRoot || str_starts_with($normFile, $normRoot . '/')) {
            return $real;
        }

        return null;
    }

    /**
     * Whether an absolute path lies under UPLOAD_PATH.
     */
    public static function isUnderUploadRoot(string $absolutePath): bool
    {
        $uploadRoot = realpath(UPLOAD_PATH);
        if ($uploadRoot === false) {
            return false;
        }
        $targetReal = realpath($absolutePath);
        if ($targetReal === false) {
            return false;
        }
        $normTarget = str_replace('\\', '/', $targetReal);
        $normRoot = str_replace('\\', '/', $uploadRoot);

        return $normTarget === $normRoot || str_starts_with($normTarget, $normRoot . '/');
    }

    /**
     * Delete a file given web path (uploads/... or /uploads/...). Removes JPEG/PNG sidecar .webp if present.
     */
    public static function deleteWebPath(?string $webPath): bool
    {
        if ($webPath === null || trim($webPath) === '') {
            return false;
        }
        $rel = self::normalizeWebPath($webPath);
        if ($rel === '' || !str_starts_with($rel, 'uploads/')) {
            return false;
        }
        $abs = ROOT_PATH . '/' . $rel;
        if (!self::isUnderUploadRoot($abs) || !is_file($abs)) {
            return false;
        }
        $ok = @unlink($abs);
        $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $abs);
        if ($webp !== $abs && is_file($webp) && self::isUnderUploadRoot($webp)) {
            @unlink($webp);
        }

        return $ok;
    }

    /**
     * Replace: after a successful upload, delete the old web path (if different from new).
     */
    public static function deleteIfReplaced(?string $oldWebPath, string $newWebPath): void
    {
        if ($oldWebPath === null || trim($oldWebPath) === '') {
            return;
        }
        $old = self::normalizeWebPath($oldWebPath);
        $new = self::normalizeWebPath($newWebPath);
        if ($old === '' || $old === $new) {
            return;
        }
        self::deleteWebPath($old);
    }

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     * @param array{
     *   bucket: string,
     *   mode: string,
     *   max_bytes?: int,
     *   replace_web_path?: ?string,
     *   use_slug_filename?: bool,
     *   datasheet_style_name?: bool,
     *   custom_basename?: string,
     * } $opts
     * @return array{ok: bool, error?: string, url?: string, web_path?: string, basename?: string}
     */
    public static function process(array $file, array $opts): array
    {
        $bucket = $opts['bucket'] ?? self::BUCKET_IMAGES;
        $mode = $opts['mode'] ?? self::MODE_STRICT_IMAGE;
        if (!isset(self::BUCKET_SUBDIRS[$bucket])) {
            return ['ok' => false, 'error' => 'Invalid upload bucket.'];
        }

        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => self::getPhpUploadErrorMessage($err)];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Invalid upload file.'];
        }

        $origName = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $size = (int) ($file['size'] ?? 0);

        // --- Absolute blocklist: reject before any allowlist check ---
        if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            $msg = 'File type ".' . $ext . '" is not permitted.';
            error_log('[UploadService] BLOCKED extension "' . $ext . '" from file "' . $origName . '" bucket=' . $bucket);
            return ['ok' => false, 'error' => $msg];
        }

        // --- Double-extension bypass detection ---
        // Reject filenames where any dot-separated segment is a blocked extension
        // e.g. "shell.php.jpg" or "page.html.png"
        $nameParts = explode('.', strtolower($origName));
        // Skip the last element (the ext we already checked) and the first (base name)
        if (count($nameParts) > 2) {
            foreach (array_slice($nameParts, 1, -1) as $midPart) {
                if (in_array($midPart, self::BLOCKED_EXTENSIONS, true)) {
                    $msg = 'Filename contains a disallowed extension segment ".' . $midPart . '".';
                    error_log('[UploadService] DOUBLE-EXT blocked "' . $origName . '" bucket=' . $bucket);
                    return ['ok' => false, 'error' => $msg];
                }
            }
        }

        $maxBytes = (int) ($opts['max_bytes'] ?? 0);
        if ($maxBytes <= 0) {
            $maxBytes = $mode === self::MODE_PDF ? self::getDefaultPdfMaxBytes() : self::getMaxImageBytes();
        }
        if ($size <= 0 || $size > $maxBytes) {
            $msg = 'File size exceeds the allowed limit.';
            error_log('[UploadService] SIZE exceeded ' . $size . '>' . $maxBytes . ' file="' . $origName . '" bucket=' . $bucket);
            return ['ok' => false, 'error' => $msg];
        }

        if ($mode === self::MODE_STRICT_IMAGE) {
            $v = self::validateStrictImage($tmp, $ext);
            if ($v !== null) {
                error_log('[UploadService] STRICT_IMAGE validation failed: ' . $v . ' file="' . $origName . '"');
                return ['ok' => false, 'error' => $v];
            }
        } elseif ($mode === self::MODE_PDF) {
            $v = self::validatePdf($tmp, $ext);
            if ($v !== null) {
                error_log('[UploadService] PDF validation failed: ' . $v . ' file="' . $origName . '"');
                return ['ok' => false, 'error' => $v];
            }
        } elseif ($mode === self::MODE_ADMIN_MIXED) {
            $allowed = self::ADMIN_MIXED_EXTENSIONS[$bucket] ?? self::ADMIN_MIXED_EXTENSIONS[self::BUCKET_IMAGES];
            if (!in_array($ext, $allowed, true)) {
                error_log('[UploadService] ADMIN_MIXED ext not allowed: ".' . $ext . '" bucket=' . $bucket . ' file="' . $origName . '"');
                return ['ok' => false, 'error' => 'File type not allowed for this folder.'];
            }
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $v = self::validateStrictImage($tmp, $ext);
                if ($v !== null) {
                    error_log('[UploadService] ADMIN_MIXED image validation failed: ' . $v . ' file="' . $origName . '"');
                    return ['ok' => false, 'error' => $v];
                }
            } elseif ($ext === 'gif') {
                $info = @getimagesize($tmp);
                if ($info === false || ($info['mime'] ?? '') !== 'image/gif') {
                    error_log('[UploadService] ADMIN_MIXED invalid GIF file="' . $origName . '"');
                    return ['ok' => false, 'error' => 'Invalid GIF image.'];
                }
            } elseif ($ext === 'pdf') {
                $v = self::validatePdf($tmp, $ext);
                if ($v !== null) {
                    error_log('[UploadService] ADMIN_MIXED PDF validation failed: ' . $v . ' file="' . $origName . '"');
                    return ['ok' => false, 'error' => $v];
                }
            } elseif (in_array($ext, ['zip', 'docx'], true)) {
                $v = self::validateZipMagic($tmp);
                if ($v !== null) {
                    error_log('[UploadService] ADMIN_MIXED ZIP/DOCX magic failed: ' . $v . ' file="' . $origName . '"');
                    return ['ok' => false, 'error' => $v];
                }
            } elseif ($ext === 'doc') {
                $v = self::validateOle2Magic($tmp);
                if ($v !== null) {
                    error_log('[UploadService] ADMIN_MIXED DOC OLE2 magic failed: ' . $v . ' file="' . $origName . '"');
                    return ['ok' => false, 'error' => $v];
                }
            }
        } else {
            return ['ok' => false, 'error' => 'Invalid upload mode.'];
        }

        $subdir = self::BUCKET_SUBDIRS[$bucket];
        $dir = rtrim(UPLOAD_PATH, '/') . '/' . $subdir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!empty($opts['custom_basename'])) {
            $basename = basename((string) $opts['custom_basename']);
        } elseif (!empty($opts['datasheet_style_name']) && $mode === self::MODE_PDF) {
            $basename = 'datasheet_' . date('YmdHis') . '_' . substr(uniqid('', true), -6) . '.pdf';
        } elseif (!empty($opts['use_slug_filename'])) {
            $basename = View::slugify(pathinfo($origName, PATHINFO_FILENAME)) . '_' . substr(uniqid('', true), -6) . '.' . $ext;
        } else {
            $basename = 'img_' . uniqid('', true) . '.' . $ext;
        }

        $basename = basename($basename);
        if ($basename === '' || $basename === '.' || $basename === '..') {
            return ['ok' => false, 'error' => 'Invalid file name.'];
        }

        $webPrefix = '/uploads/' . $subdir;
        $useProcessor = $bucket === self::BUCKET_IMAGES
            && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)
            && ($mode === self::MODE_STRICT_IMAGE || $mode === self::MODE_ADMIN_MIXED);

        try {
            if ($useProcessor) {
                $result = ImageProcessor::processUploadedImage($tmp, $dir, $webPrefix, $basename);
                $publicUrl = $result['url'];
                $pathPart = parse_url($result['url'], PHP_URL_PATH);
                $basename = $pathPart !== null && $pathPart !== ''
                    ? basename($pathPart)
                    : basename($result['path'] ?? $basename);
            } else {
                $dest = $dir . '/' . $basename;
                if (!move_uploaded_file($tmp, $dest)) {
                    error_log('[UploadService] move_uploaded_file failed dest="' . $dest . '" file="' . $origName . '"');
                    return ['ok' => false, 'error' => 'Failed to save the uploaded file.'];
                }
                @chmod($dest, 0644);
                $publicUrl = $webPrefix . '/' . $basename;
            }
        } catch (\Throwable $e) {
            if (!empty($useProcessor) && is_uploaded_file($tmp)) {
                $dest = $dir . '/' . $basename;
                if (move_uploaded_file($tmp, $dest)) {
                    @chmod($dest, 0644);
                    $publicUrl = $webPrefix . '/' . $basename;
                } else {
                    return ['ok' => false, 'error' => $e->getMessage() ?: 'Image processing failed.'];
                }
            } else {
                return ['ok' => false, 'error' => $e->getMessage() ?: 'Upload failed.'];
            }
        }

        $webPath = ltrim($publicUrl, '/');
        if (!empty($opts['replace_web_path'])) {
            self::deleteIfReplaced($opts['replace_web_path'], $webPath);
        }

        return [
            'ok' => true,
            'url' => $publicUrl,
            'web_path' => $webPath,
            'basename' => basename($webPath),
        ];
    }

    private static function validateStrictImage(string $tmp, string $ext): ?string
    {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedTypes, true)) {
            return 'Only JPG, PNG, and WebP images are allowed.';
        }
        $imageInfo = @getimagesize($tmp);
        if ($imageInfo === false) {
            return 'Uploaded file is not a valid image.';
        }
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($imageInfo['mime'] ?? '', $allowedMimes, true)) {
            return 'Invalid image MIME type.';
        }

        return null;
    }

    private static function validatePdf(string $tmp, string $ext): ?string
    {
        if ($ext !== 'pdf') {
            return 'Only PDF files are allowed.';
        }
        $hdr = @file_get_contents($tmp, false, null, 0, 5);
        if ($hdr !== '%PDF-') {
            return 'Invalid PDF file.';
        }

        return null;
    }

    /**
     * Verify ZIP magic bytes (PK\x03\x04) — covers .zip and .docx.
     */
    private static function validateZipMagic(string $tmp): ?string
    {
        $hdr = @file_get_contents($tmp, false, null, 0, 4);
        if ($hdr === false || strlen($hdr) < 4 || substr($hdr, 0, 2) !== 'PK') {
            return 'Invalid ZIP/DOCX file (bad magic bytes).';
        }

        return null;
    }

    /**
     * Verify OLE2 Compound Document magic bytes — covers legacy .doc files.
     */
    private static function validateOle2Magic(string $tmp): ?string
    {
        $magic = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
        $hdr   = @file_get_contents($tmp, false, null, 0, 8);
        if ($hdr === false || strlen($hdr) < 8 || $hdr !== $magic) {
            return 'Invalid DOC file (bad magic bytes).';
        }

        return null;
    }

    /**
     * Delete by basename: tries bucket subdirs then legacy flat uploads root.
     */
    public static function deleteByBasenameAnyBucket(string $basename): bool
    {
        $basename = basename($basename);
        if ($basename === '' || strpos($basename, '..') !== false) {
            return false;
        }
        $baseDir = rtrim(UPLOAD_PATH, '/');
        $candidates = [
            $baseDir . '/' . self::BUCKET_SUBDIRS[self::BUCKET_IMAGES] . '/' . $basename,
            $baseDir . '/' . self::BUCKET_SUBDIRS[self::BUCKET_DATASHEETS] . '/' . $basename,
            $baseDir . '/' . self::BUCKET_SUBDIRS[self::BUCKET_DOWNLOADS] . '/' . $basename,
            $baseDir . '/' . $basename,
        ];
        $uploadNorm = str_replace('\\', '/', $baseDir);
        foreach ($candidates as $abs) {
            if (!is_file($abs) || !self::isUnderUploadRoot($abs)) {
                continue;
            }
            $absNorm = str_replace('\\', '/', $abs);
            if (!str_starts_with($absNorm, $uploadNorm)) {
                continue;
            }
            $rel = 'uploads/' . ltrim(substr($absNorm, strlen($uploadNorm)), '/');

            return self::deleteWebPath($rel);
        }

        return false;
    }
}
