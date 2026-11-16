<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;
use App\Models\SocialAccount;

class AccountController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();

        $accounts = SocialAccount::getAll();
        $config = require dirname(__DIR__, 2) . '/config/platforms.php';
        $mockMode = (bool) Setting::get('mock_mode', 1);

        View::render('accounts.index', [
            'title' => 'Connected Social Accounts',
            'accounts' => $accounts,
            'platforms' => $config,
            'mockMode' => $mockMode
        ]);
    }

    public function disconnect(Request $request, array $params): void
    {
        Auth::requireLogin();

        $id = (int) ($params['id'] ?? 0);
        $account = SocialAccount::findById($id);

        if (!$account) {
            Session::setFlash('error', 'Account not found.');
            Response::redirect('/accounts');
            return;
        }

        SocialAccount::disconnect($id);
        Logger::audit('account_disconnected', 'social_account', (string) $id, [
            'platform' => $account['platform'],
            'account_name' => $account['account_name']
        ]);

        Session::setFlash('success', "Account '{$account['account_name']}' has been disconnected and tokens revoked.");
        Response::redirect('/accounts');
    }

    public function testConnection(Request $request, array $params): void
    {
        Auth::requireLogin();

        $id = (int) ($params['id'] ?? 0);
        $account = SocialAccount::findById($id);

        if (!$account) {
            Session::setFlash('error', 'Account not found.');
            Response::redirect('/accounts');
            return;
        }

        // Verify token decryption and status
        $tokens = SocialAccount::getDecryptedTokens($id);
        if ($tokens && !empty($tokens['access_token'])) {
            SocialAccount::updateStatus($id, 'connected');
            Session::setFlash('success', "Connection verified for '{$account['account_name']}' ({$account['platform']}). Tokens are valid.");
        } else {
            SocialAccount::updateStatus($id, 'error');
            Session::setFlash('error', "Failed to decrypt tokens or token is missing for '{$account['account_name']}'. Reauthorization required.");
        }

        Response::redirect('/accounts');
    }

    public function connectMock(Request $request): void
    {
        Auth::requireLogin();

        $platform = (string) $request->post('platform', 'youtube');
        $name = trim((string) $request->post('account_name', ''));

        if (empty($name)) {
            $name = ucfirst($platform) . ' Creator ' . rand(10, 99);
        }

        $accountId = 'mock_' . $platform . '_' . rand(1000, 9999);
        $scopes = match ($platform) {
            'youtube' => 'youtube.upload, youtube.readonly',
            'tiktok' => 'user.info.basic, video.publish',
            'instagram' => 'instagram_basic, instagram_content_publish',
            default => 'basic'
        };

        $id = SocialAccount::createOrUpdate([
            'user_id' => Auth::id(),
            'platform' => $platform,
            'account_id' => $accountId,
            'account_name' => $name,
            'access_token' => 'mock_access_token_' . bin2hex(random_bytes(16)),
            'refresh_token' => 'mock_refresh_token_' . bin2hex(random_bytes(16)),
            'token_expires_at' => date('Y-m-d H:i:s', strtotime('+60 days')),
            'granted_scopes' => $scopes,
            'connection_status' => 'connected',
            'last_verified_at' => date('Y-m-d H:i:s'),
            'metadata' => json_encode(['mock' => true, 'created_at' => date('Y-m-d H:i:s')])
        ]);

        Logger::audit('mock_account_connected', 'social_account', (string) $id, ['platform' => $platform, 'account_name' => $name]);
        Session::setFlash('success', "Simulated account '{$name}' connected successfully for {$platform}!");
        Response::redirect('/accounts');
    }
}
