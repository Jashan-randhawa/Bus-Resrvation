<?php
/**
 * tests/test_phase2_pwa.php
 *
 * Phase 2 Core PWA & Service Worker Verification Checklist:
 * 1. PWA Vector Icon (assets/img/app-icon.svg)
 * 2. Offline Fallback Page (offline.html)
 * 3. Web App Manifest (manifest.json)
 * 4. Service Worker Cache & Routing (sw.js)
 * 5. PWA Head Partial & Integration (_pwa-head.php in layout headers)
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function report_check(string $title, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "✅ [PASS] {$title}\n";
        $passed++;
    } else {
        echo "❌ [FAIL] {$title}" . ($detail ? " - {$detail}" : '') . "\n";
        $failed++;
    }
}

echo "=== PHASE 2 PWA VERIFICATION CHECKLIST ===\n";

// 1. App Icon
$icon_path = $root . '/assets/img/app-icon.svg';
$icon_exists = file_exists($icon_path);
$icon_valid = $icon_exists && str_contains(file_get_contents($icon_path), '<svg') && str_contains(file_get_contents($icon_path), 'viewBox="0 0 512 512"');
report_check("PWA Vector Icon: assets/img/app-icon.svg exists and is valid 512x512 SVG", $icon_valid);

// 2. Offline Fallback Page
$offline_path = $root . '/offline.html';
$offline_exists = file_exists($offline_path);
$offline_content = $offline_exists ? file_get_contents($offline_path) : '';
$offline_valid = $offline_exists && str_contains($offline_content, "You're Currently Offline") && str_contains($offline_content, "location.reload()");
report_check("Offline Fallback Page: offline.html exists with reconnect UI", $offline_valid);

// 3. Web App Manifest
$manifest_path = $root . '/manifest.json';
$manifest_raw = file_exists($manifest_path) ? file_get_contents($manifest_path) : '';
$manifest = json_decode($manifest_raw, true);
$manifest_valid = is_array($manifest)
    && ($manifest['id'] ?? '') === 'sbtbs-bus-reservation-app'
    && ($manifest['display'] ?? '') === 'standalone'
    && ($manifest['start_url'] ?? '') === './index.php'
    && count($manifest['shortcuts'] ?? []) >= 2;
report_check("Web App Manifest: manifest.json configured with standalone mode and dual shortcuts", $manifest_valid);

// 4. Service Worker
$sw_path = $root . '/sw.js';
$sw_content = file_exists($sw_path) ? file_get_contents($sw_path) : '';
$sw_valid = str_contains($sw_content, 'sbtbs-bus-cache-v1')
    && str_contains($sw_content, "addEventListener('install'")
    && str_contains($sw_content, "addEventListener('activate'")
    && str_contains($sw_content, "addEventListener('fetch'")
    && (str_contains($sw_content, "req.method !== 'GET'") || str_contains($sw_content, "request.method !== 'GET'"))
    && str_contains($sw_content, 'offline.html');
report_check("Service Worker: sw.js implements sbtbs-bus-cache-v1, non-GET bypass, and offline routing", $sw_valid);

// 5. Layout Head Integration
$head_path = $root . '/assets/partials/_pwa-head.php';
$head_content = file_exists($head_path) ? file_get_contents($head_path) : '';
$head_valid = str_contains($head_content, 'manifest.json') && str_contains($head_content, 'serviceWorker.register');

$headers = [
    'header-public.php' => $root . '/includes/layout/header-public.php',
    'header-login.php'  => $root . '/includes/layout/header-login.php',
    'header-user.php'   => $root . '/includes/layout/header-user.php',
    'header-admin.php'  => $root . '/includes/layout/header-admin.php',
];
$all_headers_included = true;
foreach ($headers as $name => $path) {
    if (!file_exists($path) || !str_contains(file_get_contents($path), '_pwa-head.php')) {
        $all_headers_included = false;
        break;
    }
}
report_check("PWA Layout Integration: _pwa-head.php registered across all 4 portal layout headers", $head_valid && $all_headers_included);

echo "==========================================\n";
echo "Total Passed: {$passed}, Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
