<?php
use App\Core\Security;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="text-white fw-bold m-0">Publishing Analytics & Reports</h4>
        <p class="text-secondary small m-0">Platform performance, success rates, and exportable publishing audits.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $app['url'] ?>/reports/export" class="btn btn-success btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-file-earmark-spreadsheet-fill"></i>
            <span>Export Publishing History to CSV</span>
        </a>
    </div>
</div>

<!-- High Level Performance Cards -->
<div class="row g-4 mb-4">
    <?php foreach (['youtube' => 'YouTube Shorts', 'tiktok' => 'TikTok', 'instagram' => 'Instagram Reels'] as $pKey => $pName): ?>
        <?php 
        $ps = $platformStats[$pKey];
        $rate = $ps['total'] > 0 ? round(($ps['published'] / $ps['total']) * 100, 1) : 0;
        ?>
        <div class="col-md-4">
            <div class="card-custom h-100">
                <div class="card-header-custom">
                    <span class="badge-platform badge-platform-<?= $pKey ?>"><?= $pName ?></span>
                    <strong class="text-white small"><?= $rate ?>% Success Rate</strong>
                </div>
                <div class="card-body-custom">
                    <div class="progress mb-3" style="height: 8px; background: #1e293b;">
                        <div class="progress-bar bg-success" style="width: <?= $rate ?>%;"></div>
                    </div>
                    <div class="row g-2 text-center text-secondary small">
                        <div class="col-4">
                            <div class="text-white fw-bold fs-5"><?= $ps['total'] ?></div>
                            <div>Total Jobs</div>
                        </div>
                        <div class="col-4">
                            <div class="text-success fw-bold fs-5"><?= $ps['published'] ?></div>
                            <div>Published</div>
                        </div>
                        <div class="col-4">
                            <div class="text-danger fw-bold fs-5"><?= $ps['failed'] ?></div>
                            <div>Failed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Channel Performance Table -->
<div class="card-custom">
    <div class="card-header-custom">
        <h6 class="m-0 text-white fw-bold"><i class="bi bi-trophy me-2 text-warning"></i>Channel Performance Breakdown</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-dark-custom">
            <thead>
                <tr>
                    <th>Platform</th>
                    <th>Account Name</th>
                    <th>Total Dispatches</th>
                    <th>Published Posts</th>
                    <th>Failed Posts</th>
                    <th>Publishing Efficiency</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($topAccounts)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">No social accounts connected yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($topAccounts as $ta): ?>
                        <?php 
                        $accRate = $ta['total_jobs'] > 0 ? round(($ta['published_count'] / $ta['total_jobs']) * 100) : 0;
                        ?>
                        <tr>
                            <td><span class="badge-platform badge-platform-<?= $ta['platform'] ?>"><?= $ta['platform'] ?></span></td>
                            <td><strong class="text-white">@<?= Security::e($ta['account_name']) ?></strong></td>
                            <td><?= $ta['total_jobs'] ?></td>
                            <td class="text-success fw-bold"><?= $ta['published_count'] ?></td>
                            <td class="text-danger"><?= $ta['failed_count'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; background: #1e293b;">
                                        <div class="progress-bar bg-success" style="width: <?= $accRate ?>%;"></div>
                                    </div>
                                    <span class="small fw-semibold"><?= $accRate ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
