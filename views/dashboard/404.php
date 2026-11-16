<?php
use App\Core\Security;
?>
<div class="text-center py-5">
    <div class="display-1 text-primary fw-bold mb-3">404</div>
    <h3 class="text-white fw-bold mb-2">Page Not Found</h3>
    <p class="text-secondary mb-4">The requested endpoint <code><?= Security::e($path ?? '') ?></code> does not exist.</p>
    <a href="<?= $app['url'] ?>/dashboard" class="btn btn-primary px-4">
        <i class="bi bi-house-door-fill me-1"></i> Return to Dashboard
    </a>
</div>
