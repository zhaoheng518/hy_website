<?php

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;
    private bool $inTransaction = false;

    private const DEFAULT_CONFIG = [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'dbname'   => '',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
        'timezone' => '+00:00',
    ];

    private function __construct()
    {
        $this->connect();
    }

    private function connect(): void
    {
        $config = $this->loadConfig();

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => sprintf(
                "SET time_zone = '%s'",
                $config['timezone']
            ),
        ];

        try {
            $this->connection = new PDO(
                $dsn,
                $config['user'],
                $config['pass'],
                $options
            );
        } catch (PDOException $e) {
            $this->handleConnectionError($e);
        }
    }

    private function loadConfig(): array
    {
        $config = self::DEFAULT_CONFIG;

        if (defined('DB_HOST'))     $config['host']     = DB_HOST;
        if (defined('DB_PORT'))    $config['port']     = DB_PORT;
        if (defined('DB_NAME'))    $config['dbname']   = DB_NAME;
        if (defined('DB_USER'))    $config['user']     = DB_USER;
        if (defined('DB_PASS'))    $config['pass']     = DB_PASS;
        if (defined('DB_CHARSET')) $config['charset']   = DB_CHARSET;

        if (class_exists(Config::class) && method_exists(Config::class, 'get')) {
            $config['host']   = Config::get('db_host',   $config['host']);
            $config['port']   = (int) Config::get('db_port',   $config['port']);
            $config['dbname'] = Config::get('db_name',   $config['dbname']);
            $config['user']   = Config::get('db_user',   $config['user']);
            $config['pass']   = Config::get('db_pass',   $config['pass']);
            $config['charset']= Config::get('db_charset',$config['charset']);
        }

        return $config;
    }

    private function handleConnectionError(PDOException $e): void
    {
        $logHost = defined('DB_HOST') ? (string) DB_HOST : 'unknown';
        $logPort = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $logDb = defined('DB_NAME') ? (string) DB_NAME : 'unknown';
        if (class_exists(Config::class)) {
            $logHost = (string) Config::get('db_host', $logHost);
            $logPort = (int) Config::get('db_port', $logPort);
            $logDb = (string) Config::get('db_name', $logDb);
        }

        $message = sprintf(
            "[%s] 数据库连接失败: %s | Host: %s:%d | DB: %s",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $logHost,
            $logPort,
            $logDb
        );
        error_log($message);

        if (defined('APP_DEBUG') && APP_DEBUG === true) {
            throw new \RuntimeException(
                "数据库连接失败: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        throw new \RuntimeException(
            "数据库服务暂时不可用，请稍后再试。",
            500,
            $e
        );
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw $this->handleQueryError($e, $sql, $params);
        }
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }

    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw $this->handleQueryError($e, $sql, $params);
        }
    }

    public function insert(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return (int) $this->connection->lastInsertId();
        } catch (PDOException $e) {
            throw $this->handleQueryError($e, $sql, $params);
        }
    }

    public function lastInsertId(): int
    {
        return (int) $this->connection->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        if ($this->inTransaction) {
            return false;
        }
        $this->inTransaction = true;
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        if (!$this->inTransaction) {
            return false;
        }
        $result = $this->connection->commit();
        $this->inTransaction = false;
        return $result;
    }

    public function rollback(): bool
    {
        if (!$this->inTransaction) {
            return false;
        }
        $result = $this->connection->rollBack();
        $this->inTransaction = false;
        return $result;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    private function handleQueryError(PDOException $e, string $sql, array $params): \RuntimeException
    {
        $truncatedParams = array_map(function ($value) {
            if (is_string($value) && strlen($value) > 100) {
                return substr($value, 0, 100) . '...';
            }
            return $value;
        }, $params);

        $message = sprintf(
            "[%s] SQL查询错误: %s\nSQL: %s\nParams: %s",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            substr($sql, 0, 500),
            json_encode($truncatedParams, JSON_UNESCAPED_UNICODE)
        );
        error_log($message);

        if (defined('APP_DEBUG') && APP_DEBUG === true) {
            return new \RuntimeException(
                "SQL执行失败: " . $e->getMessage() . "\nSQL: " . substr($sql, 0, 200),
                (int) $e->getCode(),
                $e
            );
        }

        return new \RuntimeException(
            "数据操作失败，请稍后再试。",
            500,
            $e
        );
    }

    public function quote(string $value): string
    {
        return $this->connection->quote($value);
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function __clone()
    {
        throw new \RuntimeException('Database 单例对象不可克隆');
    }

    public function __wakeup()
    {
        throw new \RuntimeException('Database 单例对象不可反序列化');
    }
}
