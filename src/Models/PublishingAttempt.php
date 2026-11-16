<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;

class PublishingAttempt
{
    public static function record(array $data): int
    {
        $data['started_at'] = $data['started_at'] ?? date('Y-m-d H:i:s');
        if (isset($data['sanitized_response']) && is_array($data['sanitized_response'])) {
            $data['sanitized_response'] = json_encode(Logger::sanitize($data['sanitized_response']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        return Database::insert('publishing_attempts', $data);
    }

    public static function getForJob(int $jobId): array
    {
        return Database::fetchAll(
            "SELECT * FROM publishing_attempts WHERE publishing_job_id = :jid ORDER BY attempt_number DESC, started_at DESC",
            ['jid' => $jobId]
        );
    }

    public static function getAll(array $filters = []): array
    {
        $sql = "SELECT pa.*, pj.platform, pj.status as job_status, v.title as video_title, sa.account_name 
                FROM publishing_attempts pa 
                JOIN publishing_jobs pj ON pa.publishing_job_id = pj.id 
                JOIN videos v ON pj.video_id = v.id 
                JOIN social_accounts sa ON pj.social_account_id = sa.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['outcome'])) {
            $sql .= " AND pa.outcome = :outcome";
            $params['outcome'] = $filters['outcome'];
        }

        if (!empty($filters['platform'])) {
            $sql .= " AND pj.platform = :platform";
            $params['platform'] = $filters['platform'];
        }

        $sql .= " ORDER BY pa.started_at DESC LIMIT 200";

        return Database::fetchAll($sql, $params);
    }
}
