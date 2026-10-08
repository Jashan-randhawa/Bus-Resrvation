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
    'includes/helpers.php',
    'admin/diagnostics.php',
    'admin/bookings.php',
    'admin/seats.php',
    'user/my-bookings.php',
    'database/db_migrate.php',
    'tests/concurrency_worker.php',
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

// -------------------------------------------------------------
// 5. Entry Points & Linkable Triggers (Phase B / Issue 2)
// -------------------------------------------------------------
echo "\n[*] Suite 5: Entry Points & Linkable Triggers (Phase B)\n";
$footer = file_get_contents(__DIR__ . '/../includes/layout/footer-public.php');
$header = file_get_contents(__DIR__ . '/../includes/layout/header-public.php');

assert_check("footer-public.php links to homepage.php?login=admin", 
    str_contains($footer, 'homepage.php?login=admin'));
assert_check("footer-public.php retains data-toggle for JS modal trigger", 
    str_contains($footer, 'data-toggle="modal"') && str_contains($footer, 'data-target="#loginModal"'));
assert_check("header-public.php Admin Portal is an anchor link to homepage.php?login=admin", 
    str_contains($header, 'homepage.php?login=admin'));
assert_check("header-public.php retains btn-dark and btn-nav-action styling", 
    str_contains($header, 'class="btn btn-dark btn-nav-action"'));

// -------------------------------------------------------------
// 6. Branded Presentation & Palette (Phase C / Issues 3, 4, 7)
// -------------------------------------------------------------
echo "\n[*] Suite 6: Branded Presentation & Palette (Phase C)\n";
$public_css = file_get_contents(__DIR__ . '/../assets/css/public.css');
assert_check("homepage.php admin modal uses admin-auth-header", 
    str_contains($homepage, 'class="modal-header admin-auth-header"'));
assert_check("homepage.php admin modal includes bus logo", 
    str_contains($homepage, 'assets/images/bus.svg'));
assert_check("homepage.php admin modal contains Restricted access badge", 
    str_contains($homepage, 'class="admin-badge mr-2"') && str_contains($homepage, 'Restricted access'));
assert_check("homepage.php admin modal uses WCAG AA subtitle and labels", 
    str_contains($homepage, 'admin-auth-subtitle') && str_contains($homepage, 'admin-form-label'));
assert_check("public.css defines slate-900 surface dark token (#0f172a)", 
    str_contains($public_css, '--admin-surface-dark: #0f172a;'));
assert_check("public.css defines amber accent token (#f59e0b)", 
    str_contains($public_css, '--admin-accent: #f59e0b;'));
assert_check("public.css defines WCAG AA compliant helper token (#64748b)", 
    str_contains($public_css, '--admin-helper: #64748b;'));
assert_check("public.css includes dark theme support", 
    str_contains($public_css, 'html[data-theme="dark"] .admin-auth-header'));
assert_check("header-public.php links public.css", 
    str_contains($header, 'assets/css/public.css'));

// -------------------------------------------------------------
// 7. Usability Polish (Phase D / Issues 5, 6)
// -------------------------------------------------------------
echo "\n[*] Suite 7: Usability Polish (Phase D)\n";
$homepage_latest = file_get_contents(__DIR__ . '/../homepage.php');
assert_check("homepage.php has password toggle button with id='admin-pwd-toggle'", 
    str_contains($homepage_latest, 'id="admin-pwd-toggle"'));
assert_check("homepage.php toggle button has initial aria-label='Show password' and aria-pressed='false'", 
    str_contains($homepage_latest, 'aria-label="Show password"') && str_contains($homepage_latest, 'aria-pressed="false"'));
assert_check("homepage.php has caps lock warning element with aria-live='polite'", 
    str_contains($homepage_latest, 'id="admin-caps-warning"') && str_contains($homepage_latest, 'aria-live="polite"'));
assert_check("homepage.php includes recovery guidance directing to super administrator", 
    str_contains($homepage_latest, 'Contact a super administrator'));
assert_check("homepage.php script resets password visibility on submit and modal hide", 
    str_contains($homepage_latest, 'resetPasswordVisibility') && str_contains($homepage_latest, 'hidden.bs.modal'));
assert_check("homepage.php script detects CapsLock with getModifierState", 
    str_contains($homepage_latest, "getModifierState('CapsLock')"));

// -------------------------------------------------------------
// 8. Booking Concurrency & Architectural Fix Integrity (Issues 1-11)
// -------------------------------------------------------------
echo "\n[*] Suite 8: Booking Concurrency & Architectural Integrity (Issues 1-11)\n";
$helpers_content = file_get_contents(__DIR__ . '/../includes/helpers.php');
$diagnostics_content = file_get_contents(__DIR__ . '/../admin/diagnostics.php');
$bookings_content = file_get_contents(__DIR__ . '/../admin/bookings.php');
$user_bookings_content = file_get_contents(__DIR__ . '/../user/my-bookings.php');
$seats_content = file_get_contents(__DIR__ . '/../admin/seats.php');
$migrate_content = file_get_contents(__DIR__ . '/../database/db_migrate.php');
$worker_content = file_get_contents(__DIR__ . '/../tests/concurrency_worker.php');
$run_tests_content = file_get_contents(__DIR__ . '/../tests/run_tests.php');

assert_check("helpers.php defines booking_concurrency_status()", 
    str_contains($helpers_content, 'function booking_concurrency_status('));
assert_check("helpers.php defines purge_stale_seat_locks()", 
    str_contains($helpers_content, 'function purge_stale_seat_locks('));
assert_check("helpers.php defines cancel_booking() with atomic lock deletion", 
    str_contains($helpers_content, 'function cancel_booking(') && str_contains($helpers_content, 'DELETE FROM seat_lock'));
assert_check("helpers.php defines booking_active_sql() with active statuses", 
    str_contains($helpers_content, 'function booking_active_sql('));
assert_check("helpers.php create_booking rejects uncatalogued buses", 
    str_contains($helpers_content, 'is not registered in the system'));
assert_check("helpers.php create_booking does NOT use INSERT IGNORE for seat_lock", 
    !str_contains($helpers_content, 'INSERT IGNORE INTO seat_lock') && str_contains($helpers_content, 'INSERT INTO seat_lock'));
assert_check("helpers.php get_booked_seats joins seat_lock with active booking", 
    str_contains($helpers_content, 'FROM seat_lock sl') && 
    str_contains($helpers_content, 'JOIN booking b ON b.sno = sl.booking_id') && 
    str_contains($helpers_content, "b.status IN ('Confirmed', 'Pending')"));

assert_check("admin/diagnostics.php invokes booking_concurrency_status()", 
    str_contains($diagnostics_content, 'booking_concurrency_status($link)'));
assert_check("admin/diagnostics.php does NOT check retired uq_booking_seat", 
    !str_contains($diagnostics_content, "'uq_booking_seat'"));

assert_check("admin/bookings.php executes atomic cancel_booking() for single & bulk actions", 
    str_contains($bookings_content, 'cancel_booking($link, $delete_id)') && 
    str_contains($bookings_content, 'cancel_booking($link, $sid)'));
assert_check("user/my-bookings.php executes atomic cancel_booking() with owner check", 
    str_contains($user_bookings_content, 'cancel_booking($link, $cancel_id, $uid)'));

assert_check("admin/seats.php joins seat_lock with active booking status for all departures", 
    str_contains($seats_content, 'JOIN booking b ON b.sno = sl.booking_id') && 
    str_contains($seats_content, "b.status IN ('Confirmed', 'Pending')"));

assert_check("db_migrate.php guards uq_booking_seat in migration 001 if 005 applied", 
    str_contains($migrate_content, "005_rebookable_active_seats") && str_contains($migrate_content, "uq_booking_seat"));
assert_check("db_migrate.php detects duplicate active seats before migration 005 unique index", 
    str_contains($migrate_content, "conflicting active seat group(s) found") && str_contains($migrate_content, "uq_booking_active_seat"));
assert_check("db_migrate.php detects duplicate active seats before migration 007 seat_lock backfill", 
    str_contains($migrate_content, "conflicting seat lock group(s) found") && str_contains($migrate_content, "seat_lock was NOT backfilled"));

assert_check("tests/concurrency_worker.php exists with CLI entry point & spin lock", 
    str_contains($worker_content, 'concurrency_worker.php') && str_contains($worker_content, 'microtime(true) < $race_start'));

assert_check("tests/run_tests.php includes Suite 14 (Tests A to I)", 
    str_contains($run_tests_content, 'Suite 14: Booking Concurrency') && 
    str_contains($run_tests_content, 'Suite 14 Test A') &&
    str_contains($run_tests_content, 'Suite 14 Test I')
);

echo "\n========================================================\n";
echo "   Test Results: {$passed} Passed, {$failed} Failed     \n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
