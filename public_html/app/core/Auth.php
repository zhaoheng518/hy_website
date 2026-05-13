<?php

namespace App\Core;

use App\Repositories\AuthRepository;

class Auth
{
    private const SESSION_KEY = 'stitch_admin';
    private const CSRF_KEY = 'stitch_csrf';
    private const CSRF_EXPIRY = 3600;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $permissionsMapCache = null;

    /**
     * Canonical permission keys + legacy aliases (values = keys to check in admin_permissions.json, first match wins).
     *
     * @var array<string, list<string>>
     */
    private const PERMISSION_ALIASES = [
        'media'      => ['media', 'files'],
        'files'      => ['media', 'files'],
        'newsletter' => ['newsletter', 'inquiries'],
        'dashboard'  => ['dashboard', 'home'],
    ];

    /**
     * Full set of permission keys written when auto-initializing an empty permissions file.
     *
     * @var list<string>
     */
    private const DEFAULT_PERMISSIONS = [
        'dashboard', 'products', 'categories', 'pages', 'blog', 'cases',
        'home', 'inquiries', 'newsletter', 'media', 'files', 'menu',
        'seo', 'languages', 'settings', 'users', 'sections', 'redirects',
    ];

    /** Prevents ensurePermissionsInitialized() from querying the DB more than once per request. */
    private static bool $permBootstrapped = false;

    /**
     * Used by editor APIs (translate / upload) — write on any of these is enough.
     *
     * @var list<string>
     */
    private const EDITOR_API_WRITE_ANY = [
        'products', 'categories', 'pages', 'blog', 'cases', 'home', 'media', 'languages', 'seo', 'menu',
    ];

    public static function login(string $username, string $password): bool
    {
        // [Module 13] IP whitelist check before any credential work
        if (!AdminSecurityHelper::isIpAllowed(self::getClientIp())) {
            error_log('[Auth] Login blocked — IP not in whitelist: ' . self::getClientIp());
            return false;
        }

        if (self::isLockedOut()) {
            return false;
        }

        try {
            $db = Database::getInstance();
            $authRepo = new AuthRepository($db);

            $user = $authRepo->getUserByUsername($username);

            if ($user === null) {
                self::recordFailedAttempt($username);
                return false;
            }

            $passwordOk = password_verify($password, (string) ($user['password_hash'] ?? ''));

            if (!$passwordOk) {
                self::recordFailedAttempt($username);
                return false;
            }

            if (!$user['is_active']) {
                self::recordFailedAttempt($username);
                return false;
            }

            self::clearFailedAttempts();

            session_regenerate_id(true);

            $_SESSION[self::SESSION_KEY] = [
                'user_id'     => $user['id'],
                'username'    => $user['username'],
                'role'        => $user['role'],
                'ip'          => self::getClientIp(),
                'ua'          => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'login_at'    => time(),
                'last_active' => time(), // [Module 13] idle timeout baseline
            ];

            $authRepo->updateLastLogin($user['id'], self::getClientIp());
            self::appendLoginLog((int) $user['id'], (string) $user['username']);

            // Populate admin_permissions.json if it is empty so the newly logged-in
            // user is not immediately blocked by deny-by-default permission checks.
            self::ensurePermissionsInitialized();

            return true;

        } catch (\Exception $e) {
            error_log("[Auth] Login failed: " . $e->getMessage());
            self::recordFailedAttempt($username);
            return false;
        }
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        unset($_SESSION[self::CSRF_KEY]);
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        $session = $_SESSION[self::SESSION_KEY];

        if (!isset($session['user_id'], $session['username'], $session['ip'], $session['ua'], $session['login_at'])) {
            self::logout();
            return false;
        }

        if ($session['ip'] !== self::getClientIp()) {
            self::logout();
            return false;
        }

        if ($session['ua'] !== substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)) {
            self::logout();
            return false;
        }

        // [Module 13] Absolute session timeout — configurable via admin_session_timeout (default 8h)
        $absoluteTimeout = max(300, (int) Config::get('admin_session_timeout', 28800));
        if (time() - (int) $session['login_at'] > $absoluteTimeout) {
            self::logout();
            return false;
        }

        // [Module 13] Idle timeout — configurable via admin_idle_timeout (0 = disabled)
        $idleTimeout = (int) Config::get('admin_idle_timeout', 0);
        if ($idleTimeout > 0) {
            $lastActive = (int) ($session['last_active'] ?? $session['login_at']);
            if (time() - $lastActive > $idleTimeout) {
                self::logout();
                return false;
            }
        }

        // [Module 13] IP whitelist — if list is non-empty, reject unlisted IPs
        if (!AdminSecurityHelper::isIpAllowed(self::getClientIp())) {
            self::logout();
            return false;
        }

        // [Module 13] Refresh last_active on every authenticated request
        $_SESSION[self::SESSION_KEY]['last_active'] = time();

        return true;
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            // [Module 13] Use configurable admin path
            $path = trim((string) Config::get('admin_path', 'admin'));
            if ($path === '') {
                $path = 'admin';
            }
            header('Location: /' . $path . '/login');
            exit;
        }

        // Admin bootstrap: seed permissions file when it is empty.
        // Runs at most once per request thanks to the $permBootstrapped flag.
        self::ensurePermissionsInitialized();
    }

    public static function role(): string
    {
        if (!self::check()) {
            return '';
        }

        return (string) ($_SESSION[self::SESSION_KEY]['role'] ?? '');
    }

    public static function isSuperAdmin(): bool
    {
        return self::role() === 'super_admin';
    }

    /**
     * 可管理用户账号（角色 super_admin 或 admin）。细粒度写操作仍走 can('users','write')。
     */
    public static function isAdminRole(): bool
    {
        if (!self::check()) {
            return false;
        }
        $r = self::role();

        return $r === 'super_admin' || $r === 'admin';
    }

    /**
     * Deny-by-default capability check.
     *
     * @param string $permission Logical module key (e.g. products, categories, media).
     * @param string $access     "read" | "write"
     */
    public static function can(string $permission, string $access = 'read'): bool
    {
        if (!self::check()) {
            return false;
        }

        $access = strtolower($access) === 'write' ? 'write' : 'read';
        if ($access === 'write' && self::role() === 'viewer') {
            return false;
        }

        if (self::isSuperAdmin()) {
            return true;
        }

        $role = self::role();
        if (!in_array($role, ['admin', 'editor', 'viewer'], true)) {
            return false;
        }

        $uid = (int) ($_SESSION[self::SESSION_KEY]['user_id'] ?? 0);
        if ($uid <= 0) {
            return false;
        }

        $level = self::maxPermissionLevelForUser($uid, $permission);
        if ($level <= 0) {
            return false;
        }

        if ($access === 'read') {
            return true;
        }

        return $level >= 2;
    }

    /**
     * Require login + permission. If $access is null, uses HTTP method: GET/HEAD → read, else → write.
     */
    public static function requireCan(string $permission, ?string $access = null): void
    {
        self::requireAuth();

        $effective = $access;
        if ($effective === null) {
            $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            $effective = ($m === 'GET' || $m === 'HEAD') ? 'read' : 'write';
        } else {
            $effective = strtolower($effective) === 'write' ? 'write' : 'read';
        }

        if (!self::can($permission, $effective)) {
            http_response_code(403);
            $adminPath = trim((string) Config::get('admin_path', 'admin')) ?: 'admin';
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403</title></head>'
                . '<body><p>Forbidden.</p><p><a href="/' . htmlspecialchars($adminPath, ENT_QUOTES, 'UTF-8') . '">Back</a></p></body></html>';
            exit;
        }
    }

    /**
     * Translate / image upload from admin forms: any configured write on content modules.
     */
    public static function canUseEditorApi(): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::isSuperAdmin()) {
            return true;
        }
        foreach (self::EDITOR_API_WRITE_ANY as $p) {
            if (self::can($p, 'write')) {
                return true;
            }
        }

        return false;
    }

    public static function requireEditorApi(): void
    {
        self::requireAuth();
        if (!self::canUseEditorApi()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Forbidden.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * @return list<string>
     */
    private static function permissionKeysToScan(string $permission): array
    {
        $p = trim($permission);
        if ($p === '') {
            return [];
        }

        return self::PERMISSION_ALIASES[$p] ?? [$p];
    }

    private static function maxPermissionLevelForUser(int $uid, string $permission): int
    {
        $map = self::loadPermissionsMap();
        $uidKey = (string) $uid;
        if (!isset($map[$uidKey]) || !is_array($map[$uidKey])) {
            return 0;
        }
        $row = $map[$uidKey];
        $max = 0;
        foreach (self::permissionKeysToScan($permission) as $key) {
            $max = max($max, self::valueToPermissionLevel($row[$key] ?? null));
        }

        return $max;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function loadPermissionsMap(): array
    {
        if (self::$permissionsMapCache !== null) {
            return self::$permissionsMapCache;
        }

        try {
            $path = DATA_PATH . '/admin_permissions.json';

            if (!is_file($path)) {
                self::ensurePermissionsInitialized();
                return self::$permissionsMapCache ?? [];
            }

            $raw = (string) file_get_contents($path);
            $t   = trim($raw);

            if ($t === '' || $t === '{}' || $t === '[]') {
                self::ensurePermissionsInitialized();
                return self::$permissionsMapCache ?? [];
            }

            $decoded = json_decode($raw, true);

            if (!is_array($decoded)) {
                // Corrupt JSON — backup the damaged file and regenerate defaults.
                $backup = $path . '.bak.' . date('YmdHis');
                @rename($path, $backup);
                error_log('[Auth] admin_permissions.json corrupt; backed up as ' . basename($backup));
                self::ensurePermissionsInitialized();
                return self::$permissionsMapCache ?? [];
            }

            $out = [];
            foreach ($decoded as $k => $v) {
                if (is_array($v)) {
                    $out[(string) $k] = $v;
                }
            }

            // All keys had scalar values — treat as effectively empty.
            if (empty($out)) {
                self::ensurePermissionsInitialized();
                return self::$permissionsMapCache ?? [];
            }

            self::$permissionsMapCache = $out;
        } catch (\Throwable $e) {
            error_log('[Auth] loadPermissionsMap: ' . $e->getMessage());
            self::$permissionsMapCache = [];
        }

        return self::$permissionsMapCache;
    }

    /**
     * @param mixed $value
     */
    private static function valueToPermissionLevel($value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_bool($value)) {
            return $value ? 2 : 0;
        }
        if (is_int($value) || is_float($value)) {
            return ((float) $value) >= 1 ? 2 : 0;
        }
        if (is_string($value)) {
            $s = strtolower(trim($value));
            if ($s === '' || $s === '0' || $s === 'false' || $s === 'no' || $s === 'off') {
                return 0;
            }
            if (in_array($s, ['r', 'read', 'readonly', 'ro'], true)) {
                return 1;
            }
            if (in_array($s, ['rw', 'write', 'all', 'w', 'yes', 'true', '1'], true)) {
                return 2;
            }
        }

        return 0;
    }

    private static function appendLoginLog(int $userId, string $username): void
    {
        try {
            $store = JsonStore::globalData('login_log');
            $store->update(function ($rows) use ($userId, $username) {
                if (!is_array($rows)) {
                    $rows = [];
                }
                $rows[] = [
                    'user_id' => $userId,
                    'username' => $username,
                    'ip' => self::getClientIp(),
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                if (count($rows) > 500) {
                    $rows = array_slice($rows, -500);
                }

                return $rows;
            });
        } catch (\Throwable $e) {
            error_log('[Auth] login_log: ' . $e->getMessage());
        }
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        try {
            $db = Database::getInstance();
            $authRepo = new AuthRepository($db);

            return $authRepo->getSafeUserData((int) $_SESSION[self::SESSION_KEY]['user_id']);
        } catch (\Exception $e) {
            error_log("[Auth] Failed to get user: " . $e->getMessage());

            return null;
        }
    }

    public static function generateCsrfToken(): string
    {
        if (isset($_SESSION[self::CSRF_KEY])) {
            $existing = $_SESSION[self::CSRF_KEY];
            if (isset($existing['token'], $existing['expires']) && time() <= $existing['expires']) {
                return $existing['token'];
            }
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::CSRF_KEY] = [
            'token' => $token,
            'expires' => time() + self::CSRF_EXPIRY,
        ];

        return $token;
    }

    public static function validateCsrfToken(string $token): bool
    {
        if (!isset($_SESSION[self::CSRF_KEY])) {
            return false;
        }

        $stored = $_SESSION[self::CSRF_KEY];

        if (!isset($stored['token'], $stored['expires'])) {
            unset($_SESSION[self::CSRF_KEY]);

            return false;
        }

        if (time() > $stored['expires']) {
            unset($_SESSION[self::CSRF_KEY]);

            return false;
        }

        return hash_equals($stored['token'], $token);
    }

    public static function consumeCsrfToken(string $token): bool
    {
        if (!self::validateCsrfToken($token)) {
            return false;
        }
        unset($_SESSION[self::CSRF_KEY]);

        return true;
    }

    public static function csrfField(): string
    {
        $token = self::generateCsrfToken();

        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function changePassword(string $currentPassword, string $newPassword): bool
    {
        if (!self::check()) {
            return false;
        }

        try {
            $db = Database::getInstance();
            $authRepo = new AuthRepository($db);
            $userId = (int) $_SESSION[self::SESSION_KEY]['user_id'];

            return $authRepo->changePassword($userId, $currentPassword, $newPassword);
        } catch (\Exception $e) {
            error_log("[Auth] Change password failed: " . $e->getMessage());

            return false;
        }
    }

    public static function isLockedOut(): bool
    {
        $key = 'stitch_attempts_' . md5(self::getClientIp());
        $lockFile = DATA_PATH . '/.lockout_' . $key;

        if (!file_exists($lockFile)) {
            return false;
        }

        $data = json_decode(file_get_contents($lockFile), true);
        if (!$data || !isset($data['until'])) {
            @unlink($lockFile);

            return false;
        }

        if (time() > $data['until']) {
            @unlink($lockFile);

            return false;
        }

        return true;
    }

    public static function getLockoutRemaining(): int
    {
        $key = 'stitch_attempts_' . md5(self::getClientIp());
        $lockFile = DATA_PATH . '/.lockout_' . $key;

        if (!file_exists($lockFile)) {
            return 0;
        }

        $data = json_decode(file_get_contents($lockFile), true);
        if (!$data || !isset($data['until'])) {
            return 0;
        }

        $remaining = $data['until'] - time();

        return max(0, $remaining);
    }

    private static function recordFailedAttempt(string $username = ''): void
    {
        $ip  = self::getClientIp();
        $key = 'stitch_attempts_' . md5($ip);
        $attemptFile = DATA_PATH . '/.attempts_' . $key;

        $attempts = 0;
        if (file_exists($attemptFile)) {
            $data = json_decode(file_get_contents($attemptFile), true);
            if ($data && isset($data['count']) && time() - ($data['time'] ?? 0) < self::LOCKOUT_DURATION) {
                $attempts = $data['count'];
            }
        }

        $attempts++;

        file_put_contents($attemptFile, json_encode([
            'count'    => $attempts,
            'time'     => time(),
            'username' => $username, // [Module 13] store for notification
        ]), LOCK_EX);

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $lockFile = DATA_PATH . '/.lockout_' . $key;
            file_put_contents($lockFile, json_encode([
                'until' => time() + self::LOCKOUT_DURATION,
            ]), LOCK_EX);

            @unlink($attemptFile);

            // [Module 13] Send lockout alert email (non-blocking, errors are swallowed)
            AdminSecurityHelper::notifyLockout($username, $ip);
        }
    }

    private static function clearFailedAttempts(): void
    {
        $key = 'stitch_attempts_' . md5(self::getClientIp());
        $attemptFile = DATA_PATH . '/.attempts_' . $key;
        $lockFile = DATA_PATH . '/.lockout_' . $key;

        if (file_exists($attemptFile)) {
            @unlink($attemptFile);
        }
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    }

    private static function getClientIp(): string
    {
        return ClientIp::get();
    }

    /**
     * Ensures admin_permissions.json is populated with full-access defaults for every
     * active non-super_admin user when the file is missing, empty, or was just corrupted.
     *
     * Safe to call multiple times — runs the DB query at most once per PHP request.
     * Called automatically by login() and requireAuth(); can also be called explicitly.
     */
    public static function ensurePermissionsInitialized(): void
    {
        if (self::$permBootstrapped) {
            return;
        }
        // Mark before any I/O so recursive/concurrent calls skip.
        self::$permBootstrapped = true;

        try {
            $path = DATA_PATH . '/admin_permissions.json';

            // File already has valid, non-empty permissions — nothing to generate.
            if (is_file($path)) {
                $raw     = (string) file_get_contents($path);
                $t       = trim($raw);
                $decoded = ($t !== '' && $t !== '{}' && $t !== '[]')
                    ? json_decode($raw, true)
                    : null;

                if (is_array($decoded) && !empty($decoded)) {
                    // Repair the in-memory cache if a prior loadPermissionsMap() call
                    // already cached an empty array before this method ran.
                    if (self::$permissionsMapCache === []) {
                        $out = [];
                        foreach ($decoded as $k => $v) {
                            if (is_array($v)) {
                                $out[(string) $k] = $v;
                            }
                        }
                        if (!empty($out)) {
                            self::$permissionsMapCache = $out;
                        }
                    }
                    return;
                }

                // Corrupt JSON: back up the file before overwriting.
                if ($t !== '' && $t !== '{}' && $t !== '[]' && json_last_error() !== JSON_ERROR_NONE) {
                    $backup = $path . '.bak.' . date('YmdHis');
                    @rename($path, $backup);
                    error_log('[Auth] admin_permissions.json corrupt; backed up as ' . basename($backup));
                }
            }

            $map = self::buildDefaultPermissionsMap();

            if (!empty($map)) {
                $json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                file_put_contents($path, $json, LOCK_EX);
                error_log('[Auth] admin_permissions.json auto-initialized (' . count($map) . ' user(s))');
            }

            self::$permissionsMapCache = $map;

        } catch (\Throwable $e) {
            error_log('[Auth] ensurePermissionsInitialized: ' . $e->getMessage());
        }
    }

    /**
     * Queries the DB for every active non-super_admin user and returns a permissions
     * map granting full access (rw) to all DEFAULT_PERMISSIONS keys.
     * Viewer-role accounts receive read-only ("r") access instead.
     *
     * @return array<string, array<string, string>>
     */
    private static function buildDefaultPermissionsMap(): array
    {
        $map = [];
        try {
            $db   = Database::getInstance();
            $rows = $db->fetchAll(
                "SELECT id, role FROM users WHERE is_active = 1 AND role != 'super_admin'"
            );
            foreach ($rows as $row) {
                $uid  = (string) ($row['id'] ?? '');
                $role = (string) ($row['role'] ?? 'editor');
                if ($uid === '') {
                    continue;
                }
                $level = ($role === 'viewer') ? 'r' : 'rw';
                $perms = [];
                foreach (self::DEFAULT_PERMISSIONS as $key) {
                    $perms[$key] = $level;
                }
                $map[$uid] = $perms;
            }
        } catch (\Throwable $e) {
            error_log('[Auth] buildDefaultPermissionsMap: ' . $e->getMessage());
        }

        return $map;
    }
}
