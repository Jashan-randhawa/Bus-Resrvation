<?php
// reset-password.php -- Set a new password using the link that was e-mailed to the customer.
require_once __DIR__ . '/includes/auth/session-bootstrap.php';
require_once __DIR__ . '/includes/db_con.php';
require_once __DIR__ . '/includes/password-reset.php';

// The URL carries a secret: never cache the page and never leak it through the Referer header.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
ob_start();

// ?code= is what the e-mail contains; ?token= is accepted for links issued by older versions.
$token = trim((string)($_GET['code'] ?? $_POST['code'] ?? $_GET['token'] ?? $_POST['token'] ?? ''));
$ip = client_ip();
$alert = null;
$alert_type = 'info';
$token_valid = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_reset'])) {
    csrf_verify();
    $res = pr_complete_reset(
        $link,
        $token,
        (string)($_POST['new_pwd'] ?? ''),
        (string)($_POST['confirm_pwd'] ?? ''),
        $ip
    );
    if ($res['ok']) {
        flash_set('success', $res['message']);
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    $alert = $res['message'];
    $alert_type = 'danger';
    // Wrong password format keeps the form open; a dead link falls through to re-validation below.
    $check = pr_find_by_token($link, $token);
    $token_valid = $check !== null;
} elseif ($token === '') {
    $alert = 'No reset link was provided. Please use the link from your email.';
    $alert_type = 'danger';
} else {
    $check = pr_validate_token($link, $token, $ip);
    $token_valid = $check['valid'];
    if (!$token_valid) {
        $alert = $check['message'];
        $alert_type = 'danger';
    }
}

$title = 'Set New Password';
require_once __DIR__ . '/includes/layout/header-login.php';
?>
<div class="auth-wrapper">
    <div class="auth-overlay"></div>
    <div class="auth-card">
        <div class="auth-header text-center">
            <a href="<?= BASE_URL ?>/homepage.php" class="d-inline-flex align-items-center mb-2 text-decoration-none">
                <img src="<?= BASE_URL ?>/assets/images/bus-emblem.png" alt="BusBook Logo" style="width: 32px; height: 32px;" class="mr-2">
                <h4 class="font-weight-bold text-dark mb-0">BusBook</h4>
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
                <form action="" method="post" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="code" value="<?= e($token) ?>">
                    <div class="form-group mb-3">
                        <label for="new_pwd" class="font-weight-bold small text-muted">New Password</label>
                        <input type="password" id="new_pwd" name="new_pwd" class="form-control" placeholder="••••••••"
                               minlength="8" maxlength="72" required autocomplete="new-password" autofocus>
                        <small class="text-muted">At least 8 characters with letters &amp; numbers.</small>
                    </div>
                    <div class="form-group mb-4">
                        <label for="confirm_pwd" class="font-weight-bold small text-muted">Confirm New Password</label>
                        <input type="password" id="confirm_pwd" name="confirm_pwd" class="form-control" placeholder="••••••••"
                               minlength="8" maxlength="72" required autocomplete="new-password">
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
