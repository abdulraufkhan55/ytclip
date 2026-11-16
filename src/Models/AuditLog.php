<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class AuditLog
{
    public static function getRecent(int $limit = 100): array
    {
        return Database::fetchAll(
            "SELECT al.*, u.name as user_name, u.email as user_email 
             FROM audit_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             ORDER BY al.created_at DESC LIMIT :lim",
            ['lim' => $limit]
        );
    }

    public static function count(): int
    {
        $res = Database::fetch("SELECT COUNT(*) as cnt FROM audit_logs");
        return (int) ($res['cnt'] ?? 0);
    }
}
