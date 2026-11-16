<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Core\Logger;
use App\Models\SocialAccount;

class TikTokPublisher extends AbstractPlatformAdapter
{
    public function validateMedia(array $video): array
    {
        $errors = [];
        if (($video['duration_seconds'] ?? 0) < 3 || ($video['duration_seconds'] ?? 0) > 60) {
            $errors[] = "TikTok short video duration must be between 3 and 60 seconds.";
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
            $errors[] = "TikTok caption cannot exceed 2200 characters.";
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
                'error_message' => 'No active OAuth access token found for TikTok account.',
                'http_status' => 401
            ];
        }

        $accessToken = $tokens['access_token'];

        // Step 1: Query Creator Info to verify permissions and privacy settings
        $creatorUrl = 'https://open.tiktokapis.com/v2/post/publish/creator_info/query/';
        $creatorRes = $this->makeHttpRequest($creatorUrl, 'POST', [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8'
        ], '{}');

        $creatorData = $creatorRes['response']['data'] ?? [];
        $privacyLevel = 'PUBLIC_TO_EVERYONE';
        if (!empty($creatorData['privacy_level_options'])) {
            if (!in_array($privacyLevel, $creatorData['privacy_level_options'], true)) {
                $privacyLevel = $creatorData['privacy_level_options'][0] ?? 'SELF_ONLY';
            }
        }

        $title = $job['platform_caption'] ?: ($job['platform_title'] ?: $video['title']);
        if (!empty($video['hashtags']) && !str_contains($title, '#')) {
            $title .= ' ' . $video['hashtags'];
        }

        // Step 2: Initialize Video Direct Post
        $initUrl = 'https://open.tiktokapis.com/v2/post/publish/video/init/';
        $postPayload = [
            'post_info' => [
                'title' => mb_substr($title, 0, 2200),
                'privacy_level' => $privacyLevel,
                'disable_duet' => false,
                'disable_stitch' => false,
                'disable_comment' => false,
                'video_cover_timestamp_ms' => 1000
            ],
            'source_info' => [
                'source' => 'FILE_UPLOAD',
                'video_size' => filesize($video['storage_path']),
                'chunk_size' => filesize($video['storage_path']),
                'total_chunk_count' => 1
            ]
        ];

        $initRes = $this->makeHttpRequest($initUrl, 'POST', [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8'
        ], json_encode($postPayload));

        if (!$initRes['success'] || empty($initRes['response']['data']['upload_url'])) {
            $errCode = $initRes['response']['error']['code'] ?? 'INIT_FAILED';
            $errMsg = $initRes['response']['error']['message'] ?? 'Failed to initialize TikTok upload.';

            // Check if user requires draft mode or inbox upload
            if (str_contains(strtolower($errMsg), 'permission') || str_contains(strtolower($errMsg), 'scope')) {
                $errMsg .= ' (Tip: Direct Post requires TikTok Commercial/Approved Content Posting API permissions; otherwise use draft mode).';
            }

            return [
                'success' => false,
                'status' => 'failed',
                'error_code' => (string) $errCode,
                'error_message' => $errMsg,
                'http_status' => $initRes['http_status'],
                'response' => $initRes['response']
            ];
        }

        $uploadUrl = $initRes['response']['data']['upload_url'];
        $publishId = $initRes['response']['data']['publish_id'] ?? '';

        // Step 3: Upload Video Chunk
        $fileSize = filesize($video['storage_path']);
        $fileData = file_get_contents($video['storage_path']);

        $uploadCh = curl_init();
        curl_setopt($uploadCh, CURLOPT_URL, $uploadUrl);
        curl_setopt($uploadCh, CURLOPT_PUT, true);
        curl_setopt($uploadCh, CURLOPT_POSTFIELDS, $fileData);
        curl_setopt($uploadCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($uploadCh, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . ($video['mime_type'] ?: 'video/mp4'),
            'Content-Range: bytes 0-' . ($fileSize - 1) . '/' . $fileSize,
            'Content-Length: ' . $fileSize
        ]);
        curl_setopt($uploadCh, CURLOPT_TIMEOUT, 180);

        $uploadRes = curl_exec($uploadCh);
        $uploadCode = curl_getinfo($uploadCh, CURLINFO_HTTP_CODE);
        curl_close($uploadCh);

        if ($uploadCode >= 200 && $uploadCode < 300) {
            return [
                'success' => true,
                'status' => 'processing',
                'platform_publish_id' => $publishId,
                'platform_media_id' => $publishId,
                'published_url' => $this->getPublishedUrl($publishId, ['account_name' => $socialAccount['account_name']]),
                'http_status' => $uploadCode,
                'response' => ['publish_id' => $publishId, 'message' => 'Video uploaded to TikTok. Processing status must be polled.']
            ];
        }

        return [
            'success' => false,
            'status' => 'failed',
            'error_code' => "HTTP_{$uploadCode}",
            'error_message' => "TikTok chunk upload failed with HTTP {$uploadCode}",
            'http_status' => $uploadCode
        ];
    }

    public function getPublishingStatus(string $platformPublishId, array $socialAccount): array
    {
        $tokens = SocialAccount::getDecryptedTokens((int) $socialAccount['id']);
        $url = 'https://open.tiktokapis.com/v2/post/publish/status/fetch/';

        $res = $this->makeHttpRequest($url, 'POST', [
            'Authorization: Bearer ' . ($tokens['access_token'] ?? ''),
            'Content-Type: application/json; charset=UTF-8'
        ], json_encode(['publish_id' => $platformPublishId]));

        if ($res['success'] && !empty($res['response']['data']['status'])) {
            $ttStatus = $res['response']['data']['status'];
            // TikTok statuses: PROCESSING_DOWNLOAD, PROCESSING_UPLOAD, SUCCESS, FAILED
            if ($ttStatus === 'SUCCESS') {
                return [
                    'success' => true,
                    'status' => 'published',
                    'platform_media_id' => $platformPublishId,
                    'published_url' => $this->getPublishedUrl($platformPublishId, ['account_name' => $socialAccount['account_name']])
                ];
            } elseif ($ttStatus === 'FAILED') {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'error_message' => $res['response']['data']['fail_reason'] ?? 'TikTok video processing failed on platform.'
                ];
            } else {
                return [
                    'success' => true,
                    'status' => 'processing',
                    'platform_publish_id' => $platformPublishId
                ];
            }
        }

        return ['success' => false, 'status' => 'failed', 'error_message' => $res['error_message'] ?? 'Failed to fetch TikTok status.'];
    }

    public function refreshAuthorization(array $socialAccount): array
    {
        $tokens = SocialAccount::getDecryptedTokens((int) $socialAccount['id']);
        if (empty($tokens['refresh_token'])) {
            return ['success' => false, 'error' => 'No TikTok refresh token'];
        }

        $config = require dirname(__DIR__, 2) . '/config/platforms.php';
        $tkConfig = $config['tiktok'];

        $postData = [
            'client_key' => $tkConfig['client_key'],
            'client_secret' => $tkConfig['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $tokens['refresh_token']
        ];

        $res = $this->makeHttpRequest('https://open.tiktokapis.com/v2/oauth/token/', 'POST', ['Content-Type: application/x-www-form-urlencoded'], $postData);

        if ($res['success'] && !empty($res['response']['data']['access_token'])) {
            $newAccess = $res['response']['data']['access_token'];
            $newRefresh = $res['response']['data']['refresh_token'] ?? $tokens['refresh_token'];
            $expiresIn = $res['response']['data']['expires_in'] ?? 86400;
            $expiresAt = date('Y-m-d H:i:s', time() + (int) $expiresIn);

            SocialAccount::updateTokens((int) $socialAccount['id'], $newAccess, $newRefresh, $expiresAt);
            return ['success' => true, 'access_token' => $newAccess];
        }

        SocialAccount::updateStatus((int) $socialAccount['id'], 'expired');
        return ['success' => false, 'error' => $res['error_message'] ?? 'TikTok token refresh failed'];
    }

    public function getPublishedUrl(string $platformMediaId, ?array $metadata = null): string
    {
        $username = $metadata['account_name'] ?? '';
        if ($username) {
            return 'https://www.tiktok.com/@' . ltrim($username, '@');
        }
        return 'https://www.tiktok.com';
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
