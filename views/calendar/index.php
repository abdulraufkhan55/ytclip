<?php
use App\Core\Security;
?>

<!-- FullCalendar CDN -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="text-white fw-bold m-0">Publishing Calendar</h4>
        <p class="text-secondary small m-0">Visual scheduling schedule in application timezone: <strong><?= Security::e($timezone) ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalBulkSchedule">
            <i class="bi bi-calendar-plus"></i>
            <span>Bulk Schedule Videos</span>
        </button>
    </div>
</div>

<div class="card-custom p-3">
    <div id="calendar" style="min-height: 600px;"></div>
</div>

<!-- Modal: Bulk Schedule -->
<div class="modal fade" id="modalBulkSchedule" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-white">Bulk Schedule Videos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= $app['url'] ?>/calendar/bulk" method="POST">
                <?= $csrf_field ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Draft Videos to Schedule</label>
                        <?php if (empty($videos)): ?>
                            <div class="text-secondary small">No unassigned draft videos found in library.</div>
                        <?php else: ?>
                            <div class="row g-2" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($videos as $v): ?>
                                    <div class="col-md-6">
                                        <div class="form-check p-2 rounded-2 bg-dark border border-secondary small">
                                            <input class="form-check-input ms-1 me-2" type="checkbox" name="video_ids[]" value="<?= $v['id'] ?>" id="bvid_<?= $v['id'] ?>">
                                            <label class="form-check-label text-truncate text-white" for="bvid_<?= $v['id'] ?>">
                                                <?= Security::e($v['title']) ?> (<?= $v['duration_seconds'] ?>s)
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Select Target Accounts</label>
                        <div class="row g-2">
                            <?php foreach (['youtube', 'tiktok', 'instagram'] as $p): ?>
                                <div class="col-md-4">
                                    <strong class="small text-secondary text-uppercase d-block mb-1"><?= ucfirst($p) ?></strong>
                                    <?php if (!empty($accounts[$p])): ?>
                                        <?php foreach ($accounts[$p] as $acc): ?>
                                            <div class="form-check text-light small">
                                                <input class="form-check-input" type="checkbox" name="account_ids[]" value="<?= $acc['id'] ?>" id="bacc_<?= $acc['id'] ?>">
                                                <label class="form-check-label text-truncate" for="bacc_<?= $acc['id'] ?>">
                                                    @<?= Security::e($acc['account_name']) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-muted small">None connected</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Starting Date & Time (<?= Security::e($timezone) ?>)</label>
                            <input type="datetime-local" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Interval Between Posts</label>
                            <select name="interval_minutes" class="form-select">
                                <option value="30">Every 30 Minutes</option>
                                <option value="60" selected>Every 1 Hour</option>
                                <option value="120">Every 2 Hours</option>
                                <option value="360">Every 6 Hours</option>
                                <option value="720">Every 12 Hours</option>
                                <option value="1440">Daily (24 Hours)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule All</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            themeSystem: 'bootstrap5',
            events: '<?= $app['url'] ?>/calendar/events',
            eventClick: function(info) {
                if (info.event.extendedProps && info.event.extendedProps.job_id) {
                    window.location.href = '<?= $app['url'] ?>/queue';
                }
            }
        });
        calendar.render();
    }
});
</script>
