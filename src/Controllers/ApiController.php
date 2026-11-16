<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\PublishingJob;
use App\Models\Video;
use App\Services\PublishingWorker;

class ApiController
{
    /**
     * Stream video file with HTTP byte-range support for seamless video player seeking
     */
    public function streamVideo(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $video = Video::findById($id);

        if (!$video || !file_exists($video['storage_path'])) {
            http_response_code(404);
            echo "Video file not found.";
            exit;
        }

        $filePath = $video['storage_path'];
        $fileSize = filesize($filePath);
        $mime = $video['mime_type'] ?: 'video/mp4';

        $start = 0;
        $end = $fileSize - 1;

        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = $_SERVER['HTTP_RANGE'];
            if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $range, $matches)) {
                $start = (int) $matches[1];
                if (!empty($matches[2])) {
                    $end = (int) $matches[2];
                }
            }
            http_response_code(206);
            header("HTTP/1.1 206 Partial Content");
            header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
        } else {
            http_response_code(200);
        }

        header("Content-Type: {$mime}");
        header("Accept-Ranges: bytes");
        header("Content-Length: " . ($end - $start + 1));
        header("Cache-Control: public, max-age=86400");

        $fp = fopen($filePath, 'rb');
        fseek($fp, $start);
        $buffer = 1024 * 64;

        while (!feof($fp) && ($pos = ftell($fp)) <= $end) {
            if ($pos + $buffer > $end) {
                $buffer = $end - $pos + 1;
            }
            echo fread($fp, $buffer);
            flush();
        }
        fclose($fp);
        exit;
    }

    public function streamThumbnail(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $video = Video::findById($id);

        if ($video && !empty($video['thumbnail_path']) && file_exists($video['thumbnail_path'])) {
            header('Content-Type: image/jpeg');
            header('Content-Length: ' . filesize($video['thumbnail_path']));
            readfile($video['thumbnail_path']);
            exit;
        }

        // Return inline SVG video placeholder
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="360" height="640" viewBox="0 0 360 640" fill="#1e293b">
                <rect width="100%" height="100%" fill="#0f172a"/>
                <circle cx="180" cy="320" r="45" fill="#3b82f6" fill-opacity="0.8"/>
                <polygon points="170,300 200,320 170,340" fill="#ffffff"/>
                <text x="180" y="390" font-family="system-ui, sans-serif" font-size="14" fill="#94a3b8" text-anchor="middle">Short Video Preview</text>
              </svg>';
        exit;
    }

    public function jobStatus(Request $request, array $params): void
    {
        Auth::requireLogin();
        $id = (int) ($params['id'] ?? 0);
        $job = PublishingJob::findById($id);

        if (!$job) {
            Response::json(['success' => false, 'message' => 'Job not found'], 404);
            return;
        }

        Response::json([
            'success' => true,
            'job' => [
                'id' => $job['id'],
                'status' => $job['status'],
                'platform' => $job['platform'],
                'published_url' => $job['published_url'],
                'attempt_count' => $job['attempt_count'],
                'last_error' => $job['last_error_message']
            ]
        ]);
    }

    public function runWorkerAjax(Request $request): void
    {
        Auth::requireLogin();
        $worker = new PublishingWorker();
        $worked = $worker->runOnce();

        Response::json([
            'success' => true,
            'worked' => $worked,
            'message' => $worked ? 'Worker processed queue jobs.' : 'No jobs currently pending.'
        ]);
    }
}
