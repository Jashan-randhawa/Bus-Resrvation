<?php
// tests/test_light_theme_fidelity.php
// Verifies complete preservation and isolation of the light theme

$total = 0;
$passed = 0;

function assert_light(string $description, bool $condition): void {
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
echo "   Light Theme Integrity & Scoping Test Suite           \n";
echo "========================================================\n\n";

$root = dirname(__DIR__);
$design_css = file_get_contents($root . '/assets/css/design-system.css');
$home_css = file_get_contents($root . '/assets/css/home.css');
$theme_emerald = file_get_contents($root . '/assets/css/theme-emerald.css');
$pwa_head = file_get_contents($root . '/assets/partials/_pwa-head.php');
$theme_toggle = file_get_contents($root . '/assets/js/theme-toggle.js');

// Suite 1: Light Theme Tokens in design-system.css
echo "[*] Suite 1: Baseline Light Theme Design Tokens\n";
assert_light("design-system.css defines default light canvas background (--c-bg: #f8fafc)",
    str_contains($design_css, '--c-bg:            #f8fafc')
);
assert_light("design-system.css defines default light surface (--c-surface: #ffffff)",
    str_contains($design_css, '--c-surface:       #ffffff')
);
assert_light("design-system.css defines default royal blue primary CTA (--c-primary: #2563eb)",
    str_contains($design_css, '--c-primary:       #2563eb')
);
assert_light("design-system.css defines default dark slate text (--c-text: #1e293b)",
    str_contains($design_css, '--c-text:          #1e293b')
);
assert_light("design-system.css defines default light border (--c-border: #e2e8f0)",
    str_contains($design_css, '--c-border:        #e2e8f0')
);
assert_light("design-system.css defines white light theme sidebar (--sidebar-bg: #ffffff)",
    str_contains($design_css, '--sidebar-bg: #ffffff')
);

// Suite 2: Homepage Light Theme Tokens & Component Styles
echo "\n[*] Suite 2: Homepage Light Theme Scoped Tokens & Components\n";
assert_light("home.css defines default light background (--home-bg-warm: #f8fafc)",
    str_contains($home_css, '--home-bg-warm:    #f8fafc')
);
assert_light("home.css defines default white surface (--home-bg-white: #ffffff)",
    str_contains($home_css, '--home-bg-white:   #ffffff')
);
assert_light("home.css defines default rich blue primary (--home-primary: #1d4ed8)",
    str_contains($home_css, '--home-primary:    #1d4ed8')
);
assert_light("home.css booking-search-card uses white surface background",
    str_contains($home_css, '.booking-search-card {') &&
    str_contains($home_css, 'background: var(--home-bg-white);')
);
assert_light("home.css navbar-public uses translucent white background in light mode",
    str_contains($home_css, '.navbar-public {') &&
    str_contains($home_css, 'rgba(255, 255, 255, 0.95)')
);

// Suite 3: theme-emerald.css Strict Dark Mode Scoping
echo "\n[*] Suite 3: theme-emerald.css Isolation & Non-Pollution of Light Theme\n";
// Ensure theme-emerald.css does NOT contain un-scoped global selectors that would corrupt light mode
assert_light("color-scheme: dark is scoped to dark mode attributes (not bare :root)",
    !preg_match('/^:root\s*\{\s*color-scheme:\s*dark;/m', $theme_emerald)
);
assert_light("Search hero card is scoped to dark mode (does not match in light mode)",
    !preg_match('/^\.journey-card,\s*\n\s*\.search-panel/m', $theme_emerald)
);
assert_light("Form inputs and selects are scoped to dark mode (do not match in light mode)",
    !preg_match('/^select,\s*\n\s*input\[type="date"\]/m', $theme_emerald)
);
assert_light("Primary buttons (.btn-primary) are scoped to dark mode (do not force emerald in light mode)",
    !preg_match('/^\.btn-find-buses,\s*\n\s*\.btn-primary/m', $theme_emerald)
);
assert_light("Seat map cards and drawer are scoped to dark mode",
    !preg_match('/^\.seat-map-drawer,\s*\n\s*\.bus-map-card\s*\{/m', $theme_emerald)
);
assert_light("Navbars and topbars are scoped to dark mode (do not force dark glass in light mode)",
    !preg_match('/^\.navbar-public,\s*\n\s*\.navbar-dark/m', $theme_emerald)
);
assert_light("Modals and auth cards are scoped to dark mode (do not force dark background in light mode)",
    !preg_match('/^\.modal-content,\s*\n\s*\.auth-card\s*\{/m', $theme_emerald)
);
assert_light("Ticket container is scoped to dark mode (preserves white boarding pass in light mode)",
    !preg_match('/^\.ticket-container\s*\{/m', $theme_emerald)
);
assert_light("Admin and user tables are scoped to dark mode (preserves white table rows in light mode)",
    !preg_match('/^\.table tbody tr\s*\{/m', $theme_emerald)
);

// Suite 4: PWA Status Bar & Dynamic Sync for Light Mode
echo "\n[*] Suite 4: PWA Status Bar & Dynamic Toggle for Light Mode\n";
assert_light("_pwa-head.php sets #ffffff theme-color for light color scheme",
    str_contains($pwa_head, '<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">')
);
assert_light("theme-toggle.js sets #ffffff status bar when switching to light mode",
    str_contains($theme_toggle, "'#ffffff'")
);

echo "\n========================================================\n";
echo "   Light Theme Results: {$passed} Passed, " . ($total - $passed) . " Failed\n";
echo "========================================================\n";

if ($passed !== $total) {
    exit(1);
}
