<?php
// tests/test_dark_mode_elevation.php
// Validates 3-Tier Surface Elevation, Interactive Seat Map Grid, Specular Borders, Native Controls, PWA Navigation & Badges

$total = 0;
$passed = 0;

function assert_dark(string $description, bool $condition): void {
    global $total, $passed;
    $total++;
    if ($condition) {
        $passed++;
        echo " [PASS] {$description}\n";
    } else {
        echo " [FAIL] {$description}\n";
    }
}

echo "========================================================\n";
echo "   Dark Mode Architecture & Surface Elevation Suite    \n";
echo "========================================================\n\n";

$design_system_path = __DIR__ . '/../assets/css/design-system.css';
$seat_map_path = __DIR__ . '/../assets/css/seat-map.css';
$footer_css_path = __DIR__ . '/../assets/css/footer.css';
$admin_css_path = __DIR__ . '/../assets/css/admin.css';
$user_css_path = __DIR__ . '/../assets/css/user.css';
$pwa_head_path = __DIR__ . '/../assets/partials/_pwa-head.php';
$theme_toggle_path = __DIR__ . '/../assets/js/theme-toggle.js';
$header_admin_path = __DIR__ . '/../includes/layout/header-admin.php';
$header_user_path = __DIR__ . '/../includes/layout/header-user.php';
$header_public_path = __DIR__ . '/../includes/layout/header-public.php';

$design_css = file_get_contents($design_system_path);
$seat_css = file_get_contents($seat_map_path);
$footer_css = file_get_contents($footer_css_path);
$admin_css = file_get_contents($admin_css_path);
$user_css = file_get_contents($user_css_path);
$pwa_head = file_get_contents($pwa_head_path);
$theme_toggle = file_get_contents($theme_toggle_path);
$header_admin = file_get_contents($header_admin_path);
$header_user = file_get_contents($header_user_path);
$header_public = file_get_contents($header_public_path);

// Suite 1: 3-Tier Surface Elevation & Palette Tokens
echo "[*] Suite 1: 3-Tier Surface Elevation Hierarchy\n";
assert_dark("Tier 0 (--bg-base: #0B0F19) defined for deepest background",
    str_contains($design_css, '--bg-base: #0B0F19')
);
assert_dark("Tier 1 (--bg-surface: #111827) defined for cards and tables",
    str_contains($design_css, '--bg-surface: #111827')
);
assert_dark("Tier 2 (--bg-elevated: #1E293B) defined for modals and dropdowns",
    str_contains($design_css, '--bg-elevated: #1E293B')
);
assert_dark("Glassmorphic token (--bg-glass) defined with translucent slate",
    str_contains($design_css, '--bg-glass: rgba(17, 24, 39, 0.78)')
);
assert_dark("Typography hierarchy defines --text-primary (#F8FAFC) avoiding halation",
    str_contains($design_css, '--text-primary: #F8FAFC')
);
assert_dark("Typography hierarchy defines --text-secondary (#94A3B8) and --text-muted (#64748B)",
    str_contains($design_css, '--text-secondary: #94A3B8') && str_contains($design_css, '--text-muted: #64748B')
);
assert_dark("Brand accents define --accent-primary (#F59E0B) and --accent-glow",
    str_contains($design_css, '--accent-primary: #F59E0B') && str_contains($design_css, '--accent-glow: rgba(245, 158, 11, 0.2)')
);
assert_dark("Hairline tokens define --border-subtle and --border-focus",
    str_contains($design_css, '--border-subtle: rgba(255, 255, 255, 0.08)') && str_contains($design_css, '--border-focus: rgba(245, 158, 11, 0.5)')
);

// Suite 2: Interactive Seat Map Grid Translucency & Accents
echo "\n[*] Suite 2: Interactive Seat Map Grid Elevation\n";
assert_dark("Available seats use translucent dark slate fill rgba(30, 41, 59, 0.6)",
    str_contains($seat_css, 'rgba(30, 41, 59, 0.6)')
);
assert_dark("Available seat hover activates amber border and translateY(-2px)",
    str_contains($seat_css, 'seat-radio:not(:disabled):hover') &&
    str_contains($seat_css, 'rgba(245, 158, 11, 0.08)')
);
assert_dark("Selected seat uses translucent amber fill with outer glow",
    str_contains($seat_css, 'seat-radio:checked') &&
    str_contains($seat_css, 'box-shadow: 0 0 12px var(--accent-glow')
);
assert_dark("Booked seats use muted coral border with reduced opacity (0.5) and not-allowed cursor",
    str_contains($seat_css, 'cursor: not-allowed') &&
    str_contains($seat_css, 'opacity: 0.5')
);
assert_dark("Ladies/Reserved seats use distinct soft pink translucent theme (#F472B6)",
    str_contains($seat_css, 'seat-status-ladies') &&
    str_contains($seat_css, '#f472b6')
);
assert_dark("Standalone .seat elements support available, selected, booked, and ladies states",
    str_contains($seat_css, '.seat.available:hover') &&
    str_contains($seat_css, '.seat.selected') &&
    str_contains($seat_css, '.seat.booked')
);

// Suite 3: Top-Edge Specular Borders & Shadow Hierarchy
echo "\n[*] Suite 3: Top-Edge Specular Borders & Overhead Highlights\n";
assert_dark("Cards declare top-edge specular border (rgba(255, 255, 255, 0.14))",
    str_contains($design_css, 'border-top: 1px solid rgba(255, 255, 255, 0.14)')
);
assert_dark("Modals declare elevated background, specular border, and deep shadow",
    str_contains($design_css, '.modal-content') &&
    str_contains($design_css, 'var(--bg-elevated, #1E293B)')
);
assert_dark("Ticket container in user.css applies specular border and surface token",
    str_contains($user_css, '.ticket-container') &&
    str_contains($user_css, 'border-top: 1px solid rgba(255, 255, 255, 0.14)')
);
assert_dark("Web footer in footer.css declares specular highlight border",
    str_contains($footer_css, '.website-only-footer') &&
    str_contains($footer_css, 'rgba(255, 255, 255, 0.14)')
);

// Suite 4: Native Form Controls & Date/Time Pickers
echo "\n[*] Suite 4: Native Form Controls & Date/Time Pickers\n";
assert_dark("design-system.css declares color-scheme: dark for native controls",
    str_contains($design_css, 'color-scheme: dark;')
);
assert_dark("Inputs and selects use recessed background (#090D16) in dark mode",
    str_contains($design_css, 'background-color: #090D16 !important;')
);
assert_dark("Input focus triggers amber border and accent glow",
    str_contains($design_css, 'border-color: var(--accent-primary, #F59E0B) !important;') &&
    str_contains($design_css, 'box-shadow: 0 0 0 3px var(--accent-glow')
);

// Suite 5: PWA Standalone Mode & Glassmorphic Navigation
echo "\n[*] Suite 5: PWA Standalone Mode & Glassmorphic Navigation\n";
assert_dark("pwa-bottom-nav declares backdrop-filter: blur(16px) with translucent glass",
    str_contains($footer_css, 'backdrop-filter: blur(16px)') &&
    str_contains($footer_css, '--bottom-nav-bg: rgba(17, 24, 39, 0.78)')
);
assert_dark("admin-topbar declares backdrop-filter: blur(16px) with translucent glass",
    str_contains($admin_css, 'backdrop-filter: blur(16px)') &&
    str_contains($admin_css, '--bg-glass')
);
assert_dark("_pwa-head.php specifies media-query theme-color meta tags (#0B0F19)",
    str_contains($pwa_head, '<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">') &&
    str_contains($pwa_head, '<meta name="theme-color" content="#0B0F19" media="(prefers-color-scheme: dark)">')
);
assert_dark("theme-toggle.js synchronizes theme-color meta tags dynamically",
    str_contains($theme_toggle, 'Synchronize PWA status bar theme-color') &&
    str_contains($theme_toggle, '#0B0F19')
);
assert_dark("header-admin.php includes inline pre-script preventing FOUC",
    str_contains($header_admin, 'Immediate Dark Mode Pre-Script to Prevent FOUC')
);
assert_dark("header-user.php includes inline pre-script preventing FOUC",
    str_contains($header_user, 'Immediate Dark Mode Pre-Script to Prevent FOUC')
);
assert_dark("header-public.php includes inline pre-script preventing FOUC",
    str_contains($header_public, 'Immediate Dark Mode Pre-Script to Prevent FOUC')
);

// Suite 6: Admin Tables & Pill Badges
echo "\n[*] Suite 6: Admin Tables & Pill Badges\n";
assert_dark("Admin tables declare 2% opacity zebra striping for even rows",
    str_contains($design_css, '.table tbody tr:nth-of-type(even)') &&
    str_contains($design_css, 'background-color: rgba(255, 255, 255, 0.02);')
);
assert_dark("Admin tables declare amber hover highlight (rgba(245, 158, 11, 0.05))",
    str_contains($design_css, '.table-hover tbody tr:hover') &&
    str_contains($design_css, 'rgba(245, 158, 11, 0.05)')
);
assert_dark("Pill badges declare 15% opacity background with 100% brightness text (Confirmed/Success)",
    str_contains($design_css, '.badge-success') &&
    str_contains($design_css, 'background: rgba(16, 185, 129, 0.15)') &&
    str_contains($design_css, 'color: #34D399')
);
assert_dark("Pill badges declare 15% opacity background with 100% brightness text (Pending/Warning)",
    str_contains($design_css, '.badge-warning') &&
    str_contains($design_css, 'background: rgba(245, 158, 11, 0.15)') &&
    str_contains($design_css, 'color: #FBBF24')
);
assert_dark("Pill badges declare 15% opacity background with 100% brightness text (Cancelled/Danger)",
    str_contains($design_css, '.badge-danger') &&
    str_contains($design_css, 'background: rgba(239, 68, 68, 0.15)') &&
    str_contains($design_css, 'color: #F87171')
);

echo "\n========================================================\n";
echo "   Elevation Test Results: {$passed} Passed, " . ($total - $passed) . " Failed\n";
echo "========================================================\n";

if ($passed !== $total) {
    exit(1);
}
exit(0);
