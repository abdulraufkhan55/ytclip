<?php
use App\Core\Security;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="text-white fw-bold m-0">Publishing Attempt History</h4>
        <p class="text-secondary small m-0">Append-only audit trail of every upload attempt and API response.</p>
    </div>
</div>

<!-- Filters -->
<div class="card-custom mb-4">
    <div class="card-body-custom p-3">
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= $app['url'] ?>/attempts" class="btn btn-sm <?= empty($currentOutcome) ? 'btn-primary' : 'btn-outline-secondary' ?>">All Attempts</a>
            <a href="<?= $app['url'] ?>/attempts?outcome=success" class="btn btn-sm <?= $currentOutcome === 'success' ? 'btn-primary' : 'btn-outline-secondary' ?>">Successful</a>
            <a href="<?= $app['url'] ?>/attempts?outcome=failed" class="btn btn-sm <?= $currentOutcome === 'failed' ? 'btn-primary' : 'btn-outline-secondary' ?>">Failed</a>
            <a href="<?= $app['url'] ?>/attempts?outcome=cancelled" class="btn btn-sm <?= $currentOutcome === 'cancelled' ? 'btn-primary' : 'btn-outline-secondary' ?>">Cancelled</a>
        </div>
    </div>
</div>

<!-- Attempts Table -->
<div class="card-custom">
    <div class="table-responsive">
        <table class="table table-dark-custom">
            <thead>
                <tr>
                    <th>Attempt ID</th>
                    <th>Video Title</th>
                    <th>Platform</th>
                    <th>Account</th>
                    <th>Attempt #</th>
                    <th>Started At</th>
                    <th>Finished At</th>
                    <th>Outcome</th>
                    <th>HTTP Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attempts)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-secondary py-5">
                            <i class="bi bi-clock-history fs-1 d-block mb-2 text-muted"></i>
                            No publishing attempts recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attempts as $a): ?>
                        <tr>
                            <td><code class="text-secondary">#<?= $a['id'] ?></code></td>
                            <td><strong class="text-white small"><?= Security::e($a['video_title']) ?></strong></td>
                            <td><span class="badge-platform badge-platform-<?= $a['platform'] ?>"><?= $a['platform'] ?></span></td>
                            <td class="text-white small">@<?= Security::e($a['account_name']) ?></td>
                            <td><span class="badge bg-secondary"><?= $a['attempt_number'] ?></span></td>
                            <td class="text-secondary small"><?= date('M d, H:i:s', strtotime($a['started_at'])) ?></td>
                            <td class="text-secondary small"><?= $a['finished_at'] ? date('M d, H:i:s', strtotime($a['finished_at'])) : '—' ?></td>
                            <td>
                                <span class="badge-status status-<?= $a['outcome'] === 'success' ? 'published' : ($a['outcome'] === 'failed' ? 'failed' : 'draft') ?>">
                                    <?= $a['outcome'] ?>
                                </span>
                            </td>
                            <td><code><?= $a['http_status'] ?: '—' ?></code></td>
                            <td>
                                <?php if (!empty($a['sanitized_response']) || !empty($a['error_message'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalAttempt_<?= $a['id'] ?>">
                                        <i class="bi bi-code-slash"></i> View Payload
                                    </button>

                                    <!-- Response Modal -->
                                    <div class="modal fade" id="modalAttempt_<?= $a['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title text-white">Attempt #<?= $a['id'] ?> Response Payload</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <?php if (!empty($a['error_message'])): ?>
                                                        <div class="alert alert-danger small mb-3">
                                                            <strong>Error:</strong> <?= Security::e($a['error_message']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <label class="form-label small text-secondary">Sanitized Platform Response:</label>
                                                    <pre class="bg-dark p-3 rounded text-info small" style="max-height: 350px; overflow-y: auto;"><code><?= Security::e($a['sanitized_response'] ?? 'No raw response body recorded.') ?></code></pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
