<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Setting;
use DateTime;
use DateTimeZone;

class SchedulerService
{
    public static function getTimezone(): string
    {
        return (string) Setting::get('app_timezone', 'UTC');
    }

    /**
     * Converts a user input local datetime to UTC for storage
     */
    public static function toUtc(string $localDatetime): string
    {
        $tz = self::getTimezone();
        $dt = new DateTime($localDatetime, new DateTimeZone($tz));
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Converts stored UTC datetime to local app timezone for display
     */
    public static function toLocal(?string $utcDatetime, string $format = 'Y-m-d H:i:s'): string
    {
        if (empty($utcDatetime)) {
            return '';
        }
        $tz = self::getTimezone();
        $dt = new DateTime($utcDatetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($tz));
        return $dt->format($format);
    }

    /**
     * Get scheduled jobs formatted as calendar events
     */
    public static function getCalendarEvents(): array
    {
        $jobs = Database::fetchAll(
            "SELECT pj.id, pj.platform, pj.status, pj.scheduled_at, pj.published_at, v.title, sa.account_name 
             FROM publishing_jobs pj 
             JOIN videos v ON pj.video_id = v.id 
             JOIN social_accounts sa ON pj.social_account_id = sa.id 
             WHERE pj.scheduled_at IS NOT NULL OR pj.published_at IS NOT NULL 
             ORDER BY pj.scheduled_at ASC"
        );

        $colors = [
            'youtube' => '#FF0000',
            'tiktok' => '#000000',
            'instagram' => '#E1306C'
        ];

        $events = [];
        foreach ($jobs as $j) {
            $time = $j['scheduled_at'] ?: $j['published_at'];
            $localTime = self::toLocal($time, 'c');

            $events[] = [
                'id' => $j['id'],
                'title' => "[{$j['platform']}] {$j['title']} (@{$j['account_name']})",
                'start' => $localTime,
                'backgroundColor' => $colors[$j['platform']] ?? '#4e73df',
                'borderColor' => $colors[$j['platform']] ?? '#4e73df',
                'status' => $j['status'],
                'platform' => $j['platform'],
                'account' => $j['account_name'],
                'job_id' => $j['id']
            ];
        }

        return $events;
    }

    /**
     * Bulk schedule multiple videos
     */
    public static function bulkSchedule(array $videoIds, array $accountIds, string $startTime, int $intervalMinutes = 60): array
    {
        $utcStartTime = self::toUtc($startTime);
        $currentTimestamp = strtotime($utcStartTime);
        $scheduledCount = 0;

        foreach ($videoIds as $vid) {
            $scheduledTime = date('Y-m-d H:i:s', $currentTimestamp);
            $res = QueueManager::dispatch((int) $vid, $accountIds, [], $scheduledTime);
            $scheduledCount += count($res['created_job_ids']);
            $currentTimestamp += ($intervalMinutes * 60);
        }

        return ['scheduled_count' => $scheduledCount];
    }
}
