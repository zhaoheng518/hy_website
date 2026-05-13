<?php
declare(strict_types=1);

/**
 * Temporary one-time login diagnose script.
 * Remove this file after use.
 *
 * Web:  ?reset=1  触发强制重置
 * CLI:   php diagnose_login.php --reset
 *        或 DIAGNOSE_LOGIN_RESET=1 php diagnose_login.php
 */

$isCli = \in_array(PHP_SAPI, ['cli', 'phpdbg'], true);
if (!$isCli) {
    header('Content-Type: text/html; charset=UTF-8');
}

$root = __DIR__;
$sitePath = $root . '/app/data/site.json';
$report = [];
$argv = $GLOBALS['argv'] ?? [];
$resetRequested = (!$isCli && isset($_GET['reset']) && $_GET['reset'] === '1')
    || ($isCli && \in_array('--reset', $argv, true))
    || (getenv('DIAGNOSE_LOGIN_RESET') === '1');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if (!is_file($sitePath)) {
    http_response_code(500);
    echo '<h1>diagnose_login</h1><p>Missing app/data/site.json</p>';
    exit;
}

$site = json_decode((string) file_get_contents($sitePath), true);
if (!is_array($site)) {
    http_response_code(500);
    echo '<h1>diagnose_login</h1><p>Invalid site.json JSON.</p>';
    exit;
}

$host = (string) ($site['db_host'] ?? 'localhost');
$port = (int) ($site['db_port'] ?? 3306);
$dbName = (string) ($site['db_name'] ?? '');
$user = (string) ($site['db_user'] ?? '');
$pass = (string) ($site['db_pass'] ?? '');

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
$pdo = null;
$users = [];
$resetMessage = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $report[] = '数据库连接: PASS';
} catch (PDOException $e) {
    $report[] = '数据库连接: FAIL - ' . $e->getMessage();
}

if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query('SELECT id, username, role, is_active FROM users ORDER BY id ASC');
        $users = $stmt->fetchAll();
        $report[] = 'users 表查询: PASS，查询到 ' . count($users) . ' 个用户';
    } catch (PDOException $e) {
        $report[] = 'users 表查询: FAIL - ' . $e->getMessage();
    }

    $adminExists = false;
    foreach ($users as $row) {
        if (($row['username'] ?? '') === 'admin') {
            $adminExists = true;
            break;
        }
    }

    if ($resetRequested || !$adminExists) {
        try {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            if ($hash === false) {
                throw new RuntimeException('password_hash failed');
            }

            if ($adminExists) {
                $update = $pdo->prepare('UPDATE users SET password_hash = :hash, is_active = 1, updated_at = NOW() WHERE username = :username');
                $update->execute([
                    'hash' => $hash,
                    'username' => 'admin',
                ]);
                $resetMessage = 'admin 用户密码已重置为 admin123';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO users (username, password_hash, role, is_active, created_at, updated_at) VALUES (:username, :hash, :role, 1, NOW(), NOW())'
                );
                $insert->execute([
                    'username' => 'admin',
                    'hash' => $hash,
                    'role' => 'super_admin',
                ]);
                $resetMessage = 'admin 用户不存在，已创建 admin/admin123（角色 super_admin）';
            }
            $report[] = '强制重置: PASS - ' . $resetMessage;
            if ($pdo instanceof PDO) {
                try {
                    $stmt = $pdo->query('SELECT id, username, role, is_active FROM users ORDER BY id ASC');
                    $users = $stmt->fetchAll();
                } catch (PDOException $e) {
                    $report[] = '重置后刷新用户列表: FAIL - ' . $e->getMessage();
                }
            }
        } catch (Throwable $e) {
            $report[] = '强制重置: FAIL - ' . $e->getMessage();
        }
    } else {
        $report[] = '强制重置: SKIP（admin 用户存在，未请求 reset=1）';
    }
}

if ($isCli) {
    echo "=== diagnose_login (CLI) ===\n";
    foreach ($report as $line) {
        echo $line . "\n";
    }
    echo "\n--- users (no password) ---\n";
    if (empty($users)) {
        echo "(empty)\n";
    } else {
        foreach ($users as $u) {
            echo sprintf(
                "%d\t%s\t%s\t%s\n",
                (int) ($u['id'] ?? 0),
                (string) ($u['username'] ?? ''),
                (string) ($u['role'] ?? ''),
                (string) ($u['is_active'] ?? '')
            );
        }
    }
    echo "\n诊断完成，请查看页面信息\n";
    exit(0);
}

?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录诊断</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111; }
        table { border-collapse: collapse; width: 100%; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f6f6f6; }
        code { background: #f0f0f0; padding: 2px 4px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>登录诊断工具</h1>
    <p>
        DB: <code><?php echo h($host); ?>:<?php echo (int) $port; ?></code>,
        Name: <code><?php echo h($dbName); ?></code>,
        User: <code><?php echo h($user); ?></code>
    </p>
    <p>
        <a href="?reset=1">执行强制重置 admin 密码为 admin123</a>
    </p>

    <h2>诊断结果</h2>
    <ul>
        <?php foreach ($report as $line): ?>
            <li><?php echo h($line); ?></li>
        <?php endforeach; ?>
    </ul>

    <h2>users 用户列表（不显示密码）</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>is_active</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="4">无用户或查询失败</td></tr>
        <?php else: ?>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo (int) ($u['id'] ?? 0); ?></td>
                    <td><?php echo h((string) ($u['username'] ?? '')); ?></td>
                    <td><?php echo h((string) ($u['role'] ?? '')); ?></td>
                    <td><?php echo h((string) ($u['is_active'] ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <p style="margin-top: 16px; font-weight: 600;">诊断完成，请查看页面信息</p>
</body>
</html>
