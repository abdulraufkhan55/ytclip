<?php
use App\Core\Security;
$appUrl = $app['url'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Security::e($title ?? 'Authentication') ?> - <?= Security::e($app['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= $appUrl ?>/assets/css/app.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at top right, #1e1b4b, #0b0f19);
        }
        .auth-card {
            width: 100%;
            max-width: 440px;
            padding: 2.5rem;
            background: #111a2e;
            border: 1px solid #1e293b;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="text-center mb-4">
        <div class="brand-icon mx-auto mb-3" style="width: 50px; height: 50px; font-size: 1.6rem;">
            <i class="bi bi-play-btn-fill"></i>
        </div>
        <h4 class="text-white fw-bold mb-1">YTClip Multi-Publisher</h4>
        <p class="text-secondary small">YouTube Shorts • TikTok • Instagram Reels</p>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($flashSuccess)): ?>
        <?php foreach ($flashSuccess as $msg): ?>
            <div class="alert alert-success alert-dismissible fade show small" role="alert">
                <?= Security::e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <?php foreach ($flashError as $msg): ?>
            <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                <?= Security::e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($flashInfo)): ?>
        <?php foreach ($flashInfo as $msg): ?>
            <div class="alert alert-info alert-dismissible fade show small" role="alert">
                <?= Security::e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?= $content ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
