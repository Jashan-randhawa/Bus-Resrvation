<?php
/**
 * tests/test_pnr_section.php
 *
 * Automated verification suite for the Enhanced PNR & Ticket Verification Section:
 * 1. Accessibility semantics & section header
 * 2. Real-time dual-factor search terminal & pre-fill detection
 * 3. Client-side input masking, counter & auto-advance controller
 * 4. Option 2 Digital Boarding Pass Result Card (Header, Corridor, Perforated Notches, Stub, Actions)
 * 5. Interactive 1-Click Copy PNR with clipboard feedback
 * 6. Human-centered troubleshooting assistance cards
 * 7. Modern scoped styles & Dark Mode elevation in home.css
 * 8. Standalone entry point pnr.php parameter forwarding & PWA head integration
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function assert_pnr(string $title, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$title}\n";
        $passed++;
    } else {
        echo " [FAIL] {$title}" . ($detail ? " - {$detail}" : '') . "\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "   PNR & Ticket Verification Section Test Suite         \n";
echo "========================================================\n";

$homepage = file_get_contents($root . '/homepage.php');
$home_css = file_get_contents($root . '/assets/css/home.css');
$pnr_php = file_get_contents($root . '/pnr.php');

// Suite 1: Accessibility Semantics & Section Header
echo "\n[*] Suite 1: Accessibility Semantics & Section Header\n";
assert_pnr("homepage.php contains section #pnr with aria-labelledby='pnrHeading'",
    str_contains($homepage, '<section id="pnr"') && str_contains($homepage, 'aria-labelledby="pnrHeading"')
);
assert_pnr("homepage.php includes real-time ticket tracker eyebrow pill and pulsing dot",
    str_contains($homepage, 'class="pnr-eyebrow-pill') && str_contains($homepage, 'class="pnr-dot"')
);
assert_pnr("homepage.php includes privacy and verification trust badges strip",
    str_contains($homepage, 'class="pnr-trust-strip"') &&
    str_contains($homepage, 'Dual-Factor Privacy Protected') &&
    str_contains($homepage, 'Instant Live Status Check')
);

// Suite 2: Search Terminal & Dual-Factor Input Controls
echo "\n[*] Suite 2: Search Terminal & Dual-Factor Input Controls\n";
assert_pnr("homepage.php provides .pnr-search-box container with CSRF token",
    str_contains($homepage, 'class="pnr-search-box') && str_contains($homepage, 'csrf_field()')
);
assert_pnr("homepage.php provides pre-fill PNR detection banner for incoming links",
    str_contains($homepage, 'class="pnr-prefill-banner"') && str_contains($homepage, '$has_prefill_pnr')
);
assert_pnr("homepage.php defines 10-char hex PNR input with pattern and uppercase styling",
    str_contains($homepage, 'id="pnr_input"') &&
    str_contains($homepage, 'name="pnr"') &&
    str_contains($homepage, 'maxlength="10"') &&
    str_contains($homepage, 'pattern="[A-Fa-f0-9]{10}"')
);
assert_pnr("homepage.php defines 4-digit phone PIN numeric input",
    str_contains($homepage, 'id="phone4_input"') &&
    str_contains($homepage, 'name="phone4"') &&
    str_contains($homepage, 'maxlength="4"') &&
    str_contains($homepage, 'pattern="\d{4}"') &&
    str_contains($homepage, 'inputmode="numeric"')
);
assert_pnr("homepage.php includes live PNR character counter badge",
    str_contains($homepage, 'id="pnrCharCounter"')
);
assert_pnr("homepage.php includes 'Where do I find my PNR?' guidance accordion",
    str_contains($homepage, 'class="pnr-helper-accordion"') &&
    str_contains($homepage, 'Where do I find my PNR')
);

// Suite 3: Client-Side Interactive Controller
echo "\n[*] Suite 3: Client-Side Interactive Controller\n";
assert_pnr("homepage.php includes auto-uppercase and auto-advance to phone input",
    str_contains($homepage, 'this.value.toUpperCase()') &&
    str_contains($homepage, 'phoneInput.focus()')
);
assert_pnr("homepage.php includes numeric input guard on phone input",
    str_contains($homepage, 'phoneInput.addEventListener(\'input\'') &&
    str_contains($homepage, 'this.value.replace(/\\D/g')
);
assert_pnr("homepage.php includes 1-click clipboard Copy PNR handler with feedback",
    str_contains($homepage, 'btn-copy-pnr') &&
    str_contains($homepage, 'navigator.clipboard.writeText') &&
    str_contains($homepage, 'Copied!')
);
assert_pnr("homepage.php includes Check Another Ticket form reset handler",
    str_contains($homepage, 'btnSearchAnother') &&
    str_contains($homepage, 'pnrInput.value = \'\'')
);

// Suite 4: Option 2 Boarding Pass Ticket Result Card
echo "\n[*] Suite 4: Option 2 Boarding Pass Ticket Result Card\n";
assert_pnr("homepage.php renders .ticket-receipt-card with role='status'",
    str_contains($homepage, 'class="ticket-receipt-card"') && str_contains($homepage, 'role="status"')
);
assert_pnr("homepage.php Zone 1 includes monospace PNR code and Copy PNR action button",
    str_contains($homepage, 'class="pnr-display-wrap"') &&
    str_contains($homepage, 'class="pnr-code"') &&
    str_contains($homepage, 'class="btn-copy-pnr"')
);
assert_pnr("homepage.php Zone 1 defines semantic status pill with icon and state class",
    str_contains($homepage, 'badge-pnr-status') &&
    str_contains($homepage, '$status_class') &&
    str_contains($homepage, '$status_icon')
);
assert_pnr("homepage.php Zone 2 renders route corridor hero with origin and destination",
    str_contains($homepage, 'class="ticket-route-corridor"') &&
    str_contains($homepage, 'class="route-city origin"') &&
    str_contains($homepage, 'class="route-city destination"')
);
assert_pnr("homepage.php Zone 2 renders transit bus pill and arrow track line",
    str_contains($homepage, 'class="route-transit-corridor"') &&
    str_contains($homepage, 'class="transit-bus-pill"') &&
    str_contains($homepage, 'class="transit-track-line"')
);
assert_pnr("homepage.php renders perforated divider with side ticket notches",
    str_contains($homepage, 'class="ticket-perforated-wrap"') &&
    str_contains($homepage, 'class="ticket-notch ticket-notch-left"') &&
    str_contains($homepage, 'class="ticket-notch ticket-notch-right"') &&
    str_contains($homepage, 'class="ticket-perforated-line"')
);
assert_pnr("homepage.php Zone 3 renders passenger and seat stub with .ticket-seat-pill",
    str_contains($homepage, 'class="ticket-stub-details"') &&
    str_contains($homepage, 'class="ticket-seat-pill"')
);
assert_pnr("homepage.php Zone 4 renders total fare paid and action toolbar",
    str_contains($homepage, 'class="ticket-receipt-footer"') &&
    str_contains($homepage, 'fare-highlight') &&
    str_contains($homepage, 'View &amp; Print Boarding Pass') &&
    str_contains($homepage, 'Verify Seal')
);

// Suite 5: Error & Troubleshooting Assistance Cards
echo "\n[*] Suite 5: Error & Troubleshooting Assistance Cards\n";
assert_pnr("homepage.php provides Rate Limit Throttled assistance card with 15-min explanation",
    str_contains($homepage, 'Verification Rate Limit Reached') &&
    str_contains($homepage, 'btnTryAgainThrottled')
);
assert_pnr("homepage.php provides Invalid Format assistance card with format guide",
    str_contains($homepage, 'Invalid Format Provided') &&
    str_contains($homepage, 'btnTryAgainInvalid')
);
assert_pnr("homepage.php provides No Matching Reservation card with troubleshooting tips",
    str_contains($homepage, 'No Matching Reservation Found') &&
    str_contains($homepage, 'btnTryAgainNotFound') &&
    str_contains($homepage, 'Troubleshooting tips')
);

// Suite 6: Stylesheet Architecture & Dark Mode Elevation
echo "\n[*] Suite 6: Stylesheet Architecture & Dark Mode Elevation\n";
assert_pnr("home.css defines .pnr-search-box with 16px radius and elevation shadow",
    str_contains($home_css, '.pnr-search-box') && str_contains($home_css, 'border-radius: var(--radius-xl, 16px);')
);
assert_pnr("home.css styles .ticket-header with dark slate background and high contrast",
    str_contains($home_css, '.ticket-header') && str_contains($home_css, 'background: #0f172a !important;')
);
assert_pnr("home.css defines .ticket-perforated-line with 2px dashed border",
    str_contains($home_css, '.ticket-perforated-line') && str_contains($home_css, '2px dashed')
);
assert_pnr("home.css defines .ticket-notch cutouts with rounded border",
    str_contains($home_css, '.ticket-notch') && str_contains($home_css, 'border-radius: 50%')
);
assert_pnr("home.css defines dark mode overrides for .ticket-header and .ticket-receipt-card",
    str_contains($home_css, 'html[data-theme="dark"] .ticket-header') &&
    str_contains($home_css, 'background: #081216 !important;')
);
assert_pnr("home.css defines dark mode overrides for .pnr-eyebrow-pill and .ticket-seat-pill",
    str_contains($home_css, 'html[data-theme="dark"] .pnr-eyebrow-pill') &&
    str_contains($home_css, 'html[data-theme="dark"] .ticket-seat-pill')
);

// Suite 7: Standalone Entry Point pnr.php Integration
echo "\n[*] Suite 7: Standalone Entry Point pnr.php Integration\n";
assert_pnr("pnr.php exists and has zero syntax errors",
    file_exists($root . '/pnr.php') && (function() use ($root) {
        exec("php -l " . escapeshellarg($root . '/pnr.php') . " 2>&1", $out, $code);
        return $code === 0;
    })()
);
assert_pnr("pnr.php forwards phone4 parameter when redirecting to homepage.php#pnr",
    str_contains($pnr_php, '$phone4') && str_contains($pnr_php, '&phone4=')
);
assert_pnr("pnr.php includes _pwa-head.php for PWA compliance",
    str_contains($pnr_php, 'assets/partials/_pwa-head.php')
);

echo "\n========================================================\n";
echo "   PNR Suite Results: {$passed} Passed, " . ($failed) . " Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
