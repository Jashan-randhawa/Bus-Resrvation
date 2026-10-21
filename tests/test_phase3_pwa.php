<?php
/**
 * tests/test_phase3_pwa.php
 *
 * Automated verification suite for Phase 3: Path-Aware PWA Head Partial
 * Validates:
 * 1. PHP syntax of assets/partials/_pwa-head.php
 * 2. Dynamic directory depth resolution ($root_prefix = '../' vs './')
 * 3. Scoped Service Worker registration ({ scope: rootPrefix })
 * 4. Mobile seat grid and table horizontal scrolling media queries
 * 5. Print media protection to keep pnr.php tickets clean
 * 6. Dynamic DOM injection of #pwa-install-banner in document.body
 * 7. Browser installability event handlers (beforeinstallprompt, appinstalled)
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function report_phase3(string $title, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "✅ [PASS] {$title}\n";
        $passed++;
    } else {
        echo "❌ [FAIL] {$title}" . ($detail ? " - {$detail}" : '') . "\n";
        $failed++;
    }
}

echo "=== PHASE 3 PWA HEADER VERIFICATION CHECKLIST ===\n";

$head_path = $root . '/assets/partials/_pwa-head.php';
$head_exists = file_exists($head_path);
report_phase3("File Existence: assets/partials/_pwa-head.php exists", $head_exists);

if (!$head_exists) {
    exit(1);
}

// 1. PHP Syntax Check
exec("php -l " . escapeshellarg($head_path) . " 2>&1", $lint_out, $lint_code);
report_phase3("PHP Syntax: _pwa-head.php has zero syntax errors", $lint_code === 0);

$content = file_get_contents($head_path);

// 2. Dynamic Path Resolution
$has_script_check = str_contains($content, 'SCRIPT_NAME') && str_contains($content, '/admin/') && str_contains($content, '/user/');
$has_root_prefix  = str_contains($content, '$root_prefix = $is_admin_route ? \'../\' : \'./\';') ||
                    (str_contains($content, '$is_admin_route') && str_contains($content, '$root_prefix'));
report_phase3("Path Resolution: Dynamically detects /admin/ and /user/ vs root and sets \$root_prefix", $has_script_check && $has_root_prefix);

// 2b. Runtime Path Resolution Execution Check
$_SERVER['SCRIPT_NAME'] = '/user/index.php';
ob_start();
include $head_path;
ob_end_clean();
$user_resolves_up = isset($root_prefix) && $root_prefix === '../';

$_SERVER['SCRIPT_NAME'] = '/index.php';
ob_start();
include $head_path;
ob_end_clean();
$root_resolves_here = isset($root_prefix) && $root_prefix === './';

$_SERVER['SCRIPT_NAME'] = '/admin/edit/edit-route.php';
ob_start();
include $head_path;
ob_end_clean();
$edit_resolves_two_up = isset($root_prefix) && $root_prefix === '../../';

report_phase3("Runtime Path Depth: /user/ resolves to '../', root resolves to './', and /admin/edit/ resolves to '../../'", $user_resolves_up && $root_resolves_here && $edit_resolves_two_up);

// 3. Root Service Worker Scope
$has_sw_scope = str_contains($content, 'scope: rootPrefix') && str_contains($content, 'serviceWorker.register');
report_phase3("SW Scope: Registers sw.js with explicit { scope: rootPrefix }", $has_sw_scope);

// 4. 38-Seat Grid & Table Overflow Mobile Scrolling
$has_seat_grid_scroll = str_contains($content, '#seat-results') &&
                        str_contains($content, 'overflow-x: auto') &&
                        str_contains($content, 'max-width: 768px');
report_phase3("Seat & Table Scrolling: Media query enables horizontal scrolling on narrow screens", $has_seat_grid_scroll);

// 5. Clean Ticket Printing Protection
$has_print_guard = str_contains($content, '@media print') &&
                   str_contains($content, '#pwa-install-banner') &&
                   str_contains($content, 'display: none !important');
report_phase3("Ticket Printing: @media print hides #pwa-install-banner during window.print()", $has_print_guard);

// 6. DOM Injection of Install Button
$has_dom_injection = str_contains($content, 'ensureInstallButton') &&
                     str_contains($content, 'createElement(\'button\')') &&
                     str_contains($content, 'document.body.appendChild');
report_phase3("DOM Injection: Install button is safely appended to document.body, not raw in <head>", $has_dom_injection);

// 7. Install Prompt Event Handlers
$has_prompt_events = str_contains($content, 'beforeinstallprompt') &&
                     str_contains($content, 'appinstalled') &&
                     str_contains($content, 'deferredInstallPrompt');
report_phase3("Install Events: Listens for beforeinstallprompt and appinstalled cleanly", $has_prompt_events);

echo "=================================================\n";
echo "Phase 3 Total Passed: {$passed}, Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
