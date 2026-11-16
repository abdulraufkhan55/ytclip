<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Core\Logger;
use App\Models\Setting;
use App\Models\SocialAccount;

class InstagramPublisher extends AbstractPlatformAdapter
{
    public function validateMedia(array $video): array
    {
        $errors = [];
        if (($video['duration_seconds'] ?? 0) < 3 || ($video['duration_seconds'] ?? 0) > 90) {
            $errors[] = "Instagram Reels duration must be between 3 and 90 seconds (standard recommendation: 30-60s).";
        }
        if (!file_exists($video['storage_path'] ?? '')) {
            $errors[] = "Video file does not exist on disk.";
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function validateMetadata(array $metadata): array
    {
        $errors = [];
        $caption = $metadata['caption'] ?? ($metadata['title'] ?? '');
        if (mb_strlen($caption) > 2200) {
            $errors[] = "Instagram caption cannot exceed 2200 characters.";
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function publish(array $job, array $video, array $socialAccount): array
    {
        $tokens = SocialAccount::getDecryptedTokens((int) $socialAccount['id']);
        if (!$tokens || empty($tokens['access_token'])) {
            return [
                'success' => false,
                'status' => 'failed',
                'error_code' => 'AUTH_REQUIRED',
                'error_message' => 'No active OAuth access token found for Instagram account.',
                'http_status' => 401
            ];
        }

        $accessToken = $tokens['access_token'];
        $igUserId = $socialAccount['account_id'];

        // Instagram requires a publicly reachable HTTPS video URL
        // We construct public download stream URL using app_url
        $appUrl = rtrim((string) Setting::get('app_url', 'http://localhost/ytclip'), '/');
        $videoUrl = $appUrl . '/api/videos/' . $video['id'] . '/stream';

        $caption = $job['platform_caption'] ?: ($job['platform_title'] ?: $video['title']);
        if (!empty($video['hashtags']) && !str_contains($caption, '#')) {
            $caption .= "\n\n" . $video['hashtags'];
        }

        // Step 1: Create IG Reels Media Container
        $createUrl = "https://graph.facebook.com/v19.0/{$igUserId}/media";
        $postData = [
            'media_type' => 'REELS',
            'video_url' => $videoUrl,
            'caption' => mb_substr($caption, 0, 2200),
            'share_to_feed' => 'true',
            'access_token' => $accessToken
        ];

        $createRes = $this->makeHttpRequest($createUrl, 'POST', ['Content-Type: application/x-www-form-urlencoded'], $postData);

        if (!$createRes['success'] || empty($createRes['response']['id'])) {
            $errMsg = $createRes['response']['error']['message'] ?? 'Failed to create Instagram Reels container.';
            $errCode = $createRes['response']['error']['code'] ?? 'IG_CONTAINER_FAILED';

            return [
                'success' => false,
                'status' => 'failed',
                'error_code' => (string) $errCode,
                'error_message' => "Instagram API error: {$errMsg} (Note: Instagram requires the video URL to be publicly accessible over HTTPS).",
                'http_status' => $createRes['http_status'],
                'response' => $createRes['response']
            ];
        }

        $containerId = (string) $createRes['response']['id'];

        // Step 2: Check status or return as processing for worker polling
        $statusCheck = $this->getPublishingStatus($containerId, $socialAccount);
        if ($statusCheck['status'] === 'published') {
            return $statusCheck;
        }

        return [
            'success' => true,
            'status' => 'processing',
            'platform_publish_id' => $containerId,
            'platform_media_id' => null,
            'http_status' => 200,
            'response' => [
                'container_id' => $containerId,
                'message' => 'Instagram Reel media container created. Video is being downloaded and transcoded by Meta.'
            ]
        ];
    }

    public function getPublishingStatus(string $platformPublishId, array $socialAccount): array
    {
        $tokens = SocialAccount::getDecryptedTokens((int) $socialAccount['id']);
        $accessToken = $tokens['access_token'] ?? '';
        $igUserId = $socialAccount['account_id'];

        // Check container status
        $statusUrl = "https://graph.facebook.com/v19.0/{$platformPublishId}?fields=status_code,status&access_token={$accessToken}";
        $res = $this->makeHttpRequest($statusUrl);

        if (!$res['success']) {
            return [
                'success' => false,
                'status' => 'failed',
                'error_message' => $res['error_message'] ?? 'Failed to check Instagram container status.'
            ];
        }

        $statusCode = $res['response']['status_code'] ?? 'IN_PROGRESS';

        if ($statusCode === 'FINISHED') {
            // Container is ready, publish it now
            $publishUrl = "https://graph.facebook.com/v19.0/{$igUserId}/media_publish";
            $pubRes = $this->makeHttpRequest($publishUrl, 'POST', ['Content-Type: application/x-www-form-urlencoded'], [
                'creation_id' => $platformPublishId,
                'access_token' => $accessToken
            ]);

            if ($pubRes['success'] && !empty($pubRes['response']['id'])) {
                $mediaId = (string) $pubRes['response']['id'];

                // Query permalink
                $permalink = $this->fetchPermalink($mediaId, $accessToken);

                return [
                    'success' => true,
                    'status' => 'published',
                    'platform_media_id' => $mediaId,
                    'published_url' => $permalink ?: $this->getPublishedUrl($mediaId, ['account_name' => $socialAccount['account_name']]),
                    'response' => $pubRes['response']
                ];
            }

            return [
                'success' => false,
                'status' => 'failed',
                'error_message' => $pubRes['response']['error']['message'] ?? 'Failed to publish finished Instagram container.',
                'response' => $pubRes['response']
            ];
        } elseif ($statusCode === 'ERROR') {
            return [
                'success' => false,
                'status' => 'failed',
                'error_message' => 'Instagram reported error while processing the media container.',
                'response' => $res['response']
            ];
        } elseif ($statusCode === 'EXPIRED') {
            return [
                'success' => false,
                'status' => 'failed',
                'error_message' => 'Instagram media container expired before publishing could complete.',
                'response' => $res['response']
            ];
        }

        // Still in progress
        return [
            'success' => true,
            'status' => 'processing',
            'platform_publish_id' => $platformPublishId
        ];
    }

    private function fetchPermalink(string $mediaId, string $accessToken): ?string
    {
        $url = "https://graph.facebook.com/v19.0/{$mediaId}?fields=permalink&access_token={$accessToken}";
        $res = $this->makeHttpRequest($url);
        return $res['response']['permalink'] ?? null;
    }

    public function refreshAuthorization(array $socialAccount): array
    {
        $tokens = SocialAccount::getDecryptedTokens((int) $socialAccount['id']);
        if (empty($tokens['access_token'])) {
            return ['success' => false, 'error' => 'No access token available'];
        }

        $config = require dirname(__DIR__, 2) . '/config/platforms.php';
        $igConfig = $config['instagram'];

        $url = "https://graph.facebook.com/v19.0/oauth/access_token?grant_type=fb_exchange_token&client_id={$igConfig['app_id']}&client_secret={$igConfig['app_secret']}&fb_exchange_token={$tokens['access_token']}";
        $res = $this->makeHttpRequest($url);

        if ($res['success'] && !empty($res['response']['access_token'])) {
            $newAccess = $res['response']['access_token'];
            $expiresIn = $res['response']['expires_in'] ?? 5184000; // 60 days long-lived
            $expiresAt = date('Y-m-d H:i:s', time() + (int) $expiresIn);

            SocialAccount::updateTokens((int) $socialAccount['id'], $newAccess, null, $expiresAt);
            return ['success' => true, 'access_token' => $newAccess];
        }

        SocialAccount::updateStatus((int) $socialAccount['id'], 'expired');
        return ['success' => false, 'error' => $res['error_message'] ?? 'Token refresh failed'];
    }

    public function getPublishedUrl(string $platformMediaId, ?array $metadata = null): string
    {
        $accountName = $metadata['account_name'] ?? '';
        if ($accountName) {
            return 'https://www.instagram.com/' . ltrim($accountName, '@') . '/';
        }
        return 'https://www.instagram.com/';
    }

    public function supportsDirectPublishing(): bool
    {
        return true;
    }

    public function supportsScheduling(): bool
    {
        return false;
    }
}
