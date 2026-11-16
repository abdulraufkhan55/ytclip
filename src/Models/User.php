<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class User
{
    public static function findById(int $id): ?array
    {
        return Database::fetch("SELECT * FROM users WHERE id = :id", ['id' => $id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetch("SELECT * FROM users WHERE email = :email", ['email' => $email]);
    }

    public static function getAll(): array
    {
        return Database::fetchAll("SELECT id, name, email, role, status, created_at, updated_at FROM users ORDER BY created_at DESC");
    }

    public static function count(): int
    {
        $res = Database::fetch("SELECT COUNT(*) as cnt FROM users");
        return (int) ($res['cnt'] ?? 0);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::insert('users', $data);
    }

    public static function update(int $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::update('users', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): int
    {
        $stmt = Database::query("DELETE FROM users WHERE id = :id", ['id' => $id]);
        return $stmt->rowCount();
    }
}
