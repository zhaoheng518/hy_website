<?php

namespace App\Core;

class Config
{
    private static ?array $config = null;

    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Configuration file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Unable to read configuration file: {$path}");
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                "Invalid JSON in configuration file: " . json_last_error_msg()
            );
        }

        self::$config = $data;
    }

    public static function get(string $key, $default = null)
    {
        if (self::$config === null) {
            return $default;
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function set(string $key, $value): void
    {
        if (self::$config === null) {
            self::$config = [];
        }

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    public static function all(): array
    {
        return self::$config ?? [];
    }

    public static function reload(string $path): void
    {
        self::$config = null;
        self::load($path);
    }
}
