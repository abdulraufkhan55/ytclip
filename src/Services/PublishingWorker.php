<?php

declare(strict_types=1);

namespace App\Services;

use App\Adapters\AdapterFactory;
use App\Core\Logger;
use App\Models\PublishingAttempt;
use App\Models\PublishingJob;
use App\Models\SocialAccount;
use App\Models\Video;

class PublishingWorker
{
    private string $workerId;
    private bool $shouldStop = false;

    public function __construct(?string $workerId = null)
    {
        $this->workerId = $workerId ?: 'worker_' . getmypid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Run a single iteration of the worker: recovers stale jobs, polls async jobs, and processes next ready job.
     * Returns true if work was done, false if idle.
     */
    public function runOnce(): bool
    {
        // 1. Recover stale jobs locked > 10 mins
        $recovered = PublishingJob::recoverStaleJobs();
        if ($recovered > 0) {
            Logger::warning("Worker recovered {$recovered} stale jobs.");
        }

        // 2. Poll ongoing asynchronous jobs (processing status)
        $this->pollProcessingJobs();

        // 3. Acquire next queued or scheduled job
        $job = PublishingJob::acquireNextJob($this->workerId);
        if (!$job) {
            return false;
        }

        $this->processJob($job);
        return true;
    }

    /**
     * Continuous loop for CLI daemon worker
     */
    public function loop(int $sleepSeconds = 5, int $maxIterations = 0): void
    {
        Logger::info("Publishing Worker started", ['worker_id' => $this->workerId]);
        $iterations = 0;

        while (!$this->shouldStop) {
            $worked = $this->runOnce();
            $iterations++;

            if ($maxIterations > 0 && $iterations >= $maxIterations) {
                break;
            }

            if (!$worked) {
                sleep($sleepSeconds);
            }
        }

        Logger::info("Publishing Worker gracefully stopped", ['worker_id' => $this->workerId]);
    }

    /**
     * Process a single publishing job
     */
    public function processJob(array $job): void
    {
        $jobId = (int) $job['id'];
        $startedAt = date('Y-m-d H:i:s');
        $attemptNumber = ((int) $job['attempt_count']) + 1;

        Logger::info("Worker processing publishing job #{$jobId}", [
            'platform' => $job['platform'],
            'video_id' => $job['video_id'],
            'attempt' => $attemptNumber
        ]);

        try {
            // Step 1: Update status to 'validating'
            PublishingJob::updateStatus($jobId, 'validating', ['attempt_count' => $attemptNumber]);

            $video = Video::findById((int) $job['video_id']);
            $socialAccount = SocialAccount::findById((int) $job['social_account_id']);

            if (!$video || !file_exists($video['storage_path'])) {
                throw new \RuntimeException("Video file missing or deleted from storage.");
            }

            if (!$socialAccount || $socialAccount['connection_status'] !== 'connected') {
                throw new \RuntimeException("Social account is disconnected or invalid.");
            }

            $adapter = AdapterFactory::create($job['platform'], $socialAccount);

            // Validate media
            $mediaCheck = $adapter->validateMedia($video);
            if (!$mediaCheck['valid']) {
                throw new \RuntimeException(implode(' ', $mediaCheck['errors']));
            }

            // Validate metadata
            $metaCheck = $adapter->validateMetadata([
                'title' => $job['platform_title'] ?: $video['title'],
                'caption' => $job['platform_caption'] ?: $video['default_caption'],
                'description' => $job['platform_description'] ?: $video['description']
            ]);
            if (!$metaCheck['valid']) {
                throw new \RuntimeException(implode(' ', $metaCheck['errors']));
            }

            // Step 2: Update status to 'uploading'
            PublishingJob::updateStatus($jobId, 'uploading');

            // Step 3: Execute publishing API call
            $result = $adapter->publish($job, $video, $socialAccount);

            $finishedAt = date('Y-m-d H:i:s');

            if ($result['success']) {
                $status = $result['status'] ?? 'published';
                $extra = [
                    'platform_media_id' => $result['platform_media_id'] ?? null,
                    'platform_publish_id' => $result['platform_publish_id'] ?? null,
                    'published_url' => $result['published_url'] ?? null,
                    'locked_at' => null,
                    'locked_by' => null,
                    'last_error_code' => null,
                    'last_error_message' => null
                ];

                PublishingJob::updateStatus($jobId, $status, $extra);

                PublishingAttempt::record([
                    'publishing_job_id' => $jobId,
                    'attempt_number' => $attemptNumber,
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                    'outcome' => $status === 'published' ? 'success' : 'processing',
                    'http_status' => $result['http_status'] ?? 200,
                    'sanitized_response' => $result['response'] ?? [],
                    'error_message' => null
                ]);

                Logger::info("Job #{$jobId} published/dispatched successfully", ['status' => $status]);
            } else {
                $this->handleJobFailure($job, $attemptNumber, $startedAt, $finishedAt, $result);
            }
        } catch (\Throwable $e) {
            $finishedAt = date('Y-m-d H:i:s');
            $failureData = [
                'error_code' => 'EXCEPTION',
                'error_message' => $e->getMessage(),
                'http_status' => 500,
                'response' => ['exception' => $e->getMessage()]
            ];
            $this->handleJobFailure($job, $attemptNumber, $startedAt, $finishedAt, $failureData);
        }
    }

    /**
     * Handles failure with exponential backoff retry scheduling or final failure
     */
    private function handleJobFailure(array $job, int $attemptNumber, string $startedAt, string $finishedAt, array $failure): void
    {
        $jobId = (int) $job['id'];
        $maxAttempts = (int) ($job['max_attempts'] ?? 3);
        $errorCode = $failure['error_code'] ?? 'UNKNOWN_ERROR';
        $errorMessage = $failure['error_message'] ?? 'Publishing attempt failed.';

        Logger::error("Publishing job #{$jobId} failed attempt {$attemptNumber}/{$maxAttempts}: {$errorMessage}");

        PublishingAttempt::record([
            'publishing_job_id' => $jobId,
            'attempt_number' => $attemptNumber,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'outcome' => 'failed',
            'http_status' => $failure['http_status'] ?? null,
            'error_code' => $errorCode,
            'sanitized_response' => $failure['response'] ?? null,
            'error_message' => $errorMessage
        ]);

        if ($attemptNumber < $maxAttempts && !in_array($errorCode, ['AUTH_REQUIRED', 'VALIDATION_FAILED'])) {
            // Exponential backoff: attempt 1 -> 2m, attempt 2 -> 10m, attempt 3 -> 60m
            $backoffMinutes = match ($attemptNumber) {
                1 => 2,
                2 => 10,
                default => 60
            };
            $nextRetryAt = date('Y-m-d H:i:s', strtotime("+{$backoffMinutes} minutes"));

            PublishingJob::updateStatus($jobId, 'retry_scheduled', [
                'next_retry_at' => $nextRetryAt,
                'locked_at' => null,
                'locked_by' => null,
                'last_error_code' => $errorCode,
                'last_error_message' => "Attempt {$attemptNumber} failed: {$errorMessage}. Next retry at {$nextRetryAt}."
            ]);
        } else {
            // Permanent failure
            PublishingJob::updateStatus($jobId, 'failed', [
                'locked_at' => null,
                'locked_by' => null,
                'last_error_code' => $errorCode,
                'last_error_message' => $errorMessage
            ]);
        }
    }

    /**
     * Poll processing status for asynchronous jobs (TikTok & Instagram media containers)
     */
    private function pollProcessingJobs(): void
    {
        $jobs = PublishingJob::getQueue(['status' => 'processing']);
        foreach ($jobs as $job) {
            if (empty($job['platform_publish_id'])) {
                continue;
            }

            try {
                $account = SocialAccount::findById((int) $job['social_account_id']);
                if (!$account) {
                    continue;
                }

                $adapter = AdapterFactory::create($job['platform']);
                $statusRes = $adapter->getPublishingStatus($job['platform_publish_id'], $account);

                if ($statusRes['status'] === 'published') {
                    PublishingJob::updateStatus((int) $job['id'], 'published', [
                        'platform_media_id' => $statusRes['platform_media_id'] ?? $job['platform_publish_id'],
                        'published_url' => $statusRes['published_url'] ?? null,
                        'last_error_message' => null
                    ]);

                    PublishingAttempt::record([
                        'publishing_job_id' => (int) $job['id'],
                        'attempt_number' => ((int) $job['attempt_count']) + 1,
                        'started_at' => date('Y-m-d H:i:s'),
                        'finished_at' => date('Y-m-d H:i:s'),
                        'outcome' => 'success',
                        'error_message' => 'Async processing completed and verified.'
                    ]);
                } elseif ($statusRes['status'] === 'failed') {
                    PublishingJob::updateStatus((int) $job['id'], 'failed', [
                        'last_error_message' => $statusRes['error_message'] ?? 'Async media processing failed.'
                    ]);
                }
            } catch (\Throwable $e) {
                Logger::error("Error polling async job #{$job['id']}: " . $e->getMessage());
            }
        }
    }
}
