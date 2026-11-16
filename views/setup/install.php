<?php
use App\Core\Security;
?>
<div class="mb-3 text-center">
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 mb-2">First-Time Setup</span>
    <h5 class="text-white fw-bold">Create Administrator Account</h5>
    <p class="text-secondary small">Set up your initial administrative credentials. No default passwords are used.</p>
</div>

<form action="<?= $app['url'] ?>/setup" method="POST">
    <?= $csrf_field ?>

    <div class="mb-3">
        <label for="name" class="form-label">Full Name</label>
        <div class="input-group">
            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" id="name" name="name" required placeholder="Admin Name">
        </div>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">Admin Email</label>
        <div class="input-group">
            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email" required placeholder="admin@example.com">
        </div>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Secure Password (min 8 chars)</label>
        <div class="input-group">
            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="password" name="password" minlength="8" required placeholder="••••••••">
        </div>
    </div>

    <div class="mb-4">
        <label for="confirm_password" class="form-label">Confirm Password</label>
        <div class="input-group">
            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-check2-circle"></i></span>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required placeholder="••••••••">
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        <i class="bi bi-shield-check me-1"></i> Initialize System & Admin
    </button>
</form>
