<?php
use App\Core\Security;
?>
<form action="<?= $app['url'] ?>/login" method="POST">
    <?= $csrf_field ?>

    <div class="mb-3">
        <label for="email" class="form-label">Administrator / Operator Email</label>
        <div class="input-group">
            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email" required autofocus placeholder="admin@example.com">
        </div>
    </div>

    <div class="mb-4">
        <label for="password" class="form-label">Password</label>
        <div class="input-group">
            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-shield-lock"></i></span>
            <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Dashboard
    </button>

    <div class="text-center mt-4">
        <span class="text-muted small">Protected by Rate-Limiting & CSRF Safeguards</span>
    </div>
</form>
