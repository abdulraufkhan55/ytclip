<?php

declare(strict_types=1);

use App\Core\Env;
use App\Models\Setting;

return [
    'youtube' => [
        'name' => 'YouTube Shorts',
        'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'api_upload_url' => 'https://www.googleapis.com/upload/youtube/v3/videos',
        'api_base_url' => 'https://www.googleapis.com/youtube/v3',
        'scopes' => [
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/youtube.readonly',
            'https://www.googleapis.com/auth/userinfo.profile'
        ],
        'client_id' => Setting::get('youtube_client_id') ?: Env::get('YOUTUBE_CLIENT_ID', ''),
        'client_secret' => Setting::get('youtube_client_secret') ?: Env::get('YOUTUBE_CLIENT_SECRET', ''),
        'redirect_uri' => Env::get('YOUTUBE_REDIRECT_URI', 'http://localhost/ytclip/oauth/youtube/callback'),
        'supports_direct_publish' => true,
        'supports_scheduling' => true,
        'max_title_length' => 100,
        'max_desc_length' => 5000,
        'aspect_ratio' => '9:16',
    ],
    'tiktok' => [
        'name' => 'TikTok',
        'auth_url' => 'https://www.tiktok.com/v2/auth/authorize/',
        'token_url' => 'https://open.tiktokapis.com/v2/oauth/token/',
        'revoke_url' => 'https://open.tiktokapis.com/v2/oauth/revoke/',
        'creator_info_url' => 'https://open.tiktokapis.com/v2/post/publish/creator_info/query/',
        'direct_post_init_url' => 'https://open.tiktokapis.com/v2/post/publish/video/init/',
        'status_url' => 'https://open.tiktokapis.com/v2/post/publish/status/fetch/',
        'scopes' => [
            'user.info.basic',
            'video.publish',
            'video.upload'
        ],
        'client_key' => Setting::get('tiktok_client_key') ?: Env::get('TIKTOK_CLIENT_KEY', ''),
        'client_secret' => Setting::get('tiktok_client_secret') ?: Env::get('TIKTOK_CLIENT_SECRET', ''),
        'redirect_uri' => Env::get('TIKTOK_REDIRECT_URI', 'http://localhost/ytclip/oauth/tiktok/callback'),
        'supports_direct_publish' => true,
        'supports_scheduling' => false,
        'max_caption_length' => 2200,
        'aspect_ratio' => '9:16',
    ],
    'instagram' => [
        'name' => 'Instagram Reels',
        'auth_url' => 'https://www.facebook.com/v19.0/dialog/oauth',
        'token_url' => 'https://graph.facebook.com/v19.0/oauth/access_token',
        'api_base_url' => 'https://graph.facebook.com/v19.0',
        'scopes' => [
            'instagram_basic',
            'instagram_content_publish',
            'pages_show_list',
            'pages_read_engagement'
        ],
        'app_id' => Setting::get('instagram_app_id') ?: Env::get('INSTAGRAM_APP_ID', ''),
        'app_secret' => Setting::get('instagram_app_secret') ?: Env::get('INSTAGRAM_APP_SECRET', ''),
        'redirect_uri' => Env::get('INSTAGRAM_REDIRECT_URI', 'http://localhost/ytclip/oauth/instagram/callback'),
        'supports_direct_publish' => true,
        'supports_scheduling' => false,
        'max_caption_length' => 2200,
        'aspect_ratio' => '9:16',
    ]
];
