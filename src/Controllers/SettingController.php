<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;

class SettingController
{
    public function index(Request $request): void
    {
        Auth::requireAdmin();

        $settings = Setting::getAll();
        $encryptionKeyConfigured = !empty(Security::getEncryptionKey());

        View::render('settings.index', [
            'title' => 'Application Settings & API Configurations',
            'settings' => $settings,
            'encryptionKeyConfigured' => $encryptionKeyConfigured,
            'timezones' => \DateTimeZone::listIdentifiers()
        ]);
    }

    public function update(Request $request): void
    {
        Auth::requireAdmin();

        $keys = [
            'app_name',
            'app_url',
            'app_timezone',
            'min_video_duration',
            'max_video_duration',
            'max_upload_size_mb',
            'daily_publishing_limit',
            'ffprobe_path',
            'ffmpeg_path',
            'mock_mode',
            'youtube_client_id',
            'youtube_client_secret',
            'tiktok_client_key',
            'tiktok_client_secret',
            'instagram_app_id',
            'instagram_app_secret'
        ];

        foreach ($keys as $k) {
            $val = $request->post($k);
            if ($k === 'mock_mode') {
                $val = $val !== null ? '1' : '0';
            }
            if ($val !== null) {
                Setting::set($k, trim((string) $val));
            }
        }

        Logger::audit('settings_updated', 'settings', 'global', ['updated_by' => Auth::id()]);
        Session::setFlash('success', 'Application settings updated successfully.');
        Response::redirect('/settings');
    }
}
