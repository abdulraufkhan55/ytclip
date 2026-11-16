<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\PublishingAttempt;
use App\Models\PublishingJob;
use App\Models\Setting;
use App\Models\Video;
use RuntimeException;

class QueueManager
{
    /**
     * Dispatch video to multiple selected accounts across platforms
     */
    public static function dispatch(int $videoId, array $accountIds, array $metadataPerPlatform = [], ?string $scheduledAt = null, bool $force = false): array
    {
        $video = Video::findById($videoId);
        if (!$video) {
            throw new RuntimeException("Video #{$videoId} not found.");
        }

        // Daily publishing limit check
        $dailyLimit = (int) Setting::get('daily_publishing_limit', 50);
        $todayJobs = Database::fetch("SELECT COUNT(*) as cnt FROM publishing_jobs WHERE DATE(created_at) = CURDATE()");
        if ((int) ($todayJobs['cnt'] ?? 0) + count($accountIds) > $dailyLimit) {
            throw new RuntimeException("Daily publishing limit of {$dailyLimit} posts reached. Try again tomorrow or increase limit in Settings.");
        }

        $createdJobs = [];
        $skippedAccounts = [];

        foreach ($accountIds as $accountId) {
            $account = Database::fetch("SELECT * FROM social_accounts WHERE id = :id", ['id' => (int) $accountId]);
            if (!$account) {
                continue;
            }

            $platform = $account['platform'];
            $meta = $metadataPerPlatform[$platform] ?? [];

            $jobData = [
                'video_id' => $videoId,
                'social_account_id' => (int) $accountId,
                'platform' => $platform,
                'platform_title' => $meta['title'] ?? $video['title'],
                'platform_caption' => $meta['caption'] ?? $video['default_caption'],
                'platform_description' => $meta['description'] ?? $video['description'],
                'scheduled_at' => !empty($scheduledAt) ? date('Y-m-d H:i:s', strtotime($scheduledAt)) : null,
                'status' => 'queued',
            ];

            $jobId = PublishingJob::createJob($jobData, $force);
            if ($jobId) {
                $createdJobs[] = $jobId;
                Logger::audit('job_created', 'publishing_job', (string) $jobId, [
                    'video_id' => $videoId,
                    'platform' => $platform,
                    'account' => $account['account_name']
                ]);
            } else {
                $skippedAccounts[] = $account['account_name'] . " ({$platform})";
            }
        }

        // Update video status to ready or scheduled
        if (!empty($createdJobs)) {
            Video::updateStatus($videoId, !empty($scheduledAt) ? 'ready' : 'ready');
        }

        return [
            'created_job_ids' => $createdJobs,
            'skipped_accounts' => $skippedAccounts
        ];
    }

    /**
     * Retry a failed publishing job
     */
    public static function retryJob(int $jobId): bool
    {
        $job = PublishingJob::findById($jobId);
        if (!$job) {
            return false;
        }

        // Reset job status to queued and clear lock
        PublishingJob::updateStatus($jobId, 'queued', [
            'locked_at' => null,
            'locked_by' => null,
            'next_retry_at' => null,
            'last_error_code' => null,
            'last_error_message' => null
        ]);

        Logger::audit('job_retry', 'publishing_job', (string) $jobId, [
            'platform' => $job['platform'],
            'previous_attempts' => $job['attempt_count']
        ]);

        return true;
    }

    /**
     * Cancel a pending or queued job
     */
    public static function cancelJob(int $jobId): bool
    {
        $job = PublishingJob::findById($jobId);
        if (!$job || in_array($job['status'], ['published', 'uploading', 'processing'])) {
            return false;
        }

        PublishingJob::updateStatus($jobId, 'cancelled', [
            'locked_at' => null,
            'locked_by' => null
        ]);

        PublishingAttempt::record([
            'publishing_job_id' => $jobId,
            'attempt_number' => ((int) $job['attempt_count']) + 1,
            'started_at' => date('Y-m-d H:i:s'),
            'finished_at' => date('Y-m-d H:i:s'),
            'outcome' => 'cancelled',
            'error_message' => 'Job manually cancelled by operator.'
        ]);

        Logger::audit('job_cancelled', 'publishing_job', (string) $jobId);

        return true;
    }
}
