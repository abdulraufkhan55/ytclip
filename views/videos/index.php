<?php
use App\Core\Security;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
    <div>
        <h4 class="text-white fw-bold m-0">Video Library</h4>
        <p class="text-secondary small m-0">Browse, manage, and dispatch short videos to connected social media platforms.</p>
    </div>
    <div class="d-flex gap-2">
        <form action="<?= $app['url'] ?>/videos/sync-folder" method="POST" class="m-0">
            <?= $csrf_field ?>
            <button type="submit" class="btn btn-outline-info btn-sm d-flex align-items-center gap-1" title="Scan 'Upload videos man' folder for newly added files">
                <i class="bi bi-folder-symlink"></i>
                <span>Sync 'Upload videos man' Folder</span>
            </button>
        </form>
        <a href="<?= $app['url'] ?>/upload" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-cloud-arrow-up"></i>
            <span>Upload New Video</span>
        </a>
    </div>
</div>

<!-- Manual Folder Quick Info -->
<div class="alert alert-dark border-secondary-subtle d-flex flex-wrap justify-content-between align-items-center py-2 px-3 mb-3 rounded-3 small">
    <div>
        <i class="bi bi-folder2-open text-warning me-2 fs-6"></i>
        <span>Manual Upload Folder:</span>
        <code class="text-info mx-1">C:\xampp\htdocs\ytclip\Upload videos man</code>
    </div>
    <span class="text-secondary">Copy/paste videos into this folder, then refresh or click <strong>Sync</strong>!</span>
</div>

<!-- Status Filters & Search Bar -->
<div class="card-custom mb-4">
    <div class="card-body-custom p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= $app['url'] ?>/videos" class="btn btn-sm <?= empty($currentStatus) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    All (<?= $stats['total'] ?>)
                </a>
                <a href="<?= $app['url'] ?>/videos?status=ready" class="btn btn-sm <?= $currentStatus === 'ready' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Ready (<?= $stats['ready'] ?>)
                </a>
                <a href="<?= $app['url'] ?>/videos?status=published" class="btn btn-sm <?= $currentStatus === 'published' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Published (<?= $stats['published'] ?>)
                </a>
                <a href="<?= $app['url'] ?>/videos?status=failed" class="btn btn-sm <?= $currentStatus === 'failed' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Failed (<?= $stats['failed'] ?>)
                </a>
                <a href="<?= $app['url'] ?>/videos?status=draft" class="btn btn-sm <?= $currentStatus === 'draft' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Drafts (<?= $stats['draft'] ?>)
                </a>
                <a href="<?= $app['url'] ?>/videos?status=archived" class="btn btn-sm <?= $currentStatus === 'archived' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Archived (<?= $stats['archived'] ?>)
                </a>
            </div>

            <form action="<?= $app['url'] ?>/videos" method="GET" class="d-flex gap-2 m-0" style="min-width: 260px;">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by title..." value="<?= Security::e($search ?? '') ?>">
                <button type="submit" class="btn btn-sm btn-outline-light"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>
</div>

<!-- Video List Table -->
<div class="card-custom">
    <div class="table-responsive">
        <table class="table table-dark-custom">
            <thead>
                <tr>
                    <th>Video</th>
                    <th>Duration</th>
                    <th>Resolution</th>
                    <th>Size</th>
                    <th>Status</th>
                    <th>Publishing Progress</th>
                    <th>Uploaded</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($videos)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-5">
                            <i class="bi bi-camera-video fs-1 d-block mb-2 text-muted"></i>
                            No videos found matching your filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($videos as $v): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= $app['url'] ?>/api/videos/<?= $v['id'] ?>/thumbnail" alt="thumbnail" style="width: 48px; height: 68px; object-fit: cover; border-radius: 6px;">
                                    <div>
                                        <a href="<?= $app['url'] ?>/videos/<?= $v['id'] ?>" class="text-white fw-bold text-decoration-none">
                                            <?= Security::e($v['title']) ?>
                                        </a>
                                        <div class="text-muted small"><?= Security::e($v['original_filename']) ?></div>
                                        <div class="text-secondary small mt-1">
                                            By <?= Security::e($v['owner_name'] ?? 'Admin') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary"><?= $v['duration_seconds'] ?>s</span>
                            </td>
                            <td class="text-secondary small">
                                <?= $v['width'] ? "{$v['width']}x{$v['height']}" : '1080x1920' ?>
                            </td>
                            <td class="text-secondary small">
                                <?= round($v['file_size'] / (1024 * 1024), 1) ?> MB
                            </td>
                            <td>
                                <span class="badge-status status-<?= $v['video_status'] ?>">
                                    <?= str_replace('_', ' ', $v['video_status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="small fw-semibold mb-1">
                                    <?= $v['published_jobs'] ?> of <?= $v['total_jobs'] ?> targets
                                </div>
                                <div class="progress" style="height: 5px; width: 100px; background-color: #1e293b;">
                                    <?php $pct = $v['total_jobs'] > 0 ? round(($v['published_jobs'] / $v['total_jobs']) * 100) : 0; ?>
                                    <div class="progress-bar bg-success" style="width: <?= $pct ?>%;"></div>
                                </div>
                            </td>
                            <td class="text-secondary small">
                                <?= date('M d, Y H:i', strtotime($v['created_at'])) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= $app['url'] ?>/videos/<?= $v['id'] ?>" class="btn btn-outline-primary" title="View & Publish">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($v['video_status'] !== 'archived'): ?>
                                    <form action="<?= $app['url'] ?>/videos/<?= $v['id'] ?>/archive" method="POST" class="d-inline" onsubmit="return confirm('Archive video? (Publishing history will be preserved)');">
                                        <?= $csrf_field ?>
                                        <button type="submit" class="btn btn-outline-secondary" title="Archive Video">
                                            <i class="bi bi-archive"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form action="<?= $app['url'] ?>/videos/<?= $v['id'] ?>/delete" method="POST" class="d-inline" onsubmit="return confirm('Permanently delete video and files?');">
                                        <?= $csrf_field ?>
                                        <button type="submit" class="btn btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
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
