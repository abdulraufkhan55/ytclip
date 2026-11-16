<?php
use App\Core\Security;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="text-white fw-bold m-0">Connected Social Accounts</h4>
        <p class="text-secondary small m-0">Manage OAuth 2.0 authorizations for YouTube, TikTok, and Instagram.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $app['url'] ?>/setup-guide" class="btn btn-outline-info btn-sm">
            <i class="bi bi-question-circle me-1"></i> API Setup Instructions
        </a>
    </div>
</div>

<?php if ($mockMode): ?>
<!-- Mock Mode Testing Banner -->
<div class="alert alert-info bg-info-subtle text-info border-info-subtle d-flex flex-wrap justify-content-between align-items-center p-3 mb-4 rounded-3 gap-3">
    <div>
        <strong><i class="bi bi-info-circle-fill me-2"></i>Development Mode Active (Mock Adapters Enabled)</strong>
        <div class="small mt-1">You can connect simulated test accounts to verify the dashboard, video upload flow, and background queue without requiring live social media API keys!</div>
    </div>
    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#modalMockAccount">
        <i class="bi bi-plus-circle me-1"></i> Connect Test Account
    </button>
</div>
<?php endif; ?>

<!-- 3 Platform Connection Cards -->
<div class="row g-4 mb-4">
    <!-- YouTube Connection Card -->
    <div class="col-md-4">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-youtube text-danger fs-4"></i>
                    <h6 class="m-0 text-white fw-bold">YouTube Shorts</h6>
                </div>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Google OAuth 2.0</span>
            </div>
            <div class="card-body-custom">
                <p class="text-secondary small">Upload short videos directly to your YouTube Channel. Requires <code>youtube.upload</code> and <code>youtube.readonly</code> permissions.</p>
                <a href="<?= $app['url'] ?>/oauth/youtube" class="btn btn-outline-danger w-100 btn-sm py-2 fw-semibold">
                    <i class="bi bi-google me-1"></i> Connect YouTube Account
                </a>
            </div>
        </div>
    </div>

    <!-- TikTok Connection Card -->
    <div class="col-md-4">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-tiktok text-info fs-4"></i>
                    <h6 class="m-0 text-white fw-bold">TikTok</h6>
                </div>
                <span class="badge bg-info-subtle text-info border border-info-subtle">TikTok v2 API</span>
            </div>
            <div class="card-body-custom">
                <p class="text-secondary small">Direct Post and Draft publishing via TikTok Content Posting API. Requires <code>video.publish</code> and <code>user.info.basic</code> scopes.</p>
                <a href="<?= $app['url'] ?>/oauth/tiktok" class="btn btn-outline-info w-100 btn-sm py-2 fw-semibold">
                    <i class="bi bi-tiktok me-1"></i> Connect TikTok Account
                </a>
            </div>
        </div>
    </div>

    <!-- Instagram Connection Card -->
    <div class="col-md-4">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-instagram fs-4" style="color: #f472b6;"></i>
                    <h6 class="m-0 text-white fw-bold">Instagram Reels</h6>
                </div>
                <span class="badge bg-pink-subtle text-pink border border-pink-subtle" style="color: #f472b6;">Meta Graph API</span>
            </div>
            <div class="card-body-custom">
                <p class="text-secondary small">Publish Reels to connected Instagram Business or Creator accounts via Facebook Login and <code>instagram_content_publish</code>.</p>
                <a href="<?= $app['url'] ?>/oauth/instagram" class="btn btn-outline-light w-100 btn-sm py-2 fw-semibold" style="border-color: #f472b6; color: #f472b6;">
                    <i class="bi bi-meta me-1"></i> Connect Instagram Account
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Connected Accounts Table -->
<div class="card-custom">
    <div class="card-header-custom">
        <h6 class="m-0 text-white fw-bold"><i class="bi bi-check-circle-fill me-2 text-success"></i>Authorized Social Channels</h6>
        <span class="text-secondary small"><?= count($accounts) ?> active authorization(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-dark-custom">
            <thead>
                <tr>
                    <th>Platform</th>
                    <th>Account Name</th>
                    <th>Account ID</th>
                    <th>Status</th>
                    <th>Granted Permissions</th>
                    <th>Token Expiry</th>
                    <th>Last Verified</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($accounts)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-5">
                            <i class="bi bi-shield-x fs-1 d-block mb-2 text-muted"></i>
                            No social accounts connected yet. Click an authorization button above or use "Connect Test Account" in Mock Mode.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($accounts as $acc): ?>
                        <tr>
                            <td>
                                <span class="badge-platform badge-platform-<?= $acc['platform'] ?>">
                                    <?= $acc['platform'] ?>
                                </span>
                            </td>
                            <td>
                                <strong class="text-white"><?= Security::e($acc['account_name']) ?></strong>
                            </td>
                            <td>
                                <code class="text-muted small"><?= Security::e(mb_substr($acc['account_id'], 0, 16)) ?>...</code>
                            </td>
                            <td>
                                <span class="badge-status status-<?= $acc['connection_status'] === 'connected' ? 'published' : 'failed' ?>">
                                    <?= $acc['connection_status'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-secondary small" title="<?= Security::e($acc['granted_scopes'] ?? '') ?>">
                                    <?= Security::e(mb_substr($acc['granted_scopes'] ?? 'default', 0, 30)) ?>...
                                </span>
                            </td>
                            <td class="text-secondary small">
                                <?= $acc['token_expires_at'] ? date('M d, Y', strtotime($acc['token_expires_at'])) : 'Never / Long-lived' ?>
                            </td>
                            <td class="text-secondary small">
                                <?= $acc['last_verified_at'] ? date('M d, H:i', strtotime($acc['last_verified_at'])) : 'Recently' ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <form action="<?= $app['url'] ?>/accounts/<?= $acc['id'] ?>/test" method="POST" class="d-inline">
                                        <?= $csrf_field ?>
                                        <button type="submit" class="btn btn-outline-info" title="Test Connection / Decrypt Tokens">
                                            <i class="bi bi-arrow-repeat"></i> Verify
                                        </button>
                                    </form>
                                    <form action="<?= $app['url'] ?>/accounts/<?= $acc['id'] ?>/disconnect" method="POST" class="d-inline" onsubmit="return confirm('Disconnect this account and revoke tokens?');">
                                        <?= $csrf_field ?>
                                        <button type="submit" class="btn btn-outline-danger" title="Disconnect Account">
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

<!-- Modal: Connect Mock Account (for easy testing without real API secrets) -->
<?php if ($mockMode): ?>
<div class="modal fade" id="modalMockAccount" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-white">Connect Simulated Test Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= $app['url'] ?>/accounts/mock" method="POST">
                <?= $csrf_field ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Platform</label>
                        <select name="platform" class="form-select">
                            <option value="youtube">YouTube Shorts</option>
                            <option value="tiktok">TikTok</option>
                            <option value="instagram">Instagram Reels</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Display Name</label>
                        <input type="text" name="account_name" class="form-control" placeholder="e.g. Daily Tech Shorts">
                    </div>
                    <p class="text-secondary small mb-0">
                        This test account generates mock access tokens and enables full end-to-end testing of the queue, scheduler, and tracker matrix without real API keys.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Connect Account</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
