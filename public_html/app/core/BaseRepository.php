<?php

namespace App\Core;

use PDOException;

abstract class BaseRepository
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    public function findById(int $id): ?array
    {
        $sql = sprintf(
            "SELECT * FROM `%s` WHERE `%s` = :id LIMIT 1",
            $this->table,
            $this->primaryKey
        );
        return $this->db->fetch($sql, ['id' => $id]);
    }

    public function findAll(array $conditions = [], string $orderBy = '', int $limit = 0, int $offset = 0): array
    {
        $where = '';
        $params = [];

        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $key => $value) {
                if ($value === null) {
                    $whereParts[] = "`{$key}` IS NULL";
                } elseif (is_array($value)) {
                    $placeholders = implode(',', array_fill(0, count($value), '?'));
                    $whereParts[] = "`{$key}` IN ({$placeholders})";
                    $params = array_merge($params, $value);
                } else {
                    $whereParts[] = "`{$key}` = ?";
                    $params[] = $value;
                }
            }
            $where = 'WHERE ' . implode(' AND ', $whereParts);
        }

        $sql = sprintf("SELECT * FROM `%s` %s", $this->table, $where);

        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        } else {
            $sql .= sprintf(" ORDER BY `%s` DESC", $this->primaryKey);
        }

        if ($limit > 0) {
            $sql .= " LIMIT " . (int) $limit;
            if ($offset > 0) {
                $sql .= " OFFSET " . (int) $offset;
            }
        }

        return $this->db->fetchAll($sql, $params);
    }

    public function count(array $conditions = []): int
    {
        $where = '';
        $params = [];

        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $key => $value) {
                if ($value === null) {
                    $whereParts[] = "`{$key}` IS NULL";
                } elseif (is_array($value)) {
                    $placeholders = implode(',', array_fill(0, count($value), '?'));
                    $whereParts[] = "`{$key}` IN ({$placeholders})";
                    $params = array_merge($params, $value);
                } else {
                    $whereParts[] = "`{$key}` = ?";
                    $params[] = $value;
                }
            }
            $where = 'WHERE ' . implode(' AND ', $whereParts);
        }

        $sql = sprintf("SELECT COUNT(*) as cnt FROM `%s` %s", $this->table, $where);
        $result = $this->db->fetch($sql, $params);
        return (int) ($result['cnt'] ?? 0);
    }

    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = sprintf(
            "INSERT INTO `%s` (`%s`) VALUES (%s)",
            $this->table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        return $this->db->insert($sql, $data);
    }

    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $sets = array_map(fn($col) => "`{$col}` = :{$col}", array_keys($data));

        $sql = sprintf(
            "UPDATE `%s` SET %s WHERE `%s` = :{$this->primaryKey}",
            $this->table,
            implode(', ', $sets),
            $this->primaryKey
        );

        $data[$this->primaryKey] = $id;
        $affected = $this->db->execute($sql, $data);
        return $affected > 0;
    }

    public function delete(int $id): bool
    {
        $sql = sprintf(
            "DELETE FROM `%s` WHERE `%s` = :id",
            $this->table,
            $this->primaryKey
        );
        $affected = $this->db->execute($sql, ['id' => $id]);
        return $affected > 0;
    }

    public function exists(int $id): bool
    {
        $sql = sprintf(
            "SELECT 1 FROM `%s` WHERE `%s` = :id LIMIT 1",
            $this->table,
            $this->primaryKey
        );
        return $this->db->fetch($sql, ['id' => $id]) !== null;
    }

    public function findOneBy(array $conditions): ?array
    {
        $result = $this->findAll($conditions, '', 1, 0);
        return $result[0] ?? null;
    }

    public function getTableName(): string
    {
        return $this->table;
    }

    protected function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    protected function commit(): bool
    {
        return $this->db->commit();
    }

    protected function rollback(): bool
    {
        return $this->db->rollback();
    }

    protected function inTransaction(): bool
    {
        return $this->db->inTransaction();
    }

    protected function logActivity(
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValue = null,
        ?array $newValue = null
    ): void {
        try {
            $logTable = 'activity_logs';
            $columns = ['user_id', 'action', 'ip_address', 'user_agent', 'created_at'];
            $placeholders = [':user_id', ':action', ':ip_address', ':user_agent', 'NOW()'];
            $params = [
                'user_id' => $userId,
                'action' => $action,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
            ];

            if ($entityType !== null) {
                $columns[] = 'entity_type';
                $placeholders[] = ':entity_type';
                $params['entity_type'] = $entityType;
            }

            if ($entityId !== null) {
                $columns[] = 'entity_id';
                $placeholders[] = ':entity_id';
                $params['entity_id'] = $entityId;
            }

            if ($oldValue !== null) {
                $columns[] = 'old_value';
                $placeholders[] = ':old_value';
                $params['old_value'] = json_encode($oldValue, JSON_UNESCAPED_UNICODE);
            }

            if ($newValue !== null) {
                $columns[] = 'new_value';
                $placeholders[] = ':new_value';
                $params['new_value'] = json_encode($newValue, JSON_UNESCAPED_UNICODE);
            }

            $sql = sprintf(
                "INSERT INTO `%s` (`%s`) VALUES (%s)",
                $logTable,
                implode('`, `', $columns),
                implode(', ', $placeholders)
            );

            $this->db->insert($sql, $params);
        } catch (\Throwable $e) {
            error_log("[ActivityLog] Failed to log: " . $e->getMessage());
        }
    }
}
