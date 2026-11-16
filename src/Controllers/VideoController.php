<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\PublishingJob;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\QueueManager;
use App\Services\SchedulerService;
use App\Services\VideoProcessor;

class VideoController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();

        // Auto-detect and import any videos placed in 'Upload videos man'
        $processor = new VideoProcessor();
        $imported = $processor->syncManualFolder(Auth::id() ?: 1);
        if (!empty($imported)) {
            Session::setFlash('success', "Detected and imported " . count($imported) . " new video(s) from 'Upload videos man': " . implode(', ', $imported));
        }

        $status = $request->get('status');
        $search = $request->get('search');

        $videos = Video::getAll([
            'status' => $status,
            'search' => $search
        ]);
        $stats = Video::getStats();

        View::render('videos.index', [
            'title' => 'Video Library',
            'videos' => $videos,
            'stats' => $stats,
            'currentStatus' => $status,
            'search' => $search
        ]);
    }

    public function syncManual(Request $request): void
    {
        Auth::requireLogin();
        $processor = new VideoProcessor();
        $imported = $processor->syncManualFolder(Auth::id() ?: 1);

        if (!empty($imported)) {
            Session::setFlash('success', "Successfully imported " . count($imported) . " new video(s) from 'Upload videos man': " . implode(', ', $imported));
        } else {
            Session::setFlash('info', "Checked 'Upload videos man' folder. No new video files (.mp4, .mov, .webm) found to import.");
        }

        Response::redirect('/videos');
    }

    public function showUpload(Request $request): void
    {
        Auth::requireLogin();
        $connectedAccounts = SocialAccount::getConnectedGrouped();

        View::render('videos.upload', [
            'title' => 'Upload New Video',
            'connectedAccounts' => $connectedAccounts
        ]);
    }

    public function upload(Request $request): void
    {
        Auth::requireLogin();

        $file = $request->file('video_file');
        if (!$file || empty($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $msg = 'Please select a valid video file to upload.';
            if ($request->isAjax()) {
                Response::json(['success' => false, 'message' => $msg], 400);
            }
            Session::setFlash('error', $msg);
            Response::redirect('/upload');
            return;
        }

        try {
            $processor = new VideoProcessor();
            $meta = $processor->processUpload($file);

            $title = trim((string) $request->post('title', ''));
            if (empty($title)) {
                $title = pathinfo($meta['original_filename'], PATHINFO_FILENAME);
                $title = ucwords(str_replace(['_', '-'], ' ', $title));
            }

            $videoId = Video::create([
                'owner_id' => Auth::id(),
                'original_filename' => $meta['original_filename'],
                'stored_filename' => $meta['stored_filename'],
                'storage_path' => $meta['storage_path'],
                'thumbnail_path' => $meta['thumbnail_path'],
                'mime_type' => $meta['mime_type'],
                'file_size' => $meta['file_size'],
                'duration_seconds' => $meta['duration_seconds'],
                'width' => $meta['width'],
                'height' => $meta['height'],
                'title' => $title,
                'description' => trim((string) $request->post('description', '')),
                'default_caption' => trim((string) $request->post('default_caption', '')),
                'hashtags' => trim((string) $request->post('hashtags', '')),
                'checksum' => $meta['checksum'],
                'video_status' => 'draft'
            ]);

            Logger::audit('video_uploaded', 'video', (string) $videoId, ['title' => $title]);

            // Check if user requested immediate publish or scheduling directly from upload form
            $action = $request->post('publish_action');
            $selectedAccounts = (array) $request->post('account_ids', []);
            $scheduledAt = $request->post('scheduled_at');

            if (!empty($selectedAccounts) && in_array($action, ['publish_now', 'schedule'])) {
                $metaPerPlatform = [
                    'youtube' => [
                        'title' => $request->post('yt_title') ?: $title,
                        'description' => $request->post('yt_description')
                    ],
                    'tiktok' => [
                        'caption' => $request->post('tt_caption') ?: $title
                    ],
                    'instagram' => [
                        'caption' => $request->post('ig_caption') ?: $title
                    ]
                ];

                $scheduleUtc = ($action === 'schedule' && !empty($scheduledAt)) ? SchedulerService::toUtc($scheduledAt) : null;
                QueueManager::dispatch($videoId, $selectedAccounts, $metaPerPlatform, $scheduleUtc);
            }

            if ($request->isAjax()) {
                Response::json([
                    'success' => true,
                    'video_id' => $videoId,
                    'redirect' => '/videos/' . $videoId
                ]);
            }

            Session::setFlash('success', "Video '{$title}' uploaded and processed successfully!");
            Response::redirect('/videos/' . $videoId);
        } catch (\Throwable $e) {
            Logger::error("Video upload failed: " . $e->getMessage());
            if ($request->isAjax()) {
                Response::json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            Session::setFlash('error', $e->getMessage());
            Response::redirect('/upload');
        }
    }

    public function show(Request $request, array $params): void
    {
        Auth::requireLogin();

        $id = (int) ($params['id'] ?? 0);
        $video = Video::findById($id);

        if (!$video) {
            Session::setFlash('error', 'Video not found.');
            Response::redirect('/videos');
            return;
        }

        $jobs = PublishingJob::getJobsForVideo($id);
        $connectedAccounts = SocialAccount::getConnectedGrouped();

        View::render('videos.show', [
            'title' => 'Video: ' . $video['title'],
            'video' => $video,
            'jobs' => $jobs,
            'connectedAccounts' => $connectedAccounts
        ]);
    }

    public function update(Request $request, array $params): void
    {
        Auth::requireLogin();

        $id = (int) ($params['id'] ?? 0);
        $video = Video::findById($id);
        if (!$video) {
            Response::redirect('/videos');
            return;
        }

        $data = [
            'title' => trim((string) $request->post('title', $video['title'])),
            'description' => trim((string) $request->post('description', $video['description'])),
            'default_caption' => trim((string) $request->post('default_caption', $video['default_caption'])),
            'hashtags' => trim((string) $request->post('hashtags', $video['hashtags'])),
        ];

        Video::update($id, $data);
        Logger::audit('video_updated', 'video', (string) $id, $data);

        Session::setFlash('success', 'Video metadata updated successfully.');
        Response::redirect('/videos/' . $id);
    }

    public function dispatchPublish(Request $request, array $params): void
    {
        Auth::requireLogin();

        $id = (int) ($params['id'] ?? 0);
        $video = Video::findById($id);
        if (!$video) {
            Response::redirect('/videos');
            return;
        }

        $action = $request->post('publish_action'); // 'publish_now' or 'schedule'
        $selectedAccounts = (array) $request->post('account_ids', []);
        $scheduledAt = $request->post('scheduled_at');
        $force = (bool) $request->post('force_republish', false);

        if (empty($selectedAccounts)) {
            Session::setFlash('error', 'Please select at least one social account to publish to.');
            Response::redirect('/videos/' . $id);
            return;
        }

        $metaPerPlatform = [
            'youtube' => [
                'title' => $request->post('yt_title') ?: $video['title'],
                'description' => $request->post('yt_description') ?: $video['description']
            ],
            'tiktok' => [
                'caption' => $request->post('tt_caption') ?: ($video['default_caption'] ?: $video['title'])
            ],
            'instagram' => [
                'caption' => $request->post('ig_caption') ?: ($video['default_caption'] ?: $video['title'])
            ]
        ];

        try {
            $scheduleUtc = ($action === 'schedule' && !empty($scheduledAt)) ? SchedulerService::toUtc($scheduledAt) : null;
            $res = QueueManager::dispatch($id, $selectedAccounts, $metaPerPlatform, $scheduleUtc, $force);

            if (!empty($res['skipped_accounts'])) {
                Session::setFlash('info', 'Some accounts were skipped to avoid duplicates: ' . implode(', ', $res['skipped_accounts']) . '. Use Force Republish to override.');
            }
            if (!empty($res['created_job_ids'])) {
                Session::setFlash('success', count($res['created_job_ids']) . ' publishing jobs created in queue!');
            }

            Response::redirect('/videos/' . $id);
        } catch (\Throwable $e) {
            Session::setFlash('error', $e->getMessage());
            Response::redirect('/videos/' . $id);
        }
    }

    public function archive(Request $request, array $params): void
    {
        Auth::requireLogin();
        $id = (int) ($params['id'] ?? 0);
        Video::archive($id);
        Logger::audit('video_archived', 'video', (string) $id);
        Session::setFlash('success', 'Video archived without deleting publishing history.');
        Response::redirect('/videos');
    }

    public function delete(Request $request, array $params): void
    {
        Auth::requireLogin();
        $id = (int) ($params['id'] ?? 0);
        Video::delete($id);
        Logger::audit('video_deleted', 'video', (string) $id);
        Session::setFlash('success', 'Video and files permanently removed.');
        Response::redirect('/videos');
    }
}
