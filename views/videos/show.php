<?php
use App\Core\Security;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge-status status-<?= $video['video_status'] ?>"><?= str_replace('_', ' ', $video['video_status']) ?></span>
            <h4 class="text-white fw-bold m-0"><?= Security::e($video['title']) ?></h4>
        </div>
        <p class="text-secondary small m-0">Uploaded on <?= date('M d, Y H:i', strtotime($video['created_at'])) ?> by <?= Security::e($video['owner_name'] ?? 'Admin') ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $app['url'] ?>/videos" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Library
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Video Player & Technical Specs -->
    <div class="col-lg-5">
        <div class="card-custom mb-4">
            <div class="card-body-custom text-center p-3">
                <video controls playsinline class="rounded-3 shadow-lg w-100" style="max-height: 480px; object-fit: contain; background: #000;" poster="<?= $app['url'] ?>/api/videos/<?= $video['id'] ?>/thumbnail">
                    <source src="<?= $app['url'] ?>/api/videos/<?= $video['id'] ?>/stream" type="<?= $video['mime_type'] ?: 'video/mp4' ?>">
                    Your browser does not support HTML5 video preview.
                </video>
            </div>
            <div class="card-body-custom border-top border-secondary-subtle pt-3">
                <h6 class="text-white fw-bold mb-3 small text-uppercase letter-spacing">Technical Media Properties</h6>
                <div class="row g-2 text-secondary small">
                    <div class="col-6"><strong>Duration:</strong> <span class="text-white"><?= $video['duration_seconds'] ?>s</span></div>
                    <div class="col-6"><strong>Resolution:</strong> <span class="text-white"><?= $video['width'] ?>x<?= $video['height'] ?></span></div>
                    <div class="col-6"><strong>File Size:</strong> <span class="text-white"><?= round($video['file_size'] / (1024*1024), 2) ?> MB</span></div>
                    <div class="col-6"><strong>MIME Type:</strong> <span class="text-white"><?= $video['mime_type'] ?></span></div>
                    <div class="col-12 mt-2">
                        <strong>SHA-256 Checksum:</strong>
                        <div class="font-monospace small text-truncate text-muted mt-1" style="font-size: 0.75rem;" title="<?= $video['checksum'] ?>"><?= $video['checksum'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Danger Zone / Archive -->
        <div class="card-custom">
            <div class="card-body-custom p-3 d-flex justify-content-between align-items-center">
                <div>
                    <strong class="text-white small d-block">Manage Asset Lifecycle</strong>
                    <span class="text-secondary small">Preserve publishing records or remove file</span>
                </div>
                <div class="d-flex gap-2">
                    <form action="<?= $app['url'] ?>/videos/<?= $video['id'] ?>/archive" method="POST" class="m-0" onsubmit="return confirm('Archive video?');">
                        <?= $csrf_field ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Archive</button>
                    </form>
                    <form action="<?= $app['url'] ?>/videos/<?= $video['id'] ?>/delete" method="POST" class="m-0" onsubmit="return confirm('Permanently delete video?');">
                        <?= $csrf_field ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Metadata Editor & Multi-Platform Dispatcher -->
    <div class="col-lg-7">
        <!-- Edit Global Metadata Form -->
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <h6 class="m-0 text-white fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Metadata Editor</h6>
            </div>
            <div class="card-body-custom">
                <form action="<?= $app['url'] ?>/videos/<?= $video['id'] ?>/update" method="POST">
                    <?= $csrf_field ?>
                    <div class="mb-3">
                        <label class="form-label">Video Title</label>
                        <input type="text" name="title" class="form-control" value="<?= Security::e($video['title']) ?>" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Default Caption</label>
                            <textarea name="default_caption" class="form-control" rows="2"><?= Security::e($video['default_caption'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hashtags</label>
                            <input type="text" name="hashtags" class="form-control" value="<?= Security::e($video['hashtags'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Extended Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= Security::e($video['description'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-save me-1"></i> Save Metadata Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- Publishing Dispatch Form -->
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <h6 class="m-0 text-white fw-bold"><i class="bi bi-send-fill me-2 text-success"></i>Dispatch to Social Accounts</h6>
            </div>
            <div class="card-body-custom">
                <form action="<?= $app['url'] ?>/videos/<?= $video['id'] ?>/dispatch" method="POST">
                    <?= $csrf_field ?>

                    <div class="mb-3">
                        <label class="form-label">Select Destination Accounts</label>
                        <div class="row g-2">
                            <?php foreach (['youtube', 'tiktok', 'instagram'] as $p): ?>
                                <div class="col-md-4">
                                    <div class="p-2 rounded-2 bg-dark border border-secondary small">
                                        <div class="badge-platform badge-platform-<?= $p ?> mb-1"><?= ucfirst($p) ?></div>
                                        <?php if (!empty($connectedAccounts[$p])): ?>
                                            <?php foreach ($connectedAccounts[$p] as $acc): ?>
                                                <div class="form-check text-light mt-1">
                                                    <input class="form-check-input" type="checkbox" name="account_ids[]" value="<?= $acc['id'] ?>" id="show_acc_<?= $acc['id'] ?>">
                                                    <label class="form-check-label text-truncate" for="show_acc_<?= $acc['id'] ?>" style="max-width: 140px;">
                                                        <?= Security::e($acc['account_name']) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-muted small">No account linked</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Action</label>
                            <select name="publish_action" class="form-select" id="dispatchActionSelect">
                                <option value="publish_now">Publish Immediately (Queue)</option>
                                <option value="schedule">Schedule for Future Time</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="dispatchScheduleCol" style="display: none;">
                            <label class="form-label">Date & Time</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control">
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="force_republish" id="forceRepublish" value="1">
                        <label class="form-check-label text-secondary small" for="forceRepublish">
                            <strong>Force Republish</strong> (Override duplicate-publishing safeguard for already published targets)
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary-custom">
                        <i class="bi bi-rocket-takeoff me-1"></i> Dispatch Publishing Jobs
                    </button>
                </form>
            </div>
        </div>

        <!-- Publishing Jobs & History for this video -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h6 class="m-0 text-white fw-bold"><i class="bi bi-clock-history me-2 text-info"></i>Publishing Targets & Status</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-custom">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Account</th>
                            <th>Status</th>
                            <th>Attempts</th>
                            <th>Published Link</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jobs)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-3 small">
                                    No publishing jobs created for this video yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jobs as $j): ?>
                                <tr>
                                    <td><span class="badge-platform badge-platform-<?= $j['platform'] ?>"><?= $j['platform'] ?></span></td>
                                    <td class="text-white small">@<?= Security::e($j['account_name']) ?></td>
                                    <td><span class="badge-status status-<?= $j['status'] ?>"><?= $j['status'] ?></span></td>
                                    <td class="small"><?= $j['attempt_count'] ?>/<?= $j['max_attempts'] ?></td>
                                    <td>
                                        <?php if (!empty($j['published_url'])): ?>
                                            <a href="<?= Security::e($j['published_url']) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-box-arrow-up-right me-1"></i> Open Video
                                            </a>
                                        <?php elseif (!empty($j['last_error_message'])): ?>
                                            <span class="text-danger small" title="<?= Security::e($j['last_error_message']) ?>">
                                                <?= Security::e(mb_substr($j['last_error_message'], 0, 30)) ?>...
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($j['status'] === 'failed'): ?>
                                            <form action="<?= $app['url'] ?>/queue/retry/<?= $j['id'] ?>" method="POST" class="m-0">
                                                <?= $csrf_field ?>
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Retry">Retry</button>
                                            </form>
                                        <?php elseif (in_array($j['status'], ['queued', 'retry_scheduled'])): ?>
                                            <form action="<?= $app['url'] ?>/queue/cancel/<?= $j['id'] ?>" method="POST" class="m-0">
                                                <?= $csrf_field ?>
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Cancel">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const actionSelect = document.getElementById('dispatchActionSelect');
    const scheduleCol = document.getElementById('dispatchScheduleCol');
    if (actionSelect && scheduleCol) {
        actionSelect.addEventListener('change', () => {
            scheduleCol.style.display = actionSelect.value === 'schedule' ? 'block' : 'none';
        });
    }
});
</script>
