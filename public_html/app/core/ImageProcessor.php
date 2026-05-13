<?php

namespace App\Core;

/**
 * Resize / compress JPEG/PNG uploads and optionally emit WebP beside the file.
 */
final class ImageProcessor
{
    public const DEFAULT_MAX_WIDTH = 1920;
    public const DEFAULT_JPEG_QUALITY = 82;

    /**
     * @return array{path:string,url:string,webp_path:?string,webp_url:?string}
     */
    public static function processUploadedImage(
        string $tmpPath,
        string $publicDir,
        string $webPrefix,
        string $baseName,
        int $maxWidth = self::DEFAULT_MAX_WIDTH,
        int $jpegQuality = self::DEFAULT_JPEG_QUALITY
    ): array {
        $jpegQuality = max(50, min(95, $jpegQuality));
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }

        $info = @getimagesize($tmpPath);
        if ($info === false) {
            throw new \RuntimeException('Invalid image.');
        }

        $mime = $info['mime'] ?? '';
        $src = self::createImageResource($tmpPath, $mime);
        if ($src === null) {
            throw new \RuntimeException('Unsupported image type.');
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $newW = $w;
        $newH = $h;
        if ($w > $maxWidth) {
            $newW = $maxWidth;
            $newH = (int) round($h * ($maxWidth / $w));
        }

        $dst = imagecreatetruecolor($newW, $newH);
        if ($dst === false) {
            imagedestroy($src);
            throw new \RuntimeException('Image resize failed.');
        }

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $ext = 'jpg';
        if ($mime === 'image/png') {
            $ext = 'png';
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            $ext = 'webp';
        }

        $fileName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $baseName);
        if ($fileName === '') {
            $fileName = uniqid('img_', true);
        }
        if (!preg_match('/\.(jpe?g|png|webp)$/i', $fileName)) {
            $fileName .= '.' . $ext;
        }

        $destPath = rtrim($publicDir, '/') . '/' . $fileName;
        self::writeImage($dst, $destPath, $mime, $jpegQuality);

        imagedestroy($dst);
        imagedestroy($src);
        @chmod($destPath, 0644);

        $rel = rtrim($webPrefix, '/') . '/' . $fileName;
        $webpPath = null;
        $webpRel = null;

        if (function_exists('imagewebp')) {
            $src2 = self::createImageResource($destPath, mime_content_type($destPath) ?: $mime);
            if ($src2 !== null) {
                $w2 = imagesx($src2);
                $h2 = imagesy($src2);
                $dw = imagecreatetruecolor($w2, $h2);
                if ($dw !== false) {
                    imagealphablending($dw, false);
                    imagesavealpha($dw, true);
                    imagecopy($dw, $src2, 0, 0, 0, 0, $w2, $h2);
                    $webpName = preg_replace('/\.(jpe?g|png)$/i', '.webp', $fileName);
                    if ($webpName !== $fileName) {
                        $webpPath = rtrim($publicDir, '/') . '/' . $webpName;
                        imagewebp($dw, $webpPath, 82);
                        @chmod($webpPath, 0644);
                        $webpRel = rtrim($webPrefix, '/') . '/' . $webpName;
                    }
                    imagedestroy($dw);
                }
                imagedestroy($src2);
            }
        }

        return [
            'path' => $destPath,
            'url' => $rel,
            'webp_path' => $webpPath,
            'webp_url' => $webpRel,
        ];
    }

    /** @return resource|\GdImage|null */
    private static function createImageResource(string $path, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
                $im = @imagecreatefromjpeg($path);
                return $im !== false ? $im : null;
            case 'image/png':
                $im = @imagecreatefrompng($path);
                return $im !== false ? $im : null;
            case 'image/webp':
                if (!function_exists('imagecreatefromwebp')) {
                    return null;
                }
                $im = @imagecreatefromwebp($path);
                return $im !== false ? $im : null;
            default:
                return null;
        }
    }

    /** @param resource|\GdImage $im */
    private static function writeImage($im, string $dest, string $mime, int $jpegQuality): void
    {
        $ok = false;
        if ($mime === 'image/png') {
            $ok = imagepng($im, $dest, 6);
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            $ok = imagewebp($im, $dest, 82);
        } else {
            $ok = imagejpeg($im, $dest, $jpegQuality);
        }
        if (!$ok) {
            throw new \RuntimeException('Failed to write image.');
        }
    }
}
