<?php
use App\Core\Security;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="text-white fw-bold m-0">Publishing Tracker</h4>
        <p class="text-secondary small m-0">Consolidated matrix view of videos across YouTube Shorts, TikTok, and Instagram Reels.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $app['url'] ?>/reports/export" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-file-earmark-excel"></i>
            <span>Export CSV Report</span>
        </a>
    </div>
</div>

<!-- Per-Platform Stats Bar -->
<div class="row g-3 mb-4">
    <?php foreach (['youtube' => 'YouTube Shorts', 'tiktok' => 'TikTok', 'instagram' => 'Instagram Reels'] as $pKey => $pName): ?>
        <?php $ps = $platformStats[$pKey]; ?>
        <div class="col-md-4">
            <div class="card-custom p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge-platform badge-platform-<?= $pKey ?>"><?= $pName ?></span>
                    <strong class="text-white small"><?= $ps['published'] ?> Published</strong>
                </div>
                <div class="d-flex justify-content-between text-secondary small">
                    <span>Queued: <strong class="text-white"><?= $ps['queued'] ?></strong></span>
                    <span>Uploading: <strong class="text-warning"><?= $ps['uploading'] ?></strong></span>
                    <span>Failed: <strong class="text-danger"><?= $ps['failed'] ?></strong></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Matrix Tracker Table -->
<div class="card-custom">
    <div class="table-responsive">
        <table class="table table-dark-custom">
            <thead>
                <tr>
                    <th style="min-width: 220px;">Video Asset</th>
                    <th>Duration</th>
                    <th>Uploaded Date</th>
                    <th class="text-center">YouTube Shorts</th>
                    <th class="text-center">TikTok</th>
                    <th class="text-center">Instagram Reels</th>
                    <th>Scheduled / Attempt</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-5">
                            <i class="bi bi-table fs-1 d-block mb-2 text-muted"></i>
                            No videos in publishing tracker yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        // Parse platform_jobs_summary string: "platform:status:url:job_id||platform:status:url:job_id"
                        $targets = ['youtube' => null, 'tiktok' => null, 'instagram' => null];
                        if (!empty($r['platform_jobs_summary'])) {
                            $items = explode('||', $r['platform_jobs_summary']);
                            foreach ($items as $item) {
                                $parts = explode(':', $item, 4);
                                if (count($parts) >= 2) {
                                    $targets[$parts[0]] = [
                                        'status' => $parts[1],
                                        'url' => $parts[2] ?? '',
                                        'job_id' => $parts[3] ?? ''
                                    ];
                                }
                            }
                        }
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= $app['url'] ?>/api/videos/<?= $r['video_id'] ?>/thumbnail" alt="thumb" style="width: 40px; height: 56px; object-fit: cover; border-radius: 5px;">
                                    <div>
                                        <a href="<?= $app['url'] ?>/videos/<?= $r['video_id'] ?>" class="text-white fw-bold text-decoration-none small">
                                            <?= Security::e($r['title']) ?>
                                        </a>
                                        <?php if (!empty($r['last_error'])): ?>
                                            <div class="text-danger small text-truncate" style="max-width: 180px;" title="<?= Security::e($r['last_error']) ?>">
                                                <i class="bi bi-exclamation-triangle"></i> <?= Security::e($r['last_error']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= $r['duration_seconds'] ?>s</td>
                            <td class="text-secondary small"><?= date('M d, Y', strtotime($r['video_created_at'])) ?></td>
                            
                            <!-- YouTube Status Column -->
                            <td class="text-center">
                                <?php if ($targets['youtube']): ?>
                                    <span class="badge-status status-<?= $targets['youtube']['status'] ?> d-inline-block mb-1">
                                        <?= str_replace('_', ' ', $targets['youtube']['status']) ?>
                                    </span>
                                    <?php if (!empty($targets['youtube']['url'])): ?>
                                        <div><a href="<?= Security::e($targets['youtube']['url']) ?>" target="_blank" class="small text-danger text-decoration-none"><i class="bi bi-play-circle"></i> View</a></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- TikTok Status Column -->
                            <td class="text-center">
                                <?php if ($targets['tiktok']): ?>
                                    <span class="badge-status status-<?= $targets['tiktok']['status'] ?> d-inline-block mb-1">
                                        <?= str_replace('_', ' ', $targets['tiktok']['status']) ?>
                                    </span>
                                    <?php if (!empty($targets['tiktok']['url'])): ?>
                                        <div><a href="<?= Security::e($targets['tiktok']['url']) ?>" target="_blank" class="small text-info text-decoration-none"><i class="bi bi-play-circle"></i> View</a></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Instagram Status Column -->
                            <td class="text-center">
                                <?php if ($targets['instagram']): ?>
                                    <span class="badge-status status-<?= $targets['instagram']['status'] ?> d-inline-block mb-1">
                                        <?= str_replace('_', ' ', $targets['instagram']['status']) ?>
                                    </span>
                                    <?php if (!empty($targets['instagram']['url'])): ?>
                                        <div><a href="<?= Security::e($targets['instagram']['url']) ?>" target="_blank" class="small text-pink text-decoration-none" style="color: #f472b6;"><i class="bi bi-play-circle"></i> View</a></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Scheduled / Attempt -->
                            <td class="text-secondary small">
                                <?php if ($r['scheduled_time']): ?>
                                    <div><i class="bi bi-clock"></i> <?= date('M d, H:i', strtotime($r['scheduled_time'])) ?></div>
                                <?php elseif ($r['last_attempt_time']): ?>
                                    <div>Last: <?= date('M d, H:i', strtotime($r['last_attempt_time'])) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">Immediate</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= $app['url'] ?>/videos/<?= $r['video_id'] ?>" class="btn btn-outline-primary" title="Details & Metadata">
                                        <i class="bi bi-sliders"></i>
                                    </a>
                                    <form action="<?= $app['url'] ?>/tracker/retry-video/<?= $r['video_id'] ?>" method="POST" class="d-inline">
                                        <?= $csrf_field ?>
                                        <button type="submit" class="btn btn-outline-warning" title="Retry All Failed For This Video">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
