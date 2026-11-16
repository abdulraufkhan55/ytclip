<?php
use App\Core\Auth;
use App\Core\Security;

$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$appUrl = $app['url'];
$isActive = function(string $route) use ($currentUri, $appUrl) {
    return str_contains($currentUri, $route) ? 'active' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Security::e($title ?? 'Dashboard') ?> - <?= Security::e($app['name']) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom Application CSS -->
    <link href="<?= $appUrl ?>/assets/css/app.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-wrapper">
    <!-- Sidebar -->
    <aside class="app-sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="bi bi-play-btn-fill"></i>
            </div>
            <div>
                <div class="brand-text">YTClip</div>
                <span class="brand-badge"><?= $app['mock_mode'] ? 'DEV MOCK' : 'LIVE API' ?></span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">Overview</div>
            <a href="<?= $appUrl ?>/dashboard" class="nav-link <?= $isActive('/dashboard') ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>

            <div class="nav-section-title">Video Management</div>
            <a href="<?= $appUrl ?>/videos" class="nav-link <?= $isActive('/videos') ?>">
                <i class="bi bi-collection-play-fill"></i>
                <span>Video Library</span>
            </a>
            <a href="<?= $appUrl ?>/upload" class="nav-link <?= $isActive('/upload') ?>">
                <i class="bi bi-cloud-arrow-up-fill"></i>
                <span>Upload Video</span>
            </a>
            <a href="<?= $appUrl ?>/calendar" class="nav-link <?= $isActive('/calendar') ?>">
                <i class="bi bi-calendar-event-fill"></i>
                <span>Scheduling Calendar</span>
            </a>

            <div class="nav-section-title">Publishing Engine</div>
            <a href="<?= $appUrl ?>/queue" class="nav-link <?= $isActive('/queue') ?>">
                <i class="bi bi-hourglass-split"></i>
                <span>Publishing Queue</span>
            </a>
            <a href="<?= $appUrl ?>/tracker" class="nav-link <?= $isActive('/tracker') ?>">
                <i class="bi bi-activity"></i>
                <span>Publishing Tracker</span>
            </a>
            <a href="<?= $appUrl ?>/attempts" class="nav-link <?= $isActive('/attempts') ?>">
                <i class="bi bi-clock-history"></i>
                <span>Attempt History</span>
            </a>
            <a href="<?= $appUrl ?>/accounts" class="nav-link <?= $isActive('/accounts') ?>">
                <i class="bi bi-share-fill"></i>
                <span>Social Accounts</span>
            </a>

            <div class="nav-section-title">Insights & Admin</div>
            <a href="<?= $appUrl ?>/reports" class="nav-link <?= $isActive('/reports') ?>">
                <i class="bi bi-file-earmark-bar-graph-fill"></i>
                <span>Reports & Analytics</span>
            </a>
            <?php if (Auth::isAdmin()): ?>
            <a href="<?= $appUrl ?>/users" class="nav-link <?= $isActive('/users') ?>">
                <i class="bi bi-people-fill"></i>
                <span>User Management</span>
            </a>
            <a href="<?= $appUrl ?>/settings" class="nav-link <?= $isActive('/settings') ?>">
                <i class="bi bi-gear-fill"></i>
                <span>Settings</span>
            </a>
            <a href="<?= $appUrl ?>/logs" class="nav-link <?= $isActive('/logs') ?>">
                <i class="bi bi-journal-code"></i>
                <span>System Logs</span>
            </a>
            <?php endif; ?>
            <a href="<?= $appUrl ?>/setup-guide" class="nav-link <?= $isActive('/setup-guide') ?>">
                <i class="bi bi-book-half"></i>
                <span>Setup & API Guide</span>
            </a>
        </nav>

        <div class="p-3 border-top border-secondary-subtle">
            <div class="d-flex align-items-center justify-content-between">
                <div class="small">
                    <div class="text-white fw-bold text-truncate" style="max-width: 140px;"><?= Security::e($currentUser['name'] ?? 'Admin') ?></div>
                    <div class="text-muted text-capitalize"><?= Security::e($currentUser['role'] ?? 'operator') ?></div>
                </div>
                <form action="<?= $appUrl ?>/logout" method="POST" class="m-0">
                    <?= $csrf_field ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Sign Out">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="app-main">
        <header class="app-header">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-dark d-lg-none" id="sidebarToggle">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <h5 class="m-0 fw-bold text-white"><?= Security::e($title ?? 'Dashboard') ?></h5>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="<?= $appUrl ?>/api/worker/run" id="btnRunWorkerAjax" class="btn btn-sm btn-outline-info d-flex align-items-center gap-1" title="Manually run background publisher worker">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Run Worker</span>
                </a>
                <a href="<?= $appUrl ?>/upload" class="btn btn-sm btn-primary d-flex align-items-center gap-1">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span>Upload Short</span>
                </a>
            </div>
        </header>

        <main class="app-content">
            <!-- Flash Messages -->
            <?php if (!empty($flashSuccess)): ?>
                <?php foreach ($flashSuccess as $msg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?= Security::e($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($flashError)): ?>
                <?php foreach ($flashError as $msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= Security::e($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($flashInfo)): ?>
                <?php foreach ($flashInfo as $msg): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i> <?= Security::e($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Application JS -->
<script src="<?= $appUrl ?>/assets/js/app.js"></script>
</body>
</html>
