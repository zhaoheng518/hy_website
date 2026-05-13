<?php

namespace App\Core;

/**
 * Module 13 — Admin Security Helper
 *
 * Standalone service — zero side-effects on existing logic.
 * Provides:
 *   1. Server-side math CAPTCHA (no GD / no external service required)
 *   2. IP whitelist enforcement (exact match + IPv4 CIDR)
 *   3. Login lockout email notification
 *   4. Security diagnostic report
 *
 * Rollback: remove call sites in Auth.php + AdminAuthController.php;
 *           set "login_captcha_enabled": false in site.json.
 */
class AdminSecurityHelper
{
    private const CAPTCHA_SESSION_KEY = 'stitch_sec_captcha';
    private const CAPTCHA_TTL         = 300; // seconds before a generated question expires

    // =========================================================================
    // CAPTCHA
    // =========================================================================

    public static function isCaptchaEnabled(): bool
    {
        return (bool) Config::get('login_captcha_enabled', true);
    }

    /**
     * Generate a new math CAPTCHA, store the answer in the session, and return
     * the question string (e.g. "8 + 5").  The answer is never sent to the client.
     */
    public static function generateCaptcha(): string
    {
        $a  = random_int(2, 15);
        $b  = random_int(1, 9);
        $op = (random_int(0, 1) === 0) ? '+' : '-';

        // Ensure subtraction result is always positive
        if ($op === '-' && $a < $b) {
            $tmp = $a; $a = $b; $b = $tmp;
        }

        $answer = ($op === '+') ? ($a + $b) : ($a - $b);

        $_SESSION[self::CAPTCHA_SESSION_KEY] = [
            'answer'  => $answer,
            'expires' => time() + self::CAPTCHA_TTL,
        ];

        return "{$a} {$op} {$b}";
    }

    /**
     * Validate the user's CAPTCHA answer against the stored session value.
     * Always consumes (deletes) the stored CAPTCHA — single-use.
     * Returns true on correct answer, false otherwise.
     */
    public static function validateCaptcha(string $input): bool
    {
        if (!isset($_SESSION[self::CAPTCHA_SESSION_KEY])) {
            return false;
        }

        $data = $_SESSION[self::CAPTCHA_SESSION_KEY];
        unset($_SESSION[self::CAPTCHA_SESSION_KEY]); // always consume, even on wrong answer

        if (!isset($data['answer'], $data['expires'])) {
            return false;
        }
        if (time() > (int) $data['expires']) {
            return false;
        }

        return (int) trim($input) === (int) $data['answer'];
    }

    // =========================================================================
    // IP Whitelist
    // =========================================================================

    /**
     * Return true when the given IP is allowed to access the admin panel.
     * An empty (or absent) whitelist means "allow all".
     * Supports exact IPv4/IPv6 match and IPv4 CIDR ranges (e.g. "192.168.1.0/24").
     */
    public static function isIpAllowed(string $ip): bool
    {
        $whitelist = Config::get('admin_ip_whitelist', []);
        if (!is_array($whitelist) || empty($whitelist)) {
            return true;
        }

        $ip = trim($ip);
        foreach ($whitelist as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }
            if ($entry === $ip) {
                return true;
            }
            if (strpos($entry, '/') !== false && self::ipInCidr($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether an IPv4 address falls within a CIDR range.
     * Returns false for IPv6 addresses or malformed input.
     */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2) {
            return false;
        }
        $bits = (int) $parts[1];
        if ($bits < 0 || $bits > 32) {
            return false;
        }
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($parts[0]);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    // =========================================================================
    // Login Lockout Notification
    // =========================================================================

    /**
     * Send an email alert when the admin login is locked out (brute-force attempt).
     * Uses login_notify_email from config, falling back to admin_email.
     * Silently swallows all errors so it never disrupts the auth flow.
     *
     * @param string $username  The username that was attempted
     * @param string $ip        The attacker's IP address
     */
    public static function notifyLockout(string $username, string $ip): void
    {
        $to = trim((string) Config::get('login_notify_email', ''));
        if ($to === '') {
            $to = trim((string) Config::get('admin_email', ''));
        }
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $siteName  = Config::get('site_name', 'Website');
            $siteUrl   = Config::get('site_url', '');
            $adminPath = trim((string) Config::get('admin_path', 'admin'));
            $time      = date('Y-m-d H:i:s T');
            $subject   = "[{$siteName}] ⚠ Admin Login Lockout Alert";

            $safeUser = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            $safeIp   = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
            $safeSite = htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8');
            $safePath = htmlspecialchars($adminPath, ENT_QUOTES, 'UTF-8');

            $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;max-width:560px;margin:0 auto;padding:0;">
<div style="background:#dc2626;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0;">
  <h2 style="margin:0;font-size:20px;">&#x26A0;&#xFE0F; Admin Login Lockout</h2>
</div>
<div style="padding:24px;background:#fef2f2;border:1px solid #fecaca;border-top:none;border-radius:0 0 8px 8px;">
  <p style="margin:0 0 16px;">The admin login for <strong>{$siteName}</strong> was locked out after repeated
  failed attempts.</p>
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <tr style="background:#fee2e2;">
      <td style="padding:10px 12px;font-weight:bold;width:40%;">Username Attempted</td>
      <td style="padding:10px 12px;">{$safeUser}</td>
    </tr>
    <tr>
      <td style="padding:10px 12px;font-weight:bold;">IP Address</td>
      <td style="padding:10px 12px;">{$safeIp}</td>
    </tr>
    <tr style="background:#fee2e2;">
      <td style="padding:10px 12px;font-weight:bold;">Time (server)</td>
      <td style="padding:10px 12px;">{$time}</td>
    </tr>
    <tr>
      <td style="padding:10px 12px;font-weight:bold;">Site</td>
      <td style="padding:10px 12px;"><a href="{$safeSite}" style="color:#1a56db;">{$safeSite}</a></td>
    </tr>
    <tr style="background:#fee2e2;">
      <td style="padding:10px 12px;font-weight:bold;">Admin Panel</td>
      <td style="padding:10px 12px;">{$safeSite}/{$safePath}</td>
    </tr>
  </table>
  <p style="margin:20px 0 8px;font-size:13px;color:#7f1d1d;">
    If this was not you, consider: changing your admin path, updating your password,
    and adding your IP to the whitelist (<code>admin_ip_whitelist</code> in site.json).
  </p>
  <p style="margin:0;font-size:11px;color:#9ca3af;">
    Automated security alert — {$siteName}
  </p>
</div>
</body>
</html>
HTML;

            $mailer = new Mailer();
            $mailer->send($to, $subject, $html);
        } catch (\Throwable $e) {
            error_log('[AdminSecurityHelper] Lockout notification failed: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Security Diagnostic
    // =========================================================================

    /**
     * Return an array of current security findings for the admin dashboard.
     *
     * Each item:
     *   ['level' => 'ok'|'warning'|'error', 'message' => '...']
     *
     * Call from admin dashboard to surface actionable security issues.
     */
    public static function diagnose(): array
    {
        $issues = [];

        // ── CAPTCHA ───────────────────────────────────────────────────────────
        if (!self::isCaptchaEnabled()) {
            $issues[] = ['level' => 'warning',
                'message' => 'Login CAPTCHA is disabled (login_captcha_enabled = false). Bots can attempt logins until lockout.'];
        } else {
            $issues[] = ['level' => 'ok', 'message' => 'Login CAPTCHA is enabled.'];
        }

        // ── IP Whitelist ──────────────────────────────────────────────────────
        $whitelist = Config::get('admin_ip_whitelist', []);
        if (!is_array($whitelist) || empty($whitelist)) {
            $issues[] = ['level' => 'warning',
                'message' => 'Admin IP whitelist is empty — any IP address may attempt to log in.'];
        } else {
            $issues[] = ['level' => 'ok',
                'message' => 'IP whitelist active: ' . count($whitelist) . ' entry/entries configured.'];
        }

        // ── Lockout notification email ────────────────────────────────────────
        $notifyEmail = trim((string) Config::get('login_notify_email', ''));
        $adminEmail  = trim((string) Config::get('admin_email', ''));
        if ($notifyEmail === '' && $adminEmail === '') {
            $issues[] = ['level' => 'warning',
                'message' => 'Neither login_notify_email nor admin_email is set — lockout alerts will NOT be sent.'];
        } else {
            $dest = $notifyEmail !== '' ? $notifyEmail : $adminEmail;
            $issues[] = ['level' => 'ok', 'message' => 'Lockout alert email configured: ' . $dest];
        }

        // ── SMTP ──────────────────────────────────────────────────────────────
        $smtpHost = trim((string) Config::get('smtp_host', ''));
        if ($smtpHost === '') {
            $issues[] = ['level' => 'warning',
                'message' => 'SMTP not configured — email alerts will fall back to PHP mail() which may not deliver.'];
        } else {
            $issues[] = ['level' => 'ok', 'message' => 'SMTP host configured: ' . $smtpHost];
        }

        // ── Admin path ────────────────────────────────────────────────────────
        $adminPath = trim((string) Config::get('admin_path', 'admin'));
        if ($adminPath === 'admin') {
            $issues[] = ['level' => 'warning',
                'message' => 'Admin path is the default "/admin". Consider a custom admin_path (e.g. "manage-xk9") for obscurity.'];
        } else {
            $issues[] = ['level' => 'ok', 'message' => 'Custom admin path is active: /' . $adminPath];
        }

        // ── Session absolute timeout ──────────────────────────────────────────
        $timeout = (int) Config::get('admin_session_timeout', 28800);
        if ($timeout > 28800) {
            $issues[] = ['level' => 'warning',
                'message' => 'Session timeout is ' . round($timeout / 3600, 1) . 'h (> 8h). Consider shortening admin_session_timeout.'];
        } else {
            $issues[] = ['level' => 'ok',
                'message' => 'Session absolute timeout: ' . round($timeout / 3600, 1) . ' hour(s).'];
        }

        // ── Session idle timeout ──────────────────────────────────────────────
        $idleTimeout = (int) Config::get('admin_idle_timeout', 0);
        if ($idleTimeout <= 0) {
            $issues[] = ['level' => 'warning',
                'message' => 'No idle timeout (admin_idle_timeout = 0). An active session never expires from inactivity.'];
        } else {
            $issues[] = ['level' => 'ok',
                'message' => 'Session idle timeout: ' . round($idleTimeout / 60, 0) . ' minute(s).'];
        }

        // ── APP_DEBUG ─────────────────────────────────────────────────────────
        if (defined('APP_DEBUG') && APP_DEBUG === true) {
            $issues[] = ['level' => 'error',
                'message' => 'APP_DEBUG is TRUE — full stack traces are shown to anyone who triggers a 500 error. '
                    . 'Set define("APP_DEBUG", false) in index.php for production.'];
        } else {
            $issues[] = ['level' => 'ok', 'message' => 'APP_DEBUG is disabled.'];
        }

        // ── site.json JSON blocking ───────────────────────────────────────────
        $htAccess = defined('ROOT_PATH') ? ROOT_PATH . '/.htaccess' : '';
        if ($htAccess !== '' && is_file($htAccess)) {
            $htContent = (string) @file_get_contents($htAccess);
            if (strpos($htContent, '.json') !== false) {
                $issues[] = ['level' => 'ok', 'message' => '.htaccess blocks direct access to .json files (site.json is protected).'];
            } else {
                $issues[] = ['level' => 'error',
                    'message' => '.htaccess does NOT block .json files — site.json (containing DB credentials) may be publicly readable.'];
            }
        }

        // ── AuthDebug logs ────────────────────────────────────────────────────
        $authFile = defined('APP_PATH') ? APP_PATH . '/core/Auth.php' : '';
        if ($authFile !== '' && is_file($authFile)) {
            $authContent = (string) @file_get_contents($authFile);
            if (strpos($authContent, '[AuthDebug]') !== false) {
                $issues[] = ['level' => 'warning',
                    'message' => 'Auth.php still contains [AuthDebug] error_log() calls that expose usernames and '
                        . 'password-verification results in the error log. Remove them before going to production.'];
            }
        }

        return $issues;
    }
}
