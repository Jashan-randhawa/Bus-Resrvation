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
// Suite 6: Phase 3 Booking & Seat Map Responsiveness (F2, F4, F5)
// -------------------------------------------------------------
echo "\n[*] Suite 6: Booking & Seat Map Responsiveness\n";

$user_booking = file_get_contents(__DIR__ . '/../user/booking.php');
$seat_map_css = file_get_contents(__DIR__ . '/../assets/css/seat-map.css');
$design_system_css = file_get_contents(__DIR__ . '/../assets/css/design-system.css');

assert_user_layout("user/booking.php contact phone input specifies inputmode='tel'",
    str_contains($user_booking, 'id="num"') &&
    str_contains($user_booking, 'inputmode="tel"')
);

assert_user_layout("user/booking.php contains .booking-sticky-footer with submit button (F5)",
    str_contains($user_booking, 'booking-sticky-footer') &&
    str_contains($user_booking, 'id="submit-booking-btn"')
);

assert_user_layout("user.css styles .booking-sticky-footer with sticky bottom positioning (F5)",
    str_contains($user_css, '.booking-sticky-footer') &&
    str_contains($user_css, 'position: sticky') &&
    str_contains($user_css, 'safe-area-inset-bottom')
);

assert_user_layout("seat-map.css computes fluid seat size via clamp to fit 320px screens (F2)",
    str_contains($seat_map_css, '--seat-w: clamp(') &&
    str_contains($seat_map_css, '--aisle-w: clamp(')
);

assert_user_layout("design-system.css eliminates dead conflicting 480px seat rules (42x54px) (F4)",
    !str_contains($design_system_css, 'height: 54px;') &&
    !str_contains($design_system_css, 'width: 42px;')
);

// -------------------------------------------------------------
// Suite 7: Phase 4 My Reservations Responsiveness (F7)
// -------------------------------------------------------------
echo "\n[*] Suite 7: My Reservations Tabs, Cards & Pagination\n";

$my_bookings = file_get_contents(__DIR__ . '/../user/my-bookings.php');

assert_user_layout("user/my-bookings.php wraps category tabs in .booking-tabs-wrap and .booking-tabs-nav (F7)",
    str_contains($my_bookings, 'booking-tabs-wrap') &&
    str_contains($my_bookings, 'booking-tabs-nav')
);

assert_user_layout("user/my-bookings.php category tabs use clean text without emojis (F7)",
    !str_contains($my_bookings, '⏳ Upcoming') &&
    !str_contains($my_bookings, '✅ Past') &&
    !str_contains($my_bookings, '❌ Cancelled') &&
    str_contains($my_bookings, 'Upcoming <span class="badge')
);

assert_user_layout("user/my-bookings.php provides compact mobile pagination for <768px",
    str_contains($my_bookings, 'd-flex d-md-none justify-content-between align-items-center') &&
    str_contains($my_bookings, 'Page <?= $page ?> of <?= $total_pages ?>')
);

assert_user_layout("user/my-bookings.php card action buttons provide 44px min-height touch targets",
    str_contains($my_bookings, 'booking-card-actions') &&
    str_contains($my_bookings, 'style="min-height: 44px;"')
);

assert_user_layout("user.css formats .booking-tabs-nav with 3-column equal grid under 576px (F7)",
    str_contains($user_css, '.booking-tabs-nav') &&
    str_contains($user_css, 'grid-template-columns: repeat(3, 1fr)')
);

// -------------------------------------------------------------
// Suite 8: Phase 5 Ticket & Verify Responsiveness (F8, Issue 5)
// -------------------------------------------------------------
echo "\n[*] Suite 8: Ticket & Verify View Mobile Responsiveness\n";

$ticket_php = file_get_contents(__DIR__ . '/../user/ticket.php');
$verify_php = file_get_contents(__DIR__ . '/../user/verify.php');

assert_user_layout("user/ticket.php ticket container uses fluid padding p-3 p-md-5",
    str_contains($ticket_php, 'card border ticket-container shadow-sm p-3 p-md-5')
);

assert_user_layout("user/ticket.php provides Web Share API integration (#shareTicketBtn)",
    str_contains($ticket_php, 'id="shareTicketBtn"') &&
    str_contains($ticket_php, 'navigator.share')
);

assert_user_layout("user/ticket.php QR code wrapper uses responsive max-width without fixed 160px min-width",
    str_contains($ticket_php, 'max-width: 220px') &&
    !str_contains($ticket_php, 'style="min-width: 160px;"')
);

assert_user_layout("user/ticket.php actions provide 44px min-height touch targets",
    str_contains($ticket_php, 'style="min-height: 44px;"')
);

assert_user_layout("user/verify.php includes viewport-fit=cover in viewport meta tag",
    str_contains($verify_php, 'viewport-fit=cover')
);

assert_user_layout("user/verify.php return link provides 44px min-height touch target",
    str_contains($verify_php, 'min-height: 44px')
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


