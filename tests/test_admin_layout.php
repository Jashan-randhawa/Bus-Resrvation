<?php
/**
 * tests/test_admin_layout.php
 * Automated Static Layout & Mobile Responsiveness Test Suite (Option A Drawer)
 */

$total_assertions = 0;
$failed_assertions = 0;

function assert_layout(string $description, bool $condition): void {
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
echo "   Admin Layout & Mobile Responsiveness Test Suite     \n";
echo "========================================================\n\n";

// -------------------------------------------------------------
// Suite 1: Layout HTML Balance & Critical Shell Integrity (F1, F2)
// -------------------------------------------------------------
echo "[*] Suite 1: Layout HTML Balance & Shell Integrity\n";

$header_admin = file_get_contents(__DIR__ . '/../includes/layout/header-admin.php');
$footer_admin = file_get_contents(__DIR__ . '/../includes/layout/footer-admin.php');

assert_layout("header-admin.php contains balanced HTML comment delimiters",
    substr_count($header_admin, '<!--') === substr_count($header_admin, '-->')
);

assert_layout("footer-admin.php contains balanced HTML comment delimiters (no swallowed scripts)",
    substr_count($footer_admin, '<!--') === substr_count($footer_admin, '-->')
);

assert_layout("footer-admin.php loads admin-mobile.js exactly once",
    substr_count($footer_admin, '<script src="<?= BASE_URL ?>/assets/js/admin-mobile.js') === 1
);

assert_layout("header-admin.php specifies viewport-fit=cover for safe-area insets",
    str_contains($header_admin, 'viewport-fit=cover')
);

assert_layout("header-admin.php includes mobile drawer close button (#sidebarCloseBtn)",
    str_contains($header_admin, 'id="sidebarCloseBtn"')
);

assert_layout("header-admin.php does not hardcode PHP 15-char substring on mobile title",
    !str_contains($header_admin, "substr(\$title, 0, 15)")
);

// -------------------------------------------------------------
// Suite 2: Mobile Navigation Controller Verification (F3, F4)
// -------------------------------------------------------------
echo "\n[*] Suite 2: Authoritative admin-mobile.js Controller\n";

$admin_mobile_js = file_get_contents(__DIR__ . '/../assets/js/admin-mobile.js');

assert_layout("admin-mobile.js handles sidebar close button (#sidebarCloseBtn)",
    str_contains($admin_mobile_js, 'sidebarCloseBtn')
);

assert_layout("admin-mobile.js applies inert attribute to closed mobile drawer",
    str_contains($admin_mobile_js, "sidebar.setAttribute('inert', '')") &&
    str_contains($admin_mobile_js, "sidebar.removeAttribute('inert')")
);

assert_layout("admin-mobile.js sets inert on admin-main when mobile drawer is open",
    str_contains($admin_mobile_js, "adminMain.setAttribute('inert', '')") &&
    str_contains($admin_mobile_js, "adminMain.removeAttribute('inert')")
);

assert_layout("admin-mobile.js restores focus to toggle button on closing",
    str_contains($admin_mobile_js, 'toggle.focus()')
);

assert_layout("admin-mobile.js matches footer links using .admin-sidebar-footer selector",
    str_contains($admin_mobile_js, '.admin-sidebar-footer')
);

assert_layout("admin-mobile.js implements swipe-left gesture detection",
    str_contains($admin_mobile_js, 'touchstart') &&
    str_contains($admin_mobile_js, 'touchend') &&
    str_contains($admin_mobile_js, 'deltaX > 50')
);

assert_layout("admin-mobile.js closes drawer when Bootstrap modal opens",
    str_contains($admin_mobile_js, 'show.bs.modal')
);

assert_layout("admin-mobile.js guards against duplicate initialization",
    str_contains($admin_mobile_js, '__adminMobileNavInitialized')
);

// -------------------------------------------------------------
// Suite 3: Mobile Stylesheet Rules & Token Hygiene (F5, F6, F7, F8)
// -------------------------------------------------------------
echo "\n[*] Suite 3: Mobile Stylesheet Scoping & De-duplication\n";

$mobile_css = file_get_contents(__DIR__ . '/../assets/css/admin-mobile-responsive.css');
$admin_css = file_get_contents(__DIR__ . '/../assets/css/admin.css');

assert_layout("admin-mobile-responsive.css does NOT contain dead Bootstrap 5 .form-select selector",
    !str_contains($mobile_css, '.form-select')
);

assert_layout("admin-mobile-responsive.css exempts .form-control-sm from 44px height forcing",
    str_contains($mobile_css, ':not(.form-control-sm)')
);

assert_layout("admin-mobile-responsive.css does NOT contain duplicate hardcoded .table-stack card styles",
    !str_contains($mobile_css, '.table-stack tbody,') &&
    !str_contains($mobile_css, 'background: #ffffff;')
);

assert_layout("admin.css defines .table-stack with theme tokens and dark mode overrides",
    str_contains($admin_css, 'var(--c-surface') &&
    str_contains($admin_css, 'html[data-theme="dark"] .table-stack tr')
);

assert_layout("admin.css styles mobile sticky bulk action bar with safe-area padding",
    str_contains($admin_css, '#bulkBookingsForm:not(.d-none)') &&
    str_contains($admin_css, 'safe-area-inset-bottom')
);

$bookings_page = file_get_contents(__DIR__ . '/../admin/bookings.php');
assert_layout("admin/bookings.php specifies .bookings-table on reservation table",
    str_contains($bookings_page, 'class="table table-hover table-stack bookings-table mb-0"')
);

assert_layout("admin.css implements Option 2 Boarding Pass CSS Grid mobile layout",
    str_contains($admin_css, '.bookings-table tr') &&
    str_contains($admin_css, 'grid-template-areas:') &&
    str_contains($admin_css, '"select pnr status"') &&
    str_contains($admin_css, '"route route route"') &&
    str_contains($admin_css, '"passenger passenger seat"') &&
    str_contains($admin_css, 'border-bottom: 2px dashed')
);

assert_layout("admin.css defines dark mode overrides for .bookings-table",
    str_contains($admin_css, 'html[data-theme="dark"] .bookings-table tr')
);

assert_layout("admin.css groups PNR label and code with justify-content flex-start without gap blowout",
    str_contains($admin_css, '.bookings-table td:nth-child(2)') &&
    str_contains($admin_css, 'justify-content: flex-start !important;')
);

assert_layout("admin.css unboxes .data-table-wrapper on mobile with stand-alone table-header pill",
    str_contains($admin_css, '.data-table-wrapper {') &&
    str_contains($admin_css, 'background: transparent !important;') &&
    str_contains($admin_css, '.data-table-wrapper .table-header {')
);

assert_layout("admin.css provides 24px vertical separation between mobile ticket cards",
    str_contains($admin_css, '.bookings-table tr {') &&
    str_contains($admin_css, 'margin-bottom: 24px !important;')
);

assert_layout("admin.css defines 20px card spacing for generic .table-stack rows on mobile",
    str_contains($admin_css, '.table-stack tr {') &&
    str_contains($admin_css, 'margin-bottom: 20px !important;')
);

assert_layout("admin.css defines status-coded colored borders and left accents for mobile booking cards",
    str_contains($admin_css, '.bookings-table tr:has(.badge-success)') &&
    str_contains($admin_css, 'border-color: #10b981') &&
    str_contains($admin_css, 'border-left: 5px solid #10b981') &&
    str_contains($admin_css, 'border-color: #f59e0b') &&
    str_contains($admin_css, 'border-color: #ef4444')
);

assert_layout("admin/bookings.php outputs semantic booking-row status class on tr",
    str_contains($bookings_page, 'booking-row-') &&
    str_contains($bookings_page, 'data-status=')
);

$buses_page = file_get_contents(__DIR__ . '/../admin/buses.php');
assert_layout("admin/buses.php specifies .buses-table on fleet vehicles table",
    str_contains($buses_page, 'class="table table-hover table-stack buses-table mb-0"')
);

assert_layout("admin.css implements Option 2 Indian License Plate / Vehicle Badge mobile layout",
    str_contains($admin_css, '.buses-table tr') &&
    str_contains($admin_css, 'grid-template-areas:') &&
    str_contains($admin_css, '"id action"') &&
    str_contains($admin_css, '"plate plate"') &&
    str_contains($admin_css, '"capacity layout"') &&
    str_contains($admin_css, 'content: "🚌 Vehicle "') &&
    str_contains($admin_css, 'content: "IND"')
);

assert_layout("admin.css defines amber commercial colored borders and left accents for buses-table",
    str_contains($admin_css, '.buses-table tr') &&
    str_contains($admin_css, 'border-left: 5px solid #f59e0b') &&
    str_contains($admin_css, 'html[data-theme="dark"] .buses-table tr')
);

$routes_page = file_get_contents(__DIR__ . '/../admin/routes.php');
assert_layout("admin/routes.php outputs .route-card-mobile with .route-card-header and .route-card-actions",
    str_contains($routes_page, 'class="route-card-mobile') &&
    str_contains($routes_page, 'class="route-card-header"') &&
    str_contains($routes_page, 'class="route-card-actions')
);

assert_layout("admin.css defines emerald colored borders, 5px left accent, and 24px spacing for route-card-mobile",
    str_contains($admin_css, '.route-card-mobile {') &&
    str_contains($admin_css, 'border-left: 5px solid #10b981 !important;') &&
    str_contains($admin_css, 'margin-bottom: 24px !important;') &&
    str_contains($admin_css, 'html[data-theme="dark"] .route-card-mobile')
);

// -------------------------------------------------------------
// Suite 4: Table Cell Data-Label Consistency (F9)
// -------------------------------------------------------------
echo "\n[*] Suite 4: Table Data-Label Consistency\n";

$queries_content = file_get_contents(__DIR__ . '/../admin/queries.php');

assert_layout("admin/queries.php action column uses data-label='Actions' matching design system",
    str_contains($queries_content, 'data-label="Actions"')
);

// -------------------------------------------------------------
// Suite 5: SVG Sprite Icons & Navigation Semantics (Section 3.3)
// -------------------------------------------------------------
echo "\n[*] Suite 5: SVG Sprite Icons & Navigation Semantics\n";

$required_symbols = [
    'nav-dashboard', 'nav-ticket', 'nav-seat', 'nav-clipboard',
    'nav-bus', 'nav-route', 'nav-user-cog', 'nav-users',
    'nav-shield', 'nav-message', 'nav-activity', 'nav-list',
    'nav-menu', 'nav-close', 'nav-bell', 'nav-external-link'
];

$all_symbols_present = true;
foreach ($required_symbols as $sym) {
    if (!str_contains($header_admin, 'id="' . $sym . '"')) {
        $all_symbols_present = false;
        break;
    }
}
assert_layout("header-admin.php contains inline SVG sprite with all 16 required nav symbols",
    $all_symbols_present
);

assert_layout("header-admin.php replaces emoji with nav-* SVG symbol references",
    str_contains($header_admin, "'nav-dashboard'") &&
    str_contains($header_admin, "'nav-ticket'") &&
    str_contains($header_admin, "'nav-seat'") &&
    str_contains($header_admin, "'nav-clipboard'") &&
    str_contains($header_admin, "'nav-message'")
);

// -------------------------------------------------------------
// Suite 6: Modern Topbar Structure & Drawer Semantics (Section 3.1 & 3.2)
// -------------------------------------------------------------
echo "\n[*] Suite 6: Modern Topbar Structure & Drawer Semantics\n";

assert_layout("header-admin.php topbar includes hamburger menu button (.admin-menu-btn)",
    str_contains($header_admin, 'class="admin-menu-btn"') &&
    str_contains($header_admin, 'id="sidebarToggle"')
);

assert_layout("header-admin.php topbar includes two-line title block with eyebrow header",
    str_contains($header_admin, 'topbar-title-block') &&
    str_contains($header_admin, 'topbar-eyebrow')
);

assert_layout("header-admin.php topbar includes eyebrow pill with dot and dynamic section group slug",
    str_contains($header_admin, 'topbar-eyebrow') &&
    str_contains($header_admin, 'topbar-eyebrow-dot') &&
    str_contains($header_admin, 'group-pill-')
);

assert_layout("admin.css defines distinct eyebrow pill themes for all 5 section groups",
    str_contains($admin_css, '.topbar-eyebrow.group-pill-overview') &&
    str_contains($admin_css, '.topbar-eyebrow.group-pill-operations') &&
    str_contains($admin_css, '.topbar-eyebrow.group-pill-fleet-schedule') &&
    str_contains($admin_css, '.topbar-eyebrow.group-pill-people') &&
    str_contains($admin_css, '.topbar-eyebrow.group-pill-system')
);

assert_layout("header-admin.php topbar includes unread query bell button with badge support",
    str_contains($header_admin, 'topbar-bell-btn') &&
    str_contains($header_admin, 'topbar-bell-badge')
);

assert_layout("header-admin.php displays Bus Service brand title in mobile drawer",
    str_contains($header_admin, 'class="brand-title"')
);

assert_layout("header-admin.php applies viewer role distinct styling",
    str_contains($header_admin, "\$admin_role === 'viewer'")
);

// -------------------------------------------------------------
// Suite 7: Navigation Design Tokens & Motion (Section 4 & 3.3)
// -------------------------------------------------------------
echo "\n[*] Suite 7: Navigation Design Tokens & Motion\n";

assert_layout("admin.css defines authoritative navigation tokens in :root",
    str_contains($admin_css, '--nav-item-h: 48px;') &&
    str_contains($admin_css, '--nav-radius: 12px;') &&
    str_contains($admin_css, '--nav-active-bg:') &&
    str_contains($admin_css, '--topbar-h: 56px;')
);

assert_layout("admin.css applies single width rule min(86vw, 320px) to mobile drawer",
    str_contains($admin_css, 'width: min(86vw, 320px)')
);

assert_layout("admin.css gates hover effects inside @media (hover: hover)",
    str_contains($admin_css, '@media (hover: hover)') &&
    str_contains($admin_css, '.admin-nav .nav-link:hover')
);

assert_layout("admin.css honors prefers-reduced-motion for drawer and overlay transitions",
    str_contains($admin_css, '@media (prefers-reduced-motion: reduce)') &&
    str_contains($admin_css, 'transition: none !important;')
);

$design_system_css = file_get_contents(__DIR__ . '/../assets/css/design-system.css');
assert_layout("admin.css and design-system.css define global white light theme and dark theme for sidebar drawer",
    str_contains($admin_css, '--sidebar-bg: #ffffff;') &&
    str_contains($admin_css, '--sidebar-bg: #0f172a;') &&
    str_contains($design_system_css, '--sidebar-bg: #ffffff;') &&
    str_contains($design_system_css, '--sidebar-bg:            #0f172a;')
);

// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo "\n========================================================\n";
echo "   Layout Test Results: " . ($total_assertions - $failed_assertions) . " Passed, {$failed_assertions} Failed\n";
echo "========================================================\n";

if ($failed_assertions > 0) {
    exit(1);
}
