<?php
$title = 'Sign In';
require_once __DIR__ . '/includes/auth/session-bootstrap.php';
require_once __DIR__ . '/includes/layout/header-login.php';
?>
<div class="auth-wrapper">
    <div class="auth-overlay"></div>
    <div class="auth-card">
        <div class="auth-header">
            <a href="<?= BASE_URL ?>/homepage.php" class="d-inline-flex align-items-center mb-2 text-decoration-none">
                <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="Logo" style="width: 32px; height: 32px;" class="mr-2">
                <h4 class="font-weight-bold text-dark mb-0">Bus Service</h4>
            </a>
            <h5 class="font-weight-bold mb-1">Welcome Back</h5>
            <p class="text-muted small mb-0">Sign in to manage your tickets and bookings</p>
        </div>
        <div class="auth-body">
            <?php if (function_exists('flash_get')): ?>
                <?php foreach (flash_get() as $f): ?>
                    <div class="alert alert-<?= e($f['type'] === 'error' ? 'danger' : $f['type']) ?> alert-dismissible fade show" role="alert">
                        <?= e($f['msg']) ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <form action="homepage.php" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e(safe_next_url($_GET['next'] ?? $_POST['next'] ?? null) ?? '') ?>">
                <div class="form-group mb-3">
                    <label for="email" class="font-weight-bold small text-muted">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" class="form-control" required>
                </div>
                <div class="form-group mb-2">
                    <label for="password" class="font-weight-bold small text-muted">Password</label>
                    <input type="password" id="password" name="pwd" placeholder="••••••••" class="form-control" required>
                </div>
                <div class="text-right mb-3">
                    <a href="<?= BASE_URL ?>/forgot-password.php" class="small text-muted">Forgot password?</a>
                </div>
                <button type="submit" value="Login" name="user" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm">
                    Sign In
                </button>
            </form>
            <div class="text-center mt-4">
                <a href="<?= BASE_URL ?>/homepage.php?login=admin" class="small text-muted font-weight-bold">
                    Administrator Sign In &rarr;
                </a>
            </div>
            <div class="text-center mt-2">
                <a href="<?= BASE_URL ?>/homepage.php" class="small text-muted text-decoration-none">
                    &larr; Return to Homepage
                </a>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/layout/footer.php'; ?>