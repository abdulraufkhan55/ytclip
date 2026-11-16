<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Core\Logger;
use App\Models\SocialAccount;
use RuntimeException;

class YouTubePublisher extends AbstractPlatformAdapter
{
    public function validateMedia(array $video): array
    {
        $errors = [];
        if (($video['duration_seconds'] ?? 0) > 60) {
            $errors[] = "YouTube Shorts maximum duration is 60 seconds (video is {$video['duration_seconds']}s).";
        }
        if (!file_exists($video['storage_path'] ?? '')) {
            $errors[] = "Video file does not exist on disk.";
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function validateMetadata(array $metadata): array
    {
        $errors = [];
        $title = $metadata['title'] ?? '';
        if (mb_strlen($title) === 0) {
            $errors[] = "YouTube title is required.";
        }
        if (mb_strlen($title) > 100) {
            $errors[] = "YouTube title cannot exceed 100 characters.";
        }
        if (mb_strlen($metadata['description'] ?? '') > 5000) {
            $errors[] = "YouTube description cannot exceed 5000 characters.";
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
                'error_message' => 'No active OAuth access token found for YouTube account. Please reconnect account.',
                'http_status' => 401
            ];
        }

        $accessToken = $tokens['access_token'];

        // Step 1: Initiate Resumable Upload Session
        $initUrl = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status';

        $title = $job['platform_title'] ?: $video['title'];
        // Auto-append #Shorts if not present
        if (!str_contains(strtolower($title), '#shorts')) {
            $title .= ' #Shorts';
        }

        $description = $job['platform_description'] ?: ($video['description'] ?? '');
        if (!empty($video['hashtags'])) {
            $description .= "\n\n" . $video['hashtags'];
        }

        $privacyStatus = 'public';
        $statusSnippet = [
            'privacyStatus' => $privacyStatus,
            'selfDeclaredMadeForKids' => false
        ];

        if (!empty($job['scheduled_at'])) {
            $privacyStatus = 'private'; // Must be private when publishAt is set
            $statusSnippet['privacyStatus'] = 'private';
            $statusSnippet['publishAt'] = date('c', strtotime($job['scheduled_at']));
        }

        $metadataPayload = [
            'snippet' => [
                'title' => mb_substr($title, 0, 100),
                'description' => mb_substr($description, 0, 5000),
                'tags' => array_filter(array_map('trim', explode(' ', $video['hashtags'] ?? ''))),
                'categoryId' => '22' // People & Blogs
            ],
            'status' => $statusSnippet
        ];

        $initHeaders = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Type: ' . ($video['mime_type'] ?: 'video/mp4'),
            'X-Upload-Content-Length: ' . filesize($video['storage_path'])
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $initUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metadataPayload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $initHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // We need Location header
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 401) {
            // Token expired, attempt refresh
            $refreshResult = $this->refreshAuthorization($socialAccount);
            if ($refreshResult['success']) {
                return $this->publish($job, $video, $socialAccount);
            }
            return [
                'success' => false,
                'status' => 'failed',
                'error_code' => 'TOKEN_EXPIRED',
                'error_message' => 'OAuth token expired and refresh failed. Reauthorization required.',
                'http_status' => 401
            ];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'status' => 'failed',
                'error_code' => "HTTP_{$httpCode}",
                'error_message' => "YouTube upload initialization failed with HTTP {$httpCode}: {$response}",
                'http_status' => $httpCode,
                'response' => $response
            ];
        }

        $headers = substr($response, 0, $headerSize);
        preg_match('/Location:\s*([^\r\n]+)/i', $headers, $matches);
        $uploadUrl = trim($matches[1] ?? '');

        if (empty($uploadUrl)) {
            return [
                'success' => false,
                'status' => 'failed',
                'error_code' => 'NO_UPLOAD_URL',
                'error_message' => 'Failed to obtain YouTube resumable upload URL from response headers.',
                'http_status' => $httpCode
            ];
        }

        // Step 2: Upload Video File Content
        $fileSize = filesize($video['storage_path']);
        $fileHandle = fopen($video['storage_path'], 'rb');

        $uploadCh = curl_init();
        curl_setopt($uploadCh, CURLOPT_URL, $uploadUrl);
        curl_setopt($uploadCh, CURLOPT_UPLOAD, true);
        curl_setopt($uploadCh, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($uploadCh, CURLOPT_INFILE, $fileHandle);
        curl_setopt($uploadCh, CURLOPT_INFILESIZE, $fileSize);
        curl_setopt($uploadCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($uploadCh, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . ($video['mime_type'] ?: 'video/mp4'),
            'Content-Length: ' . $fileSize
        ]);
        curl_setopt($uploadCh, CURLOPT_TIMEOUT, 300);

        $uploadResponse = curl_exec($uploadCh);
        $uploadHttpCode = curl_getinfo($uploadCh, CURLINFO_HTTP_CODE);
        curl_close($uploadCh);
        fclose($fileHandle);

        $uploadData = json_decode((string) $uploadResponse, true);

        if ($uploadHttpCode >= 200 && $uploadHttpCode < 300 && !empty($uploadData['id'])) {
            $videoId = $uploadData['id'];
            return [
                'success' => true,
                'status' => 'published',
                'platform_media_id' => $videoId,
                'published_url' => $this->getPublishedUrl($videoId),
                'http_status' => $uploadHttpCode,
                'response' => $uploadData
            ];
        }

        return [
            'success' => false,
            'status' => 'failed',
            'error_code' => "HTTP_{$uploadHttpCode}",
            'error_message' => $uploadData['error']['message'] ?? "YouTube video upload failed with HTTP {$uploadHttpCode}",
            'http_status' => $uploadHttpCode,
            'response' => $uploadData
        ];
    }

    public function getPublishingStatus(string $platformPublishId, array $socialAccount): array
    {
        $tokens = SocialAccount::getDecryptedTokens((int) $socialAccount['id']);
        $url = 'https://www.googleapis.com/youtube/v3/videos?part=status,processingDetails&id=' . urlencode($platformPublishId);
        $res = $this->makeHttpRequest($url, 'GET', ['Authorization: Bearer ' . ($tokens['access_token'] ?? '')]);

        if ($res['success'] && !empty($res['response']['items'][0])) {
            $status = $res['response']['items'][0]['status']['uploadStatus'] ?? 'uploaded';
            return [
                'success' => true,
                'status' => $status === 'processed' ? 'published' : 'processing',
                'platform_media_id' => $platformPublishId,
                'published_url' => $this->getPublishedUrl($platformPublishId)
            ];
        }

        return ['success' => false, 'status' => 'failed', 'error_message' => $res['error_message']];
    }

    public function refreshAuthorization(array $socialAccount): array
    {
        $tokens = SocialAccount::getDecryptedTokens((int) $socialAccount['id']);
        if (empty($tokens['refresh_token'])) {
            return ['success' => false, 'error' => 'No refresh token available'];
        }

        $config = require dirname(__DIR__, 2) . '/config/platforms.php';
        $ytConfig = $config['youtube'];

        $postData = [
            'client_id' => $ytConfig['client_id'],
            'client_secret' => $ytConfig['client_secret'],
            'refresh_token' => $tokens['refresh_token'],
            'grant_type' => 'refresh_token'
        ];

        $res = $this->makeHttpRequest('https://oauth2.googleapis.com/token', 'POST', ['Content-Type: application/x-www-form-urlencoded'], $postData);

        if ($res['success'] && !empty($res['response']['access_token'])) {
            $newAccess = $res['response']['access_token'];
            $expiresIn = $res['response']['expires_in'] ?? 3600;
            $expiresAt = date('Y-m-d H:i:s', time() + (int) $expiresIn);

            SocialAccount::updateTokens((int) $socialAccount['id'], $newAccess, null, $expiresAt);
            return ['success' => true, 'access_token' => $newAccess];
        }

        SocialAccount::updateStatus((int) $socialAccount['id'], 'expired');
        return ['success' => false, 'error' => $res['error_message'] ?? 'Token refresh failed'];
    }

    public function getPublishedUrl(string $platformMediaId, ?array $metadata = null): string
    {
        return 'https://www.youtube.com/shorts/' . $platformMediaId;
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
