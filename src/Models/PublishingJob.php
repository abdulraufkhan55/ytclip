<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class PublishingJob
{
    public static function findById(int $id): ?array
    {
        return Database::fetch(
            "SELECT pj.*, v.title as video_title, v.storage_path, v.duration_seconds, v.original_filename, 
                    sa.account_name, sa.platform as sa_platform, sa.connection_status
             FROM publishing_jobs pj 
             JOIN videos v ON pj.video_id = v.id 
             JOIN social_accounts sa ON pj.social_account_id = sa.id 
             WHERE pj.id = :id",
            ['id' => $id]
        );
    }

    public static function getJobsForVideo(int $videoId): array
    {
        return Database::fetchAll(
            "SELECT pj.*, sa.account_name 
             FROM publishing_jobs pj 
             JOIN social_accounts sa ON pj.social_account_id = sa.id 
             WHERE pj.video_id = :vid 
             ORDER BY pj.created_at DESC",
            ['vid' => $videoId]
        );
    }

    public static function getQueue(array $filters = []): array
    {
        $sql = "SELECT pj.*, v.title as video_title, v.duration_seconds, v.stored_filename, sa.account_name 
                FROM publishing_jobs pj 
                JOIN videos v ON pj.video_id = v.id 
                JOIN social_accounts sa ON pj.social_account_id = sa.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND pj.status = :status";
            $params['status'] = $filters['status'];
        } else {
            // Default queue shows non-finished or pending
            $sql .= " AND pj.status IN ('queued', 'validating', 'uploading', 'processing', 'retry_scheduled', 'awaiting_user_action')";
        }

        if (!empty($filters['platform'])) {
            $sql .= " AND pj.platform = :platform";
            $params['platform'] = $filters['platform'];
        }

        $sql .= " ORDER BY (pj.scheduled_at IS NOT NULL) DESC, pj.scheduled_at ASC, pj.created_at ASC";

        return Database::fetchAll($sql, $params);
    }

    public static function getTrackerList(array $filters = []): array
    {
        $sql = "SELECT v.id as video_id, v.title, v.duration_seconds, v.created_at as video_created_at, v.storage_path, v.thumbnail_path,
                (SELECT GROUP_CONCAT(CONCAT(pj.platform, ':', pj.status, ':', IFNULL(pj.published_url, ''), ':', pj.id) SEPARATOR '||') 
                 FROM publishing_jobs pj WHERE pj.video_id = v.id) as platform_jobs_summary,
                (SELECT MAX(pj.scheduled_at) FROM publishing_jobs pj WHERE pj.video_id = v.id) as scheduled_time,
                (SELECT MAX(pa.finished_at) FROM publishing_attempts pa 
                 JOIN publishing_jobs pj ON pa.publishing_job_id = pj.id 
                 WHERE pj.video_id = v.id) as last_attempt_time,
                (SELECT pj.last_error_message FROM publishing_jobs pj WHERE pj.video_id = v.id AND pj.last_error_message IS NOT NULL ORDER BY pj.updated_at DESC LIMIT 1) as last_error
                FROM videos v 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND v.title LIKE :search";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['platform'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM publishing_jobs pj WHERE pj.video_id = v.id AND pj.platform = :platform)";
            $params['platform'] = $filters['platform'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM publishing_jobs pj WHERE pj.video_id = v.id AND pj.status = :status)";
            $params['status'] = $filters['status'];
        }

        $sql .= " ORDER BY v.created_at DESC";

        return Database::fetchAll($sql, $params);
    }

    public static function createJob(array $data, bool $force = false): ?int
    {
        // Duplicate-publication safeguard: check if existing non-failed/non-cancelled job exists
        if (!$force) {
            $existing = Database::fetch(
                "SELECT id, status FROM publishing_jobs 
                 WHERE video_id = :vid AND social_account_id = :said 
                 AND status NOT IN ('failed', 'cancelled')",
                ['vid' => $data['video_id'], 'said' => $data['social_account_id']]
            );
            if ($existing) {
                return null; // Safeguard triggered
            }
        }

        $data['status'] = $data['status'] ?? 'queued';
        $data['attempt_count'] = 0;
        $data['max_attempts'] = 3;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return Database::insert('publishing_jobs', $data);
    }

    public static function updateStatus(int $id, string $status, array $extra = []): void
    {
        $update = array_merge($extra, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        if ($status === 'published' && !isset($update['published_at'])) {
            $update['published_at'] = date('Y-m-d H:i:s');
        }

        Database::update('publishing_jobs', $update, 'id = :id', ['id' => $id]);

        // Sync parent video status
        $job = Database::fetch("SELECT video_id FROM publishing_jobs WHERE id = :id", ['id' => $id]);
        if ($job) {
            self::syncVideoStatus((int) $job['video_id']);
        }
    }

    public static function syncVideoStatus(int $videoId): void
    {
        $jobs = Database::fetchAll("SELECT status FROM publishing_jobs WHERE video_id = :vid", ['vid' => $videoId]);
        if (empty($jobs)) {
            return;
        }

        $statuses = array_column($jobs, 'status');
        $allPublished = true;
        $anyPublished = false;
        $anyFailed = false;
        $anyProcessing = false;

        foreach ($statuses as $s) {
            if ($s === 'published') {
                $anyPublished = true;
            } else {
                $allPublished = false;
            }

            if ($s === 'failed') {
                $anyFailed = true;
            }
            if (in_array($s, ['uploading', 'processing', 'validating'])) {
                $anyProcessing = true;
            }
        }

        if ($allPublished) {
            $vStatus = 'published';
        } elseif ($anyPublished) {
            $vStatus = 'partially_published';
        } elseif ($anyProcessing) {
            $vStatus = 'processing';
        } elseif ($anyFailed && !in_array('queued', $statuses) && !in_array('retry_scheduled', $statuses)) {
            $vStatus = 'failed';
        } else {
            $vStatus = 'ready';
        }

        Database::update('videos', ['video_status' => $vStatus, 'updated_at' => date('Y-m-d H:i:s')], 'id = :vid', ['vid' => $videoId]);
    }

    /**
     * Atomically locks the next ready job for the worker.
     */
    public static function acquireNextJob(string $workerId): ?array
    {
        $now = date('Y-m-d H:i:s');

        // Check for jobs that are queued or retry_scheduled where scheduled_at/next_retry_at is due
        $sql = "SELECT id FROM publishing_jobs 
                WHERE (
                    (status = 'queued' AND (scheduled_at IS NULL OR scheduled_at <= :now1))
                    OR
                    (status = 'retry_scheduled' AND next_retry_at <= :now2)
                )
                AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE))
                ORDER BY (scheduled_at IS NOT NULL) DESC, scheduled_at ASC, id ASC 
                LIMIT 1";

        $row = Database::fetch($sql, ['now1' => $now, 'now2' => $now]);
        if (!$row) {
            return null;
        }

        $jobId = (int) $row['id'];
        $affected = Database::update(
            'publishing_jobs',
            [
                'locked_at' => date('Y-m-d H:i:s'),
                'locked_by' => $workerId,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = :id AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE))',
            ['id' => $jobId]
        );

        if ($affected > 0) {
            return self::findById($jobId);
        }

        return null;
    }

    public static function releaseLock(int $id): void
    {
        Database::update('publishing_jobs', [
            'locked_at' => null,
            'locked_by' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $id]);
    }

    public static function recoverStaleJobs(): int
    {
        $tenMinutesAgo = date('Y-m-d H:i:s', strtotime('-10 minutes'));
        $stmt = Database::query(
            "UPDATE publishing_jobs 
             SET locked_at = NULL, locked_by = NULL, status = 'queued', updated_at = NOW() 
             WHERE locked_at IS NOT NULL AND locked_at < :cutoff AND status IN ('validating', 'uploading', 'processing')",
            ['cutoff' => $tenMinutesAgo]
        );
        return $stmt->rowCount();
    }

    public static function getPlatformStats(): array
    {
        $platforms = ['youtube', 'tiktok', 'instagram'];
        $stats = [];

        foreach ($platforms as $p) {
            $total = Database::fetch("SELECT COUNT(*) as cnt FROM publishing_jobs WHERE platform = :p", ['p' => $p]);
            $published = Database::fetch("SELECT COUNT(*) as cnt FROM publishing_jobs WHERE platform = :p AND status = 'published'", ['p' => $p]);
            $failed = Database::fetch("SELECT COUNT(*) as cnt FROM publishing_jobs WHERE platform = :p AND status = 'failed'", ['p' => $p]);
            $queued = Database::fetch("SELECT COUNT(*) as cnt FROM publishing_jobs WHERE platform = :p AND status IN ('queued', 'retry_scheduled')", ['p' => $p]);
            $uploading = Database::fetch("SELECT COUNT(*) as cnt FROM publishing_jobs WHERE platform = :p AND status IN ('uploading', 'processing', 'validating')", ['p' => $p]);
            $scheduled = Database::fetch("SELECT COUNT(*) as cnt FROM publishing_jobs WHERE platform = :p AND scheduled_at IS NOT NULL AND status = 'queued'", ['p' => $p]);

            $stats[$p] = [
                'total' => (int) ($total['cnt'] ?? 0),
                'published' => (int) ($published['cnt'] ?? 0),
                'failed' => (int) ($failed['cnt'] ?? 0),
                'queued' => (int) ($queued['cnt'] ?? 0),
                'uploading' => (int) ($uploading['cnt'] ?? 0),
                'scheduled' => (int) ($scheduled['cnt'] ?? 0)
            ];
        }

        return $stats;
    }

    public static function getOverallStats(): array
    {
        $totalVideos = Database::fetch("SELECT COUNT(*) as cnt FROM videos");
        $readyVideos = Database::fetch("SELECT COUNT(*) as cnt FROM videos WHERE video_status = 'ready'");
        $queuedJobs = Database::fetch("SELECT COUNT(*) as cnt FROM publishing_jobs WHERE status = 'queued'");
        $uploadingJobs = Database::fetch("SELECT COUNT(*) as cnt FROM publishing_jobs WHERE status IN ('uploading', 'processing', 'validating')");
        $publishedJobs = Database::fetch("SELECT COUNT(*) as cnt FROM publishing_jobs WHERE status = 'published'");
        $partiallyVideos = Database::fetch("SELECT COUNT(*) as cnt FROM videos WHERE video_status = 'partially_published'");
        $failedJobs = Database::fetch("SELECT COUNT(*) as cnt FROM publishing_jobs WHERE status = 'failed'");
        $scheduledPosts = Database::fetch("SELECT COUNT(*) as cnt FROM publishing_jobs WHERE scheduled_at IS NOT NULL AND status = 'queued'");
        $connectedAccounts = Database::fetch("SELECT COUNT(*) as cnt FROM social_accounts WHERE connection_status = 'connected'");

        return [
            'total_videos' => (int) ($totalVideos['cnt'] ?? 0),
            'ready_videos' => (int) ($readyVideos['cnt'] ?? 0),
            'queued_jobs' => (int) ($queuedJobs['cnt'] ?? 0),
            'uploading_jobs' => (int) ($uploadingJobs['cnt'] ?? 0),
            'published_jobs' => (int) ($publishedJobs['cnt'] ?? 0),
            'partially_published' => (int) ($partiallyVideos['cnt'] ?? 0),
            'failed_jobs' => (int) ($failedJobs['cnt'] ?? 0),
            'scheduled_posts' => (int) ($scheduledPosts['cnt'] ?? 0),
            'connected_accounts' => (int) ($connectedAccounts['cnt'] ?? 0),
        ];
    }
}
