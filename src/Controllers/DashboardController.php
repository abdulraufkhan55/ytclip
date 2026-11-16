<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\PublishingJob;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\PublishingWorker;
use App\Services\SchedulerService;

class DashboardController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();

        // Actual figures from database
        $overallStats = PublishingJob::getOverallStats();
        $platformStats = PublishingJob::getPlatformStats();

        // Recent upload activity (last 5 videos)
        $recentVideos = Video::getAll(['limit' => 5]);

        // Failed job alerts
        $failedJobs = Database::fetchAll(
            "SELECT pj.*, v.title, sa.account_name 
             FROM publishing_jobs pj 
             JOIN videos v ON pj.video_id = v.id 
             JOIN social_accounts sa ON pj.social_account_id = sa.id 
             WHERE pj.status = 'failed' 
             ORDER BY pj.updated_at DESC LIMIT 5"
        );

        // Upcoming scheduled posts
        $upcomingScheduled = Database::fetchAll(
            "SELECT pj.*, v.title, sa.account_name 
             FROM publishing_jobs pj 
             JOIN videos v ON pj.video_id = v.id 
             JOIN social_accounts sa ON pj.social_account_id = sa.id 
             WHERE pj.scheduled_at IS NOT NULL AND pj.status = 'queued' 
             ORDER BY pj.scheduled_at ASC LIMIT 5"
        );

        // Daily publishing chart data (last 7 days)
        $chartDays = [];
        $chartCounts = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $chartDays[] = date('M d', strtotime($date));
            $res = Database::fetch(
                "SELECT COUNT(*) as cnt FROM publishing_jobs WHERE status = 'published' AND DATE(published_at) = :d",
                ['d' => $date]
            );
            $chartCounts[] = (int) ($res['cnt'] ?? 0);
        }

        View::render('dashboard.index', [
            'title' => 'Publishing Dashboard',
            'stats' => $overallStats,
            'platformStats' => $platformStats,
            'recentVideos' => array_slice($recentVideos, 0, 5),
            'failedJobs' => $failedJobs,
            'upcomingScheduled' => $upcomingScheduled,
            'chartDays' => $chartDays,
            'chartCounts' => $chartCounts,
        ]);
    }

    public function triggerWorker(Request $request): void
    {
        Auth::requireLogin();

        $worker = new PublishingWorker();
        $processed = $worker->runOnce();

        if ($processed) {
            Session::setFlash('success', 'Publishing worker ran successfully! Ready jobs were processed.');
        } else {
            Session::setFlash('info', 'Publishing worker ran: No jobs were queued or ready at this moment.');
        }

        Response::redirect('/dashboard');
    }

    public function retryAllFailed(Request $request): void
    {
        Auth::requireLogin();

        $affected = Database::query(
            "UPDATE publishing_jobs 
             SET status = 'queued', locked_at = NULL, locked_by = NULL, next_retry_at = NULL, last_error_message = NULL, updated_at = NOW() 
             WHERE status = 'failed'"
        )->rowCount();

        if ($affected > 0) {
            Session::setFlash('success', "Queued {$affected} previously failed jobs for retry.");
        } else {
            Session::setFlash('info', "No failed jobs to retry.");
        }

        Response::redirect('/dashboard');
    }
}
