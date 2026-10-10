<?php
// tests/test_perf_overview.php
// Validates Performance Overview: Charts & Indicators implementation according to specification.

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
echo "   Performance Overview: Charts & Indicators Test Suite  \n";
echo "========================================================\n\n";

$dashboard_path = __DIR__ . '/../admin/dashboard.php';
$css_path = __DIR__ . '/../assets/css/admin.css';
$js_path = __DIR__ . '/../assets/js/perf-chart.js';

// 1. Dashboard markup & data layer
assert_test("dashboard.php exists", file_exists($dashboard_path));
$dash_content = file_get_contents($dashboard_path);

assert_test("dashboard.php builds continuous zero-filled daily series", 
    str_contains($dash_content, '$series[] = [') && 
    str_contains($dash_content, "'d' => \$d") &&
    str_contains($dash_content, "'b' => (int)") &&
    str_contains($dash_content, "'r' => (float)"));

assert_test("dashboard.php executes previous-period query with prepared statement", 
    str_contains($dash_content, 'prev_start') && 
    str_contains($dash_content, 'prev_end') && 
    str_contains($dash_content, "WHERE `date` BETWEEN ? AND ?"));

assert_test("dashboard.php defines perf_pct_change and perf_compact_num helpers", 
    str_contains($dash_content, 'perf_pct_change') && 
    str_contains($dash_content, 'perf_compact_num'));

assert_test("dashboard.php implements cancel rate threshold constants", 
    str_contains($dash_content, 'CANCEL_RATE_WARN_THRESHOLD') && 
    str_contains($dash_content, 'CANCEL_RATE_DANGER_THRESHOLD'));

assert_test("dashboard.php outputs chart payload with JSON_HEX flags and e() escaping", 
    str_contains($dash_content, 'JSON_HEX_TAG') && 
    str_contains($dash_content, 'JSON_HEX_AMP') &&
    str_contains($dash_content, 'data-chart="<?= e($chart_json) ?>"'));

assert_test("dashboard.php renders full-width .perf-card with dynamic title and date range", 
    str_contains($dash_content, 'class="card perf-card') && 
    str_contains($dash_content, 'Performance Overview') &&
    str_contains($dash_content, 'window_start') && 
    str_contains($dash_content, 'window_end'));

assert_test("dashboard.php renders 4 indicator tiles with delta badges", 
    str_contains($dash_content, 'class="perf-tiles') && 
    str_contains($dash_content, 'Reservations') && 
    str_contains($dash_content, 'Confirmed Revenue') && 
    str_contains($dash_content, 'Cancel Rate') && 
    str_contains($dash_content, 'Avg / Day') && 
    str_contains($dash_content, 'perf-delta'));

assert_test("dashboard.php renders combo chart canvas and donut canvas", 
    str_contains($dash_content, 'id="perfChart"') && 
    str_contains($dash_content, 'id="perfDonut"'));

assert_test("dashboard.php includes collapsible daily breakdown table panel", 
    str_contains($dash_content, 'data-target="#dailyTable"') && 
    str_contains($dash_content, 'id="dailyTable"') && 
    str_contains($dash_content, 'class="collapse'));

assert_test("dashboard.php loads Chart.js with SRI hash and perf-chart.js with defer", 
    str_contains($dash_content, 'chart.umd.min.js') && 
    str_contains($dash_content, 'integrity="sha384-') && 
    str_contains($dash_content, 'crossorigin="anonymous"') && 
    str_contains($dash_content, 'perf-chart.js') && 
    str_contains($dash_content, 'defer'));

// 2. CSS Styling
assert_test("admin.css exists", file_exists($css_path));
$css_content = file_get_contents($css_path);

assert_test("admin.css styles .perf-tiles grid (4 cols desktop, 2 cols mobile)", 
    str_contains($css_content, '.perf-tiles') && 
    str_contains($css_content, 'grid-template-columns: repeat(4, 1fr)') && 
    str_contains($css_content, 'grid-template-columns: repeat(2, 1fr)'));

assert_test("admin.css defines .perf-delta status pill variants", 
    str_contains($css_content, '.perf-delta.is-up') && 
    str_contains($css_content, '.perf-delta.is-down') && 
    str_contains($css_content, '.perf-delta.is-flat') && 
    str_contains($css_content, '.perf-delta.is-new'));

assert_test("admin.css defines .perf-value cancel rate threshold color classes", 
    str_contains($css_content, '.perf-value.is-ok') && 
    str_contains($css_content, '.perf-value.is-warn') && 
    str_contains($css_content, '.perf-value.is-bad'));

assert_test("admin.css defines .perf-grid layout (2fr 1fr desktop, 1fr tablet/mobile)", 
    str_contains($css_content, '.perf-grid') && 
    str_contains($css_content, 'grid-template-columns: 2fr 1fr') && 
    str_contains($css_content, 'grid-template-columns: 1fr'));

assert_test("admin.css includes dark mode overrides for perf elements", 
    str_contains($css_content, 'html[data-theme="dark"] .perf-tile') && 
    str_contains($css_content, 'html[data-theme="dark"] .perf-value') && 
    str_contains($css_content, 'html[data-theme="dark"] .perf-delta.is-up'));

// 3. JavaScript Chart logic
assert_test("perf-chart.js exists", file_exists($js_path));
$js_content = file_get_contents($js_path);

assert_test("perf-chart.js parses JSON data-chart attribute safely", 
    str_contains($js_content, "getAttribute('data-chart')") && 
    str_contains($js_content, 'JSON.parse'));

assert_test("perf-chart.js handles Chart.js load failure gracefully", 
    str_contains($js_content, "typeof Chart === 'undefined'"));

assert_test("perf-chart.js configures combo chart with stacked bars and revenue line", 
    str_contains($js_content, "stack: 'bookings'") && 
    str_contains($js_content, "yAxisID: 'y1'") && 
    str_contains($js_content, "type: 'line'"));

assert_test("perf-chart.js configures outcome donut chart with center text plugin", 
    str_contains($js_content, "type: 'doughnut'") && 
    str_contains($js_content, "cutout: '70%'") && 
    str_contains($js_content, 'Cancel Rate'));

assert_test("perf-chart.js observes data-theme changes to live-update chart theme", 
    str_contains($js_content, 'MutationObserver') && 
    str_contains($js_content, 'data-theme'));

assert_test("perf-chart.js respects prefers-reduced-motion", 
    str_contains($js_content, 'prefers-reduced-motion: reduce'));

echo "\n========================================================\n";
echo "   Performance Overview Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
