<?php
/**
 * tests/test_modern_footer.php - Automated Test Suite for Modernized Footer & PWA Navigation
 */

$passed = 0;
$failed = 0;

function assert_footer(string $label, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$label}\n";
    } else {
        $failed++;
        echo " [FAIL] {$label}\n";
    }
}

echo "========================================================\n";
echo "   Modern Footer & PWA Navigation Test Suite           \n";
echo "========================================================\n\n";

$base_dir = dirname(__DIR__);

// Suite 1: File Existence & Syntax Linting
echo "[*] Suite 1: File Existence & Linting\n";
$footer_php = $base_dir . '/includes/footer.php';
$bottom_nav_php = $base_dir . '/includes/bottom-nav.php';
$admin_footer_php = $base_dir . '/admin/includes/footer.php';
$footer_css = $base_dir . '/assets/css/footer.css';

assert_footer("includes/footer.php exists", file_exists($footer_php));
assert_footer("includes/bottom-nav.php exists", file_exists($bottom_nav_php));
assert_footer("admin/includes/footer.php exists", file_exists($admin_footer_php));
assert_footer("assets/css/footer.css exists", file_exists($footer_css));

// Suite 2: Modular 4-Column Web Footer Content
echo "\n[*] Suite 2: Modular 4-Column Web Footer (includes/footer.php)\n";
$footer_content = file_get_contents($footer_php);

assert_footer("Wraps content in .website-only-footer", str_contains($footer_content, 'class="website-only-footer'));
assert_footer("Contains .footer-grid container", str_contains($footer_content, 'class="footer-grid"'));
assert_footer("Column 1 contains brand logo and tagline", 
    str_contains($footer_content, 'assets/images/bus-emblem.png') && str_contains($footer_content, 'footer-tagline'));
assert_footer("Column 1 contains #installPwaBtn with .pwa-install-btn", 
    str_contains($footer_content, 'id="installPwaBtn"') && str_contains($footer_content, 'pwa-install-btn'));
assert_footer("Column 2 contains Quick Links with session awareness", 
    str_contains($footer_content, 'homepage.php#search') && str_contains($footer_content, '$is_logged_in'));
assert_footer("Column 2 contains Admin Portal link with modal triggers", 
    str_contains($footer_content, 'homepage.php?login=admin') && str_contains($footer_content, 'data-target="#loginModal"'));
assert_footer("Column 3 contains top route corridors", 
    str_contains($footer_content, 'Delhi') && str_contains($footer_content, 'Mumbai') && str_contains($footer_content, 'Bengaluru'));
assert_footer("Column 4 contains 24/7 Helpline and support email", 
    str_contains($footer_content, '1800') && str_contains($footer_content, 'support@busbook.transit'));
assert_footer("Column 4 contains trust & security badges", 
    str_contains($footer_content, '256-Bit TLS') && str_contains($footer_content, 'Atomic Locks') && str_contains($footer_content, 'Verified PNR'));
assert_footer("Sub-footer contains dynamic copyright year", 
    str_contains($footer_content, "date('Y')"));
assert_footer("Requires includes/bottom-nav.php partial", 
    str_contains($footer_content, "require_once __DIR__ . '/bottom-nav.php'"));

// Suite 3: Standalone PWA Bottom Navigation (includes/bottom-nav.php)
echo "\n[*] Suite 3: PWA Standalone Bottom Navigation (includes/bottom-nav.php)\n";
$bottom_nav_content = file_get_contents($bottom_nav_php);

assert_footer("Wraps in .pwa-bottom-nav navigation element", str_contains($bottom_nav_content, 'class="pwa-bottom-nav"'));
assert_footer("Tab 1: Home link exists", str_contains($bottom_nav_content, 'homepage.php#home'));
assert_footer("Tab 2: Search link exists", str_contains($bottom_nav_content, 'homepage.php#search'));
assert_footer("Tab 3: My Tickets / Track PNR with session condition", 
    str_contains($bottom_nav_content, 'user/my-bookings.php') && str_contains($bottom_nav_content, 'homepage.php#pnr'));
assert_footer("Tab 4: Profile / Sign In with session condition", 
    str_contains($bottom_nav_content, 'user/profile.php') && str_contains($bottom_nav_content, 'data-target="#userLoginModal"'));
assert_footer("Calculates active tab dynamically based on current script", 
    str_contains($bottom_nav_content, '$active_tab'));

// Suite 4: Minimalist Admin Utility Bar (admin/includes/footer.php)
echo "\n[*] Suite 4: Minimalist Admin Utility Bar (admin/includes/footer.php)\n";
$admin_footer_content = file_get_contents($admin_footer_php);

assert_footer("Wraps in .admin-utility-footer element", str_contains($admin_footer_content, 'class="admin-utility-footer'));
assert_footer("Displays BusBook release version badge", str_contains($admin_footer_content, 'BusBook v2.4.0'));
assert_footer("Displays System Operational status indicator", str_contains($admin_footer_content, 'System Operational'));
assert_footer("Displays database engine and TLS indicator", str_contains($admin_footer_content, 'TiDB Serverless / MySQL 8.0 (TLS)'));
assert_footer("Displays dynamic copyright year", str_contains($admin_footer_content, "date('Y')"));

$layout_admin_footer = file_get_contents($base_dir . '/includes/layout/footer-admin.php');
assert_footer("footer-admin.php includes admin/includes/footer.php utility bar", 
    str_contains($layout_admin_footer, "admin/includes/footer.php"));

// Suite 5: Scoped Stylesheet & Media Queries (assets/css/footer.css)
echo "\n[*] Suite 5: Stylesheet & PWA Media Queries (assets/css/footer.css)\n";
$css_content = file_get_contents($footer_css);

assert_footer("Defines dark-slate tokens (#0f172a / #1e293b)", 
    str_contains($css_content, '#0f172a') && str_contains($css_content, '#1e293b'));
assert_footer("Defines 4-column .footer-grid desktop rule", 
    str_contains($css_content, '.footer-grid') && str_contains($css_content, 'grid-template-columns: 2fr 1.2fr 1.3fr 1.5fr'));
assert_footer("Hides web footer and shows bottom nav in standalone PWA mode", 
    str_contains($css_content, '@media all and (display-mode: standalone)') && 
    str_contains($css_content, '.website-only-footer') && 
    str_contains($css_content, 'display: none !important') &&
    str_contains($css_content, '.pwa-bottom-nav') &&
    str_contains($css_content, 'display: flex !important'));
assert_footer("Provides 768px single-column mobile stacking", 
    str_contains($css_content, '@media (max-width: 768px)') && str_contains($css_content, 'grid-template-columns: 1fr'));
assert_footer("Provides safe-area-inset-bottom padding for modern phones", 
    str_contains($css_content, 'env(safe-area-inset-bottom'));
assert_footer("Hides footer elements during print", 
    str_contains($css_content, '@media print') && str_contains($css_content, 'display: none !important'));
assert_footer("Hides bottom navigation bar in mobile view (<= 768px)", 
    str_contains($css_content, '@media (max-width: 768px)') && str_contains($css_content, '.pwa-bottom-nav') && str_contains($css_content, 'display: none !important'));
assert_footer("Does not force bottom-nav on <= 576px screens", 
    !str_contains($css_content, '@media (max-width: 576px)'));

$header_public = file_get_contents($base_dir . '/includes/layout/header-public.php');
assert_footer("header-public.php links assets/css/footer.css", str_contains($header_public, 'assets/css/footer.css'));

echo "\n========================================================\n";
echo "   Modern Footer Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
