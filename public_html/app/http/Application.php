<?php

namespace App\Http;

use App\Core\Router;

/**
 * Thin HTTP front: canonical host / TLS, then router dispatch (light MVC entry).
 */
final class Application
{
    public static function serveHttp(): void
    {
        self::redirectCanonicalHostIfNeeded();
        (new Router())->dispatch();
    }

    private static function redirectCanonicalHostIfNeeded(): void
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $needRedirect = false;
        $redirectUrl = '';

        if (strncmp($host, 'www.', 4) === 0) {
            $needRedirect = true;
            $redirectUrl = 'https://' . substr($host, 4) . $uri;
        } elseif (!self::isHttps() && !self::isLocalDevHost($host)) {
            $needRedirect = true;
            $redirectUrl = 'https://' . $host . $uri;
        }

        if ($needRedirect && $redirectUrl !== '') {
            header('Location: ' . $redirectUrl, true, 301);
            exit;
        }
    }

    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            return true;
        }
        if (($SERVER_PORT = $_SERVER['SERVER_PORT'] ?? '') !== '' && (string) $SERVER_PORT === '443') {
            return true;
        }

        return false;
    }

    /**
     * Skip HTTPS upgrade for local dev. HTTP_HOST often includes a port (e.g. localhost:8888),
     * which must not be compared to the bare string "localhost".
     */
    private static function isLocalDevHost(string $host): bool
    {
        $name = self::hostNameWithoutPort($host);
        $name = strtolower($name);

        return $name === 'localhost'
            || $name === '127.0.0.1'
            || $name === '::1'
            || (strlen($name) >= 10 && substr($name, -10) === '.localhost');
    }

    private static function hostNameWithoutPort(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            return '';
        }
        // [::1] or [::1]:8888
        if ($host[0] === '[') {
            $end = strpos($host, ']');
            if ($end !== false) {
                return substr($host, 1, $end - 1);
            }
        }
        // hostname:port (IPv4 hostnames only)
        $pos = strrpos($host, ':');
        if ($pos !== false) {
            $tail = substr($host, $pos + 1);
            if ($tail !== '' && ctype_digit($tail)) {
                return substr($host, 0, $pos);
            }
        }

        return $host;
    }
}
