<?php
// user/profile.php -- User Profile & Account Settings (U-17, Phase 1.4, 1.5, 4.2)
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/google-auth.php';

$uid = (int)($_SESSION['uid'] ?? 0);
$user = db_one($link, 'SELECT * FROM costumer WHERE id = ?', 'i', [$uid]);
if (!$user) {
    logout_all();
    header('Location: ' . BASE_URL . '/homepage.php');
    exit;
}

$pwd_set = (int)($user['pwd_set'] ?? 1);
$is_google_account = !empty($user['google_id']);
$reauth_valid = (int)($_SESSION['reauth_at'] ?? 0) > 0 && (time() - (int)$_SESSION['reauth_at']) <= GOOGLE_REAUTH_TTL;
$reauth_remaining = $reauth_valid ? max(0, GOOGLE_REAUTH_TTL - (time() - (int)$_SESSION['reauth_at'])) : 0;

// 1. Handle Profile Information Update (Phase 1.4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_verify();
    $raw_name = (string)($_POST['name'] ?? '');
    $raw_phone = (string)($_POST['phone'] ?? '');
    $raw_address = (string)($_POST['address'] ?? '');

    $person_check = validate_person_fields($raw_name, $raw_phone, $raw_address);

    if (!$person_check['ok']) {
        flash_set('danger', implode(' ', $person_check['errors']));
    } else {
        db_exec($link,
            'UPDATE costumer SET name = ?, phone = ?, address = ? WHERE id = ?',
            'sssi',
            [$person_check['name'], $person_check['phone'], $person_check['address'], $uid]
        );

        // U-17: Refresh session state immediately so name and phone are never stale
        $_SESSION['name'] = $person_check['name'];
        $_SESSION['phone'] = $person_check['phone'];

        flash_set('success', 'Profile details updated successfully.');
    }
    header('Location: ' . BASE_URL . '/user/profile.php');
    exit;
}

// 2. Handle Password Change with Throttling & Session ID Regeneration (Phase 4.2 & Google Support)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrf_verify();
    $pwd_key = 'pwd_change:' . $uid;
    if (throttle_blocked($link, $pwd_key, 5, 300)) {
        flash_set('danger', 'Too many attempts. Please wait 5 minutes before trying again.');
        header('Location: ' . BASE_URL . '/user/profile.php');
        exit;
    }

    $current_pwd = (string)($_POST['current_pwd'] ?? '');
    $new_pwd = (string)($_POST['new_pwd'] ?? '');
    $confirm_pwd = (string)($_POST['confirm_pwd'] ?? '');

    $pwd_errors = [];
    if (!customer_proof_ok($user, $current_pwd)) {
        throttle_hit($link, $pwd_key);
        $pwd_errors[] = ($pwd_set === 1) ? 'Current password is incorrect.' : 'Please confirm with Google first, then try again.';
    }
    if (strlen($new_pwd) < 8 || !preg_match('/[A-Za-z]/', $new_pwd) || !preg_match('/\d/', $new_pwd)) {
        $pwd_errors[] = 'New password must be at least 8 characters long and contain both letters and digits.';
    }
    if ($new_pwd !== $confirm_pwd) {
        $pwd_errors[] = 'New password and confirmation password do not match.';
    }

    if (!empty($pwd_errors)) {
        flash_set('danger', implode(' ', $pwd_errors));
    } else {
        throttle_clear($link, $pwd_key);
        $hash = password_hash($new_pwd, PASSWORD_DEFAULT);
        db_exec($link, 'UPDATE costumer SET pwd = ?, pwd_set = 1 WHERE id = ?', 'si', [$hash, $uid]);

        try {
            audit($link, 'UPDATE', 'customer', $uid, null, ['field' => 'password', 'method' => $pwd_set === 1 ? 'password' : 'google']);
        } catch (Throwable $e) {
            error_log('[busres] audit password change error: ' . $e->getMessage());
        }

        // Regenerate session ID to prevent session fixation upon credential changes
        session_regenerate_id(true);

        $notice_subject = 'Security Notice: Your Password Was Changed';
        $notice_body = '<p>Hello ' . htmlspecialchars($user['name']) . ',</p>'
            . '<p>Your account password was successfully updated on ' . date('Y-m-d H:i:s') . '.</p>'
            . '<p>If you did not perform this change, please contact support immediately.</p>';
        send_app_mail((string)$user['email'], $notice_subject, $notice_body);

        flash_set('success', 'Your password has been changed successfully.');
    }
    header('Location: ' . BASE_URL . '/user/profile.php');
    exit;
}

// 3. Handle Email Address Change (Phase 4.2 & Google Support)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_email'])) {
    csrf_verify();
    $current_pwd = (string)($_POST['email_current_pwd'] ?? '');
    $new_email = trim((string)($_POST['new_email'] ?? ''));

    $email_errors = [];
    if (!customer_proof_ok($user, $current_pwd)) {
        $email_errors[] = ($pwd_set === 1) ? 'Current password is required to change your email.' : 'Please confirm with Google first, then try again.';
    }
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $email_errors[] = 'Please enter a valid new email address.';
    } elseif ($new_email === (string)$user['email']) {
        $email_errors[] = 'The new email is identical to your current email.';
    } else {
        $existing = db_one($link, 'SELECT id FROM costumer WHERE email = ? AND id != ?', 'si', [$new_email, $uid]);
        if ($existing) {
            $email_errors[] = 'That email address is already in use by another account.';
        }
    }

    if (!empty($email_errors)) {
        flash_set('danger', implode(' ', $email_errors));
    } else {
        db_exec($link, 'UPDATE costumer SET email = ? WHERE id = ?', 'si', [$new_email, $uid]);
        try {
            audit($link, 'UPDATE', 'customer', $uid, null, ['field' => 'email', 'old' => $user['email'], 'new' => $new_email, 'method' => $pwd_set === 1 ? 'password' : 'google']);
        } catch (Throwable $e) {
            error_log('[busres] audit email change error: ' . $e->getMessage());
        }
        $_SESSION['email'] = $new_email;
        session_regenerate_id(true);

        $mail_sub = 'Your Account Email Has Been Updated';
        $mail_body = '<p>Hello ' . htmlspecialchars($user['name']) . ',</p><p>Your account email address was changed to ' . htmlspecialchars($new_email) . '.</p>';
        send_app_mail($new_email, $mail_sub, $mail_body);

        flash_set('success', 'Your email address has been updated successfully.');
    }
    header('Location: ' . BASE_URL . '/user/profile.php');
    exit;
}

// 4. Handle Account Deletion / Deactivation (Phase 4.2 & Google Support)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    csrf_verify();
    $confirm_pwd = (string)($_POST['delete_account_pwd'] ?? '');
    if (!customer_proof_ok($user, $confirm_pwd)) {
        $msg = ($pwd_set === 1) ? 'Password incorrect. Account deletion cancelled.' : 'Please confirm with Google first, then try again.';
        flash_set('danger', $msg);
        header('Location: ' . BASE_URL . '/user/profile.php');
        exit;
    }

    // Anonymize personal details to fulfill privacy while maintaining booking referential records
    $anonymized_email = 'deactivated_' . $uid . '_' . bin2hex(random_bytes(4)) . '@archived.local';
    db_exec($link,
        'UPDATE costumer SET name = ?, email = ?, phone = ?, address = NULL, pwd = ?, pwd_set = 0, archived_at = NOW() WHERE id = ?',
        'ssssi', ['Deactivated Account', $anonymized_email, '0000000000', google_unusable_password_hash(), $uid]
    );

    try {
        audit($link, 'DELETE', 'customer', $uid, null, ['action' => 'account_deactivated', 'method' => $pwd_set === 1 ? 'password' : 'google']);
    } catch (Throwable $e) {
        error_log('[busres] audit delete error: ' . $e->getMessage());
    }

    logout_all();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    flash_set('info', 'Your account has been deactivated successfully.');
    header('Location: ' . BASE_URL . '/homepage.php');
    exit;
}

$title = 'My Profile';
require_once __DIR__ . '/../includes/layout/header-user.php';
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <div class="d-flex align-items-center flex-wrap">
            <h1 class="page-title mb-0">Account Profile &amp; Settings</h1>
            <?php if ($is_google_account): ?>
                <span class="badge badge-light border text-dark ml-2 py-1 px-2 d-inline-flex align-items-center" style="font-size: 0.82rem;">
                    <svg width="14" height="14" viewBox="0 0 48 48" class="mr-1" aria-hidden="true" focusable="false"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    Signed in with Google
                </span>
            <?php endif; ?>
            <?php if ($pwd_set === 0): ?>
                <span class="badge badge-warning text-dark ml-2 py-1 px-2" style="font-size: 0.82rem;">Google-Only Credentials</span>
            <?php endif; ?>
        </div>
        <p class="page-subtitle mt-1 mb-0">Manage your personal information, contact phone number, and account security credentials.</p>
    </div>
</div>

<div class="row">
    <!-- Profile Info Form -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Personal Information</h5>
            </div>
            <div class="card-body p-3 p-md-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="email" class="font-weight-bold small text-muted">Email Address (Read-only)</label>
                        <input type="email" id="email" class="form-control bg-light" value="<?= e($user['email'] ?? '') ?>" readonly>
                        <small class="text-muted">Email address is your primary account identifier and cannot be changed directly.</small>
                    </div>

                    <div class="form-group">
                        <label for="name" class="font-weight-bold small text-muted">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?= e($user['name'] ?? '') ?>" minlength="2" maxlength="100" required autocomplete="name">
                    </div>

                    <div class="form-group">
                        <label for="phone" class="font-weight-bold small text-muted">Contact Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" minlength="10" maxlength="20" required autocomplete="tel" inputmode="tel">
                        <small class="text-muted d-block mt-1">Note: Public ticket lookups match the phone number entered at the time of each booking. Updating your phone here updates your account profile for future bookings.</small>
                    </div>

                    <div class="form-group">
                        <label for="address" class="font-weight-bold small text-muted">Residential Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3" maxlength="255" placeholder="Street, City, Postal Code"><?= e($user['address'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" name="update_profile" class="btn btn-primary px-4 font-weight-bold shadow-sm btn-block-mobile" style="min-height: 44px;">
                            Save Profile Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Change Form -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 font-weight-bold"><?= ($pwd_set === 1) ? 'Change Password' : 'Set Account Password' ?></h5>
                <?php if ($pwd_set === 0): ?>
                    <a href="<?= BASE_URL ?>/forgot-password.php" class="small text-muted font-weight-bold">Reset via Email &rarr;</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-3 p-md-4">
                <?php if ($pwd_set === 0): ?>
                    <?php if ($reauth_valid): ?>
                        <div class="alert alert-success small mb-3">
                            <strong>✓ Google identity confirmed</strong> (valid for <?= (int)ceil($reauth_remaining / 60) ?> min). Enter your new password below:
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info small mb-3">
                            <div class="font-weight-bold mb-1">Google Confirmation Required</div>
                            <div>Your account was created via Google. To set a password, please confirm your Google identity first.</div>
                            <div class="mt-2">
                                <a href="<?= BASE_URL ?>/google-reauth.php?next=<?= rawurlencode('/user/profile.php') ?>" class="btn btn-primary btn-sm font-weight-bold d-inline-flex align-items-center">
                                    <svg width="14" height="14" viewBox="0 0 48 48" class="mr-1" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                                    Confirm with Google
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form action="" method="post">
                    <?= csrf_field() ?>
                    <?php if ($pwd_set === 1): ?>
                        <div class="form-group">
                            <label for="current_pwd" class="font-weight-bold small text-muted">Current Password</label>
                            <div class="input-group">
                                <input type="password" id="current_pwd" name="current_pwd" class="form-control pwd-input" placeholder="••••••••" required autocomplete="current-password">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary toggle-pwd-btn" data-target="current_pwd" title="Show/Hide Password" aria-label="Toggle password visibility" style="min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">👁️</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="new_pwd" class="font-weight-bold small text-muted"><?= ($pwd_set === 1) ? 'New Password' : 'Choose Account Password' ?></label>
                        <div class="input-group">
                            <input type="password" id="new_pwd" name="new_pwd" class="form-control pwd-input" placeholder="••••••••" minlength="8" required autocomplete="new-password" <?= ($pwd_set === 0 && !$reauth_valid) ? 'disabled' : '' ?>>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary toggle-pwd-btn" data-target="new_pwd" title="Show/Hide Password" aria-label="Toggle password visibility" style="min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">👁️</button>
                            </div>
                        </div>
                        <small class="text-muted">Must contain at least 8 characters with letters and digits.</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_pwd" class="font-weight-bold small text-muted">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" id="confirm_pwd" name="confirm_pwd" class="form-control pwd-input" placeholder="••••••••" minlength="8" required autocomplete="new-password" <?= ($pwd_set === 0 && !$reauth_valid) ? 'disabled' : '' ?>>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary toggle-pwd-btn" data-target="confirm_pwd" title="Show/Hide Password" aria-label="Toggle password visibility" style="min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">👁️</button>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <?php if ($pwd_set === 0 && !$reauth_valid): ?>
                            <a href="<?= BASE_URL ?>/google-reauth.php?next=<?= rawurlencode('/user/profile.php') ?>" class="btn btn-warning px-4 font-weight-bold shadow-sm text-dark btn-block-mobile d-inline-flex align-items-center justify-content-center" style="min-height: 44px;">
                                Confirm with Google to Set Password
                            </a>
                        <?php else: ?>
                            <button type="submit" name="change_password" class="btn btn-warning px-4 font-weight-bold shadow-sm text-dark btn-block-mobile" style="min-height: 44px;">
                                <?= ($pwd_set === 1) ? 'Update Password' : 'Save Account Password' ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Email Management & Account Control -->
<div class="row">
    <!-- Change Email Address -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Change Email Address</h5>
            </div>
            <div class="card-body p-3 p-md-4">
                <?php if ($pwd_set === 0): ?>
                    <?php if ($reauth_valid): ?>
                        <div class="alert alert-success small mb-3">
                            <strong>✓ Google identity confirmed</strong> (valid for <?= (int)ceil($reauth_remaining / 60) ?> min).
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info small mb-3">
                            <div class="font-weight-bold mb-1">Google Confirmation Required</div>
                            <div>Changing your email requires verifying your Google account identity.</div>
                            <div class="mt-2">
                                <a href="<?= BASE_URL ?>/google-reauth.php?next=<?= rawurlencode('/user/profile.php') ?>" class="btn btn-primary btn-sm font-weight-bold d-inline-flex align-items-center">
                                    <svg width="14" height="14" viewBox="0 0 48 48" class="mr-1" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                                    Confirm with Google
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="new_email" class="font-weight-bold small text-muted">New Email Address</label>
                        <input type="email" id="new_email" name="new_email" class="form-control" placeholder="new.email@example.com" required autocomplete="email" <?= ($pwd_set === 0 && !$reauth_valid) ? 'disabled' : '' ?>>
                        <small class="text-muted">A confirmation notification will be sent to this address.</small>
                    </div>

                    <?php if ($pwd_set === 1): ?>
                        <div class="form-group">
                            <label for="email_current_pwd" class="font-weight-bold small text-muted">Current Password</label>
                            <div class="input-group">
                                <input type="password" id="email_current_pwd" name="email_current_pwd" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary toggle-pwd-btn" data-target="email_current_pwd" title="Show/Hide Password" aria-label="Toggle password visibility" style="min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">👁️</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-end mt-4">
                        <?php if ($pwd_set === 0 && !$reauth_valid): ?>
                            <a href="<?= BASE_URL ?>/google-reauth.php?next=<?= rawurlencode('/user/profile.php') ?>" class="btn btn-primary px-4 font-weight-bold shadow-sm btn-block-mobile d-inline-flex align-items-center justify-content-center" style="min-height: 44px;">
                                Confirm with Google to Update Email
                            </a>
                        <?php else: ?>
                            <button type="submit" name="change_email" class="btn btn-primary px-4 font-weight-bold shadow-sm btn-block-mobile" style="min-height: 44px;">
                                Update Email
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Account Closure / Deactivation -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm border-left border-danger h-100" style="border-left-width: 4px !important;">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold text-danger">Deactivate Account</h5>
            </div>
            <div class="card-body p-3 p-md-4 d-flex flex-column justify-content-between">
                <div>
                    <p class="text-muted small">
                        Deactivating your account will disable your login and anonymize your personal profile data. Past booking history and receipts will be safely preserved in line with operator retention requirements.
                    </p>
                </div>

                <?php if ($pwd_set === 0): ?>
                    <?php if ($reauth_valid): ?>
                        <div class="alert alert-success small mb-3">
                            <strong>✓ Google identity confirmed.</strong>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning small mb-3">
                            <div class="font-weight-bold mb-1">Google Confirmation Required</div>
                            <div>Deactivating your account requires confirming your Google account identity.</div>
                            <div class="mt-2">
                                <a href="<?= BASE_URL ?>/google-reauth.php?next=<?= rawurlencode('/user/profile.php') ?>" class="btn btn-danger btn-sm font-weight-bold d-inline-flex align-items-center">
                                    <svg width="14" height="14" viewBox="0 0 48 48" class="mr-1" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                                    Confirm with Google to Deactivate
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form action="" method="post" onsubmit="return confirm('Are you sure you want to permanently deactivate your account? This action cannot be undone.');">
                    <?= csrf_field() ?>
                    <?php if ($pwd_set === 1): ?>
                        <div class="form-group">
                            <label for="delete_account_pwd" class="font-weight-bold small text-muted">Confirm with Current Password</label>
                            <div class="input-group">
                                <input type="password" id="delete_account_pwd" name="delete_account_pwd" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary toggle-pwd-btn" data-target="delete_account_pwd" title="Show/Hide Password" aria-label="Toggle password visibility" style="min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">👁️</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-end mt-3">
                        <?php if ($pwd_set === 0 && !$reauth_valid): ?>
                            <a href="<?= BASE_URL ?>/google-reauth.php?next=<?= rawurlencode('/user/profile.php') ?>" class="btn btn-outline-danger px-4 font-weight-bold btn-block-mobile d-inline-flex align-items-center justify-content-center" style="min-height: 44px;">
                                Confirm with Google to Deactivate
                            </a>
                        <?php else: ?>
                            <button type="submit" name="delete_account" class="btn btn-outline-danger px-4 font-weight-bold btn-block-mobile" style="min-height: 44px;">
                                Deactivate My Account
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var toggles = document.querySelectorAll('.toggle-pwd-btn');
    toggles.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetId = this.getAttribute('data-target');
            var input = document.getElementById(targetId);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                this.textContent = '🔒';
                this.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                this.textContent = '👁️';
                this.setAttribute('aria-label', 'Show password');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-user.php'; ?>
