<?php
use App\Core\Security;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="text-white fw-bold m-0">Application & Platform Settings</h4>
        <p class="text-secondary small m-0">Manage global limits, binary paths, encryption, and developer API credentials.</p>
    </div>
</div>

<form action="<?= $app['url'] ?>/settings" method="POST">
    <?= $csrf_field ?>

    <div class="row g-4 mb-4">
        <!-- General System Settings -->
        <div class="col-lg-6">
            <div class="card-custom h-100">
                <div class="card-header-custom">
                    <h6 class="m-0 text-white fw-bold"><i class="bi bi-gear-fill me-2 text-primary"></i>General Settings</h6>
                </div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label class="form-label">Application Name</label>
                        <input type="text" name="app_name" class="form-control" value="<?= Security::e($settings['app_name'] ?? 'YTClip Multi-Publisher') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Base Application URL</label>
                        <input type="url" name="app_url" class="form-control" value="<?= Security::e($settings['app_url'] ?? 'http://localhost/ytclip') ?>">
                        <small class="text-muted">Used for OAuth callback redirection and Instagram media streaming.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Application Timezone</label>
                        <select name="app_timezone" class="form-select">
                            <?php foreach ($timezones as $tz): ?>
                                <option value="<?= $tz ?>" <?= ($settings['app_timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Daily Publishing Limit Safeguard</label>
                        <input type="number" name="daily_publishing_limit" class="form-control" value="<?= Security::e($settings['daily_publishing_limit'] ?? '50') ?>">
                        <small class="text-muted">Maximum jobs allowed per day to prevent exceeding platform quotas.</small>
                    </div>

                    <!-- Development Simulation Mode -->
                    <div class="p-3 rounded-3 bg-dark border border-secondary">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="mock_mode" id="mockModeSwitch" value="1" <?= (!empty($settings['mock_mode']) && $settings['mock_mode'] == '1') ? 'checked' : '' ?>>
                            <label class="form-check-label text-white fw-bold" for="mockModeSwitch">
                                Enable Development Mock Mode
                            </label>
                        </div>
                        <small class="text-secondary d-block mt-1">
                            When enabled, simulated platform publishers are used to test the full publishing queue and dashboard metrics without requiring live API keys.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Video Processing & Binary Paths -->
        <div class="col-lg-6">
            <div class="card-custom h-100">
                <div class="card-header-custom">
                    <h6 class="m-0 text-white fw-bold"><i class="bi bi-film me-2 text-info"></i>Video Processing & Inspection</h6>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Min Video Duration (seconds)</label>
                            <input type="number" step="1" name="min_video_duration" class="form-control" value="<?= Security::e($settings['min_video_duration'] ?? '30') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Max Video Duration (seconds)</label>
                            <input type="number" step="1" name="max_video_duration" class="form-control" value="<?= Security::e($settings['max_video_duration'] ?? '60') ?>">
                        </div>
                    </div>
                    <small class="text-muted d-block mb-3">Default accepted duration is 30–60s for Shorts and Reels. Configurable per system rules.</small>

                    <div class="mb-3">
                        <label class="form-label">Maximum Upload Size (MB)</label>
                        <input type="number" name="max_upload_size_mb" class="form-control" value="<?= Security::e($settings['max_upload_size_mb'] ?? '200') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">FFprobe Binary Path (Optional)</label>
                        <input type="text" name="ffprobe_path" class="form-control" value="<?= Security::e($settings['ffprobe_path'] ?? '') ?>" placeholder="e.g. C:\ffmpeg\bin\ffprobe.exe or /usr/bin/ffprobe">
                        <small class="text-muted">Pure PHP MP4 parser is automatically used as fallback when ffprobe is blank.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">FFmpeg Binary Path (Optional)</label>
                        <input type="text" name="ffmpeg_path" class="form-control" value="<?= Security::e($settings['ffmpeg_path'] ?? '') ?>" placeholder="e.g. C:\ffmpeg\bin\ffmpeg.exe or /usr/bin/ffmpeg">
                    </div>

                    <!-- Token Encryption Status -->
                    <div class="p-3 rounded-3 bg-dark border border-secondary mt-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-shield-lock-fill text-success fs-5"></i>
                                <div>
                                    <strong class="text-white small">AES-256-GCM Token Encryption</strong>
                                    <div class="text-secondary small">Authenticated encryption at rest</div>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Official Platform Developer API Credentials -->
    <div class="card-custom mb-4">
        <div class="card-header-custom">
            <h6 class="m-0 text-white fw-bold"><i class="bi bi-key-fill me-2 text-warning"></i>Official Platform OAuth 2.0 Credentials</h6>
            <a href="<?= $app['url'] ?>/setup-guide" class="small text-decoration-none">View App Creation Guide</a>
        </div>
        <div class="card-body-custom">
            <!-- YouTube -->
            <div class="p-3 rounded-3 mb-3" style="background: rgba(255, 0, 0, 0.05); border: 1px solid rgba(255, 0, 0, 0.2);">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-youtube text-danger fs-5"></i>
                    <strong class="text-white">Google Cloud Console (YouTube Data API v3)</strong>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">OAuth Client ID</label>
                        <input type="text" name="youtube_client_id" class="form-control form-control-sm" value="<?= Security::e($settings['youtube_client_id'] ?? '') ?>" placeholder="xxxx.apps.googleusercontent.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">OAuth Client Secret</label>
                        <input type="password" name="youtube_client_secret" class="form-control form-control-sm" value="<?= Security::e($settings['youtube_client_secret'] ?? '') ?>" placeholder="••••••••">
                    </div>
                </div>
            </div>

            <!-- TikTok -->
            <div class="p-3 rounded-3 mb-3" style="background: rgba(0, 242, 254, 0.05); border: 1px solid rgba(0, 242, 254, 0.2);">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-tiktok text-info fs-5"></i>
                    <strong class="text-white">TikTok for Developers (Content Posting API)</strong>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Client Key</label>
                        <input type="text" name="tiktok_client_key" class="form-control form-control-sm" value="<?= Security::e($settings['tiktok_client_key'] ?? '') ?>" placeholder="awxxxxxxxxxxxx">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Client Secret</label>
                        <input type="password" name="tiktok_client_secret" class="form-control form-control-sm" value="<?= Security::e($settings['tiktok_client_secret'] ?? '') ?>" placeholder="••••••••">
                    </div>
                </div>
            </div>

            <!-- Instagram -->
            <div class="p-3 rounded-3" style="background: rgba(225, 48, 108, 0.05); border: 1px solid rgba(225, 48, 108, 0.2);">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-instagram fs-5" style="color: #f472b6;"></i>
                    <strong class="text-white">Meta for Developers (Instagram Graph API)</strong>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">App ID</label>
                        <input type="text" name="instagram_app_id" class="form-control form-control-sm" value="<?= Security::e($settings['instagram_app_id'] ?? '') ?>" placeholder="xxxxxxxxxxxxxxx">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">App Secret</label>
                        <input type="password" name="instagram_app_secret" class="form-control form-control-sm" value="<?= Security::e($settings['instagram_app_secret'] ?? '') ?>" placeholder="••••••••">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
        <i class="bi bi-save me-1"></i> Save Application Settings
    </button>
</form>
