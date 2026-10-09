<?php
// admin/profile.php -- Administrator Profile & Security Settings (Issues 1, 2, 20, 24)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/auth/totp.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_row = db_one($link, "SELECT * FROM `admin` WHERE id = ? LIMIT 1", 'i', [$admin_id]);

if (!$admin_row) {
    logout_all();
    header('Location: ' . BASE_URL . '/homepage.php?login=admin');
    exit;
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Referrer-Policy: no-referrer');
}

$alert = null;
$alert_type = 'info';

if (isset($_GET['mfa_required'])) {
    if (defined('ADMIN_MFA_ENFORCE') && ADMIN_MFA_ENFORCE) {
        $alert = 'Two-Factor Authentication is required for your administrative role. Please configure your authenticator app below to proceed.';
        $alert_type = 'warning';
    } else {
        $alert = 'Two-Factor Authentication is optional but recommended. You can set it up below or continue to your dashboard at any time.';
        $alert_type = 'info';
    }
}

$display_recovery_codes = $_SESSION['new_recovery_codes'] ?? null;
unset($_SESSION['new_recovery_codes']);

// Handle Change Password (Issues 1, 24)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrf_verify();
    $current_pwd = (string)($_POST['current_password'] ?? '');
    $new_pwd = (string)($_POST['new_password'] ?? '');
    $confirm_pwd = (string)($_POST['confirm_password'] ?? '');

    $pwd_errors = validate_new_password($new_pwd, 'admin');

    if ($current_pwd === '' || $new_pwd === '' || $confirm_pwd === '') {
        $alert = 'Please fill all password fields.';
        $alert_type = 'danger';
    } elseif ($new_pwd !== $confirm_pwd) {
        $alert = 'New password and confirmation password do not match.';
        $alert_type = 'danger';
    } elseif (!empty($pwd_errors)) {
        $alert = implode(' ', $pwd_errors);
        $alert_type = 'danger';
    } elseif (!password_verify($current_pwd, $admin_row['Password'])) {
        $alert = 'Current password is incorrect.';
        $alert_type = 'danger';
    } else {
        $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
        db_exec($link, "UPDATE `admin` SET `Password` = ?, `password_changed_at` = NOW() WHERE id = ?", 'si', [$new_hash, $admin_id]);
        
        // Refresh admin row and update session reference so current session remains valid
        $admin_row = db_one($link, "SELECT * FROM `admin` WHERE id = ? LIMIT 1", 'i', [$admin_id]);
        $_SESSION['pwd_ref'] = (string)($admin_row['password_changed_at'] ?? '');
        session_regenerate_id(true);

        try {
            audit($link, 'UPDATE', 'admin', $admin_id, null, ['action' => 'password_change']);
        } catch (Throwable $e) {}

        $alert = 'Your password has been changed successfully.';
        $alert_type = 'success';
    }
}

// Handle Update Profile Information (name, phone)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_verify();
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    if ($name === '' || $phone === '') {
        $alert = 'Name and phone cannot be empty.';
        $alert_type = 'danger';
    } else {
        $old_profile = ['name' => $admin_row['name'], 'phone' => $admin_row['phone']];
        db_exec($link, "UPDATE `admin` SET `name` = ?, `phone` = ? WHERE id = ?", 'ssi', [$name, $phone, $admin_id]);
        $_SESSION['name'] = $name;
        $_SESSION['phone'] = $phone;
        try {
            audit($link, 'UPDATE', 'admin', $admin_id, $old_profile, ['name' => $name, 'phone' => $phone]);
        } catch (Throwable $e) {}
        $alert = 'Profile updated successfully.';
        $alert_type = 'success';
        $admin_row = db_one($link, "SELECT * FROM `admin` WHERE id = ? LIMIT 1", 'i', [$admin_id]);
    }
}

// Handle Enable Two-Factor Authentication (Issue 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enable_totp'])) {
    csrf_verify();
    $confirm_pwd = (string)($_POST['totp_confirm_pwd'] ?? '');
    $totp_code   = trim((string)($_POST['totp_code'] ?? ''));
    $pending_secret = (string)($_SESSION['totp_enroll_secret'] ?? '');

    if ($pending_secret === '' || strlen($pending_secret) !== 16) {
        $alert = 'Enrollment session expired. Please refresh and scan the QR code again.';
        $alert_type = 'danger';
    } elseif (!password_verify($confirm_pwd, $admin_row['Password'])) {
        $alert = 'Incorrect account password. Identity verification failed.';
        $alert_type = 'danger';
    } else {
        $matched_step = null;
        if (!totp_verify_code($pending_secret, $totp_code, 1, $matched_step)) {
            $alert = 'Invalid 6-digit verification code. Please verify your device clock and try again.';
            $alert_type = 'danger';
        } else {
            $encrypted_secret = totp_encrypt_secret($pending_secret);
            $recovery_codes = totp_generate_recovery_codes(10);
            store_recovery_codes($link, $admin_id, $recovery_codes);

            db_exec($link, "UPDATE `admin` SET totp_secret = ?, totp_enabled = 1, last_totp_step = ? WHERE id = ?", 
                'sii', [$encrypted_secret, $matched_step, $admin_id]);

            try {
                audit($link, 'UPDATE', 'admin', $admin_id, ['totp_enabled' => 0], ['totp_enabled' => 1, 'action' => 'mfa_enabled']);
            } catch (Throwable $e) {}

            unset($_SESSION['totp_enroll_secret']);
            $display_recovery_codes = $recovery_codes;
            $alert = 'Two-Factor Authentication is now ENABLED! Securely store the 10 backup recovery codes shown below.';
            $alert_type = 'success';
            $admin_row = db_one($link, "SELECT * FROM `admin` WHERE id = ? LIMIT 1", 'i', [$admin_id]);
        }
    }
}

// Handle Disable Two-Factor Authentication (Issue 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_totp'])) {
    csrf_verify();
    $confirm_pwd = (string)($_POST['disable_confirm_pwd'] ?? '');
    $totp_code   = trim((string)($_POST['disable_totp_code'] ?? ''));

    if (!password_verify($confirm_pwd, $admin_row['Password'])) {
        $alert = 'Incorrect account password. Identity verification failed.';
        $alert_type = 'danger';
    } else {
        $decrypted_secret = totp_decrypt_secret((string)($admin_row['totp_secret'] ?? ''));
        $code_ok = false;
        if ($decrypted_secret && totp_verify_code($decrypted_secret, $totp_code, 1)) {
            $code_ok = true;
        } elseif (verify_and_consume_recovery_code($link, $admin_id, $totp_code)) {
            $code_ok = true;
        }

        if (!$code_ok) {
            $alert = 'Invalid 6-digit verification code or recovery code.';
            $alert_type = 'danger';
        } else {
            db_exec($link, "UPDATE `admin` SET totp_secret = NULL, totp_enabled = 0, last_totp_step = NULL WHERE id = ?", 'i', [$admin_id]);
            ensure_admin_recovery_table($link);
            db_exec($link, "DELETE FROM admin_recovery_codes WHERE admin_id = ?", 'i', [$admin_id]);

            try {
                audit($link, 'UPDATE', 'admin', $admin_id, ['totp_enabled' => 1], ['totp_enabled' => 0, 'action' => 'mfa_disabled']);
            } catch (Throwable $e) {}

            $alert = 'Two-Factor Authentication has been disabled for your account.';
            $alert_type = 'warning';
            $admin_row = db_one($link, "SELECT * FROM `admin` WHERE id = ? LIMIT 1", 'i', [$admin_id]);
        }
    }
}

// Generate or retrieve current pending TOTP secret for setup
$is_totp_enabled = !empty($admin_row['totp_enabled']);
if (!$is_totp_enabled) {
    if (empty($_SESSION['totp_enroll_secret']) || strlen($_SESSION['totp_enroll_secret']) !== 16) {
        $_SESSION['totp_enroll_secret'] = totp_generate_secret(16);
    }
    $enroll_secret = $_SESSION['totp_enroll_secret'];
    $otpauth_uri = totp_get_otpauth_uri($enroll_secret, (string)($admin_row['Email_id'] ?? 'admin@busres.local'));
}

$title = 'Admin Profile & Security';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Profile & Security Credentials</h1>
        <p class="page-subtitle">Manage administrative profile details, credential rotation, and two-factor authentication.</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-dark"><i class="fas fa-tachometer-alt mr-1"></i> Dashboard</a>
    </div>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<?php if (!empty($display_recovery_codes)): ?>
    <div class="card border-warning mb-4 shadow-sm">
        <div class="card-header bg-warning text-dark font-weight-bold">
            ⚠️ Single-Use Backup Recovery Codes (Keep in a Safe Place)
        </div>
        <div class="card-body">
            <p class="text-muted small">
                If you lose access to your authenticator app, these 10 one-time recovery codes can each be used once to sign in. 
                They will <strong>not</strong> be shown again.
            </p>
            <div class="row">
                <?php foreach ($display_recovery_codes as $idx => $code): ?>
                    <div class="col-sm-6 col-md-4 col-lg-3 mb-2">
                        <code class="d-block p-2 bg-light border rounded text-dark font-weight-bold text-center">
                            <?= e($code) ?>
                        </code>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Info Card -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Administrator Profile</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="small font-weight-bold text-muted">Email Address (Read-only)</label>
                        <input type="email" class="form-control" value="<?= e($admin_row['Email_id'] ?? '') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold text-muted">Administrative Role</label>
                        <div>
                            <span class="badge badge-primary px-3 py-2 text-uppercase"><?= e(str_replace('_', ' ', $admin_row['role'] ?? 'operator')) ?></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold text-muted">Last Sign-In</label>
                        <input type="text" class="form-control" value="<?= !empty($admin_row['last_login_at']) ? e(date('d M Y, H:i:s', strtotime($admin_row['last_login_at']))) : 'Never' ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="name" class="small font-weight-bold text-muted">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?= e($admin_row['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone" class="small font-weight-bold text-muted">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($admin_row['phone'] ?? '') ?>" required>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary px-4">
                        Save Profile Details
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Rotation Card -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Rotate Master Password</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="current_password" class="small font-weight-bold text-muted">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password" class="small font-weight-bold text-muted">New Password (Min 12 chars, max 72 bytes)</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" minlength="12" maxlength="72" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="small font-weight-bold text-muted">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="12" maxlength="72" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-dark px-4 font-weight-bold">
                        Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Two-Factor Authentication Card (Issue 2) -->
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 font-weight-bold">Two-Factor Authentication (RFC 6238 TOTP)</h5>
                <?php if ($is_totp_enabled): ?>
                    <span class="badge badge-success px-3 py-2 font-weight-bold">✓ ACTIVE & ENFORCED</span>
                <?php else: ?>
                    <span class="badge badge-warning text-dark px-3 py-2 font-weight-bold">NOT ENROLLED</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <?php if ($is_totp_enabled): ?>
                    <div class="alert alert-success">
                        <strong>Security Status:</strong> Two-Factor Authentication is currently protecting your administrative account. Every sign-in requires an authentication code from your mobile authenticator.
                    </div>
                    <p class="text-muted small">
                        To disable Two-Factor Authentication, confirm your account password and provide a current authenticator code.
                    </p>
                    <form method="post" action="" class="form-inline mt-3" onsubmit="return confirm('Are you sure you want to disable 2FA? This will reduce account security.');">
                        <?= csrf_field() ?>
                        <div class="form-group mr-2 mb-2">
                            <input type="password" name="disable_confirm_pwd" class="form-control" placeholder="Account Password" required>
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <input type="text" name="disable_totp_code" class="form-control" placeholder="6-digit TOTP / Recovery" required>
                        </div>
                        <button type="submit" name="disable_totp" class="btn btn-outline-danger mb-2 font-weight-bold">
                            Disable 2FA
                        </button>
                    </form>
                <?php else: ?>
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center mb-4 mb-md-0">
                            <div class="p-3 bg-light border rounded d-inline-block" style="min-width: 196px; min-height: 196px;">
                                <div id="totp-qrcode" class="d-flex justify-content-center align-items-center" style="min-width: 180px; min-height: 180px;">
                                    <span class="spinner-border spinner-border-sm text-secondary" id="totp-qr-loading" role="status" aria-hidden="true"></span>
                                </div>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted d-block font-weight-bold">Secret Key:</small>
                                <div class="d-flex justify-content-center align-items-center mt-1">
                                    <code id="totp-secret-key" class="h6 font-weight-bold text-dark font-family-monospace letter-spacing-1 mb-0 py-1 px-2 bg-light border rounded"><?= chunk_split(e($enroll_secret), 4, ' ') ?></code>
                                    <button type="button" class="btn btn-outline-secondary btn-sm ml-2" onclick="navigator.clipboard.writeText('<?= e($enroll_secret) ?>'); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy', 2000);">Copy</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="font-weight-bold text-dark mb-2">Set Up Authenticator App</h6>
                            <ol class="small text-muted pl-3 mb-3">
                                <li>Scan the QR code above using your authenticator app (e.g. Google Authenticator, 1Password, Authy).</li>
                                <li>If you cannot scan, manually enter the 16-character Secret Key shown below the QR code.</li>
                                <li>Enter your current account password and the 6-digit code displayed in the app to verify and activate 2FA.</li>
                            </ol>
                            <form method="post" action="" class="border-top pt-3">
                                <?= csrf_field() ?>
                                <div class="form-row">
                                    <div class="form-group col-sm-6">
                                        <label class="small font-weight-bold text-muted">Confirm Account Password</label>
                                        <input type="password" name="totp_confirm_pwd" class="form-control" placeholder="Current password" required>
                                    </div>
                                    <div class="form-group col-sm-6">
                                        <label class="small font-weight-bold text-muted">6-Digit Verification Code</label>
                                        <input type="text" name="totp_code" class="form-control text-center font-weight-bold" placeholder="000000" maxlength="6" autocomplete="off" required>
                                    </div>
                                </div>
                                <button type="submit" name="enable_totp" class="btn btn-success px-4 font-weight-bold">
                                    Activate Two-Factor Authentication
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
</div>

<?php if (!$is_totp_enabled): ?>
<script src="<?= BASE_URL ?>/assets/js/vendor/qrcode.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var qrBox = document.getElementById("totp-qrcode");
    var qrLoading = document.getElementById("totp-qr-loading");
    if (typeof QRCode !== "undefined" && qrBox) {
        if (qrLoading) { qrLoading.remove(); }
        var uri = <?= json_encode($otpauth_uri, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        new QRCode(qrBox, {
            text: uri,
            width: 180,
            height: 180,
            correctLevel: QRCode.CorrectLevel.M
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>
