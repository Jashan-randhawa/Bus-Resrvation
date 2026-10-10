<?php
// tests/test_departure_carousel.php
// Validates Departure Schedule Carousel implementation according to specification.

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
echo "   Departure Schedule Carousel Test Suite               \n";
echo "========================================================\n\n";

$dashboard_path = __DIR__ . '/../admin/dashboard.php';
$css_path = __DIR__ . '/../assets/css/admin.css';
$js_path = __DIR__ . '/../assets/js/dep-carousel.js';

// 1. Dashboard markup & integration
assert_test("dashboard.php exists", file_exists($dashboard_path));
$dash_content = file_get_contents($dashboard_path);

assert_test("dashboard.php contains .dep-carousel container with aria-roledescription", 
    str_contains($dash_content, 'class="dep-carousel"') && 
    str_contains($dash_content, 'aria-roledescription="carousel"'));

assert_test("dashboard.php contains .dep-viewport and .dep-track structure", 
    str_contains($dash_content, 'class="dep-viewport"') && 
    str_contains($dash_content, 'class="dep-track"'));

assert_test("dashboard.php renders .dep-card with role='group' and slide role description", 
    str_contains($dash_content, 'class="dep-card"') && 
    str_contains($dash_content, 'role="group"') &&
    str_contains($dash_content, 'aria-roledescription="slide"'));

assert_test("dashboard.php retains existing empty state message", 
    str_contains($dash_content, 'No active departures configured for today.'));

assert_test("dashboard.php retains manifest URL with bus, date, and time query params", 
    str_contains($dash_content, 'manifest.php?bus=') && 
    str_contains($dash_content, 'date=') && 
    str_contains($dash_content, 'time='));

assert_test("dashboard.php includes controls: prev, next, dots, and pause toggle", 
    str_contains($dash_content, 'class="dep-prev"') && 
    str_contains($dash_content, 'class="dep-next"') && 
    str_contains($dash_content, 'class="dep-dots"') && 
    str_contains($dash_content, 'class="dep-toggle-pause"'));

assert_test("dashboard.php loads dep-carousel.js with defer", 
    str_contains($dash_content, 'assets/js/dep-carousel.js') && 
    str_contains($dash_content, 'defer'));

// 2. CSS Styling & Breakpoints
assert_test("admin.css exists", file_exists($css_path));
$css_content = file_get_contents($css_path);

assert_test("admin.css defines .dep-carousel responsive CSS variable --dep-visible", 
    str_contains($css_content, '--dep-visible: 1') && 
    str_contains($css_content, '--dep-visible: 2') && 
    str_contains($css_content, '--dep-visible: 3') && 
    str_contains($css_content, '--dep-visible: 4'));

assert_test("admin.css styles .dep-track with flex and 500ms transition", 
    str_contains($css_content, '.dep-track') && 
    str_contains($css_content, 'display: flex') && 
    str_contains($css_content, '500ms'));

assert_test("admin.css calculates .dep-card width using --dep-visible variable", 
    str_contains($css_content, 'calc(') && 
    str_contains($css_content, 'var(--dep-visible)'));

assert_test("admin.css provides dark mode support via [data-theme=\"dark\"]", 
    str_contains($css_content, 'html[data-theme="dark"] .dep-card') && 
    str_contains($css_content, 'html[data-theme="dark"] .dep-prev'));

assert_test("admin.css includes prefers-reduced-motion media query", 
    str_contains($css_content, 'prefers-reduced-motion: reduce') && 
    str_contains($css_content, 'transition: none'));

// 3. JavaScript carousel logic
assert_test("dep-carousel.js exists", file_exists($js_path));
$js_content = file_get_contents($js_path);

assert_test("dep-carousel.js implements interval timer with data-interval fallback (3000ms)", 
    str_contains($js_content, 'data-interval') && 
    str_contains($js_content, '3000'));

assert_test("dep-carousel.js implements pause guards for hover, focus, and hidden tab", 
    str_contains($js_content, 'mouseenter') && 
    str_contains($js_content, 'mouseleave') && 
    str_contains($js_content, 'focusin') && 
    str_contains($js_content, 'focusout') && 
    str_contains($js_content, 'visibilitychange'));

assert_test("dep-carousel.js implements visible count and edge case (cards <= visibleCount)", 
    str_contains($js_content, '--dep-visible') && 
    str_contains($js_content, 'totalCards <= visibleCount'));

assert_test("dep-carousel.js implements mobile touch swipe detection", 
    str_contains($js_content, 'touchstart') && 
    str_contains($js_content, 'touchend'));

assert_test("dep-carousel.js respects prefers-reduced-motion in JavaScript", 
    str_contains($js_content, 'prefers-reduced-motion: reduce'));

assert_test("dep-carousel.js implements interactive dots and pause/play toggle", 
    str_contains($js_content, 'dep-dot') && 
    str_contains($js_content, 'dep-toggle-pause'));

echo "\n========================================================\n";
echo "   Carousel Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
