<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Core\Logger;

class MockPublisher extends AbstractPlatformAdapter
{
    private string $platform;

    public function __construct(string $platform, array $config = [])
    {
        parent::__construct($config);
        $this->platform = $platform;
    }

    public function validateMedia(array $video): array
    {
        $errors = [];
        if (($video['duration_seconds'] ?? 0) > 60) {
            $errors[] = "[MOCK] Video duration exceeds 60 seconds.";
        }
        if (!file_exists($video['storage_path'] ?? '')) {
            $errors[] = "[MOCK] Video file does not exist on disk.";
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function validateMetadata(array $metadata): array
    {
        $errors = [];
        $title = $metadata['title'] ?? ($metadata['caption'] ?? '');
        if (mb_strlen($title) === 0) {
            $errors[] = "[MOCK] Title or caption is required.";
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function publish(array $job, array $video, array $socialAccount): array
    {
        // Simulate network latency / upload progress
        usleep(300000); // 300ms

        $randomId = bin2hex(random_bytes(6));
        $mediaId = "mock_{$this->platform}_{$randomId}";

        Logger::info("[MOCK PUBLISHER] Simulated successful publishing", [
            'platform' => $this->platform,
            'job_id' => $job['id'],
            'media_id' => $mediaId
        ]);

        return [
            'success' => true,
            'status' => 'published',
            'platform_media_id' => $mediaId,
            'platform_publish_id' => "pub_{$randomId}",
            'published_url' => $this->getPublishedUrl($mediaId, ['account_name' => $socialAccount['account_name']]),
            'http_status' => 200,
            'response' => [
                'mode' => 'SIMULATED_MOCK_MODE',
                'media_id' => $mediaId,
                'published_at' => date('Y-m-d H:i:s'),
                'note' => 'Published in Mock Mode for testing without posting to actual platform.'
            ]
        ];
    }

    public function getPublishingStatus(string $platformPublishId, array $socialAccount): array
    {
        return [
            'success' => true,
            'status' => 'published',
            'platform_media_id' => $platformPublishId,
            'published_url' => $this->getPublishedUrl($platformPublishId, ['account_name' => $socialAccount['account_name']])
        ];
    }

    public function refreshAuthorization(array $socialAccount): array
    {
        return [
            'success' => true,
            'access_token' => 'mock_access_token_' . time()
        ];
    }

    public function getPublishedUrl(string $platformMediaId, ?array $metadata = null): string
    {
        $acc = $metadata['account_name'] ?? 'demo_channel';
        return match ($this->platform) {
            'youtube' => 'https://www.youtube.com/shorts/' . $platformMediaId,
            'tiktok' => 'https://www.tiktok.com/@' . ltrim($acc, '@') . '/video/' . $platformMediaId,
            'instagram' => 'https://www.instagram.com/reel/' . $platformMediaId . '/',
            default => 'https://example.com/' . $platformMediaId
        };
    }

    public function supportsDirectPublishing(): bool
    {
        return true;
    }

    public function supportsScheduling(): bool
    {
        return true;
    }
}
