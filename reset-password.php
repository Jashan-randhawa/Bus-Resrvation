<?php
// reset-password.php -- Self-Service Password Reset Completion (U-17)
require_once __DIR__ . '/includes/auth/session-bootstrap.php';
require_once __DIR__ . '/includes/db_con.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$alert = null;
$alert_type = 'info';
$token_valid = false;
$reset_record = null;

ensure_password_resets_table($link);

if ($token !== '') {
    $reset_record = db_one($link,
        'SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()',
        's',
        [$token]
    );
    if ($reset_record) {
        $token_valid = true;
    } else {
        $alert = 'This password reset link is invalid or has expired. Please request a new one.';
        $alert_type = 'danger';
    }
} else {
    $alert = 'No reset token provided.';
    $alert_type = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_reset']) && $token_valid && $reset_record) {
    csrf_verify();
    $new_pwd = (string)($_POST['new_pwd'] ?? '');
    $confirm_pwd = (string)($_POST['confirm_pwd'] ?? '');

    $pwd_errors = [];
    if (strlen($new_pwd) < 8 || !preg_match('/[A-Za-z]/', $new_pwd) || !preg_match('/\d/', $new_pwd)) {
        $pwd_errors[] = 'Password must be at least 8 characters long and contain both letters and digits.';
    }
    if ($new_pwd !== $confirm_pwd) {
        $pwd_errors[] = 'Password and confirmation password do not match.';
    }

    if (!empty($pwd_errors)) {
        $alert = implode(' ', $pwd_errors);
        $alert_type = 'danger';
    } else {
        $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
        db_exec($link, 'UPDATE costumer SET pwd = ? WHERE email = ?', 'ss', [$new_hash, $reset_record['email']]);
        // Invalidate used token
        db_exec($link, 'DELETE FROM password_resets WHERE email = ?', 's', [$reset_record['email']]);

        flash_set('success', 'Your password has been successfully reset. Please sign in with your new password.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

$title = 'Set New Password';
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
            <h5 class="font-weight-bold mb-1">Set New Password</h5>
            <p class="text-muted small mb-0">Choose a secure password for your account.</p>
        </div>
        <div class="auth-body">
            <?php if ($alert): ?>
                <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
                    <?= e($alert) ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <?php if ($token_valid): ?>
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="form-group mb-3">
                        <label for="new_pwd" class="font-weight-bold small text-muted">New Password</label>
                        <input type="password" id="new_pwd" name="new_pwd" class="form-control" placeholder="••••••••" minlength="8" required autocomplete="new-password">
                        <small class="text-muted">Minimum 8 characters with letters &amp; numbers.</small>
                    </div>
                    <div class="form-group mb-4">
                        <label for="confirm_pwd" class="font-weight-bold small text-muted">Confirm New Password</label>
                        <input type="password" id="confirm_pwd" name="confirm_pwd" class="form-control" placeholder="••••••••" minlength="8" required autocomplete="new-password">
                    </div>
                    <button type="submit" name="complete_reset" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm">
                        Save New Password
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center mt-3">
                    <a href="<?= BASE_URL ?>/forgot-password.php" class="btn btn-outline-primary btn-sm">
                        Request New Reset Link
                    </a>
                </div>
            <?php endif; ?>

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
