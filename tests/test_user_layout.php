<?php
/**
 * tests/test_user_layout.php
 * Automated Static Layout & Mobile Responsiveness Test Suite for User Portal
 */

$total_assertions = 0;
$failed_assertions = 0;

function assert_user_layout(string $description, bool $condition): void {
    global $total_assertions, $failed_assertions;
    $total_assertions++;
    if ($condition) {
        echo " [PASS] {$description}\n";
    } else {
        echo " [FAIL] {$description}\n";
        $failed_assertions++;
    }
}

echo "========================================================\n";
echo "   User Portal Layout & Mobile Responsiveness Tests    \n";
echo "========================================================\n\n";

// -------------------------------------------------------------
// Suite 1: Shell HTML Balance & Viewport (F1, F3)
// -------------------------------------------------------------
echo "[*] Suite 1: Shell HTML Balance & Viewport\n";

$header_user = file_get_contents(__DIR__ . '/../includes/layout/header-user.php');
$footer_user = file_get_contents(__DIR__ . '/../includes/layout/footer-user.php');
$user_css = file_get_contents(__DIR__ . '/../assets/css/user.css');

assert_user_layout("header-user.php contains balanced HTML comment delimiters",
    substr_count($header_user, '<!--') === substr_count($header_user, '-->')
);

assert_user_layout("footer-user.php contains balanced HTML comment delimiters",
    substr_count($footer_user, '<!--') === substr_count($footer_user, '-->')
);

assert_user_layout("header-user.php specifies viewport-fit=cover for notched safe areas (F3)",
    str_contains($header_user, 'viewport-fit=cover')
);

assert_user_layout("header-user.php includes drawer close button (#sidebarCloseBtn) (F1)",
    str_contains($header_user, 'id="sidebarCloseBtn"')
);

// -------------------------------------------------------------
// Suite 2: Controller & Script Integration (F1, F14)
// -------------------------------------------------------------
echo "\n[*] Suite 2: Controller & Script Integration\n";

assert_user_layout("footer-user.php loads admin-mobile.js controller exactly once (F1)",
    substr_count($footer_user, 'admin-mobile.js') === 1
);

assert_user_layout("footer-user.php does NOT contain duplicate inline sidebar toggle script",
    !str_contains($footer_user, "sidebar.classList.toggle('show'")
);

assert_user_layout("header-user.php or footer-user.php loads user-idle.js externally (F14)",
    str_contains($header_user, 'user-idle.js') || str_contains($footer_user, 'user-idle.js')
);

// -------------------------------------------------------------
// Suite 3: Stylesheet Loading & Performance (F1, F12)
// -------------------------------------------------------------
echo "\n[*] Suite 3: Stylesheet Loading & Performance\n";

assert_user_layout("header-user.php links admin.css directly instead of blocking @import (F12)",
    str_contains($header_user, 'assets/css/admin.css')
);

assert_user_layout("user.css does NOT contain blocking @import url('admin.css') (F12)",
    !str_contains($user_css, '@import')
);

assert_user_layout("header-user.php links admin-mobile-responsive.css (F1)",
    str_contains($header_user, 'assets/css/admin-mobile-responsive.css')
);

// -------------------------------------------------------------
// Suite 4: Topbar Semantics & 44px Touch Targets (F9)
// -------------------------------------------------------------
echo "\n[*] Suite 4: Topbar Semantics & Touch Targets\n";

assert_user_layout("header-user.php uses admin-menu-btn with aria attributes for topbar toggle",
    str_contains($header_user, 'admin-menu-btn') &&
    str_contains($header_user, 'id="sidebarToggle"') &&
    str_contains($header_user, 'aria-controls="adminSidebar"')
);

assert_user_layout("header-user.php topbar uses .page-title-mobile for clean responsive title",
    str_contains($header_user, 'page-title-mobile')
);

assert_user_layout("header-user.php topbar uses .theme-toggle-btn 44px touch target",
    str_contains($header_user, 'class="theme-toggle-btn')
);

// -------------------------------------------------------------
// Suite 5: Phase 2 Mobile Search & Card Responsiveness (F6, F10)
// -------------------------------------------------------------
echo "\n[*] Suite 5: Mobile Search & Card Responsiveness\n";

$user_index = file_get_contents(__DIR__ . '/../user/index.php');

assert_user_layout("user/index.php contains circular mobile swap button (#swap-cities-btn-mobile) (F6)",
    str_contains($user_index, 'id="swap-cities-btn-mobile"') &&
    str_contains($user_index, 'swap-cities-btn-circle')
);

assert_user_layout("user/index.php upcoming journey card uses .upcoming-journey-card and responsive flex",
    str_contains($user_index, 'upcoming-journey-card') &&
    str_contains($user_index, 'flex-md-row')
);

assert_user_layout("user/index.php mobile result cards use .search-result-card with departure & tariff hierarchy",
    str_contains($user_index, 'search-result-card') &&
    str_contains($user_index, 'per seat')
);

assert_user_layout("user.css offsets search-results anchor for sticky topbar",
    str_contains($user_css, 'scroll-margin-top') &&
    str_contains($user_css, '#search-results')
);

assert_user_layout("user.css prevents iOS Safari input zoom with 16px font-size below 768px (F10)",
    str_contains($user_css, '16px !important')
);

assert_user_layout("user.css defines 44px min-touch target for circular swap button",
    str_contains($user_css, '.swap-cities-btn-circle') &&
    str_contains($user_css, 'width: 44px')
);

// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo "\n========================================================\n";
echo "   User Layout Results: " . ($total_assertions - $failed_assertions) . " Passed, {$failed_assertions} Failed\n";
echo "========================================================\n";

if ($failed_assertions > 0) {
    exit(1);
}
