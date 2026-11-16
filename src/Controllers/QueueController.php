<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\PublishingJob;
use App\Services\PublishingWorker;
use App\Services\QueueManager;

class QueueController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();

        $status = $request->get('status');
        $platform = $request->get('platform');

        $jobs = PublishingJob::getQueue([
            'status' => $status,
            'platform' => $platform
        ]);

        View::render('queue.index', [
            'title' => 'Publishing Queue',
            'jobs' => $jobs,
            'currentStatus' => $status,
            'currentPlatform' => $platform
        ]);
    }

    public function retry(Request $request, array $params): void
    {
        Auth::requireLogin();

        $id = (int) ($params['id'] ?? 0);
        if (QueueManager::retryJob($id)) {
            Session::setFlash('success', "Job #{$id} reset to queued for publishing.");
        } else {
            Session::setFlash('error', "Could not retry job #{$id}.");
        }

        Response::redirect('/queue');
    }

    public function cancel(Request $request, array $params): void
    {
        Auth::requireLogin();

        $id = (int) ($params['id'] ?? 0);
        if (QueueManager::cancelJob($id)) {
            Session::setFlash('success', "Job #{$id} was cancelled.");
        } else {
            Session::setFlash('error', "Cannot cancel job #{$id} in its current state.");
        }

        Response::redirect('/queue');
    }

    public function runWorkerNow(Request $request): void
    {
        Auth::requireLogin();

        $worker = new PublishingWorker();
        $worked = $worker->runOnce();

        if ($worked) {
            Session::setFlash('success', 'Worker processed the queue item successfully!');
        } else {
            Session::setFlash('info', 'Worker ran: No jobs ready to execute at this moment.');
        }

        Response::redirect('/queue');
    }
}
