<?php
// database/reset-mfa.php -- CLI Break-Glass 2FA Reset Script (Issue 2)
// Usage: php database/reset-mfa.php "admin@example.com"

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("CLI execution only.\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth/totp.php';

$email = trim((string)($argv[1] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php database/reset-mfa.php \"admin@example.com\"\n");
    exit(1);
}

$admin = db_one($link, "SELECT id, name, Email_id, totp_enabled FROM `admin` WHERE Email_id = ? LIMIT 1", 's', [$email]);
if (!$admin) {
    fwrite(STDERR, "Error: No administrator found with email '{$email}'.\n");
    exit(1);
}

$admin_id = (int)$admin['id'];
$was_enabled = (int)($admin['totp_enabled'] ?? 0);

// Reset TOTP columns in admin table
db_exec($link, "UPDATE `admin` SET totp_enabled = 0, totp_secret = NULL, last_totp_step = NULL WHERE id = ?", 'i', [$admin_id]);

// Clean up stored recovery codes
ensure_admin_recovery_table($link);
db_exec($link, "DELETE FROM admin_recovery_codes WHERE admin_id = ?", 'i', [$admin_id]);

// Record audit entry
try {
    audit($link, 'UPDATE', 'admin', $admin_id, 
        ['totp_enabled' => $was_enabled], 
        ['totp_enabled' => 0, 'action' => 'mfa_reset_cli', 'initiated_by' => 'system_cli']
    );
} catch (Throwable $e) {
    fwrite(STDERR, "Warning: Audit log entry failed: " . $e->getMessage() . "\n");
}

fwrite(STDOUT, "Success: Two-factor authentication has been disabled for administrator {$email} (ID: {$admin_id}).\n");
exit(0);
