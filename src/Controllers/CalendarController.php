<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\SchedulerService;

class CalendarController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();

        $videos = Video::getAll(['status' => 'draft']);
        $accounts = SocialAccount::getConnectedGrouped();
        $timezone = SchedulerService::getTimezone();

        View::render('calendar.index', [
            'title' => 'Publishing Calendar & Scheduling',
            'videos' => $videos,
            'accounts' => $accounts,
            'timezone' => $timezone
        ]);
    }

    public function events(Request $request): void
    {
        Auth::requireLogin();
        $events = SchedulerService::getCalendarEvents();
        Response::json($events);
    }

    public function bulkSchedule(Request $request): void
    {
        Auth::requireLogin();

        $videoIds = (array) $request->post('video_ids', []);
        $accountIds = (array) $request->post('account_ids', []);
        $startTime = (string) $request->post('start_time', '');
        $interval = (int) $request->post('interval_minutes', 60);

        if (empty($videoIds) || empty($accountIds) || empty($startTime)) {
            Session::setFlash('error', 'Please select videos, accounts, and start time for bulk scheduling.');
            Response::redirect('/calendar');
            return;
        }

        try {
            $res = SchedulerService::bulkSchedule($videoIds, $accountIds, $startTime, $interval);
            Session::setFlash('success', "Successfully scheduled {$res['scheduled_count']} publishing jobs across selected accounts!");
        } catch (\Throwable $e) {
            Session::setFlash('error', $e->getMessage());
        }

        Response::redirect('/calendar');
    }
}
