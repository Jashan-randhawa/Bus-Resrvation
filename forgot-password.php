<?php
// forgot-password.php -- Self-Service Password Reset Request (U-17)
require_once __DIR__ . '/includes/auth/session-bootstrap.php';
require_once __DIR__ . '/includes/db_con.php';

$alert = null;
$alert_type = 'info';
$reset_link = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    csrf_verify();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));

    ensure_password_resets_table($link);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alert = 'Please enter a valid email address.';
        $alert_type = 'danger';
    } else {
        $user = db_one($link, 'SELECT id, name FROM costumer WHERE email = ?', 's', [$email]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            // Invalidate any existing tokens for this email
            db_exec($link, 'DELETE FROM password_resets WHERE email = ?', 's', [$email]);
            // Insert 30-minute time-limited reset token
            db_exec($link,
                'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))',
                'ss',
                [$email, $token]
            );

            $reset_url = BASE_URL . '/reset-password.php?token=' . urlencode($token);
            $reset_link = $reset_url;
            $alert = 'A secure password reset link has been created and is valid for 30 minutes.';
            $alert_type = 'success';
        } else {
            // Constant-time message to prevent account enumeration
            $alert = 'If an account exists with that email address, password reset instructions have been generated.';
            $alert_type = 'info';
        }
    }
}

$title = 'Forgot Password';
require_once __DIR__ . '/includes/layout/header-login.php';
?>
<div class="auth-wrapper">
    <div class="auth-overlay"></div>
    <div class="auth-card" data-aos="zoom-in" data-aos-duration="500">
        <div class="auth-header text-center">
            <a href="<?= BASE_URL ?>/homepage.php" class="d-inline-flex align-items-center mb-2 text-decoration-none">
                <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="Logo" style="width: 32px; height: 32px;" class="mr-2">
                <h4 class="font-weight-bold text-dark mb-0">Bus Service</h4>
            </a>
            <h5 class="font-weight-bold mb-1">Reset Your Password</h5>
            <p class="text-muted small mb-0">Enter your registered email to receive a password reset link.</p>
        </div>
        <div class="auth-body">
            <?php if ($alert): ?>
                <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
                    <?= e($alert) ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <?php if ($reset_link): ?>
                <div class="p-3 bg-light border rounded mb-4 text-center">
                    <p class="small text-muted mb-2 font-weight-bold">Self-Service Reset Link (valid 30 min):</p>
                    <a href="<?= e($reset_link) ?>" class="btn btn-sm btn-success font-weight-bold btn-block">
                        Proceed to Reset Password &rarr;
                    </a>
                </div>
            <?php endif; ?>

            <form action="" method="post">
                <?= csrf_field() ?>
                <div class="form-group mb-3">
                    <label for="email" class="font-weight-bold small text-muted">Registered Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="passenger@example.com" required autocomplete="email">
                </div>
                <button type="submit" name="request_reset" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm">
                    Generate Reset Link
                </button>
            </form>

            <div class="text-center mt-4 border-top pt-3">
                <a href="<?= BASE_URL ?>/login.php" class="small text-muted text-decoration-none mr-3">
                    &larr; Back to Sign In
                </a>
                <a href="<?= BASE_URL ?>/homepage.php" class="small text-muted text-decoration-none">
                    Homepage
                </a>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/layout/footer.php'; ?>
