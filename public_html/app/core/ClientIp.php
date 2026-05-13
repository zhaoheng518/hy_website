<?php

declare(strict_types=1);

namespace App\Core;

/**
 * 客户端 IP：优先 Cloudflare / 反向代理可信头，不优先信任 X-Forwarded-For（易被伪造）。
 */
final class ClientIp
{
    /**
     * 顺序：HTTP_CF_CONNECTING_IP → HTTP_X_REAL_IP → REMOTE_ADDR
     */
    public static function get(): string
    {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($candidates as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }
            $raw = (string) $_SERVER[$key];
            $ip = trim(explode(',', $raw, 2)[0]);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }
}
