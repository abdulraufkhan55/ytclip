<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Models\Setting;
use InvalidArgumentException;

class AdapterFactory
{
    public static function create(string $platform, ?array $socialAccount = null): PlatformAdapterInterface
    {
        $platform = strtolower(trim($platform));
        $config = require dirname(__DIR__, 2) . '/config/platforms.php';

        if (!isset($config[$platform])) {
            throw new InvalidArgumentException("Unsupported social media platform: {$platform}");
        }

        // If the account is explicitly a test/demo account, use MockPublisher
        if ($socialAccount) {
            $accId = (string) ($socialAccount['account_id'] ?? '');
            if (str_starts_with($accId, 'mock_') || str_ends_with($accId, '_test_01')) {
                return new MockPublisher($platform, $config[$platform]);
            }
        }

        $mockMode = (bool) Setting::get('mock_mode', 0);

        if ($mockMode) {
            return new MockPublisher($platform, $config[$platform]);
        }

        return match ($platform) {
            'youtube' => new YouTubePublisher($config['youtube']),
            'tiktok' => new TikTokPublisher($config['tiktok']),
            'instagram' => new InstagramPublisher($config['instagram']),
            default => throw new InvalidArgumentException("Unsupported social media platform: {$platform}")
        };
    }
}
