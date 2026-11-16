<?php
use App\Core\Security;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="text-white fw-bold m-0">Publishing Queue</h4>
        <p class="text-secondary small m-0">Live background publishing queue monitored by the CLI worker process.</p>
    </div>
    <div class="d-flex gap-2">
        <form action="<?= $app['url'] ?>/queue/worker-now" method="POST" class="m-0">
            <?= $csrf_field ?>
            <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-play-circle"></i>
                <span>Execute Worker Batch Now</span>
            </button>
        </form>
    </div>
</div>

<!-- Filters -->
<div class="card-custom mb-4">
    <div class="card-body-custom p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= $app['url'] ?>/queue" class="btn btn-sm <?= empty($currentStatus) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Active Queue
                </a>
                <a href="<?= $app['url'] ?>/queue?status=queued" class="btn btn-sm <?= $currentStatus === 'queued' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Queued
                </a>
                <a href="<?= $app['url'] ?>/queue?status=uploading" class="btn btn-sm <?= $currentStatus === 'uploading' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Uploading / Transcoding
                </a>
                <a href="<?= $app['url'] ?>/queue?status=retry_scheduled" class="btn btn-sm <?= $currentStatus === 'retry_scheduled' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Retry Scheduled
                </a>
                <a href="<?= $app['url'] ?>/queue?status=failed" class="btn btn-sm <?= $currentStatus === 'failed' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Failed
                </a>
                <a href="<?= $app['url'] ?>/queue?status=published" class="btn btn-sm <?= $currentStatus === 'published' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Published
                </a>
            </div>

            <div class="d-flex gap-2">
                <a href="<?= $app['url'] ?>/queue?platform=youtube" class="btn btn-sm btn-outline-danger">YouTube</a>
                <a href="<?= $app['url'] ?>/queue?platform=tiktok" class="btn btn-sm btn-outline-info">TikTok</a>
                <a href="<?= $app['url'] ?>/queue?platform=instagram" class="btn btn-sm btn-outline-light" style="color: #f472b6; border-color: #f472b6;">Instagram</a>
            </div>
        </div>
    </div>
</div>

<!-- Queue Jobs Table -->
<div class="card-custom">
    <div class="table-responsive">
        <table class="table table-dark-custom">
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Video Title</th>
                    <th>Platform</th>
                    <th>Target Account</th>
                    <th>Scheduled For</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Last Error / Status Detail</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jobs)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-5">
                            <i class="bi bi-check-all fs-1 d-block mb-2 text-success"></i>
                            Queue is currently empty! No publishing jobs waiting.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($jobs as $j): ?>
                        <tr>
                            <td><code class="text-secondary">#<?= $j['id'] ?></code></td>
                            <td>
                                <strong class="text-white"><?= Security::e($j['video_title']) ?></strong>
                            </td>
                            <td>
                                <span class="badge-platform badge-platform-<?= $j['platform'] ?>">
                                    <?= $j['platform'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-white small">@<?= Security::e($j['account_name']) ?></span>
                            </td>
                            <td class="text-secondary small">
                                <?= $j['scheduled_at'] ? date('M d, H:i', strtotime($j['scheduled_at'])) : '<span class="text-info">Immediate</span>' ?>
                            </td>
                            <td>
                                <span class="badge-status status-<?= $j['status'] ?>">
                                    <?= str_replace('_', ' ', $j['status']) ?>
                                </span>
                            </td>
                            <td class="small">
                                <?= $j['attempt_count'] ?>/<?= $j['max_attempts'] ?>
                            </td>
                            <td class="small" style="max-width: 200px;">
                                <?php if (!empty($j['last_error_message'])): ?>
                                    <span class="text-danger" title="<?= Security::e($j['last_error_message']) ?>">
                                        <?= Security::e(mb_substr($j['last_error_message'], 0, 40)) ?>...
                                    </span>
                                <?php elseif (!empty($j['published_url'])): ?>
                                    <a href="<?= Security::e($j['published_url']) ?>" target="_blank" class="text-success text-decoration-none">
                                        <i class="bi bi-check-circle"></i> Published
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <?php if ($j['status'] === 'failed'): ?>
                                        <form action="<?= $app['url'] ?>/queue/retry/<?= $j['id'] ?>" method="POST" class="d-inline">
                                            <?= $csrf_field ?>
                                            <button type="submit" class="btn btn-outline-warning" title="Retry Job">
                                                <i class="bi bi-arrow-counterclockwise"></i> Retry
                                            </button>
                                        </form>
                                    <?php elseif (in_array($j['status'], ['queued', 'retry_scheduled'])): ?>
                                        <form action="<?= $app['url'] ?>/queue/cancel/<?= $j['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('Cancel this queued job?');">
                                            <?= $csrf_field ?>
                                            <button type="submit" class="btn btn-outline-danger" title="Cancel Job">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
