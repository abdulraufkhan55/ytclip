<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\PublishingJob;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\QueueManager;

class TrackerController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();

        $search = $request->get('search');
        $platform = $request->get('platform');
        $status = $request->get('status');

        $rows = PublishingJob::getTrackerList([
            'search' => $search,
            'platform' => $platform,
            'status' => $status
        ]);

        $platformStats = PublishingJob::getPlatformStats();
        $accounts = SocialAccount::getAll();

        View::render('tracker.index', [
            'title' => 'Publishing Tracker',
            'rows' => $rows,
            'platformStats' => $platformStats,
            'accounts' => $accounts,
            'search' => $search,
            'currentPlatform' => $platform,
            'currentStatus' => $status
        ]);
    }

    public function retryVideoJobs(Request $request, array $params): void
    {
        Auth::requireLogin();

        $videoId = (int) ($params['video_id'] ?? 0);
        $jobs = PublishingJob::getJobsForVideo($videoId);
        $retried = 0;

        foreach ($jobs as $j) {
            if ($j['status'] === 'failed') {
                QueueManager::retryJob((int) $j['id']);
                $retried++;
            }
        }

        if ($retried > 0) {
            Session::setFlash('success', "Queued {$retried} failed jobs for retry on Video #{$videoId}.");
        } else {
            Session::setFlash('info', "No failed jobs to retry for this video.");
        }

        Response::redirect('/tracker');
    }
}
