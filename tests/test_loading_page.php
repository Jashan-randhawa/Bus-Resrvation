<?php
/**
 * tests/test_loading_page.php
 *
 * Automated verification suite for the BusBook Loading Page & Preloader System:
 * 1. Standalone loading page (loading.html) structure, assets, and accessibility
 * 2. Reusable preloader CSS (assets/css/preloader.css)
 * 3. Preloader JS controller (assets/js/preloader.js)
 * 4. Path-aware preloader partial (assets/partials/_preloader.php)
 * 5. Layout integration across all portal headers
 * 6. Service worker caching registration
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function assert_loading(string $desc, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "✅ [PASS] {$desc}\n";
        $passed++;
    } else {
        echo "❌ [FAIL] {$desc}" . ($detail ? " ({$detail})" : '') . "\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "   BusBook Loading Page & Preloader Test Suite          \n";
echo "========================================================\n\n";

// Suite 1: Standalone loading.html
echo "[*] Suite 1: Standalone Loading Page (loading.html)\n";
$loading_path = $root . '/loading.html';
assert_loading("loading.html exists in repository root", file_exists($loading_path));

$loading_content = file_exists($loading_path) ? file_get_contents($loading_path) : '';
assert_loading("loading.html references BusBook logo (bus-emblem.png)", strpos($loading_content, 'assets/images/bus-emblem.png') !== false);
assert_loading("loading.html contains progress bar and shimmer", strpos($loading_content, 'id="progressBar"') !== false && strpos($loading_content, 'progress-shimmer') !== false);
assert_loading("loading.html contains dynamic status text", strpos($loading_content, 'id="statusText"') !== false);
assert_loading("loading.html contains percentage counter", strpos($loading_content, 'id="percentageText"') !== false);
assert_loading("loading.html provides interactive launch button", strpos($loading_content, 'id="launchBtn"') !== false);
assert_loading("loading.html includes accessibility semantics (role=status, aria-live)", strpos($loading_content, 'role="status"') !== false && strpos($loading_content, 'aria-live="polite"') !== false);
assert_loading("loading.html includes prefers-reduced-motion media query", strpos($loading_content, 'prefers-reduced-motion') !== false);
assert_loading("loading.html implements light/dark theme toggle", strpos($loading_content, 'data-theme') !== false && strpos($loading_content, 'themeToggleBtn') !== false);

// Suite 2: Preloader Stylesheet & Controller
echo "\n[*] Suite 2: Preloader Core Assets (CSS & JS)\n";
$css_path = $root . '/assets/css/preloader.css';
assert_loading("assets/css/preloader.css exists", file_exists($css_path));

$css_content = file_exists($css_path) ? file_get_contents($css_path) : '';
assert_loading("preloader.css defines #busbook-preloader with smooth fadeout transitions", strpos($css_content, '#busbook-preloader') !== false && strpos($css_content, '.preloader-hidden') !== false);
assert_loading("preloader.css defines glowing logo halo and progress bar animations", strpos($css_content, 'preloaderHalo') !== false && strpos($css_content, 'preloaderBarAnim') !== false);

$js_path = $root . '/assets/js/preloader.js';
assert_loading("assets/js/preloader.js exists", file_exists($js_path));

$js_content = file_exists($js_path) ? file_get_contents($js_path) : '';
assert_loading("preloader.js listens for window load event", strpos($js_content, "window.addEventListener('load'") !== false);
assert_loading("preloader.js implements fail-safe maximum timeout", strpos($js_content, 'MAX_SAFETY_MS') !== false);
assert_loading("preloader.js exports global window.BusBookLoader API", strpos($js_content, 'window.BusBookLoader') !== false && strpos($js_content, 'show:') !== false);

// Suite 3: Preloader Partial & Layout Integration
echo "\n[*] Suite 3: Preloader Partial & Layout Integration\n";
$partial_path = $root . '/assets/partials/_preloader.php';
assert_loading("assets/partials/_preloader.php exists", file_exists($partial_path));

$partial_content = file_exists($partial_path) ? file_get_contents($partial_path) : '';
assert_loading("_preloader.php resolves path depth for admin vs root", strpos($partial_content, '$is_admin_route_preloader') !== false);
assert_loading("_preloader.php detects /user/ portal route", strpos($partial_content, '/user/') !== false);

$_SERVER['SCRIPT_NAME'] = '/user/index.php';
ob_start();
include $partial_path;
ob_end_clean();
assert_loading("_preloader.php resolves preloader_prefix to '../' for /user/ routes", isset($preloader_prefix) && $preloader_prefix === '../');

$_SERVER['SCRIPT_NAME'] = '/index.php';
ob_start();
include $partial_path;
ob_end_clean();
assert_loading("_preloader.php resolves preloader_prefix to './' for root routes", isset($preloader_prefix) && $preloader_prefix === './');

assert_loading("_preloader.php embeds #busbook-preloader element", strpos($partial_content, 'id="busbook-preloader"') !== false);

$header_public = file_get_contents($root . '/includes/layout/header-public.php');
assert_loading("header-public.php includes _preloader.php", strpos($header_public, '_preloader.php') !== false);

$header_admin = file_get_contents($root . '/includes/layout/header-admin.php');
assert_loading("header-admin.php includes _preloader.php", strpos($header_admin, '_preloader.php') !== false);

$header_user = file_get_contents($root . '/includes/layout/header-user.php');
assert_loading("header-user.php includes _preloader.php", strpos($header_user, '_preloader.php') !== false);

$header_login = file_get_contents($root . '/includes/layout/header-login.php');
assert_loading("header-login.php includes _preloader.php", strpos($header_login, '_preloader.php') !== false);

// Suite 4: Offline & Service Worker Caching
echo "\n[*] Suite 4: Offline & Service Worker Caching\n";
$sw_content = file_get_contents($root . '/sw.js');
assert_loading("sw.js pre-caches loading.html", strpos($sw_content, './loading.html') !== false);
assert_loading("sw.js pre-caches preloader.css and preloader.js", strpos($sw_content, './assets/css/preloader.css') !== false && strpos($sw_content, './assets/js/preloader.js') !== false);

$offline_content = file_get_contents($root . '/offline.html');
assert_loading("offline.html uses BusBook brand emblem", strpos($offline_content, 'assets/images/bus-emblem.png') !== false);

// Summary
echo "\n========================================================\n";
echo "   Loading Suite Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
