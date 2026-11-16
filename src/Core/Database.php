<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = require dirname(__DIR__, 2) . '/config/database.php';

            $dsn = sprintf(
                '%s:host=%s;port=%d;dbname=%s;charset=%s',
                $config['driver'],
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
            } catch (PDOException $e) {
                Logger::error('Database connection failed: ' . $e->getMessage());
                throw new RuntimeException('Database connection failed. Please verify your configuration or run install.php: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $res = self::query($sql, $params)->fetch();
        return $res ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int
    {
        $keys = array_keys($data);
        $fields = implode(', ', array_map(fn($k) => "`{$k}`", $keys));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", $keys));

        $sql = "INSERT INTO `{$table}` ({$fields}) VALUES ({$placeholders})";
        self::query($sql, $data);
        return (int) self::getConnection()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = [];
        $params = [];
        foreach ($data as $k => $v) {
            $set[] = "`{$k}` = :set_{$k}";
            $params["set_{$k}"] = $v;
        }
        $setString = implode(', ', $set);
        $sql = "UPDATE `{$table}` SET {$setString} WHERE {$where}";
        $stmt = self::query($sql, array_merge($params, $whereParams));
        return $stmt->rowCount();
    }

    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    public static function rollBack(): bool
    {
        return self::getConnection()->rollBack();
    }
}
