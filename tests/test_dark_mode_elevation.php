<?php
// tests/test_dark_mode_elevation.php
// Validates Complete Dark Mode Removal & Permanent Clean Light Mode Architecture

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
echo "   Dark Mode Removal & Light Mode Architecture Suite    \n";
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
$header_login_path = __DIR__ . '/../includes/layout/header-login.php';

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
$header_login = file_get_contents($header_login_path);

// Suite 1: Clean Light Surface Elevation & Palette Tokens
echo "[*] Suite 1: Clean Light Surface Elevation Hierarchy\n";
assert_dark("Tier 0 light canvas background (--c-bg: #f8fafc) defined",
    str_contains($design_css, '--c-bg:            #f8fafc')
);
assert_dark("Tier 1 light surface (--c-surface: #ffffff) defined for cards and tables",
    str_contains($design_css, '--c-surface:       #ffffff')
);
assert_dark("Clean text primary (--c-text: #1e293b) defined with high contrast",
    str_contains($design_css, '--c-text:          #1e293b')
);
assert_dark("Clean border token (--c-border: #e2e8f0) defined",
    str_contains($design_css, '--c-border:        #e2e8f0')
);
assert_dark("design-system.css declares color-scheme: light for native controls",
    str_contains($design_css, 'color-scheme: light;')
);
assert_dark("design-system.css does NOT declare dark tokens override",
    !str_contains($design_css, 'html[data-theme="dark"]') &&
    !str_contains($design_css, 'html.dark-mode')
);

// Suite 2: Interactive Seat Map Clean Light Fidelity
echo "\n[*] Suite 2: Interactive Seat Map Clean Light Fidelity\n";
assert_dark("seat-map.css does NOT contain Section 9 dark mode rules",
    !str_contains($seat_css, 'html[data-theme="dark"]') &&
    !str_contains($seat_css, '.dark-mode')
);
assert_dark("Seat styling provides high-contrast clean light mode presentation",
    str_contains($seat_css, '.bus-map-card') &&
    str_contains($seat_css, '.seat-label')
);

// Suite 3: Cards, Modals, and E-Tickets
echo "\n[*] Suite 3: Cards, Modals, and E-Tickets Clean Light Presentation\n";
assert_dark("admin.css contains NO html[data-theme='dark'] card overrides",
    !str_contains($admin_css, 'html[data-theme="dark"]')
);
assert_dark("user.css contains NO html[data-theme='dark'] ticket container overrides",
    !str_contains($user_css, 'html[data-theme="dark"]')
);
assert_dark("footer.css does NOT contain html:not([data-theme='dark']) block",
    !str_contains($footer_css, 'html:not([data-theme="dark"])')
);

// Suite 4: Native Form Controls & Date/Time Pickers
echo "\n[*] Suite 4: Native Form Controls & Date/Time Pickers\n";
assert_dark("design-system.css enforces light native control scheme",
    str_contains($design_css, 'color-scheme: light;') &&
    !str_contains($design_css, 'color-scheme: dark;')
);

// Suite 5: PWA Standalone Mode & Layout Head Partials
echo "\n[*] Suite 5: PWA Standalone Mode & Layout Head Partials\n";
assert_dark("_pwa-head.php sets clean light theme-color (#ffffff)",
    str_contains($pwa_head, '<meta name="theme-color" content="#ffffff">') &&
    !str_contains($pwa_head, '#0B0F19') &&
    !str_contains($pwa_head, '#081216')
);
assert_dark("header-public.php sets data-theme='light' on html",
    str_contains($header_public, '<html lang="en" data-theme="light">')
);
assert_dark("header-admin.php sets data-theme='light' on html",
    str_contains($header_admin, '<html lang="en" data-theme="light">')
);
assert_dark("header-user.php sets data-theme='light' on html",
    str_contains($header_user, '<html lang="en" data-theme="light">')
);
assert_dark("header-login.php sets data-theme='light' on html",
    str_contains($header_login, '<html lang="en" data-theme="light">')
);
assert_dark("header templates do not contain theme-toggle-btn",
    !str_contains($header_public, 'theme-toggle-btn') &&
    !str_contains($header_admin, 'theme-toggle-btn') &&
    !str_contains($header_user, 'theme-toggle-btn')
);
assert_dark("theme-toggle.js purges legacy dark mode storage and enforces light theme",
    str_contains($theme_toggle, 'busres_theme_mode') &&
    str_contains($theme_toggle, "setAttribute('data-theme', 'light')")
);

// Suite 6: Admin Tables & Pill Badges
echo "\n[*] Suite 6: Admin Tables & Clean Status Badges\n";
assert_dark("Admin bookings table uses clean light styling without dark overrides",
    !str_contains($admin_css, 'html[data-theme="dark"] .bookings-table')
);
assert_dark("Admin queries table uses clean light styling without dark overrides",
    !str_contains($admin_css, 'html[data-theme="dark"] .queries-table')
);
assert_dark("Admin customers table uses clean light styling without dark overrides",
    !str_contains($admin_css, 'html[data-theme="dark"] .customers-table')
);

echo "\n========================================================\n";
echo "   Results: {$passed} Passed, " . ($total - $passed) . " Failed\n";
echo "========================================================\n";

if ($passed !== $total) {
    exit(1);
}
