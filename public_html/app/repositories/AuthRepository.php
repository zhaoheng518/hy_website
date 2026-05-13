<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;

class AuthRepository extends BaseRepository
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';

    private const SESSION_KEY = 'stitch_admin';
    private const CSRF_KEY = 'stitch_csrf';
    private const CSRF_EXPIRY = 3600;
    private const SESSION_LIFETIME = 28800;
    private const LOCKOUT_DURATION = 900;
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    /**
     * 用户认证（登录验证）
     */
    public function authenticate(string $username, string $password): bool
    {
        if ($this->isLockedOut()) {
            return false;
        }

        $user = $this->getUserByUsername($username);

        if ($user === null) {
            $this->recordFailedAttempt();
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt();
            return false;
        }

        if (!$user['is_active']) {
            return false;
        }

        $this->clearFailedAttempts();
        $this->createSession($user);

        return true;
    }

    /**
     * 通过用户名获取用户（用于登录验证）
     */
    public function getUserByUsername(string $username): ?array
    {
        $sql = "SELECT * FROM users WHERE username = :username LIMIT 1";
        return $this->db->fetch($sql, ['username' => $username]);
    }

    /**
     * 通过 ID 获取用户
     */
    public function getUserById(int $id): ?array
    {
        $sql = "SELECT * FROM users WHERE id = :id LIMIT 1";
        return $this->db->fetch($sql, ['id' => $id]);
    }

    /**
     * 获取安全的用户数据（不含密码）
     */
    public function getSafeUserData(int $id): ?array
    {
        $sql = "SELECT id, username, email, role, last_login_at, created_at FROM users WHERE id = :id";
        $user = $this->db->fetch($sql, ['id' => $id]);

        if ($user === null) {
            return null;
        }

        unset($user['password_hash']);
        return $user;
    }

    /**
     * 创建会话
     */
    public function createSession(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'user_id'  => $user['id'],
            'username' => $user['username'],
            'role'     => $user['role'],
            'ip'       => $this->getClientIp(),
            'ua'       => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'login_at' => time(),
        ];

        $this->updateLastLogin($user['id'], $this->getClientIp());
    }

    /**
     * 销毁会话
     */
    public function destroySession(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        unset($_SESSION[self::CSRF_KEY]);
        session_regenerate_id(true);
    }

    /**
     * 检查是否已认证
     */
    public function isAuthenticated(): bool
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        $session = $_SESSION[self::SESSION_KEY];

        $requiredKeys = ['user_id', 'username', 'ip', 'ua', 'login_at'];
        foreach ($requiredKeys as $key) {
            if (!isset($session[$key])) {
                $this->destroySession();
                return false;
            }
        }

        if ($session['ip'] !== $this->getClientIp()) {
            $this->destroySession();
            return false;
        }

        if ($session['ua'] !== substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)) {
            $this->destroySession();
            return false;
        }

        if (time() - $session['login_at'] > self::SESSION_LIFETIME) {
            $this->destroySession();
            return false;
        }

        return true;
    }

    /**
     * 要求认证（未认证则重定向到登录页）
     */
    public function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            $this->redirectToLogin();
        }
    }

    /**
     * 获取当前登录用户
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $session = $_SESSION[self::SESSION_KEY];
        return $this->getSafeUserData($session['user_id']);
    }

    /**
     * 获取当前登录用户 ID
     */
    public function getCurrentUserId(): ?int
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return (int) $_SESSION[self::SESSION_KEY]['user_id'];
    }

    /**
     * 生成 CSRF Token
     */
    public function generateCsrfToken(): string
    {
        if (isset($_SESSION[self::CSRF_KEY])) {
            $existing = $_SESSION[self::CSRF_KEY];
            if (isset($existing['token'], $existing['expires']) && time() <= $existing['expires']) {
                return $existing['token'];
            }
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::CSRF_KEY] = [
            'token'   => $token,
            'expires' => time() + self::CSRF_EXPIRY,
        ];

        return $token;
    }

    /**
     * 验证 CSRF Token
     */
    public function validateCsrfToken(string $token): bool
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

    /**
     * 消费 CSRF Token（验证后删除）
     */
    public function consumeCsrfToken(string $token): bool
    {
        if (!$this->validateCsrfToken($token)) {
            return false;
        }
        unset($_SESSION[self::CSRF_KEY]);
        return true;
    }

    /**
     * 生成 CSRF 隐藏字段
     */
    public function csrfField(): string
    {
        $token = $this->generateCsrfToken();
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * 修改密码（需验证原密码）
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = $this->getUserById($userId);

        if ($user === null) {
            return false;
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return false;
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $sql = "UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id";

        return $this->db->execute($sql, ['password_hash' => $newHash, 'id' => $userId]) > 0;
    }

    /**
     * 直接更新密码（管理员操作，无需原密码）
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $sql = "UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id";

        return $this->db->execute($sql, ['password_hash' => $newHash, 'id' => $userId]) > 0;
    }

    /**
     * 更新最后登录信息
     */
    public function updateLastLogin(int $userId, string $ip): void
    {
        $sql = "UPDATE users SET 
                last_login_at = NOW(), 
                last_login_ip = :ip, 
                updated_at = NOW() 
                WHERE id = :id";

        $this->db->execute($sql, [
            'ip' => $ip,
            'id' => $userId,
        ]);
    }

    /**
     * 检查是否被锁定
     */
    public function isLockedOut(): bool
    {
        $key = $this->getLockoutKey();
        $lockFile = $this->getLockoutFile($key);

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

    /**
     * 获取锁定剩余时间（秒）
     */
    public function getLockoutRemaining(): int
    {
        $key = $this->getLockoutKey();
        $lockFile = $this->getLockoutFile($key);

        if (!file_exists($lockFile)) {
            return 0;
        }

        $data = json_decode(file_get_contents($lockFile), true);

        if (!$data || !isset($data['until'])) {
            return 0;
        }

        return max(0, $data['until'] - time());
    }

    /**
     * 记录失败登录尝试
     */
    private function recordFailedAttempt(): void
    {
        $key = $this->getLockoutKey();
        $attemptFile = $this->getAttemptFile($key);

        $attempts = 0;
        if (file_exists($attemptFile)) {
            $data = json_decode(file_get_contents($attemptFile), true);
            if ($data && isset($data['count']) && time() - ($data['time'] ?? 0) < self::LOCKOUT_DURATION) {
                $attempts = $data['count'];
            }
        }

        $attempts++;

        file_put_contents($attemptFile, json_encode([
            'count' => $attempts,
            'time'  => time(),
        ]), LOCK_EX);

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $lockFile = $this->getLockoutFile($key);
            file_put_contents($lockFile, json_encode([
                'until' => time() + self::LOCKOUT_DURATION,
            ]), LOCK_EX);

            @unlink($attemptFile);

            $this->logActivity(
                null,
                'account_lockout',
                'users',
                null,
                null,
                ['ip' => $this->getClientIp(), 'attempts' => $attempts]
            );
        }
    }

    /**
     * 清除失败登录记录
     */
    private function clearFailedAttempts(): void
    {
        $key = $this->getLockoutKey();
        $attemptFile = $this->getAttemptFile($key);
        $lockFile = $this->getLockoutFile($key);

        if (file_exists($attemptFile)) {
            @unlink($attemptFile);
        }
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    }

    /**
     * 获取客户端 IP
     */
    private function getClientIp(): string
    {
        return \App\Core\ClientIp::get();
    }

    private function getLockoutKey(): string
    {
        return md5($this->getClientIp());
    }

    private function getLockoutFile(string $key): string
    {
        return DATA_PATH . '/.lockout_' . $key;
    }

    private function getAttemptFile(string $key): string
    {
        return DATA_PATH . '/.attempts_' . $key;
    }

    private function redirectToLogin(): void
    {
        header('Location: /admin/login');
        exit;
    }

    /**
     * 创建新用户
     */
    public function createUser(array $data): int
    {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            unset($data['password']);
        }

        if (!isset($data['role'])) {
            $data['role'] = 'admin';
        }
        if (!isset($data['is_active'])) {
            $data['is_active'] = 1;
        }

        return parent::create($data);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listUsers(): array
    {
        $sql = 'SELECT id, username, email, role, is_active, last_login_at, created_at FROM users ORDER BY id ASC';
        return $this->db->fetchAll($sql);
    }
}
