<?php
/**
 * tests/test_pwa.php
 *
 * Automated verification suite for PWA (Progressive Web App) capabilities:
 * - Web App Manifest validation (manifest.json)
 * - Service Worker validation (sw.js)
 * - PWA Vector Icon verification (assets/img/app-icon.svg)
 * - PWA Head Partial & Layout Integration (assets/partials/_pwa-head.php)
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function assert_test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $description\n";
        $passed++;
    } else {
        echo " [FAIL] $description\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "   Progressive Web App (PWA) Verification Suite         \n";
echo "========================================================\n\n";

// Suite 1: Web App Manifest
echo "[*] Suite 1: Web App Manifest (manifest.json)\n";
$manifest_path = $root . '/manifest.json';
assert_test("manifest.json exists", file_exists($manifest_path));

$manifest_raw = file_get_contents($manifest_path);
$manifest = json_decode($manifest_raw, true);
assert_test("manifest.json is valid JSON", json_last_error() === JSON_ERROR_NONE);
assert_test("manifest specifies name and short_name", !empty($manifest['name']) && !empty($manifest['short_name']));
assert_test("manifest specifies display: standalone", isset($manifest['display']) && $manifest['display'] === 'standalone');
assert_test("manifest specifies start_url", !empty($manifest['start_url']));
assert_test("manifest specifies theme_color and background_color", !empty($manifest['theme_color']) && !empty($manifest['background_color']));
assert_test("manifest defines icons array with maskable purpose", !empty($manifest['icons']) && is_array($manifest['icons']) && strpos(json_encode($manifest['icons']), 'maskable') !== false);

// Suite 2: Service Worker
echo "\n[*] Suite 2: Service Worker (sw.js)\n";
$sw_path = $root . '/sw.js';
assert_test("sw.js exists in repository root", file_exists($sw_path));

$sw_content = file_get_contents($sw_path);
assert_test("sw.js handles install event and pre-caches assets", strpos($sw_content, "addEventListener('install'") !== false && strpos($sw_content, "addAll") !== false);
assert_test("sw.js handles activate event and claims clients", strpos($sw_content, "addEventListener('activate'") !== false && strpos($sw_content, "clients.claim()") !== false);
assert_test("sw.js handles fetch event", strpos($sw_content, "addEventListener('fetch'") !== false);
assert_test("sw.js safely skips non-GET requests (protects bookings and mutations)", strpos($sw_content, "req.method !== 'GET'") !== false);
assert_test("sw.js implements navigation fallback for offline access", strpos($sw_content, "navigate") !== false);

// Suite 3: PWA Icon Assets
echo "\n[*] Suite 3: PWA Icon Assets\n";
$icon_path = $root . '/assets/img/app-icon.svg';
assert_test("assets/img/app-icon.svg exists", file_exists($icon_path));
$icon_content = file_get_contents($icon_path);
assert_test("assets/img/app-icon.svg is a valid SVG document", strpos($icon_content, '<svg') !== false && strpos($icon_content, '</svg>') !== false);

// Suite 4: PWA Head Partial & Integration
echo "\n[*] Suite 4: PWA Head Partial & Layout Integration\n";
$pwa_head_path = $root . '/assets/partials/_pwa-head.php';
assert_test("assets/partials/_pwa-head.php exists", file_exists($pwa_head_path));

$pwa_head_content = file_get_contents($pwa_head_path);
assert_test("_pwa-head.php links manifest.json", strpos($pwa_head_content, 'rel="manifest"') !== false);
assert_test("_pwa-head.php sets mobile-web-app-capable and apple-mobile-web-app-capable", strpos($pwa_head_content, 'apple-mobile-web-app-capable') !== false);
assert_test("_pwa-head.php links apple-touch-icon", strpos($pwa_head_content, 'rel="apple-touch-icon"') !== false);
assert_test("_pwa-head.php contains serviceWorker registration script", strpos($pwa_head_content, 'serviceWorker.register') !== false);

$header_public = file_get_contents($root . '/includes/layout/header-public.php');
assert_test("header-public.php includes _pwa-head.php", strpos($header_public, '_pwa-head.php') !== false);

$header_login = file_get_contents($root . '/includes/layout/header-login.php');
assert_test("header-login.php includes _pwa-head.php", strpos($header_login, '_pwa-head.php') !== false);

$header_user = file_get_contents($root . '/includes/layout/header-user.php');
assert_test("header-user.php includes _pwa-head.php", strpos($header_user, '_pwa-head.php') !== false);

$header_admin = file_get_contents($root . '/includes/layout/header-admin.php');
assert_test("header-admin.php includes _pwa-head.php", strpos($header_admin, '_pwa-head.php') !== false);

// Summary
echo "\n========================================================\n";
echo "   PWA Test Results: $passed Passed, $failed Failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
