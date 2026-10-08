<?php
// admin/mfa.php -- Two-Factor Authentication Login Challenge (Issue 2)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth/session-bootstrap.php';
require_once __DIR__ . '/../includes/auth/totp.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin_id = (int)($_SESSION['mfa_admin_id'] ?? 0);
$mfa_started = (int)($_SESSION['mfa_started'] ?? 0);

// Validate pending MFA session lifecycle (10 minute window)
if ($admin_id <= 0 || $mfa_started === 0 || (time() - $mfa_started) > 600) {
    unset($_SESSION['mfa_admin_id'], $_SESSION['mfa_started']);
    header('Location: ' . BASE_URL . '/homepage.php?login=admin&error=expired');
    exit;
}

$admin_row = db_one($link, 'SELECT * FROM `admin` WHERE id = ? LIMIT 1', 'i', [$admin_id]);
if (!$admin_row || (int)($admin_row['is_active'] ?? 1) === 0 || empty($admin_row['totp_enabled'])) {
    unset($_SESSION['mfa_admin_id'], $_SESSION['mfa_started']);
    header('Location: ' . BASE_URL . '/homepage.php?login=admin&error=deactivated');
    exit;
}

$alert = null;
$alert_type = 'danger';

$throttle_key = 'mfa:' . $admin_id;
$is_blocked = throttle_blocked($link, $throttle_key, 5, 900);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_mfa'])) {
    csrf_verify();

    if ($is_blocked) {
        $alert = 'Too many failed attempts. Account authentication temporarily locked for 15 minutes.';
    } else {
        $code = trim((string)($_POST['mfa_code'] ?? ''));
        $verified = false;
        $method = '';

        if (strlen($code) === 6 && ctype_digit($code)) {
            // TOTP 6-digit Code Verification
            $decrypted_secret = totp_decrypt_secret((string)($admin_row['totp_secret'] ?? ''));
            if ($decrypted_secret) {
                $matched_step = null;
                $totp_ok = totp_verify_code($decrypted_secret, $code, 1, $matched_step);
                $last_step = (int)($admin_row['last_totp_step'] ?? 0);

                if ($totp_ok && $matched_step !== null && $matched_step > $last_step) {
                    $verified = true;
                    $method = 'totp';
                    // Anti-replay: record last accepted time step counter
                    db_exec($link, 'UPDATE `admin` SET last_totp_step = ? WHERE id = ?', 'ii', [$matched_step, $admin_id]);
                }
            }
        } else {
            // Check Backup Recovery Code
            if (verify_and_consume_recovery_code($link, $admin_id, $code)) {
                $verified = true;
                $method = 'recovery_code';
            }
        }

        if ($verified) {
            throttle_clear($link, $throttle_key);
            unset($_SESSION['mfa_admin_id'], $_SESSION['mfa_started']);

            $login_ok = login_user('admin', $admin_row);
            if ($login_ok) {
                db_exec($link, 'UPDATE `admin` SET last_login_at = NOW() WHERE id = ?', 'i', [$admin_id]);
                try {
                    audit($link, 'LOGIN', 'admin', $admin_id, null, [
                        'email'  => $admin_row['Email_id'] ?? '',
                        'status' => 'success',
                        'mfa'    => $method
                    ]);
                } catch (Throwable $e) {}

                header('Location: ' . BASE_URL . '/admin/index.php');
                exit;
            } else {
                $alert = 'Account configuration error. Please contact a super administrator.';
            }
        } else {
            throttle_hit($link, $throttle_key);
            try {
                audit($link, 'LOGIN_FAILED', 'admin', $admin_id, null, [
                    'email'  => $admin_row['Email_id'] ?? '',
                    'reason' => 'invalid_mfa_code'
                ]);
            } catch (Throwable $e) {}

            $alert = 'Invalid verification code or recovery code. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication | Admin Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/public.css">
    <style>
        body {
            background-color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .mfa-card {
            max-width: 440px;
            width: 100%;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .mfa-header {
            background: #1e293b;
            color: #ffffff;
            padding: 1.5rem;
            text-align: center;
            border-bottom: 2px solid #f59e0b;
        }
        .mfa-body {
            padding: 2rem;
        }
        .code-input {
            letter-spacing: 0.25em;
            font-size: 1.5rem;
            text-align: center;
            font-family: monospace;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="mfa-card">
    <div class="mfa-header">
        <div class="mb-2">
            <span class="badge badge-warning text-dark font-weight-bold px-2 py-1">RESTRICTED ACCESS</span>
        </div>
        <h4 class="mb-1 font-weight-bold">Two-Factor Authentication</h4>
        <p class="small text-muted mb-0">Verify your identity to complete admin sign-in</p>
    </div>
    <div class="mfa-body">
        <?php if ($alert): ?>
            <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
                <?= e($alert) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <p class="text-muted small mb-3">
            Enter the 6-digit code from your authenticator app (Google Authenticator, Authy, etc.) or a single-use backup recovery code.
        </p>

        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="form-group mb-4">
                <label for="mfa_code" class="small font-weight-bold text-dark">Security Code / Recovery Code</label>
                <input type="text"
                       name="mfa_code"
                       id="mfa_code"
                       class="form-control code-input"
                       placeholder="000000"
                       autocomplete="one-time-code"
                       autofocus
                       required>
            </div>
            <button type="submit" name="verify_mfa" class="btn btn-dark btn-block py-2 font-weight-bold">
                Authenticate Session
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center">
            <a href="<?= BASE_URL ?>/homepage.php?login=admin" class="small text-muted font-weight-bold">
                &larr; Return to Sign In
            </a>
        </div>
    </div>
</div>
</body>
</html>
