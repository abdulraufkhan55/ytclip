<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Setting;
use App\Models\SocialAccount;

class OAuthController
{
    private array $platforms;

    public function __construct()
    {
        $this->platforms = require dirname(__DIR__, 2) . '/config/platforms.php';
    }

    public function redirect(Request $request, array $params): void
    {
        Auth::requireLogin();
        $platform = strtolower((string) ($params['platform'] ?? ''));

        if (!isset($this->platforms[$platform])) {
            Session::setFlash('error', "Unsupported platform: {$platform}");
            Response::redirect('/accounts');
            return;
        }

        $cfg = $this->platforms[$platform];
        $state = bin2hex(random_bytes(16));
        Session::set('oauth_state_' . $platform, $state);

        $authUrl = match ($platform) {
            'youtube' => $cfg['auth_url'] . '?' . http_build_query([
                'client_id' => Setting::get('youtube_client_id') ?: $cfg['client_id'],
                'redirect_uri' => $cfg['redirect_uri'],
                'response_type' => 'code',
                'scope' => implode(' ', $cfg['scopes']),
                'access_type' => 'offline',
                'prompt' => 'consent',
                'state' => $state
            ]),
            'tiktok' => $cfg['auth_url'] . '?' . http_build_query([
                'client_key' => Setting::get('tiktok_client_key') ?: $cfg['client_key'],
                'redirect_uri' => $cfg['redirect_uri'],
                'response_type' => 'code',
                'scope' => implode(',', $cfg['scopes']),
                'state' => $state
            ]),
            'instagram' => $cfg['auth_url'] . '?' . http_build_query([
                'client_id' => Setting::get('instagram_app_id') ?: $cfg['app_id'],
                'redirect_uri' => $cfg['redirect_uri'],
                'response_type' => 'code',
                'scope' => implode(',', $cfg['scopes']),
                'state' => $state
            ]),
            default => null
        };

        if (!$authUrl) {
            Session::setFlash('error', "Could not generate auth URL for {$platform}");
            Response::redirect('/accounts');
            return;
        }

        header("Location: {$authUrl}");
        exit;
    }

    public function callback(Request $request, array $params): void
    {
        Auth::requireLogin();
        $platform = strtolower((string) ($params['platform'] ?? ''));

        if (!isset($this->platforms[$platform])) {
            Session::setFlash('error', "Invalid platform in OAuth callback.");
            Response::redirect('/accounts');
            return;
        }

        $code = $request->get('code');
        $state = $request->get('state');
        $savedState = Session::get('oauth_state_' . $platform);
        Session::remove('oauth_state_' . $platform);

        if (empty($code) || empty($state) || !hash_equals((string) $savedState, (string) $state)) {
            Session::setFlash('error', 'OAuth state verification failed or authorization was cancelled.');
            Response::redirect('/accounts');
            return;
        }

        try {
            match ($platform) {
                'youtube' => $this->handleYouTubeCallback((string) $code),
                'tiktok' => $this->handleTikTokCallback((string) $code),
                'instagram' => $this->handleInstagramCallback((string) $code),
            };

            Session::setFlash('success', ucfirst($platform) . ' account connected successfully!');
        } catch (\Throwable $e) {
            Logger::error("OAuth callback error for {$platform}: " . $e->getMessage());
            Session::setFlash('error', "Authorization failed: " . $e->getMessage());
        }

        Response::redirect('/accounts');
    }

    private function handleYouTubeCallback(string $code): void
    {
        $cfg = $this->platforms['youtube'];
        $clientId = Setting::get('youtube_client_id') ?: $cfg['client_id'];
        $clientSecret = Setting::get('youtube_client_secret') ?: $cfg['client_secret'];

        $postData = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $cfg['redirect_uri'],
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init($cfg['token_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode((string) $res, true);
        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException($tokenData['error_description'] ?? 'Failed to exchange YouTube code for access token.');
        }

        // Fetch Channel Details
        $channelCh = curl_init('https://www.googleapis.com/youtube/v3/channels?part=snippet&mine=true');
        curl_setopt($channelCh, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
        curl_setopt($channelCh, CURLOPT_RETURNTRANSFER, true);
        $channelRes = curl_exec($channelCh);
        curl_close($channelCh);

        $channelData = json_decode((string) $channelRes, true);
        $channel = $channelData['items'][0] ?? null;

        $accountId = $channel['id'] ?? ('yt_' . time());
        $accountName = $channel['snippet']['title'] ?? 'YouTube Channel';

        $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        SocialAccount::createOrUpdate([
            'user_id' => Auth::id(),
            'platform' => 'youtube',
            'account_id' => $accountId,
            'account_name' => $accountName,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'token_expires_at' => $expiresAt,
            'granted_scopes' => $tokenData['scope'] ?? 'youtube.upload',
            'connection_status' => 'connected',
            'last_verified_at' => date('Y-m-d H:i:s'),
            'metadata' => json_encode($channel)
        ]);

        Logger::audit('account_connected', 'social_account', $accountId, ['platform' => 'youtube', 'name' => $accountName]);
    }

    private function handleTikTokCallback(string $code): void
    {
        $cfg = $this->platforms['tiktok'];
        $clientKey = Setting::get('tiktok_client_key') ?: $cfg['client_key'];
        $clientSecret = Setting::get('tiktok_client_secret') ?: $cfg['client_secret'];

        $postData = [
            'client_key' => $clientKey,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $cfg['redirect_uri']
        ];

        $ch = curl_init($cfg['token_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode((string) $res, true);
        if (empty($tokenData['data']['access_token'])) {
            throw new \RuntimeException($tokenData['error']['message'] ?? 'Failed to exchange TikTok code for access token.');
        }

        $data = $tokenData['data'];
        $openId = $data['open_id'] ?? ('tiktok_' . time());

        // Fetch User Info
        $userCh = curl_init('https://open.tiktokapis.com/v2/user/info/?fields=open_id,union_id,avatar_url,display_name');
        curl_setopt($userCh, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $data['access_token']]);
        curl_setopt($userCh, CURLOPT_RETURNTRANSFER, true);
        $userRes = curl_exec($userCh);
        curl_close($userCh);

        $userData = json_decode((string) $userRes, true);
        $displayName = $userData['data']['user']['display_name'] ?? ('TikTok User (' . substr($openId, 0, 8) . ')');

        $expiresIn = (int) ($data['expires_in'] ?? 86400);
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        SocialAccount::createOrUpdate([
            'user_id' => Auth::id(),
            'platform' => 'tiktok',
            'account_id' => $openId,
            'account_name' => $displayName,
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'token_expires_at' => $expiresAt,
            'granted_scopes' => $data['scope'] ?? 'video.publish',
            'connection_status' => 'connected',
            'last_verified_at' => date('Y-m-d H:i:s'),
            'metadata' => json_encode($userData)
        ]);

        Logger::audit('account_connected', 'social_account', $openId, ['platform' => 'tiktok', 'name' => $displayName]);
    }

    private function handleInstagramCallback(string $code): void
    {
        $cfg = $this->platforms['instagram'];
        $appId = Setting::get('instagram_app_id') ?: $cfg['app_id'];
        $appSecret = Setting::get('instagram_app_secret') ?: $cfg['app_secret'];

        $url = $cfg['token_url'] . '?' . http_build_query([
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'redirect_uri' => $cfg['redirect_uri'],
            'code' => $code
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode((string) $res, true);
        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException($tokenData['error']['message'] ?? 'Failed to exchange Meta/Instagram code for access token.');
        }

        $userAccessToken = $tokenData['access_token'];

        // Query Facebook Pages connected to user and their instagram_business_account
        $pagesUrl = "https://graph.facebook.com/v19.0/me/accounts?fields=name,access_token,instagram_business_account{id,username,name}&access_token={$userAccessToken}";
        $chPages = curl_init($pagesUrl);
        curl_setopt($chPages, CURLOPT_RETURNTRANSFER, true);
        $pagesRes = curl_exec($chPages);
        curl_close($chPages);

        $pagesData = json_decode((string) $pagesRes, true);
        $connectedCount = 0;

        if (!empty($pagesData['data'])) {
            foreach ($pagesData['data'] as $page) {
                if (!empty($page['instagram_business_account'])) {
                    $ig = $page['instagram_business_account'];
                    $pageAccessToken = $page['access_token'];

                    SocialAccount::createOrUpdate([
                        'user_id' => Auth::id(),
                        'platform' => 'instagram',
                        'account_id' => (string) $ig['id'],
                        'account_name' => '@' . ($ig['username'] ?? $ig['name'] ?? 'instagram_account'),
                        'access_token' => $pageAccessToken,
                        'refresh_token' => null,
                        'token_expires_at' => date('Y-m-d H:i:s', strtotime('+60 days')),
                        'granted_scopes' => 'instagram_basic,instagram_content_publish',
                        'connection_status' => 'connected',
                        'last_verified_at' => date('Y-m-d H:i:s'),
                        'metadata' => json_encode(['page_id' => $page['id'], 'page_name' => $page['name'], 'ig' => $ig])
                    ]);

                    $connectedCount++;
                }
            }
        }

        if ($connectedCount === 0) {
            throw new \RuntimeException('No eligible Instagram Business or Creator accounts linked to your Facebook Pages were found. Please verify your Meta Business settings.');
        }
    }
}
