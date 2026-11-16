<?php

declare(strict_types=1);

namespace App\Adapters;

interface PlatformAdapterInterface
{
    /**
     * Validate media file specifications for this platform (duration, resolution, format)
     */
    public function validateMedia(array $video): array;

    /**
     * Validate title, caption, hashtags, description against platform limits
     */
    public function validateMetadata(array $metadata): array;

    /**
     * Publish video or initialize publishing process
     */
    public function publish(array $job, array $video, array $socialAccount): array;

    /**
     * Query processing status for asynchronous publishing
     */
    public function getPublishingStatus(string $platformPublishId, array $socialAccount): array;

    /**
     * Refresh OAuth access token using refresh token
     */
    public function refreshAuthorization(array $socialAccount): array;

    /**
     * Return public URL of published short/reel
     */
    public function getPublishedUrl(string $platformMediaId, ?array $metadata = null): string;

    /**
     * Whether this platform supports direct publishing
     */
    public function supportsDirectPublishing(): bool;

    /**
     * Whether this platform natively supports scheduling
     */
    public function supportsScheduling(): bool;
}
