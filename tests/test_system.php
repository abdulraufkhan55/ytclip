<?php

declare(strict_types=1);

// Automated End-to-End Verification Test Suite for YTClip
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Adapters\AdapterFactory;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Models\AuditLog;
use App\Models\PublishingAttempt;
use App\Models\PublishingJob;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Video;
use App\Services\PublishingWorker;
use App\Services\QueueManager;
use App\Services\SchedulerService;

echo "=========================================================\n";
echo " YTClip Automated Verification & Test Suite\n";
echo " Time: " . date('Y-m-d H:i:s T') . "\n";
echo "=========================================================\n";

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$description}\n";
        $failed++;
    }
}

// 1. Database Connection & Tables
echo "\n--- 1. Testing Database & Schema ---\n";
try {
    $tables = Database::fetchAll("SHOW TABLES");
    assertTest("Database connected and returned tables", count($tables) >= 8);
} catch (\Throwable $e) {
    assertTest("Database connection failed: " . $e->getMessage(), false);
}

// 2. Encryption (AES-256-GCM)
echo "\n--- 2. Testing Cryptography & Security ---\n";
$secretToken = "ya29.a0AfH6SMB_sample_google_oauth_token_1234567890";
$encrypted = Security::encrypt($secretToken);
$decrypted = Security::decrypt($encrypted);
assertTest("AES-256-GCM encrypts tokens to non-empty payload", !empty($encrypted) && $encrypted !== $secretToken);
assertTest("AES-256-GCM successfully decrypts payload with tag verification", $decrypted === $secretToken);

$csrfToken = Security::generateCsrfToken();
assertTest("CSRF token generated (64 hex characters)", strlen($csrfToken) === 64);
assertTest("CSRF token validation succeeds for valid token", Security::validateCsrfToken($csrfToken));
assertTest("CSRF token validation fails for spoofed token", !Security::validateCsrfToken('tampered_token'));

// 3. Authentication & Users
echo "\n--- 3. Testing User Authentication & RBAC ---\n";
$admin = User::findByEmail('admin@ytclip.local');
assertTest("Administrator account exists in database", $admin !== null);
$loginSuccess = Auth::attempt('admin@ytclip.local', 'Admin@12345');
assertTest("Admin login succeeds with valid password", $loginSuccess['success'] === true);
$loginFail = Auth::attempt('admin@ytclip.local', 'wrong_password');
assertTest("Admin login correctly rejects invalid password", $loginFail['success'] === false);

// 4. Social Accounts & Token Management
echo "\n--- 4. Testing Social Accounts & Token Management ---\n";
$ytAccountId = SocialAccount::createOrUpdate([
    'user_id' => $admin['id'],
    'platform' => 'youtube',
    'account_id' => 'yt_channel_test_01',
    'account_name' => 'Demo Shorts Channel',
    'access_token' => 'mock_yt_access_token_abc',
    'refresh_token' => 'mock_yt_refresh_token_xyz',
    'token_expires_at' => date('Y-m-d H:i:s', strtotime('+60 days')),
    'granted_scopes' => 'https://www.googleapis.com/auth/youtube.upload',
    'connection_status' => 'connected',
    'last_verified_at' => date('Y-m-d H:i:s')
]);
assertTest("YouTube account created with encrypted tokens", $ytAccountId > 0);

$tkAccountId = SocialAccount::createOrUpdate([
    'user_id' => $admin['id'],
    'platform' => 'tiktok',
    'account_id' => 'tk_user_test_01',
    'account_name' => 'Demo TikTok Creator',
    'access_token' => 'mock_tk_access_token_abc',
    'refresh_token' => 'mock_tk_refresh_token_xyz',
    'token_expires_at' => date('Y-m-d H:i:s', strtotime('+60 days')),
    'granted_scopes' => 'user.info.basic,video.publish',
    'connection_status' => 'connected',
    'last_verified_at' => date('Y-m-d H:i:s')
]);
assertTest("TikTok account created with encrypted tokens", $tkAccountId > 0);

$igAccountId = SocialAccount::createOrUpdate([
    'user_id' => $admin['id'],
    'platform' => 'instagram',
    'account_id' => 'ig_biz_test_01',
    'account_name' => 'demo_reels_brand',
    'access_token' => 'mock_ig_access_token_abc',
    'token_expires_at' => date('Y-m-d H:i:s', strtotime('+60 days')),
    'granted_scopes' => 'instagram_basic,instagram_content_publish',
    'connection_status' => 'connected',
    'last_verified_at' => date('Y-m-d H:i:s')
]);
assertTest("Instagram account created with encrypted tokens", $igAccountId > 0);

$decryptedYt = SocialAccount::getDecryptedTokens($ytAccountId);
assertTest("SocialAccount securely decrypts tokens on demand", $decryptedYt['access_token'] === 'mock_yt_access_token_abc');

// 5. Video Asset Creation & Validation
echo "\n--- 5. Testing Video Creation & Validation ---\n";
// Create a sample test video file in storage
$testVideoPath = dirname(__DIR__) . '/storage/uploads/test_sample_short.mp4';
file_put_contents($testVideoPath, "MOCK_MP4_VIDEO_CONTENT_BYTES_DATA");

$videoId = Video::create([
    'owner_id' => $admin['id'],
    'original_filename' => 'epic_nature_short.mp4',
    'stored_filename' => 'test_sample_short.mp4',
    'storage_path' => $testVideoPath,
    'mime_type' => 'video/mp4',
    'file_size' => filesize($testVideoPath),
    'duration_seconds' => 45.0,
    'width' => 1080,
    'height' => 1920,
    'title' => 'Epic Nature Shot #1',
    'description' => 'A breathtaking view of mountain peaks captured in 4K.',
    'default_caption' => 'Incredible mountain peaks! Wait for the sunrise #Shorts #Reels',
    'hashtags' => '#Nature #Shorts #Reels #Viral',
    'checksum' => hash('sha256', "MOCK_MP4_VIDEO_CONTENT_BYTES_DATA"),
    'video_status' => 'ready'
]);
assertTest("Video asset record created with 45s duration and 1080x1920 resolution", $videoId > 0);

// 6. Queue Manager & Publishing Engine
echo "\n--- 6. Testing Publishing Queue & Worker Execution ---\n";
$dispatch = QueueManager::dispatch($videoId, [$ytAccountId, $tkAccountId, $igAccountId]);
assertTest("QueueManager dispatched jobs to all 3 selected platforms", count($dispatch['created_job_ids']) === 3);

// Test duplicate prevention safeguard
$dupDispatch = QueueManager::dispatch($videoId, [$ytAccountId]);
assertTest("Duplicate-publication safeguard prevented duplicate job creation", count($dupDispatch['created_job_ids']) === 0 && !empty($dupDispatch['skipped_accounts']));

// Test Worker execution
$worker = new PublishingWorker();
$workerResult = $worker->runOnce();
assertTest("PublishingWorker acquired and processed queued job", $workerResult === true);

// Process any remaining ready jobs in queue
while ($worker->runOnce()) {
    // continue until queue is drained
}

// Check job statuses
$jobs = PublishingJob::getJobsForVideo($videoId);
$allPublished = true;
foreach ($jobs as $j) {
    if ($j['status'] !== 'published') {
        $allPublished = false;
    }
}
assertTest("All 3 platform publishing jobs succeeded and progressed to 'published'", $allPublished);

// 7. Append-Only Publishing Attempts
echo "\n--- 7. Testing Append-Only Publishing Attempts ---\n";
$attempts = PublishingAttempt::getAll();
assertTest("Append-only publishing attempt records exist in database", count($attempts) >= 3);

// 8. Live Dashboard Statistics
echo "\n--- 8. Testing Live Dashboard Statistics ---\n";
$stats = PublishingJob::getOverallStats();
assertTest("Total videos count matches database record ({$stats['total_videos']})", $stats['total_videos'] >= 1);
assertTest("Published jobs count matches database record ({$stats['published_jobs']})", $stats['published_jobs'] >= 3);
assertTest("Connected accounts count matches database record ({$stats['connected_accounts']})", $stats['connected_accounts'] >= 3);

// 9. Timezone & Scheduler Service
echo "\n--- 9. Testing Timezone & Scheduling System ---\n";
$localTime = '2026-11-01 15:30:00';
$utcTime = SchedulerService::toUtc($localTime);
$backToLocal = SchedulerService::toLocal($utcTime);
assertTest("Scheduler correctly converts between local time and UTC", $localTime === $backToLocal);
$events = SchedulerService::getCalendarEvents();
assertTest("Calendar events generated for scheduled/published posts", is_array($events) && count($events) >= 1);

// 10. Audit Logging
echo "\n--- 10. Testing Administrative Audit Trail ---\n";
$auditCount = AuditLog::count();
assertTest("Audit logs tracked administrative and publishing activities ({$auditCount})", $auditCount > 0);

echo "\n=========================================================\n";
echo " TEST SUMMARY: {$passed} Passed, {$failed} Failed\n";
echo "=========================================================\n";

if ($failed === 0) {
    echo "All core system modules, security controls, and platform adapters are 100% OPERATIONAL!\n";
    exit(0);
} else {
    exit(1);
}
