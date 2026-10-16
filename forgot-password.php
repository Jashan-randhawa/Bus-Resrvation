<?php
// forgot-password.php -- Request a password reset link by e-mail.
// The link is only ever sent to the account's inbox; it is never shown on this page.
require_once __DIR__ . '/includes/auth/session-bootstrap.php';
require_once __DIR__ . '/includes/db_con.php';
require_once __DIR__ . '/includes/password-reset.php';

// Buffer the page so pr_after_response() can send it before the (slower) e-mail work runs.
ob_start();

$alert = null;
$alert_type = 'info';
$sent = false;
$email_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    csrf_verify();
    $email_value = strtolower(trim((string)($_POST['email'] ?? '')));

    $result = pr_request_reset($link, $email_value, client_ip());

    if ($result['status'] === 'invalid') {
        $alert = $result['message'];
        $alert_type = 'danger';
    } elseif ($result['status'] === 'rate_limited') {
        $alert = $result['message'];
        $alert_type = 'warning';
    } else {
        // Same page for existing and unknown addresses; lookup + e-mail run after the response.
        $sent = true;
        $to = $email_value;
        pr_after_response(static function () use ($link, $to): void {
            pr_issue_and_send($link, $to);
        });
    }
}

$title = 'Forgot Password';
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
            <h5 class="font-weight-bold mb-1"><?= $sent ? 'Check Your Inbox' : 'Reset Your Password' ?></h5>
            <p class="text-muted small mb-0">
                <?= $sent ? 'We have sent password reset instructions.' : 'Enter your registered email and we will send you a reset link.' ?>
            </p>
        </div>
        <div class="auth-body">
            <?php if ($alert): ?>
                <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
                    <?= e($alert) ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <?php if ($sent): ?>
                <div class="text-center py-2">
                    <div style="font-size:44px;line-height:1;" aria-hidden="true">&#9993;&#65039;</div>
                    <p class="mt-3 mb-1">
                        If an account exists for <strong><?= e($email_value) ?></strong>,
                        a reset link is on its way.
                    </p>
                    <p class="small text-muted mb-3">
                        The link expires in <?= (int)pr_ttl_minutes() ?> minutes and works once.
                        Check your spam folder if you do not see it.
                    </p>
                </div>
                <form action="" method="post" id="resendForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="email" value="<?= e($email_value) ?>">
                    <button type="submit" name="request_reset" id="resendBtn" class="btn btn-outline-primary btn-block font-weight-bold" disabled>
                        Resend link <span id="resendTimer">(60s)</span>
                    </button>
                </form>
                <script>
                (function () {
                    var left = <?= (int)PR_DEBOUNCE_SECS ?>;
                    var btn = document.getElementById('resendBtn');
                    var t = document.getElementById('resendTimer');
                    var iv = setInterval(function () {
                        left--;
                        if (left <= 0) { clearInterval(iv); btn.disabled = false; t.textContent = ''; }
                        else { t.textContent = '(' + left + 's)'; }
                    }, 1000);
                })();
                </script>
            <?php else: ?>
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group mb-3">
                        <label for="email" class="font-weight-bold small text-muted">Registered Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="passenger@example.com"
                               value="<?= e($email_value) ?>" required maxlength="100" autocomplete="email" autofocus>
                    </div>
                    <button type="submit" name="request_reset" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm">
                        Send Reset Link
                    </button>
                </form>
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
