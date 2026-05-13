<?php

namespace App\Core;

/**
 * IP reputation for inquiry spam prevention via IPQualityScore Proxy & VPN Detection API.
 *
 * @see https://www.ipqualityscore.com/documentation/proxy-detection-api/overview
 */
final class SpamProtection
{
    private const API_BASE = 'https://www.ipqualityscore.com/api/json/ip/';
    private const HTTP_TIMEOUT = 4;
    private const LOG_BASENAME = '.inquiry_ipqs.log';

    /**
     * When API key unset: always allowed (no external call).
     * When IP is private/reserved: allowed without lookup.
     * On API/network failure: allowed (fail-open) with error_log.
     *
     * @return array{
     *   allowed: bool,
     *   skipped?: bool,
     *   reason?: string,
     *   fraud_score?: int,
     *   risk_score?: int,
     *   proxy?: bool,
     *   vpn?: bool,
     *   tor?: bool,
     *   bot_status?: bool,
     *   request_id?: string
     * }
     */
    public static function evaluateInquiryClient(string $ip, ?string $userAgent = null, ?string $acceptLanguage = null): array
    {
        $ip = trim($ip);
        if ($ip === '') {
            $ip = '0.0.0.0';
        }

        $apiKey = trim((string) Config::get('ipqs_api_key', ''));
        if ($apiKey === '') {
            return ['allowed' => true, 'skipped' => true];
        }

        if (!self::isPublicRoutableIp($ip)) {
            return ['allowed' => true, 'skipped' => true];
        }

        $payload = self::fetchIpqs($apiKey, $ip, $userAgent ?? '', $acceptLanguage ?? '');
        if ($payload === null) {
            return ['allowed' => true, 'skipped' => true];
        }

        if (empty($payload['success'])) {
            $msg = (string) ($payload['message'] ?? 'IPQS error');
            error_log('[SpamProtection] IPQS success=false: ' . $msg);

            return ['allowed' => true, 'skipped' => true];
        }

        $high = self::classifyHighRisk($payload);
        if ($high['block']) {
            self::logRejection($ip, $high['reason'], $payload);
        }

        $fraud = (int) round((float) ($payload['fraud_score'] ?? 0));
        $risk = (int) round((float) ($payload['risk_score'] ?? 0));

        return [
            'allowed' => !$high['block'],
            'reason' => $high['reason'],
            'fraud_score' => $fraud,
            'risk_score' => $risk,
            'proxy' => !empty($payload['proxy']),
            'vpn' => !empty($payload['vpn']) || !empty($payload['active_vpn']),
            'tor' => !empty($payload['tor']) || !empty($payload['active_tor']),
            'bot_status' => !empty($payload['bot_status']),
            'request_id' => (string) ($payload['request_id'] ?? ''),
        ];
    }

    private static function isPublicRoutableIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @param array<string, mixed> $d
     * @return array{block: bool, reason: string}
     */
    private static function classifyHighRisk(array $d): array
    {
        $fraud = (int) round((float) ($d['fraud_score'] ?? 0));
        $risk = (int) round((float) ($d['risk_score'] ?? 0));
        $score = max($fraud, $risk);

        $blockAt = (int) Config::get('ipqs_fraud_block_at', 90);
        if ($blockAt < 0) {
            $blockAt = 90;
        }
        if ($blockAt > 100) {
            $blockAt = 100;
        }

        if ($score >= $blockAt) {
            return ['block' => true, 'reason' => 'abuse_score'];
        }

        if (!empty($d['tor']) || !empty($d['active_tor'])) {
            return ['block' => true, 'reason' => 'tor'];
        }

        if (!empty($d['bot_status']) && empty($d['is_crawler'])) {
            return ['block' => true, 'reason' => 'bot'];
        }

        if (Config::get('ipqs_block_any_proxy_vpn', false)) {
            if (!empty($d['vpn']) || !empty($d['active_vpn']) || !empty($d['proxy'])) {
                return ['block' => true, 'reason' => 'vpn_or_proxy'];
            }

            return ['block' => false, 'reason' => ''];
        }

        $vpnProxy = !empty($d['vpn']) || !empty($d['active_vpn']) || !empty($d['proxy']);
        $vpnMin = (int) Config::get('ipqs_proxy_vpn_fraud_min', 75);
        if ($vpnMin < 0) {
            $vpnMin = 75;
        }
        if ($vpnMin > 100) {
            $vpnMin = 100;
        }
        if ($vpnProxy && $score >= $vpnMin) {
            return ['block' => true, 'reason' => 'vpn_proxy'];
        }

        return ['block' => false, 'reason' => ''];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function logRejection(string $ip, string $reason, array $payload): void
    {
        if (!defined('DATA_PATH') || !is_dir(DATA_PATH)) {
            return;
        }

        $line = json_encode([
            'timestamp' => date('c'),
            'event' => 'ipqs_high_risk',
            'ip' => $ip,
            'reason' => $reason,
            'fraud_score' => (int) round((float) ($payload['fraud_score'] ?? 0)),
            'risk_score' => (int) round((float) ($payload['risk_score'] ?? 0)),
            'proxy' => !empty($payload['proxy']),
            'vpn' => !empty($payload['vpn']),
            'active_vpn' => !empty($payload['active_vpn']),
            'tor' => !empty($payload['tor']),
            'active_tor' => !empty($payload['active_tor']),
            'bot_status' => !empty($payload['bot_status']),
            'is_crawler' => !empty($payload['is_crawler']),
            'request_id' => (string) ($payload['request_id'] ?? ''),
        ], JSON_UNESCAPED_UNICODE) . "\n";

        @file_put_contents(DATA_PATH . '/' . self::LOG_BASENAME, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchIpqs(string $apiKey, string $ip, string $userAgent, string $acceptLanguage): ?array
    {
        $strictness = (int) Config::get('ipqs_strictness', 1);
        if ($strictness < 0) {
            $strictness = 0;
        }
        if ($strictness > 3) {
            $strictness = 3;
        }

        $allowPublic = Config::get('ipqs_allow_public_access_points', true) ? 'true' : 'false';
        $lighter = Config::get('ipqs_lighter_penalties', false) ? 'true' : 'false';

        $query = http_build_query([
            'strictness' => $strictness,
            'allow_public_access_points' => $allowPublic,
            'lighter_penalties' => $lighter,
            'user_agent' => substr($userAgent, 0, 2048),
            'user_language' => substr($acceptLanguage, 0, 256),
        ], '', '&', PHP_QUERY_RFC3986);

        $url = self::API_BASE . rawurlencode($apiKey) . '/' . rawurlencode($ip) . '?' . $query;

        $raw = self::httpGet($url);
        if ($raw === null || $raw === '') {
            error_log('[SpamProtection] IPQS empty response for IP lookup');

            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            error_log('[SpamProtection] IPQS invalid JSON');

            return null;
        }

        return $decoded;
    }

    private static function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => self::HTTP_TIMEOUT,
                    CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
                    CURLOPT_HTTPHEADER => ['Accept: application/json'],
                ]);
                $body = curl_exec($ch);
                $err = curl_error($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($body === false || $body === '') {
                    error_log('[SpamProtection] IPQS curl failed: ' . $err);

                    return null;
                }
                if ($code >= 400) {
                    error_log('[SpamProtection] IPQS HTTP ' . $code);

                    return null;
                }

                return (string) $body;
            }
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::HTTP_TIMEOUT,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);

        return $body !== false ? (string) $body : null;
    }
}
