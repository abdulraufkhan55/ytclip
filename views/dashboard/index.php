<?php
use App\Core\Security;
?>

<!-- Action Bar / Quick Shortcuts -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="text-white fw-bold m-0">Publishing Operations Dashboard</h4>
        <p class="text-secondary small m-0">Live aggregated metrics calculated directly from database records.</p>
    </div>
    <div class="d-flex gap-2">
        <form action="<?= $app['url'] ?>/dashboard/retry-failed" method="POST" class="m-0">
            <?= $csrf_field ?>
            <button type="submit" class="btn btn-outline-warning btn-sm d-flex align-items-center gap-1" <?= ($stats['failed_jobs'] === 0) ? 'disabled' : '' ?>>
                <i class="bi bi-arrow-counterclockwise"></i>
                <span>Retry Failed Jobs (<?= $stats['failed_jobs'] ?>)</span>
            </button>
        </form>
        <a href="<?= $app['url'] ?>/dashboard" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" title="Refresh Live Status">
            <i class="bi bi-arrow-clockwise"></i>
            <span>Refresh</span>
        </a>
    </div>
</div>

<!-- Failed Job Alert (if any) -->
<?php if (!empty($failedJobs)): ?>
<div class="alert alert-danger bg-danger-subtle text-danger border-danger-subtle d-flex align-items-start gap-3 mb-4 rounded-3 p-3">
    <i class="bi bi-exclamation-octagon-fill fs-4 mt-1"></i>
    <div class="flex-grow-1">
        <div class="fw-bold mb-1">Attention Required: <?= count($failedJobs) ?> publishing job(s) failed recently!</div>
        <div class="small">
            <?php foreach ($failedJobs as $fj): ?>
                <div>• [<?= strtoupper($fj['platform']) ?>] <strong><?= Security::e($fj['title']) ?></strong> (Account: @<?= Security::e($fj['account_name']) ?>): <?= Security::e($fj['last_error_message'] ?: 'Unknown error') ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <a href="<?= $app['url'] ?>/queue?status=failed" class="btn btn-sm btn-danger align-self-center">Review Queue</a>
</div>
<?php endif; ?>

<!-- 9 Summary Metric Cards -->
<div class="row g-3 mb-4">
    <!-- Total Videos -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon bg-primary-subtle text-primary">
                <i class="bi bi-camera-reels"></i>
            </div>
            <div class="stat-value"><?= number_format($stats['total_videos']) ?></div>
            <div class="stat-label">Total Videos</div>
        </div>
    </div>
    <!-- Ready to Publish -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon bg-info-subtle text-info">
                <i class="bi bi-check2-circle"></i>
            </div>
            <div class="stat-value"><?= number_format($stats['ready_videos']) ?></div>
            <div class="stat-label">Ready to Publish</div>
        </div>
    </div>
    <!-- Queued Jobs -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon bg-secondary-subtle text-light">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-value"><?= number_format($stats['queued_jobs']) ?></div>
            <div class="stat-label">Videos Queued</div>
        </div>
    </div>
    <!-- Uploading / Processing -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon bg-warning-subtle text-warning">
                <i class="bi bi-cloud-upload"></i>
            </div>
            <div class="stat-value"><?= number_format($stats['uploading_jobs']) ?></div>
            <div class="stat-label">Uploading Now</div>
        </div>
    </div>
    <!-- Successfully Published -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon bg-success-subtle text-success">
                <i class="bi bi-patch-check-fill"></i>
            </div>
            <div class="stat-value"><?= number_format($stats['published_jobs']) ?></div>
            <div class="stat-label">Published Jobs</div>
        </div>
    </div>
    <!-- Partially Published -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon bg-warning-subtle text-warning">
                <i class="bi bi-pie-chart"></i>
            </div>
            <div class="stat-value"><?= number_format($stats['partially_published']) ?></div>
            <div class="stat-label">Partially Done</div>
        </div>
    </div>
    <!-- Failed Jobs -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon bg-danger-subtle text-danger">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <div class="stat-value text-danger"><?= number_format($stats['failed_jobs']) ?></div>
            <div class="stat-label">Failed Jobs</div>
        </div>
    </div>
    <!-- Scheduled Posts -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon bg-primary-subtle text-indigo" style="color: #818cf8;">
                <i class="bi bi-calendar-event"></i>
            </div>
            <div class="stat-value"><?= number_format($stats['scheduled_posts']) ?></div>
            <div class="stat-label">Scheduled Posts</div>
        </div>
    </div>
    <!-- Connected Accounts -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon bg-success-subtle text-success">
                <i class="bi bi-person-check-fill"></i>
            </div>
            <div class="stat-value"><?= number_format($stats['connected_accounts']) ?></div>
            <div class="stat-label">Connected Accounts</div>
        </div>
    </div>
</div>

<!-- Platform-Wise Breakdown Cards -->
<div class="row g-3 mb-4">
    <!-- YouTube Shorts -->
    <div class="col-md-4">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge-platform badge-platform-youtube"><i class="bi bi-youtube"></i> YouTube Shorts</span>
                </div>
                <span class="badge bg-secondary"><?= $platformStats['youtube']['total'] ?> Total</span>
            </div>
            <div class="card-body-custom">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary small">Published:</span>
                    <span class="text-success fw-bold"><?= $platformStats['youtube']['published'] ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary small">Queued / Scheduled:</span>
                    <span class="text-light fw-bold"><?= $platformStats['youtube']['queued'] + $platformStats['youtube']['scheduled'] ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-secondary small">Failed:</span>
                    <span class="text-danger fw-bold"><?= $platformStats['youtube']['failed'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- TikTok -->
    <div class="col-md-4">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge-platform badge-platform-tiktok"><i class="bi bi-tiktok"></i> TikTok</span>
                </div>
                <span class="badge bg-secondary"><?= $platformStats['tiktok']['total'] ?> Total</span>
            </div>
            <div class="card-body-custom">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary small">Published:</span>
                    <span class="text-success fw-bold"><?= $platformStats['tiktok']['published'] ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary small">Queued / Scheduled:</span>
                    <span class="text-light fw-bold"><?= $platformStats['tiktok']['queued'] + $platformStats['tiktok']['scheduled'] ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-secondary small">Failed:</span>
                    <span class="text-danger fw-bold"><?= $platformStats['tiktok']['failed'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Instagram Reels -->
    <div class="col-md-4">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge-platform badge-platform-instagram"><i class="bi bi-instagram"></i> Instagram Reels</span>
                </div>
                <span class="badge bg-secondary"><?= $platformStats['instagram']['total'] ?> Total</span>
            </div>
            <div class="card-body-custom">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary small">Published:</span>
                    <span class="text-success fw-bold"><?= $platformStats['instagram']['published'] ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary small">Queued / Scheduled:</span>
                    <span class="text-light fw-bold"><?= $platformStats['instagram']['queued'] + $platformStats['instagram']['scheduled'] ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-secondary small">Failed:</span>
                    <span class="text-danger fw-bold"><?= $platformStats['instagram']['failed'] ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart & Upcoming Posts -->
<div class="row g-4 mb-4">
    <!-- Chart.js 7-Day Publishing Activity -->
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-header-custom">
                <h6 class="m-0 text-white fw-bold"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Daily Publishing Activity (Last 7 Days)</h6>
                <span class="text-secondary small">Posts Published</span>
            </div>
            <div class="card-body-custom">
                <canvas id="publishingChart" height="120"></canvas>
            </div>
        </div>
    </div>

    <!-- Upcoming Scheduled Posts -->
    <div class="col-lg-4">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <h6 class="m-0 text-white fw-bold"><i class="bi bi-clock-history me-2 text-info"></i>Upcoming Scheduled Posts</h6>
                <a href="<?= $app['url'] ?>/calendar" class="small text-decoration-none">Calendar</a>
            </div>
            <div class="card-body-custom p-0">
                <?php if (empty($upcomingScheduled)): ?>
                    <div class="p-4 text-center text-secondary small">
                        <i class="bi bi-calendar-check fs-2 d-block mb-2 text-muted"></i>
                        No posts scheduled. Upload a video and pick a schedule time!
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcomingScheduled as $up): ?>
                            <div class="list-group-item bg-transparent text-light border-secondary-subtle p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge-platform badge-platform-<?= $up['platform'] ?>"><?= $up['platform'] ?></span>
                                    <span class="small text-secondary"><?= date('M d, H:i', strtotime($up['scheduled_at'])) ?></span>
                                </div>
                                <div class="fw-semibold text-truncate small"><?= Security::e($up['title']) ?></div>
                                <div class="text-muted small">@<?= Security::e($up['account_name']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Upload Activity Table -->
<div class="card-custom">
    <div class="card-header-custom">
        <h6 class="m-0 text-white fw-bold"><i class="bi bi-collection-play me-2 text-primary"></i>Recent Upload Activity</h6>
        <a href="<?= $app['url'] ?>/videos" class="btn btn-sm btn-outline-light">View All Videos</a>
    </div>
    <div class="table-responsive">
        <table class="table table-dark-custom">
            <thead>
                <tr>
                    <th>Video</th>
                    <th>Duration</th>
                    <th>Size</th>
                    <th>Status</th>
                    <th>Publishing Progress</th>
                    <th>Uploaded Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentVideos)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">
                            No videos uploaded yet. Click <strong>Upload Short</strong> to begin!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentVideos as $v): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= $app['url'] ?>/api/videos/<?= $v['id'] ?>/thumbnail" alt="thumb" style="width: 44px; height: 58px; object-fit: cover; border-radius: 6px;">
                                    <div>
                                        <div class="fw-bold text-white"><?= Security::e($v['title']) ?></div>
                                        <div class="text-muted small"><?= Security::e($v['original_filename']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= $v['duration_seconds'] ?>s</td>
                            <td><?= round($v['file_size'] / (1024 * 1024), 1) ?> MB</td>
                            <td><span class="badge-status status-<?= $v['video_status'] ?>"><?= str_replace('_', ' ', $v['video_status']) ?></span></td>
                            <td>
                                <span class="badge bg-secondary"><?= $v['published_jobs'] ?>/<?= $v['total_jobs'] ?> targets</span>
                            </td>
                            <td class="text-secondary small"><?= date('M d, Y H:i', strtotime($v['created_at'])) ?></td>
                            <td>
                                <a href="<?= $app['url'] ?>/videos/<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary">Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('publishingChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartDays) ?>,
                datasets: [{
                    label: 'Videos Published',
                    data: <?= json_encode($chartCounts) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.15)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointBackgroundColor: '#818cf8',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { color: '#1e293b' },
                        ticks: { color: '#94a3b8' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#1e293b' },
                        ticks: { color: '#94a3b8', precision: 0 }
                    }
                }
            }
        });
    }
});
</script>
