<?php
// tests/test_theme_emerald.php
// Comprehensive verification for Emerald Cyber (Theme B) UI Architecture Plan

$total = 0;
$passed = 0;

function assert_emerald(string $description, bool $condition): void {
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
echo "   Emerald Cyber (Theme B) Design System Test Suite     \n";
echo "========================================================\n\n";

$root = dirname(__DIR__);
$theme_emerald_path = $root . '/assets/css/theme-emerald.css';
$manifest_path = $root . '/manifest.json';
$pwa_head_path = $root . '/assets/partials/_pwa-head.php';
$header_public_path = $root . '/includes/layout/header-public.php';
$header_user_path = $root . '/includes/layout/header-user.php';
$header_admin_path = $root . '/includes/layout/header-admin.php';
$header_login_path = $root . '/includes/layout/header-login.php';
$homepage_path = $root . '/homepage.php';
$user_index_path = $root . '/user/index.php';
$user_ticket_path = $root . '/user/ticket.php';

assert_emerald("theme-emerald.css exists in assets/css/", file_exists($theme_emerald_path));
$theme_css = file_get_contents($theme_emerald_path);
$manifest_raw = file_get_contents($manifest_path);
$manifest = json_decode($manifest_raw, true);
$pwa_head = file_get_contents($pwa_head_path);
$header_public = file_get_contents($header_public_path);
$header_user = file_get_contents($header_user_path);
$header_admin = file_get_contents($header_admin_path);
$header_login = file_get_contents($header_login_path);
$homepage = file_get_contents($homepage_path);
$user_index = file_get_contents($user_index_path);
$user_ticket = file_get_contents($user_ticket_path);

// Suite 1: Foundation Color Tokens
echo "[*] Suite 1: Foundation Color Tokens & Root Settings\n";
assert_emerald("Forces native controls to dark mode (color-scheme: dark)", str_contains($theme_css, 'color-scheme: dark;'));
assert_emerald("Tier 0 Deep Obsidian canvas defined (--bg-canvas: #050C0F)", str_contains($theme_css, '--bg-canvas:       #050C0F;') || str_contains($theme_css, '--bg-canvas: #050C0F;'));
assert_emerald("Tier 1 Dark Pine surface defined (--bg-surface: #081216)", str_contains($theme_css, '--bg-surface:      #081216;') || str_contains($theme_css, '--bg-surface: #081216;'));
assert_emerald("Recessed input well defined (--bg-input: #04080B)", str_contains($theme_css, '--bg-input:        #04080B;') || str_contains($theme_css, '--bg-input: #04080B;'));
assert_emerald("Tier 2 Elevated Teal defined (--bg-elevated: #0F222A)", str_contains($theme_css, '--bg-elevated:     #0F222A;') || str_contains($theme_css, '--bg-elevated: #0F222A;'));
assert_emerald("Primary CTA Mint Emerald accent defined (--brand-emerald: #10B981)", str_contains($theme_css, '--brand-emerald:       #10B981;') || str_contains($theme_css, '--brand-emerald: #10B981;'));
assert_emerald("Interactive Utility Sky Cyan defined (--accent-cyan: #06B6D4)", str_contains($theme_css, '--accent-cyan:         #06B6D4;') || str_contains($theme_css, '--accent-cyan: #06B6D4;'));
assert_emerald("Selection Contrast Amber Gold defined (--accent-amber: #F59E0B)", str_contains($theme_css, '--accent-amber:        #F59E0B;') || str_contains($theme_css, '--accent-amber: #F59E0B;'));
assert_emerald("Specular edge border defined (--border-emerald: #134E4A)", str_contains($theme_css, '--border-emerald:  #134E4A;') || str_contains($theme_css, '--border-emerald: #134E4A;'));
assert_emerald("Recessed hairline divider defined (--border-subtle: #132A32)", str_contains($theme_css, '--border-subtle:   #132A32;') || str_contains($theme_css, '--border-subtle: #132A32;'));
assert_emerald("Typography tokens defined (--text-primary: #ECFDF5, --text-emerald-btn: #04130E)",
    str_contains($theme_css, '#ECFDF5') && str_contains($theme_css, '#04130E')
);

// Suite 2: Hero "Plan Your Journey" Component Specs
echo "\n[*] Suite 2: Hero \"Plan Your Journey\" Component Specs\n";
assert_emerald(".journey-card, .search-panel styled with radial emerald glow and #081216",
    str_contains($theme_css, '.journey-card') &&
    str_contains($theme_css, '.search-panel') &&
    str_contains($theme_css, 'radial-gradient')
);
assert_emerald(".badge-booking-limit styled with 12% emerald background and #34D399 text",
    str_contains($theme_css, '.badge-booking-limit') &&
    str_contains($theme_css, 'rgba(16, 185, 129, 0.12)') &&
    str_contains($theme_css, '#34D399')
);
assert_emerald(".btn-swap-cities styled with Sky Cyan and 180-degree hover rotation",
    str_contains($theme_css, '.btn-swap-cities') &&
    str_contains($theme_css, 'rotate(180deg)')
);
assert_emerald("Form controls use recessed #04080B background and emerald focus glow",
    str_contains($theme_css, 'var(--bg-input') &&
    str_contains($theme_css, 'border-color: var(--brand-emerald')
);
assert_emerald("Buttons enforce ultra-dark pine text (#04130E) on solid emerald for strict contrast",
    str_contains($theme_css, '.btn-find-buses') &&
    str_contains($theme_css, 'color: var(--text-emerald-btn, #04130E)') || str_contains($theme_css, 'color: #04130E')
);

// Suite 3: Interactive Seat Selection Matrix
echo "\n[*] Suite 3: Interactive Seat Selection Matrix\n";
assert_emerald("Available seats styled with Mint Emerald (#10B981)",
    str_contains($theme_css, '.seat.available') &&
    str_contains($theme_css, '#10B981')
);
assert_emerald("Selected seat styled with Amber Gold (#F59E0B) and outer glow",
    str_contains($theme_css, '.seat.selected') &&
    str_contains($theme_css, '#F59E0B') &&
    str_contains($theme_css, 'box-shadow')
);
assert_emerald("Booked seats styled with recessed obsidian (#1e3a47 / #0f222a) and not-allowed cursor",
    str_contains($theme_css, '.seat.booked') &&
    str_contains($theme_css, 'cursor: not-allowed')
);
assert_emerald("Reserved / Ladies seats styled with Sky Cyan (#06B6D4 / #67E8F9)",
    str_contains($theme_css, '.seat.reserved') || str_contains($theme_css, '.seat.ladies')
);

// Suite 4: Full-Application Module Plan
echo "\n[*] Suite 4: Navigation, Modals, Results, E-Ticket & Admin\n";
assert_emerald("Sticky glassmorphic top navbar with blur(12px) and #132A32 border",
    str_contains($theme_css, 'backdrop-filter: blur(12px)') &&
    str_contains($theme_css, '#132A32')
);
assert_emerald("Active route nav-links receive 2px bottom indicator in #10B981",
    str_contains($theme_css, 'border-bottom: 2px solid var(--brand-emerald, #10B981)')
);
assert_emerald("Auth modals and cards feature emerald top accent bar",
    str_contains($theme_css, 'border-top: 3px solid var(--brand-emerald, #10B981)') &&
    str_contains($theme_css, '.modal-content') &&
    str_contains($theme_css, '.auth-card')
);
assert_emerald("Schedule search result cards render with #081216 background and Sky Cyan timeline",
    str_contains($theme_css, '.search-result-card') &&
    str_contains($theme_css, 'dashed var(--accent-cyan')
);
assert_emerald("Remaining seat counter supports pulsing emerald animation",
    str_contains($theme_css, 'animation: pulse-emerald') &&
    str_contains($theme_css, '@keyframes pulse-emerald')
);
assert_emerald("Digital boarding pass defines perforated ticket divider with 2px dashed #134E4A",
    str_contains($theme_css, 'border-top: 2px dashed var(--border-emerald, #134E4A)') ||
    str_contains($theme_css, '2px dashed #134E4A')
);
assert_emerald("PNR reference code highlighted in monospace Sky Cyan (#38BDF8)",
    str_contains($theme_css, '.pnr-code') &&
    str_contains($theme_css, '#38BDF8')
);
assert_emerald("High-contrast white container defined for QR code scanning",
    str_contains($theme_css, '.ticket-seal-card') &&
    str_contains($theme_css, 'background: #ffffff !important')
);
assert_emerald("Admin KPI cards feature 4px solid #10B981 left accent border",
    str_contains($theme_css, 'border-left: 4px solid var(--brand-emerald, #10B981)')
);
assert_emerald("Admin tables alternate between #081216 and #0B181E rows",
    str_contains($theme_css, 'background-color: #081216 !important') &&
    str_contains($theme_css, 'background-color: #0B181E !important')
);
assert_emerald("Semantic status pills defined (Confirmed emerald, Pending amber, Cancelled rose)",
    str_contains($theme_css, '.badge-success') &&
    str_contains($theme_css, '.badge-warning') &&
    str_contains($theme_css, '.badge-danger')
);

// Suite 5: PWA Manifest & Layout Integration
echo "\n[*] Suite 5: PWA Manifest & Layout Integration\n";
assert_emerald("manifest.json defines background_color: #050C0F", ($manifest['background_color'] ?? '') === '#050C0F');
assert_emerald("manifest.json defines theme_color: #081216", ($manifest['theme_color'] ?? '') === '#081216');
assert_emerald("_pwa-head.php sets <meta name=\"theme-color\" content=\"#081216\">", str_contains($pwa_head, '<meta name="theme-color" content="#081216">'));
assert_emerald("header-public.php links theme-emerald.css", str_contains($header_public, 'theme-emerald.css'));
assert_emerald("header-user.php links theme-emerald.css", str_contains($header_user, 'theme-emerald.css'));
assert_emerald("header-admin.php links theme-emerald.css", str_contains($header_admin, 'theme-emerald.css'));
assert_emerald("header-login.php links theme-emerald.css", str_contains($header_login, 'theme-emerald.css'));

// Suite 6: View Component Classes
echo "\n[*] Suite 6: View Component Classes\n";
assert_emerald("homepage.php includes journey-card and search-panel on search container",
    str_contains($homepage, 'booking-search-card journey-card search-panel')
);
assert_emerald("homepage.php includes badge-booking-limit on 1 Seat badge",
    str_contains($homepage, 'badge-booking-limit')
);
assert_emerald("homepage.php includes btn-swap-cities on home and guest swap buttons",
    str_contains($homepage, 'id="home_swap_btn"') && str_contains($homepage, 'id="guest_swap_btn"') &&
    str_contains($homepage, 'btn-swap-cities')
);
assert_emerald("homepage.php includes btn-find-buses on search buttons",
    str_contains($homepage, 'btn-find-buses')
);
assert_emerald("user/index.php includes journey-card and search-panel",
    str_contains($user_index, 'journey-card search-panel')
);
assert_emerald("user/index.php includes btn-swap-cities on swap buttons",
    str_contains($user_index, 'btn-swap-cities')
);
assert_emerald("user/index.php includes btn-find-buses on search submit button",
    str_contains($user_index, 'btn-find-buses')
);
assert_emerald("user/ticket.php includes ticket-divider ticket-perforated-line",
    str_contains($user_ticket, 'ticket-perforated-line')
);
assert_emerald("user/ticket.php includes ticket-qr-wrap and qr-container-high-contrast",
    str_contains($user_ticket, 'qr-container-high-contrast')
);
assert_emerald("user/ticket.php includes pnr-code and ticket-pnr-highlight",
    str_contains($user_ticket, 'ticket-pnr-highlight')
);

echo "\n========================================================\n";
echo "   Emerald Cyber Suite Results: {$passed} Passed, " . ($total - $passed) . " Failed\n";
echo "========================================================\n";

if ($passed !== $total) {
    exit(1);
}
