<?php
/**
 * tests/test_phase4_pwa.php
 *
 * Automated verification suite for Phase 4: PHP View Injection & Git Push
 * Audits all 9 Customer and Admin views:
 * 1. Root customer views (index.php, pnr.php)
 * 2. Admin portal views (admin/dashboard.php, admin/booking.php, admin/bus.php,
 *    admin/route.php, admin/seat.php, admin/customer.php, admin/signup.php)
 * 3. Backend safety guard: confirms pure backend endpoints (_getJSON.php, _handleLogin.php)
 *    remain unmodified.
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function report_phase4(string $title, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "✅ [PASS] {$title}\n";
        $passed++;
    } else {
        echo "❌ [FAIL] {$title}" . ($detail ? " - {$detail}" : '') . "\n";
        $failed++;
    }
}

echo "=== PHASE 4 VERIFICATION CHECKLIST ===\n";

// 1. Root Customer Views
$root_views = ['index.php', 'pnr.php'];
foreach ($root_views as $f) {
    $path = $root . '/' . $f;
    $exists = file_exists($path);
    $has_pwa = $exists && str_contains(file_get_contents($path), 'assets/partials/_pwa-head.php');
    exec("php -l " . escapeshellarg($path) . " 2>&1", $lint_out, $lint_code);
    report_phase4("Root View: {$f} includes _pwa-head.php and has valid syntax", $exists && $has_pwa && $lint_code === 0);
}

// 2. Admin Views
$admin_views = [
    'admin/dashboard.php',
    'admin/booking.php',
    'admin/bus.php',
    'admin/route.php',
    'admin/seat.php',
    'admin/customer.php',
    'admin/signup.php'
];
foreach ($admin_views as $f) {
    $path = $root . '/' . $f;
    $exists = file_exists($path);
    $has_pwa = $exists && str_contains(file_get_contents($path), '../assets/partials/_pwa-head.php');
    exec("php -l " . escapeshellarg($path) . " 2>&1", $lint_out, $lint_code);
    report_phase4("Admin View: {$f} includes ../assets/partials/_pwa-head.php and has valid syntax", $exists && $has_pwa && $lint_code === 0);
}

// 3. Backend Safety Check
$backend_safe = true;
$backend_files = ['assets/partials/_getJSON.php', 'assets/partials/_handleLogin.php'];
foreach ($backend_files as $bf) {
    $bpath = $root . '/' . $bf;
    if (file_exists($bpath) && str_contains(file_get_contents($bpath), '_pwa-head.php')) {
        $backend_safe = false;
        break;
    }
}
report_phase4("Backend Safety: _getJSON.php and _handleLogin.php remain pure backend scripts", $backend_safe);

echo "======================================\n";
echo "Phase 4 Total Passed: {$passed}, Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
