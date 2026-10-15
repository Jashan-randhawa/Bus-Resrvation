<?php
/**
 * tests/test_phase1_db.php
 *
 * Phase 1 Database Connection Verification Checklist:
 * 1. PHP Syntax of assets/partials/_functions.php
 * 2. Tier 1 (Cloud Env Vars: DB_HOST, DB_PORT, DB_SSL)
 * 3. Tier 2 (_db-local.php hook)
 * 4. UTF-8mb4 Charset
 * 5. Template File (assets/partials/_db-local.example.php)
 * 6. .gitignore Security Protection
 */

$root = dirname(__DIR__);
$functions_file = $root . '/assets/partials/_functions.php';
$example_file   = $root . '/assets/partials/_db-local.example.php';
$gitignore_file = $root . '/.gitignore';

echo "=== PHASE 1 VERIFICATION CHECKLIST ===\n";

// 1. Check PHP Syntax of _functions.php
exec("php -l " . escapeshellarg($functions_file) . " 2>&1", $lint_output, $lint_code);
if ($lint_code === 0) {
    echo "✅ [PASS] PHP Syntax: assets/partials/_functions.php has zero syntax errors.\n";
} else {
    echo "❌ [FAIL] PHP Syntax: Syntax error found in assets/partials/_functions.php!\n";
    echo implode("\n", $lint_output) . "\n";
}

// 2. Check Tier 1 (Environment Variables + Custom Port + SSL)
$funcContent = file_exists($functions_file) ? file_get_contents($functions_file) : '';
if (strpos($funcContent, "getenv('DB_HOST')") !== false && strpos($funcContent, "getenv('DB_PORT')") !== false) {
    echo "✅ [PASS] Tier 1 (Cloud Env Vars & Port): DB_HOST and DB_PORT support detected.\n";
} else {
    echo "❌ [FAIL] Tier 1: Missing getenv('DB_HOST') or getenv('DB_PORT') in _functions.php.\n";
}

// 3. Check Tier 2 (_db-local.php fallback hook)
if (strpos($funcContent, "_db-local.php") !== false) {
    echo "✅ [PASS] Tier 2 (Shared Hosting Hook): _db-local.php check is present in _functions.php.\n";
} else {
    echo "❌ [FAIL] Tier 2: Missing _db-local.php loader inside _functions.php.\n";
}

// 4. Check UTF-8mb4 Charset
if (strpos($funcContent, "utf8mb4") !== false) {
    echo "✅ [PASS] Charset: utf8mb4 encoding is configured.\n";
} else {
    echo "❌ [FAIL] Charset: Missing mysqli_set_charset(\$conn, 'utf8mb4').\n";
}

// 5. Check Template File (_db-local.example.php)
if (file_exists($example_file)) {
    echo "✅ [PASS] Template File: assets/partials/_db-local.example.php exists.\n";
} else {
    echo "❌ [FAIL] Template File: assets/partials/_db-local.example.php is missing.\n";
}

// 6. Check .gitignore Protection
$gitIgnoreContent = file_exists($gitignore_file) ? file_get_contents($gitignore_file) : '';
if (strpos($gitIgnoreContent, "_db-local.php") !== false) {
    echo "✅ [PASS] Git Security: assets/partials/_db-local.php is protected in .gitignore.\n";
} else {
    echo "❌ [FAIL] Git Security: _db-local.php is NOT listed in .gitignore!\n";
}

echo "======================================\n";
