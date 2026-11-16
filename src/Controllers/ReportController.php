<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use App\Models\PublishingJob;

class ReportController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();

        $platformStats = PublishingJob::getPlatformStats();
        $overallStats = PublishingJob::getOverallStats();

        // Top publishing channels
        $topAccounts = Database::fetchAll(
            "SELECT sa.platform, sa.account_name, COUNT(pj.id) as total_jobs,
                    SUM(CASE WHEN pj.status = 'published' THEN 1 ELSE 0 END) as published_count,
                    SUM(CASE WHEN pj.status = 'failed' THEN 1 ELSE 0 END) as failed_count
             FROM social_accounts sa 
             LEFT JOIN publishing_jobs pj ON sa.id = pj.social_account_id 
             GROUP BY sa.id 
             ORDER BY published_count DESC"
        );

        View::render('reports.index', [
            'title' => 'Publishing Reports & Analytics',
            'platformStats' => $platformStats,
            'overallStats' => $overallStats,
            'topAccounts' => $topAccounts
        ]);
    }

    public function exportCsv(Request $request): void
    {
        Auth::requireLogin();

        $filename = 'ytclip_publishing_report_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fputs($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'Job ID',
            'Video Title',
            'Platform',
            'Social Account',
            'Status',
            'Attempt Count',
            'Scheduled At (UTC)',
            'Published At (UTC)',
            'Published URL',
            'Last Error Message'
        ]);

        $sql = "SELECT pj.id, v.title, pj.platform, sa.account_name, pj.status, pj.attempt_count,
                       pj.scheduled_at, pj.published_at, pj.published_url, pj.last_error_message 
                FROM publishing_jobs pj 
                JOIN videos v ON pj.video_id = v.id 
                JOIN social_accounts sa ON pj.social_account_id = sa.id 
                ORDER BY pj.created_at DESC";

        $stmt = Database::query($sql);
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['title'],
                ucfirst($row['platform']),
                $row['account_name'],
                strtoupper($row['status']),
                $row['attempt_count'],
                $row['scheduled_at'] ?: 'Immediate',
                $row['published_at'] ?: 'N/A',
                $row['published_url'] ?: 'N/A',
                $row['last_error_message'] ?: 'None'
            ]);
        }

        fclose($output);
        exit;
    }
}
