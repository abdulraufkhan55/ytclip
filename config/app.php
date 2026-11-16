<?php

declare(strict_types=1);

use App\Core\Env;

$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
Env::load($envPath);

return [
    'name' => Env::get('APP_NAME', 'YTClip Multi-Publisher'),
    'env' => Env::get('APP_ENV', 'development'),
    'debug' => filter_var(Env::get('APP_DEBUG', true), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim(Env::get('APP_URL', 'http://localhost/ytclip'), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'UTC'),
    'key' => Env::get('APP_KEY', ''),
    'storage_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage',
    'min_video_duration' => (float) Env::get('MIN_VIDEO_DURATION', 5),
    'max_video_duration' => (float) Env::get('MAX_VIDEO_DURATION', 60),
    'max_upload_size_mb' => (int) Env::get('MAX_UPLOAD_SIZE_MB', 200),
    'mock_mode' => (bool) Env::get('MOCK_MODE', 1),
    'daily_publishing_limit' => (int) Env::get('DAILY_PUBLISHING_LIMIT', 50),
    'ffprobe_path' => Env::get('FFPROBE_PATH', ''),
    'ffmpeg_path' => Env::get('FFMPEG_PATH', ''),
];
