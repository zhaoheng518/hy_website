<?php

namespace App\Core;

/**
 * Maps browser Accept-Language to a configured site language code.
 */
final class LanguageNegotiation
{
    /**
     * @param array<int, string> $supported
     */
    public static function pickFromAcceptLanguage(array $supported, string $fallback): string
    {
        $supported = array_values(array_filter(array_map('strval', $supported), static function ($s) {
            return $s !== '';
        }));
        if ($supported === []) {
            return 'en';
        }
        if (!in_array($fallback, $supported, true)) {
            $fallback = $supported[0];
        }

        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($header === '' || $header === '*') {
            return $fallback;
        }

        $candidates = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $tag = $part;
            $q = 1.0;
            if (preg_match('/^(.*?)\s*;\s*q\s*=\s*([\d.]+)\s*$/i', $part, $m)) {
                $tag = trim($m[1]);
                $q = (float) $m[2];
            }
            if ($tag !== '' && $tag !== '*') {
                $candidates[] = ['tag' => $tag, 'q' => $q];
            }
        }

        usort($candidates, static function ($a, $b) {
            return $b['q'] <=> $a['q'];
        });

        foreach ($candidates as $c) {
            $resolved = self::mapTagToSupported($c['tag'], $supported);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $fallback;
    }

    /**
     * @param array<int, string> $supported
     */
    public static function mapTagToSupported(string $tag, array $supported): ?string
    {
        $tag = strtolower(str_replace('_', '-', trim($tag)));
        if ($tag === '') {
            return null;
        }

        $aliases = [
            'zh' => 'cn', 'zh-cn' => 'cn', 'zh-hans' => 'cn', 'zh-hant' => 'cn', 'zh-tw' => 'cn', 'zh-hk' => 'cn',
            'en' => 'en', 'en-us' => 'en', 'en-gb' => 'en', 'en-au' => 'en',
            'es' => 'es', 'es-es' => 'es', 'es-mx' => 'es', 'es-ar' => 'es',
            'ru' => 'ru', 'ru-ru' => 'ru',
            'ar' => 'ar', 'ar-sa' => 'ar', 'ar-ae' => 'ar',
        ];

        if (in_array($tag, $supported, true)) {
            return $tag;
        }
        if (isset($aliases[$tag]) && in_array($aliases[$tag], $supported, true)) {
            return $aliases[$tag];
        }

        $primary = explode('-', $tag)[0];
        if (in_array($primary, $supported, true)) {
            return $primary;
        }
        if (isset($aliases[$primary]) && in_array($aliases[$primary], $supported, true)) {
            return $aliases[$primary];
        }

        return null;
    }
}
