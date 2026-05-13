<?php

namespace App\Core;

class JsonStore
{
    private string $filePath;
    private static int $lockTimeout = 5;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;

        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function read(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $handle = fopen($this->filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open file for reading: {$this->filePath}");
        }

        $locked = flock($handle, LOCK_SH);
        if (!$locked) {
            fclose($handle);
            throw new \RuntimeException("Unable to acquire shared lock: {$this->filePath}");
        }

        $content = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            if ($chunk === false) {
                flock($handle, LOCK_UN);
                fclose($handle);
                throw new \RuntimeException("Error reading file: {$this->filePath}");
            }
            $content .= $chunk;
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        if (trim($content) === '') {
            return [];
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                "JSON decode error in {$this->filePath}: " . json_last_error_msg()
            );
        }

        return $data ?? [];
    }

    public function write(array $data): bool
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException("JSON encode error: " . json_last_error_msg());
        }

        $tempFile = $this->filePath . '.tmp.' . uniqid('', true);

        $written = file_put_contents($tempFile, $json, LOCK_EX);
        if ($written === false) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            throw new \RuntimeException("Unable to write temporary file: {$tempFile}");
        }

        if (!rename($tempFile, $this->filePath)) {
            @unlink($tempFile);
            throw new \RuntimeException(
                "Unable to rename temp file to: {$this->filePath}"
            );
        }

        return true;
    }

    public function update(callable $callback): bool
    {
        $handle = null;
        $locked = false;

        try {
            $handle = fopen($this->filePath, 'c+');
            if ($handle === false) {
                throw new \RuntimeException("Unable to open file: {$this->filePath}");
            }

            $startTime = microtime(true);
            while (microtime(true) - $startTime < self::$lockTimeout) {
                $locked = flock($handle, LOCK_EX | LOCK_NB);
                if ($locked) {
                    break;
                }
                usleep(50000);
            }

            if (!$locked) {
                throw new \RuntimeException(
                    "Unable to acquire exclusive lock within timeout: {$this->filePath}"
                );
            }

            $content = '';
            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk !== false) {
                    $content .= $chunk;
                }
            }

            $currentData = [];
            if (trim($content) !== '') {
                $currentData = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException(
                        "JSON decode error in {$this->filePath}: " . json_last_error_msg()
                    );
                }
                $currentData = $currentData ?? [];
            }

            $newData = $callback($currentData);

            $json = json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($json === false) {
                throw new \RuntimeException('JSON encode error: ' . json_last_error_msg());
            }

            ftruncate($handle, 0);
            rewind($handle);

            $written = fwrite($handle, $json);
            if ($written === false) {
                throw new \RuntimeException("Unable to write file: {$this->filePath}");
            }

            fflush($handle);

            return true;
        } catch (\Throwable $e) {
            error_log('[JsonStore] update failed: ' . $e->getMessage() . ' | ' . $this->filePath);

            return false;
        } finally {
            if (is_resource($handle)) {
                if ($locked) {
                    flock($handle, LOCK_UN);
                }
                fclose($handle);
            }
        }
    }

    public function exists(): bool
    {
        return file_exists($this->filePath);
    }

    public function delete(): bool
    {
        if (!$this->exists()) {
            return true;
        }
        return unlink($this->filePath);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public static function langData(string $lang, string $type): self
    {
        $path = DATA_PATH . '/' . $lang . '/' . $type . '.json';
        return new self($path);
    }

    public static function globalData(string $name): self
    {
        $path = DATA_PATH . '/' . $name . '.json';
        return new self($path);
    }

    public static function setLockTimeout(int $seconds): void
    {
        self::$lockTimeout = max(1, $seconds);
    }
}
