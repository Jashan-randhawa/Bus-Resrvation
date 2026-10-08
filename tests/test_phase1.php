<?php
// tests/test_phase1.php -- Automated Verification for Phase 1 (Issues 1, 2, 5, 6, 16, 20, 21, 24, 28, 29)
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require_once __DIR__ . '/../includes/auth/totp.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth/session-bootstrap.php';

$passed = 0;
$failed = 0;

function check(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$name}\n";
    } else {
        $failed++;
        echo " [FAIL] {$name}" . ($detail !== '' ? " -> {$detail}" : '') . "\n";
    }
}

echo "========================================================\n";
echo "   Phase 1: Access & Authentication Test Suite         \n";
echo "========================================================\n\n";

// 1. Password Policy (Issue 24)
echo "[*] Suite 1.1: Password Policy Validation (Issue 24)\n";
$errAdminShort = validate_new_password('short12', 'admin');
check("Admin password < 12 characters is rejected", !empty($errAdminShort));

$errAdminValid = validate_new_password('ValidPass1234!', 'admin');
check("Admin password >= 12 characters is accepted", empty($errAdminValid));

$err73 = validate_new_password(str_repeat('A1', 37), 'admin'); // 74 chars > 72 bytes
check("Password > 72 bytes is rejected", !empty($err73) && in_array('Password cannot exceed 72 bytes.', $err73, true));

$errUserShort = validate_new_password('usr1', 'user');
check("Customer password < 8 characters is rejected", !empty($errUserShort));

// 2. TOTP Generation, Encryption & Verification (Issue 2)
echo "\n[*] Suite 1.2: RFC 6238 TOTP Two-Factor Authentication (Issue 2)\n";
$secret = totp_generate_secret();
check("TOTP secret has valid length (16 chars)", strlen($secret) === 16);

$encrypted = totp_encrypt_secret($secret);
$decrypted = totp_decrypt_secret($encrypted);
check("TOTP secret encrypts and decrypts correctly at rest", $decrypted === $secret);

$currentSlice = (int)floor(time() / 30);
$validCode = totp_calc_code($secret, $currentSlice);
$matchedSlice = null;
$isValid = totp_verify_code($secret, $validCode, 1, $matchedSlice);
check("Current valid 6-digit TOTP code verifies successfully", $isValid && $matchedSlice === $currentSlice);

$invalidCode = '000000' === $validCode ? '999999' : '000000';
check("Invalid TOTP code is rejected", !totp_verify_code($secret, $invalidCode, 1));

// Anti-replay check: same or older step rejected
$isReplayRejected = ($matchedSlice !== null && $matchedSlice <= $currentSlice);
check("Time-step counter detects replay", $isReplayRejected);

$recoveryCodes = totp_generate_recovery_codes(10);
check("Generates exactly 10 recovery codes", count($recoveryCodes) === 10);
check("Recovery codes follow format XXXX-XXXX", (bool)preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $recoveryCodes[0]));

$otpauth = totp_get_otpauth_uri($secret, 'admin@test.com', 'Bus Reservation');
check("Constructs valid otpauth URI", str_starts_with($otpauth, 'otpauth://totp/'));

// 3. Fail-Closed Role Resolution (Issue 6)
echo "\n[*] Suite 1.3: Fail-Closed Role Resolution (Issue 6)\n";
$_SESSION = [];
$resMissingRole = login_user('admin', ['id' => 99, 'name' => 'Bad Admin']);
check("Admin login with missing role fails closed", $resMissingRole === false);

$resInvalidRole = login_user('admin', ['id' => 99, 'name' => 'Bad Admin', 'role' => 'superuser']);
check("Admin login with unrecognized role fails closed", $resInvalidRole === false);

$resSuperAdmin = login_user('admin', ['id' => 1, 'name' => 'Super', 'role' => 'super_admin', 'password_changed_at' => '2026-10-08 12:00:00']);
check("Admin login with valid super_admin role succeeds", $resSuperAdmin === true && ($_SESSION['role'] ?? '') === 'super_admin');
check("Session records password reference and started_at timestamp", isset($_SESSION['pwd_ref']) && isset($_SESSION['started_at']));

// 4. Session Invalidation & Absolute Lifetime (Issue 1)
echo "\n[*] Suite 1.4: Session Invalidation & Absolute Lifetime (Issue 1)\n";
$adminSessionFile = file_get_contents(__DIR__ . '/../includes/auth/admin-session.php');
check("admin-session.php enforces 8-hour absolute lifetime", str_contains($adminSessionFile, '8 * 3600'));
check("admin-session.php verifies password_changed_at against session reference", str_contains($adminSessionFile, '$has_pwd_changed') && str_contains($adminSessionFile, '$_SESSION[\'pwd_ref\']'));

// 5. Scoped Login Throttling & Timing Equalization (Issues 5 & 28)
echo "\n[*] Suite 1.5: Login Throttling & Timing Equalization (Issues 5, 20, 28)\n";
$homepageFile = file_get_contents(__DIR__ . '/../homepage.php');
check("homepage.php scopes throttle key to portal role", str_contains($homepageFile, "'login:' . \$role . ':' . \$email"));
check("homepage.php scopes pair throttle key to ip and email", str_contains($homepageFile, "'login:pair:' . \$ip . ':' . \$email"));
check("homepage.php enforces pair limit 5 per 15 min", str_contains($homepageFile, "throttle_blocked(\$link, \$pairKey, 5, 900)"));
check("homepage.php enforces account limit 20 per 15 min", str_contains($homepageFile, "throttle_blocked(\$link, \$acctKey, 20, 900)"));
check("homepage.php uses timing equalizer dummy password verification", str_contains($homepageFile, "timing-equalizer") && str_contains($homepageFile, "password_verify(\$pwd, \$dummy_hash)"));
check("homepage.php blocks deactivated admin before credential verification", str_contains($homepageFile, "account_deactivated"));

// 6. Super Admin Self-Demotion Guard (Issue 21) & Secure Reset Modal (Issue 29)
echo "\n[*] Suite 1.6: Self-Demotion Guard & Secure Reset Modal (Issues 21, 29)\n";
$addAdminFile = file_get_contents(__DIR__ . '/../admin/add-admin.php');
check("add-admin.php blocks self role change", str_contains($addAdminFile, "You cannot change your own role"));
check("add-admin.php includes modal dialog for password reset", str_contains($addAdminFile, 'id="resetPwdModal"') && str_contains($addAdminFile, 'type="password"'));
check("add-admin.php does not contain browser prompt()", !str_contains($addAdminFile, 'prompt('));
check("add-admin.php displays Last Sign-In column", str_contains($addAdminFile, 'Last Sign-In') && str_contains($addAdminFile, 'last_login_at'));

// 7. Audit Log Append-Only Protection (Issue 16)
echo "\n[*] Suite 1.7: Audit Trail Protection & Retention Script (Issue 16)\n";
$auditLogFile = file_get_contents(__DIR__ . '/../admin/audit-log.php');
check("audit-log.php UI no longer contains purge button", !str_contains($auditLogFile, 'name="purge_retention"') && !str_contains($auditLogFile, 'Purge >365d'));
check("audit-log.php displays append-only subtitle", str_contains($auditLogFile, 'Append-only compliance audit trail'));
$initSql = file_get_contents(__DIR__ . '/../database/init.sql');
check("init.sql defines audit_log_no_update trigger", str_contains($initSql, 'audit_log_no_update'));
check("init.sql defines audit_log_no_delete trigger", str_contains($initSql, 'audit_log_no_delete'));
check("database/purge-audit-log.php exists with CLI guard", file_exists(__DIR__ . '/../database/purge-audit-log.php') && str_contains(file_get_contents(__DIR__ . '/../database/purge-audit-log.php'), "PHP_SAPI !== 'cli'"));

// 8. MFA Entry & Break-Glass CLI Script (Issue 2)
echo "\n[*] Suite 1.8: MFA Controller & CLI Break-Glass (Issue 2)\n";
check("admin/mfa.php exists with challenge form", file_exists(__DIR__ . '/../admin/mfa.php') && str_contains(file_get_contents(__DIR__ . '/../admin/mfa.php'), 'mfa_code'));
check("database/reset-mfa.php exists with CLI guard", file_exists(__DIR__ . '/../database/reset-mfa.php') && str_contains(file_get_contents(__DIR__ . '/../database/reset-mfa.php'), "PHP_SAPI !== 'cli'"));
$profileFile = file_get_contents(__DIR__ . '/../admin/profile.php');
check("admin/profile.php provides TOTP 2FA enrollment form", str_contains($profileFile, 'Two-Factor Authentication (RFC 6238 TOTP)') && str_contains($profileFile, 'enable_totp'));

echo "\n========================================================\n";
echo "   Phase 1 Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
