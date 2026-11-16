<?php
use App\Core\Security;
?>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="text-white fw-bold m-0">Upload Short Video</h4>
                <p class="text-secondary small m-0">Upload 30–60s video, set metadata per platform, and dispatch to connected social accounts.</p>
            </div>
            <a href="<?= $app['url'] ?>/videos" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Library
            </a>
        </div>

        <form action="<?= $app['url'] ?>/upload" method="POST" enctype="multipart/form-data" id="videoUploadForm">
            <?= $csrf_field ?>

            <!-- Drag and Drop Upload Area -->
            <div class="card-custom mb-4">
                <div class="card-body-custom">
                    <div class="dropzone-area" id="videoDropzone">
                        <div class="dropzone-icon">
                            <i class="bi bi-cloud-arrow-up"></i>
                        </div>
                        <h5 class="text-white fw-bold mb-1">Drag & Drop your video file here</h5>
                        <p class="text-secondary small mb-3">Accepts MP4, MOV, or WebM format (Duration: 5–60 seconds, max <?= $app['max_upload_size_mb'] ?>MB)</p>
                        <button type="button" class="btn btn-outline-primary btn-sm px-3">Browse Computer</button>
                        <input type="file" id="videoFileInput" name="video_file" class="d-none" accept="video/mp4,video/quicktime,video/webm">
                    </div>

                    <!-- Client-Side Video Preview Container (Initially Hidden) -->
                    <div id="videoPreviewContainer" class="d-none mt-3">
                        <div class="row align-items-center g-3">
                            <div class="col-md-5 text-center">
                                <video id="previewVideo" controls playsinline class="rounded-3 shadow-lg" style="max-height: 280px; width: 100%; object-fit: contain; background: #000;"></video>
                            </div>
                            <div class="col-md-7">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Video Loaded</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnChangeVideo">
                                        <i class="bi bi-arrow-repeat me-1"></i> Replace File
                                    </button>
                                </div>
                                <div id="previewMeta"></div>
                                <div id="durationAlert" class="alert alert-warning py-2 px-3 small mt-3 d-none"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Progress Bar (shown during upload) -->
                    <div id="uploadProgressContainer" class="mt-3 d-none">
                        <label class="form-label small text-secondary">Uploading video...</label>
                        <div class="progress" style="height: 12px; background-color: #1e293b;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="uploadProgressBar" style="width: 0%;">0%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Global Video Metadata -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h6 class="m-0 text-white fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Global Video Metadata</h6>
                    <span class="text-secondary small">Base information for all platforms</span>
                </div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label for="videoTitle" class="form-label">Video Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="videoTitle" name="title" required placeholder="Catchy title for your Short/Reel...">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="defaultCaption" class="form-label">Default Caption</label>
                            <textarea class="form-control" id="defaultCaption" name="default_caption" rows="3" placeholder="Caption displayed across platforms..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="videoHashtags" class="form-label">Hashtags</label>
                            <input type="text" class="form-control" id="videoHashtags" name="hashtags" placeholder="#Shorts #Reels #Viral #Trending">
                            <small class="text-muted">Separate with spaces. E.g.: #tech #shorts</small>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label for="videoDescription" class="form-label">Extended Description (YouTube / Details)</label>
                        <textarea class="form-control" id="videoDescription" name="description" rows="2" placeholder="Full video description, links, and credits..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Target Platform & Account Selection -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h6 class="m-0 text-white fw-bold"><i class="bi bi-share-fill me-2 text-primary"></i>Select Target Platforms & Accounts</h6>
                    <span class="text-secondary small">Choose which social channels will receive this video</span>
                </div>
                <div class="card-body-custom">
                    <?php if (empty($connectedAccounts['youtube']) && empty($connectedAccounts['tiktok']) && empty($connectedAccounts['instagram'])): ?>
                        <div class="alert alert-warning d-flex align-items-center justify-content-between p-3">
                            <div>
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                No connected social accounts found. You can save as a draft or connect accounts first!
                            </div>
                            <a href="<?= $app['url'] ?>/accounts" class="btn btn-sm btn-dark">Manage Accounts</a>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <!-- YouTube Shorts Target -->
                        <div class="col-md-4">
                            <div class="p-3 rounded-3" style="background: rgba(255, 0, 0, 0.05); border: 1px solid rgba(255, 0, 0, 0.2);">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-youtube text-danger fs-5"></i>
                                        <strong class="text-white">YouTube Shorts</strong>
                                    </div>
                                    <span class="badge bg-secondary"><?= count($connectedAccounts['youtube']) ?> connected</span>
                                </div>
                                <?php if (!empty($connectedAccounts['youtube'])): ?>
                                    <div class="mt-2">
                                        <?php foreach ($connectedAccounts['youtube'] as $acc): ?>
                                            <div class="form-check text-light small mb-1">
                                                <input class="form-check-input" type="checkbox" name="account_ids[]" value="<?= $acc['id'] ?>" id="acc_<?= $acc['id'] ?>">
                                                <label class="form-check-label text-truncate" for="acc_<?= $acc['id'] ?>">
                                                    <?= Security::e($acc['account_name']) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <small class="text-muted d-block mt-2">No YouTube account linked.</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- TikTok Target -->
                        <div class="col-md-4">
                            <div class="p-3 rounded-3" style="background: rgba(0, 242, 254, 0.05); border: 1px solid rgba(0, 242, 254, 0.2);">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-tiktok text-info fs-5"></i>
                                        <strong class="text-white">TikTok</strong>
                                    </div>
                                    <span class="badge bg-secondary"><?= count($connectedAccounts['tiktok']) ?> connected</span>
                                </div>
                                <?php if (!empty($connectedAccounts['tiktok'])): ?>
                                    <div class="mt-2">
                                        <?php foreach ($connectedAccounts['tiktok'] as $acc): ?>
                                            <div class="form-check text-light small mb-1">
                                                <input class="form-check-input" type="checkbox" name="account_ids[]" value="<?= $acc['id'] ?>" id="acc_<?= $acc['id'] ?>">
                                                <label class="form-check-label text-truncate" for="acc_<?= $acc['id'] ?>">
                                                    <?= Security::e($acc['account_name']) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <small class="text-muted d-block mt-2">No TikTok account linked.</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Instagram Reels Target -->
                        <div class="col-md-4">
                            <div class="p-3 rounded-3" style="background: rgba(225, 48, 108, 0.05); border: 1px solid rgba(225, 48, 108, 0.2);">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-instagram text-pink fs-5" style="color: #f472b6;"></i>
                                        <strong class="text-white">Instagram Reels</strong>
                                    </div>
                                    <span class="badge bg-secondary"><?= count($connectedAccounts['instagram']) ?> connected</span>
                                </div>
                                <?php if (!empty($connectedAccounts['instagram'])): ?>
                                    <div class="mt-2">
                                        <?php foreach ($connectedAccounts['instagram'] as $acc): ?>
                                            <div class="form-check text-light small mb-1">
                                                <input class="form-check-input" type="checkbox" name="account_ids[]" value="<?= $acc['id'] ?>" id="acc_<?= $acc['id'] ?>">
                                                <label class="form-check-label text-truncate" for="acc_<?= $acc['id'] ?>">
                                                    <?= Security::e($acc['account_name']) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <small class="text-muted d-block mt-2">No Instagram account linked.</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Platform-Specific Customization Accordion/Tabs -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h6 class="m-0 text-white fw-bold"><i class="bi bi-sliders me-2 text-primary"></i>Platform-Specific Metadata Overrides (Optional)</h6>
                    <span class="text-secondary small">Leave blank to inherit global metadata</span>
                </div>
                <div class="card-body-custom">
                    <ul class="nav nav-pills mb-3" id="metaTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active small py-1 px-3" data-bs-toggle="pill" data-bs-target="#tab-yt" type="button">YouTube Specific</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link small py-1 px-3" data-bs-toggle="pill" data-bs-target="#tab-tt" type="button">TikTok Specific</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link small py-1 px-3" data-bs-toggle="pill" data-bs-target="#tab-ig" type="button">Instagram Specific</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-yt">
                            <div class="mb-2">
                                <label class="form-label">YouTube Shorts Title (max 100 chars)</label>
                                <input type="text" name="yt_title" class="form-control" maxlength="100" placeholder="Custom YouTube title...">
                            </div>
                            <div>
                                <label class="form-label">YouTube Description (max 5000 chars)</label>
                                <textarea name="yt_description" class="form-control" rows="2" placeholder="Custom YouTube description..."></textarea>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-tt">
                            <div>
                                <label class="form-label">TikTok Caption (max 2200 chars)</label>
                                <textarea name="tt_caption" class="form-control" rows="2" placeholder="Custom TikTok caption and hashtags..."></textarea>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-ig">
                            <div>
                                <label class="form-label">Instagram Reels Caption (max 2200 chars)</label>
                                <textarea name="ig_caption" class="form-control" rows="2" placeholder="Custom Instagram caption and hashtags..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Publishing Action & Scheduling Options -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h6 class="m-0 text-white fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Publishing Timing & Execution</h6>
                </div>
                <div class="card-body-custom">
                    <div class="d-flex flex-wrap gap-4 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="publish_action" id="actionDraft" value="draft" checked>
                            <label class="form-check-label text-light" for="actionDraft">
                                <strong>Save as Draft</strong>
                                <div class="text-secondary small">Store video in library without triggering publishing queue</div>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="publish_action" id="actionQueue" value="publish_now">
                            <label class="form-check-label text-light" for="actionQueue">
                                <strong>Queue Immediately</strong>
                                <div class="text-secondary small">Dispatch to worker queue for immediate background processing</div>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="publish_action" id="actionSchedule" value="schedule">
                            <label class="form-check-label text-light" for="actionSchedule">
                                <strong>Schedule for Later</strong>
                                <div class="text-secondary small">Publish automatically at a designated future date & time</div>
                            </label>
                        </div>
                    </div>

                    <!-- Date & Time Picker (Toggled when Schedule is picked) -->
                    <div id="schedulePickerContainer" class="d-none p-3 rounded-3 bg-dark border border-secondary mt-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-6">
                                <label for="scheduledAtInput" class="form-label">Scheduled Date & Time (App Timezone: <?= Security::e($app['timezone']) ?>)</label>
                                <input type="datetime-local" class="form-control" id="scheduledAtInput" name="scheduled_at">
                            </div>
                            <div class="col-md-6">
                                <small class="text-secondary">
                                    <i class="bi bi-info-circle me-1"></i>
                                    The post timestamp is automatically normalized to UTC in the database and picked up by the background scheduler worker.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="<?= $app['url'] ?>/videos" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" id="submitUploadBtn" class="btn btn-primary-custom px-4 py-2">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Upload & Process Video
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?= $app['url'] ?>/assets/js/uploader.js"></script>
