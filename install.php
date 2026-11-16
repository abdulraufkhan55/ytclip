<?php

declare(strict_types=1);

// Standalone Web & CLI Database Installer & Setup Wizard
require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Models\User;

$isCli = (php_sapi_name() === 'cli');

function checkRequirement(string $name, bool $pass, string $info = ''): array {
    return ['name' => $name, 'pass' => $pass, 'info' => $info];
}

$checks = [
    checkRequirement('PHP Version >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>='), 'Current: ' . PHP_VERSION),
    checkRequirement('PDO MySQL Extension', extension_loaded('pdo_mysql')),
    checkRequirement('cURL Extension', extension_loaded('curl')),
    checkRequirement('OpenSSL Extension (AES-256-GCM)', extension_loaded('openssl')),
    checkRequirement('Mbstring Extension', extension_loaded('mbstring')),
    checkRequirement('Fileinfo Extension', extension_loaded('fileinfo')),
    checkRequirement('Storage Directory Writable', is_writable(__DIR__ . '/storage') || @mkdir(__DIR__ . '/storage', 0755, true)),
];

$allPass = !in_array(false, array_column($checks, 'pass'), true);

$message = null;
$error = null;

if (($isCli && isset($argv[1])) || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    try {
        $dbConfig = require __DIR__ . '/config/database.php';
        
        // 1. Connect without db to ensure database exists
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // 2. Import Schema
        $schemaSql = file_get_contents(__DIR__ . '/database/schema.sql');
        $pdo->exec("USE `{$dbConfig['database']}`");
        $pdo->exec($schemaSql);

        // 3. Create Admin if credentials provided
        $name = $_POST['admin_name'] ?? ($argv[1] ?? null);
        $email = $_POST['admin_email'] ?? ($argv[2] ?? null);
        $password = $_POST['admin_password'] ?? ($argv[3] ?? null);

        if ($name && $email && $password) {
            User::create([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'admin',
                'status' => 'active'
            ]);
            $message = "Database migrated and Administrator '{$email}' created successfully!";
        } else {
            $message = "Database tables and default settings successfully migrated!";
        }

        if ($isCli) {
            echo "[SUCCESS] {$message}\n";
            exit(0);
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        if ($isCli) {
            echo "[ERROR] Installation failed: {$error}\n";
            exit(1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YTClip System Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #0b0f19; color: #f8fafc; font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem 0; }
        .card-box { width: 100%; max-width: 600px; background: #111a2e; border: 1px solid #1e293b; border-radius: 14px; padding: 2.5rem; }
    </style>
</head>
<body>
<div class="card-box">
    <div class="text-center mb-4">
        <h4 class="fw-bold text-white"><i class="bi bi-play-btn-fill text-primary me-2"></i>YTClip Installation Wizard</h4>
        <p class="text-secondary small">Database migration & initial environment check</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($message) ?>
            <div class="mt-2"><a href="public/index.php" class="btn btn-sm btn-primary">Go to Sign In</a></div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <h6 class="text-white fw-bold mb-3">System Prerequisites</h6>
    <ul class="list-group mb-4">
        <?php foreach ($checks as $chk): ?>
            <li class="list-group-item bg-dark text-light border-secondary d-flex justify-content-between align-items-center">
                <span><?= htmlspecialchars($chk['name']) ?> <small class="text-secondary"><?= htmlspecialchars($chk['info']) ?></small></span>
                <?php if ($chk['pass']): ?>
                    <span class="badge bg-success"><i class="bi bi-check"></i> Passed</span>
                <?php else: ?>
                    <span class="badge bg-danger"><i class="bi bi-x"></i> Failed</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($allPass): ?>
        <form method="POST">
            <h6 class="text-white fw-bold mb-3">Create Initial Administrator Account</h6>
            <div class="mb-3">
                <label class="form-label small text-secondary">Admin Name</label>
                <input type="text" name="admin_name" class="form-control" required placeholder="Administrator">
            </div>
            <div class="mb-3">
                <label class="form-label small text-secondary">Admin Email</label>
                <input type="email" name="admin_email" class="form-control" required placeholder="admin@example.com">
            </div>
            <div class="mb-4">
                <label class="form-label small text-secondary">Admin Password (min 8 characters)</label>
                <input type="password" name="admin_password" class="form-control" minlength="8" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                <i class="bi bi-database-check me-1"></i> Migrate Database & Create Administrator
            </button>
        </form>
    <?php else: ?>
        <div class="alert alert-warning">
            Please resolve the failed prerequisites in your PHP environment before continuing.
        </div>
    <?php endif; ?>
</div>
</body>
</html>
