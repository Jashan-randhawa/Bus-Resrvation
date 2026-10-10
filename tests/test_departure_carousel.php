<?php
// tests/test_departure_carousel.php
// Validates Compact Operational Departure List implementation according to specification.

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$name}\n";
        $passed++;
    } else {
        echo " [FAIL] {$name}" . ($detail ? " - {$detail}" : "") . "\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "   Today's Departures Compact List Test Suite           \n";
echo "========================================================\n\n";

$dashboard_path = __DIR__ . '/../admin/dashboard.php';
$css_path = __DIR__ . '/../assets/css/dashboard.css';
$js_carousel_path = __DIR__ . '/../assets/js/dep-carousel.js';
$js_strip_path = __DIR__ . '/../assets/js/dep-strip.js';

// 1. Files existence & verification
assert_test("dashboard.php exists", file_exists($dashboard_path));
assert_test("dashboard.css exists", file_exists($css_path));
assert_test("dep-carousel.js has been removed", !file_exists($js_carousel_path));
assert_test("dep-strip.js has been removed", !file_exists($js_strip_path));

$dash_content = file_get_contents($dashboard_path);
$css_content = file_get_contents($css_path);

// 2. Removal of Carousel Artifacts
assert_test("dashboard.php does NOT contain carousel auto-scroll markup or controls",
    !str_contains($dash_content, 'class="dep-carousel"') &&
    !str_contains($dash_content, 'dep-prev') &&
    !str_contains($dash_content, 'dep-next') &&
    !str_contains($dash_content, 'dep-toggle-pause') &&
    !str_contains($dash_content, 'dep-dots') &&
    !str_contains($dash_content, 'dep-progress-bar'));

assert_test("dashboard.php does NOT load removed carousel script tags",
    !str_contains($dash_content, 'dep-carousel.js') &&
    !str_contains($dash_content, 'dep-strip.js'));

// 3. Compact List Structure & Layout
assert_test("dashboard.php contains .dep-section and .dep-card-wrap structure",
    str_contains($dash_content, 'dep-section') &&
    str_contains($dash_content, 'dep-card-wrap'));

assert_test("dashboard.php contains desktop table header with correct columns",
    str_contains($dash_content, 'dep-table-head') &&
    str_contains($dash_content, 'Departure') &&
    str_contains($dash_content, 'Origin &rarr; Destination') &&
    str_contains($dash_content, 'Bus') &&
    str_contains($dash_content, 'Status') &&
    str_contains($dash_content, 'Occupancy &amp; Remaining') &&
    str_contains($dash_content, 'Manifest'));

assert_test("dashboard.php contains scrollable list container .dep-list-scroll",
    str_contains($dash_content, 'dep-list-scroll'));

assert_test("dashboard.php renders departure row .dep-row with listitem role",
    str_contains($dash_content, 'class="dep-row"') &&
    str_contains($dash_content, 'role="listitem"'));

// 4. Departure Row Data Fields
assert_test("dashboard.php renders departure time with .dep-row-time",
    str_contains($dash_content, 'dep-row-time') &&
    str_contains($dash_content, '$dep[\'formatted_time\']'));

assert_test("dashboard.php renders origin and destination route names",
    str_contains($dash_content, 'dep-city-origin') &&
    str_contains($dash_content, 'dep-city-dest') &&
    str_contains($dash_content, '$dep[\'city1\']') &&
    str_contains($dash_content, '$dep[\'city2\']'));

assert_test("dashboard.php renders bus number badge",
    str_contains($dash_content, 'dep-bus-badge') &&
    str_contains($dash_content, '$dep[\'busno\']'));

assert_test("dashboard.php renders status chips (Boarding soon / Upcoming)",
    str_contains($dash_content, 'dep-chip') &&
    str_contains($dash_content, '$dep[\'status_chip\']'));

assert_test("dashboard.php renders seat occupancy counts and slim progress indicator",
    str_contains($dash_content, 'dep-occ-counts') &&
    str_contains($dash_content, 'dep-progress-slim') &&
    str_contains($dash_content, 'dep-progress-fill'));

assert_test("dashboard.php renders seats remaining and overbooked alert text",
    str_contains($dash_content, 'dep-seats-remaining') &&
    str_contains($dash_content, '$dep[\'seats_left\']') &&
    str_contains($dash_content, '(OVERBOOKED)'));

assert_test("dashboard.php manifest link carries bus, date, and time parameters",
    str_contains($dash_content, 'admin/manifest.php?bus=') &&
    str_contains($dash_content, '&date=') &&
    str_contains($dash_content, '&time='));

// 5. Departure Ordering & Departed Trip Filtering
assert_test("dashboard.php filters out Departed trips from active list",
    str_contains($dash_content, '$active_departures') &&
    str_contains($dash_content, "return \$d['status_chip'] !== 'Departed';"));

assert_test("dashboard.php preserves empty state for no configured departures",
    str_contains($dash_content, 'No active departures configured for today.'));

assert_test("dashboard.php provides all-departed empty state when trips completed",
    str_contains($dash_content, 'All scheduled departures have departed for today.'));

assert_test("dashboard.php provides link to route schedules and manifest in header and empty state",
    str_contains($dash_content, 'admin/routes.php') &&
    str_contains($dash_content, 'admin/manifest.php') &&
    str_contains($dash_content, 'Manage Routes'));

// 6. CSS Styling, Responsive Breakpoints & Accessibility
assert_test("dashboard.css defines desktop grid layout for departure table",
    str_contains($css_content, '.dep-table-head') &&
    str_contains($css_content, '.dep-row') &&
    str_contains($css_content, 'grid-template-columns: 110px minmax(180px, 1.5fr) 120px 130px 220px 110px'));

assert_test("dashboard.css defines tablet responsive layout (< 992px)",
    str_contains($css_content, '@media (max-width: 991.98px)') &&
    str_contains($css_content, 'grid-template-columns: 95px minmax(140px, 1fr) 100px 115px 180px 95px'));

assert_test("dashboard.css defines mobile stacked responsive layout (< 576px)",
    str_contains($css_content, '@media (max-width: 575.98px)') &&
    str_contains($css_content, 'flex-direction: column'));

assert_test("dashboard.css provides dark mode overrides for compact departure list",
    str_contains($css_content, 'html[data-theme="dark"] .dep-card-wrap') &&
    str_contains($css_content, 'html[data-theme="dark"] .dep-table-head') &&
    str_contains($css_content, 'html[data-theme="dark"] .dep-row') &&
    str_contains($css_content, 'html[data-theme="dark"] .dep-row-time') &&
    str_contains($css_content, 'html[data-theme="dark"] .dep-bus-badge'));

assert_test("dashboard.css honors prefers-reduced-motion for departure list",
    str_contains($css_content, 'prefers-reduced-motion: reduce') &&
    str_contains($css_content, '.dep-row') &&
    str_contains($css_content, '.dep-progress-fill'));

echo "\n========================================================\n";
echo "   Departure Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
