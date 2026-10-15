<?php
/**
 * tests/test_phase5_pwa.php
 *
 * Automated verification suite for Phase 5: HTTPS Cloud Deployment & App Launch
 * Validates:
 * 1. Root Database Dump existence (sbtbsphp.sql)
 * 2. Dual-Environment Database configuration ready in _functions.php (Tier 1 & Tier 2)
 * 3. Shared Hosting Template existence (_db-local.example.php)
 * 4. Git Security: _db-local.php and .env protected in .gitignore
 * 5. All PWA static assets and manifests ready for HTTPS deployment
 * 6. Live Audit verification script existence (tests/live_audit.sh)
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function report_phase5(string $title, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "✅ [PASS] {$title}\n";
        $passed++;
    } else {
        echo "❌ [FAIL] {$title}" . ($detail ? " - {$detail}" : '') . "\n";
        $failed++;
    }
}

echo "=== PHASE 5 DEPLOYMENT READINESS AUDIT ===\n";

// 1. Root Database Dump
$sql_path = $root . '/sbtbsphp.sql';
$sql_ready = file_exists($sql_path) && filesize($sql_path) > 1000 && str_contains(file_get_contents($sql_path), 'CREATE TABLE');
report_phase5("Database Schema: sbtbsphp.sql exists in root and contains schema definitions", $sql_ready);

// 2. Dual-Environment DB Configuration (Track A & Track B)
$func_path = $root . '/assets/partials/_functions.php';
$func_content = file_exists($func_path) ? file_get_contents($func_path) : '';
$track_a_ready = str_contains($func_content, '_db-local.php');
$track_b_ready = str_contains($func_content, "getenv('DB_HOST')") && str_contains($func_content, "getenv('DB_PORT')");
report_phase5("Track A & B Readiness: _functions.php supports both Cloud Env Vars and _db-local.php", $track_a_ready && $track_b_ready);

// 3. Shared Hosting Template
$example_path = $root . '/assets/partials/_db-local.example.php';
$example_ready = file_exists($example_path) && str_contains(file_get_contents($example_path), 'LOCAL_DB_HOST');
report_phase5("Track A Template: _db-local.example.php exists with credentials template", $example_ready);

// 4. Git Security Protection
$gitignore_path = $root . '/.gitignore';
$gitignore_content = file_exists($gitignore_path) ? file_get_contents($gitignore_path) : '';
$git_secure = str_contains($gitignore_content, '_db-local.php') && str_contains($gitignore_content, '.env');
report_phase5("Secret Protection: _db-local.php and .env are protected in .gitignore", $git_secure);

// 5. PWA Manifest & Service Worker Deployable Files
$pwa_files = [
    'manifest.json',
    'sw.js',
    'offline.html',
    'assets/img/app-icon.svg',
    'assets/partials/_pwa-head.php'
];
$all_pwa_ready = true;
foreach ($pwa_files as $f) {
    if (!file_exists($root . '/' . $f)) {
        $all_pwa_ready = false;
        break;
    }
}
report_phase5("PWA Core Files: manifest.json, sw.js, offline.html, app-icon.svg, and _pwa-head.php present", $all_pwa_ready);

// 6. Live Audit Script
$audit_path = $root . '/tests/live_audit.sh';
report_phase5("Live Audit Script: tests/live_audit.sh ready for production verification", file_exists($audit_path));

echo "==========================================\n";
echo "Phase 5 Total Passed: {$passed}, Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
