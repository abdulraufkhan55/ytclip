<?php
use App\Core\Security;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="text-white fw-bold m-0">System & Audit Logs</h4>
        <p class="text-secondary small m-0">Inspect structured execution logs and immutable administrative audit trails.</p>
    </div>
    <form action="<?= $app['url'] ?>/logs/clear-file" method="POST" class="m-0" onsubmit="return confirm('Clear application file log?');">
        <?= $csrf_field ?>
        <button type="submit" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-trash me-1"></i> Clear Application Log File
        </button>
    </form>
</div>

<!-- Tabs: Audit Logs vs File Logs -->
<ul class="nav nav-pills mb-4" id="logTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active fw-semibold" data-bs-toggle="pill" data-bs-target="#tabAuditLogs" type="button">
            <i class="bi bi-shield-check me-1"></i> Administrative Audit Logs (<?= count($auditLogs) ?>)
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-semibold" data-bs-toggle="pill" data-bs-target="#tabFileLogs" type="button">
            <i class="bi bi-file-text me-1"></i> Structured Application Logs (app.log)
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Audit Logs -->
    <div class="tab-pane fade show active" id="tabAuditLogs">
        <div class="card-custom">
            <div class="table-responsive">
                <table class="table table-dark-custom">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Action</th>
                            <th>User</th>
                            <th>Target Entity</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($auditLogs)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-4">No audit logs recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($auditLogs as $al): ?>
                                <tr>
                                    <td class="text-secondary small"><?= date('Y-m-d H:i:s', strtotime($al['created_at'])) ?></td>
                                    <td>
                                        <code class="text-primary fw-bold"><?= Security::e($al['action']) ?></code>
                                    </td>
                                    <td class="text-white small">
                                        <?= Security::e($al['user_name'] ?? 'System / Anonymous') ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark border border-secondary">
                                            <?= Security::e($al['entity_type']) ?>: <?= Security::e($al['entity_id'] ?? 'none') ?>
                                        </span>
                                    </td>
                                    <td class="text-secondary small"><code><?= Security::e($al['ip_address']) ?></code></td>
                                    <td class="small text-muted" style="max-width: 280px;">
                                        <?= Security::e($al['details'] ?? '—') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Structured File Logs -->
    <div class="tab-pane fade" id="tabFileLogs">
        <div class="card-custom">
            <div class="card-header-custom">
                <h6 class="m-0 text-white fw-bold"><i class="bi bi-terminal me-2 text-info"></i>Latest Application Log Output (Last 100 entries)</h6>
                <span class="text-secondary small">storage/logs/app.log</span>
            </div>
            <div class="card-body-custom p-0">
                <pre class="bg-dark text-light p-3 m-0 rounded-bottom" style="max-height: 500px; overflow-y: auto; font-size: 0.82rem; font-family: Consolas, monospace;"><code><?php
                if (empty($fileLogs)) {
                    echo "Log file is empty.";
                } else {
                    foreach ($fileLogs as $line) {
                        echo Security::e($line) . "\n";
                    }
                }
                ?></code></pre>
            </div>
        </div>
    </div>
</div>
