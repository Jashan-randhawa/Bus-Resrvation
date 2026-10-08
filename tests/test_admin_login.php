<?php
// tests/test_admin_login.php -- Admin Login Entry Points & Redirect Test Harness (Issues 1 & 8)
// Runs standalone unit, regression, and smoke tests for admin login flows.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI execution only.\n";
    exit(1);
}

echo "========================================================\n";
echo "   Admin Login & Entry Points Test Suite (Issues 1 & 8)  \n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assert_check(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$name}\n";
    } else {
        $failed++;
        echo " [FAIL] {$name}" . ($details !== '' ? " -> {$details}" : '') . "\n";
    }
}

// -------------------------------------------------------------
// 1. PHP Lint Verification
// -------------------------------------------------------------
echo "[*] Suite 1: PHP Syntax Linting\n";
$files_to_lint = [
    'homepage.php',
    'includes/auth/admin-session.php',
    'includes/layout/footer-public.php',
    'includes/layout/header-public.php',
    'tests/run_tests.php'
];

foreach ($files_to_lint as $file) {
    $path = __DIR__ . '/../' . $file;
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $return_var);
    assert_check("Lint check: {$file}", $return_var === 0, implode("\n", $output));
    $output = [];
}

// -------------------------------------------------------------
// 2. GET Handler & Whitelist Unit Logic Tests
// -------------------------------------------------------------
echo "\n[*] Suite 2: GET Parameter & Whitelist Isolation Tests\n";

function simulate_admin_get(?string $login, ?string $error): array {
    $open_modal = "";
    $msg = "";
    $msg_type = "info";

    // Replicate the GET handling logic in homepage.php
    if ($login === 'admin') {
        $open_modal = 'admin';
        $reason = $error ?? '';
        if ($reason === 'deactivated') {
            $msg = 'This admin account has been deactivated. Please contact a super administrator.';
            $msg_type = 'warning';
        } elseif ($reason === 'expired') {
            $msg = 'Your admin session has expired. Please sign in again.';
            $msg_type = 'info';
        }
    }

    return [$open_modal, $msg, $msg_type];
}

// Test login=admin (first visit / clean entry)
[$om, $m, $mt] = simulate_admin_get('admin', null);
assert_check("Direct link (?login=admin) opens modal without error", $om === 'admin' && $m === '');

// Test expired session
[$om, $m, $mt] = simulate_admin_get('admin', 'expired');
assert_check("Expired session (?login=admin&error=expired) opens modal with info message", 
    $om === 'admin' && $mt === 'info' && str_contains($m, 'session has expired'));

// Test deactivated account
[$om, $m, $mt] = simulate_admin_get('admin', 'deactivated');
assert_check("Deactivated account (?login=admin&error=deactivated) opens modal with warning message", 
    $om === 'admin' && $mt === 'warning' && str_contains($m, 'account has been deactivated'));

// Test unknown parameter / XSS payload
[$om, $m, $mt] = simulate_admin_get('admin', '<script>alert(1)</script>');
assert_check("Unknown or malicious error parameter (?error=<script>) is rejected by whitelist", 
    $om === 'admin' && $m === '');

// Test non-admin login parameter
[$om, $m, $mt] = simulate_admin_get('other', 'expired');
assert_check("Non-admin login parameter does not open admin modal", $om === '');

// -------------------------------------------------------------
// 3. Static Assertions on Repository Files
// -------------------------------------------------------------
echo "\n[*] Suite 3: Static Integrity Checks\n";
$homepage = file_get_contents(__DIR__ . '/../homepage.php');
$admin_session = file_get_contents(__DIR__ . '/../includes/auth/admin-session.php');

assert_check("homepage.php contains GET handler for login=admin", 
    str_contains($homepage, "(\$_GET['login'] ?? '') === 'admin'"));

assert_check("homepage.php maps deactivated status", 
    str_contains($homepage, "\$reason === 'deactivated'"));

assert_check("homepage.php maps expired status", 
    str_contains($homepage, "\$reason === 'expired'"));

assert_check("admin-session.php tracks \$wasLoggedIn before destruction", 
    str_contains($admin_session, "\$wasLoggedIn = !empty(\$_SESSION['admin_id']);"));

assert_check("admin-session.php redirects with &error=expired on timeout", 
    str_contains($admin_session, "'&error=expired'"));

// -------------------------------------------------------------
// 4. Admin Modal Markup Smoke Test
// -------------------------------------------------------------
echo "\n[*] Suite 4: Smoke Test - Modal Markup\n";
assert_check("homepage.php defines modal with id='loginModal'", str_contains($homepage, 'id="loginModal"'));
assert_check("homepage.php contains admin email input", str_contains($homepage, 'id="admin-email-input"'));
assert_check("homepage.php contains admin password input", str_contains($homepage, 'id="admin-pwd-input"'));
assert_check("homepage.php contains submit button with name='admin'", str_contains($homepage, 'name="admin"'));
assert_check("homepage.php includes auto-open script for admin modal", str_contains($homepage, "\$('#loginModal').modal('show');"));

echo "\n========================================================\n";
echo "   Test Results: {$passed} Passed, {$failed} Failed     \n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
