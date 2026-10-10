<?php
// tests/test_dashboard_audit.php
// Validates the deep audit and executive dashboard upgrade:
// Reporting modes, authoritative KPIs, attention-needed panel, corridor rankings, CSV export, and RBAC.

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
echo "   Executive Dashboard Deep Audit & Upgrade Test Suite  \n";
echo "========================================================\n\n";

$dashboard_path = __DIR__ . '/../admin/dashboard.php';
$css_path = __DIR__ . '/../assets/css/dashboard.css';
$wiki_guide_path = __DIR__ . '/../wiki/Administrator-Guide.md';

// 1. Files existence & syntax
assert_test("dashboard.php exists", file_exists($dashboard_path));
assert_test("dashboard.css exists", file_exists($css_path));
assert_test("Administrator-Guide.md exists", file_exists($wiki_guide_path));

$dash_content = file_get_contents($dashboard_path);
$css_content = file_get_contents($css_path);
$guide_content = file_get_contents($wiki_guide_path);

// 2. Query Deduplication & Optimization
$matches_top_routes = substr_count($dash_content, '$top_routes = db_all(');
assert_test("dashboard.php does NOT contain duplicate top_routes query executions", $matches_top_routes === 1, "Found {$matches_top_routes} occurrences");
assert_test("dashboard.php contains dedicated top_routes_revenue query", str_contains($dash_content, '$top_routes_revenue = db_all('));

// 3. Reporting Date Modes (Journey Date vs Booking Date)
assert_test("dashboard.php implements reporting_mode parameter validation", 
    str_contains($dash_content, '$reporting_mode') && 
    str_contains($dash_content, "['journey', 'created']"));
assert_test("dashboard.php supports booking creation date mode (DATE(created_at))", 
    str_contains($dash_content, "DATE(created_at) BETWEEN ? AND ?"));
assert_test("dashboard.php renders reporting mode switcher controls", 
    str_contains($dash_content, 'dash-mode-toggle') && 
    str_contains($dash_content, 'Journey Date') && 
    str_contains($dash_content, 'Booking Date'));

// 4. Authoritative KPI Calculations & Operational Metrics
assert_test("dashboard.php calculates Average Confirmed Booking Value", 
    str_contains($dash_content, '$avg_booking_val') && 
    str_contains($dash_content, 'cur_confirmed_bks'));
assert_test("dashboard.php calculates Trip Occupancy percentage across today's departures", 
    str_contains($dash_content, '$overall_trip_occupancy') && 
    str_contains($dash_content, 'today_total_capacity'));
assert_test("dashboard.php segregates unread inquiries from total inquiries", 
    str_contains($dash_content, '$new_queries_count') && 
    str_contains($dash_content, "status = 'new'"));
assert_test("dashboard.php calculates booking lead time when supported", 
    str_contains($dash_content, 'lead_time_info') && 
    str_contains($dash_content, 'DATEDIFF(`date`, DATE(created_at))'));

// 5. Trip Capacity Bounds & Overbooking Integrity Detection
assert_test("dashboard.php detects overbooked departures where booked > capacity", 
    str_contains($dash_content, 'is_overbooked') && 
    str_contains($dash_content, 'OVERBOOKED'));

// 6. Attention-Needed Operational Panel
assert_test("dashboard.php renders Attention-Needed operational panel", 
    str_contains($dash_content, 'attention-card') && 
    str_contains($dash_content, 'Attention-Needed Items'));
assert_test("dashboard.php includes automated seat-lock concurrency integrity check", 
    str_contains($dash_content, 'booking_concurrency_status') && 
    str_contains($dash_content, 'concurrency_diag'));

// 7. Route Intelligence: Dual Volume & Revenue Ranking
assert_test("dashboard.php renders Corridor Volume and Revenue tab switcher", 
    str_contains($dash_content, 'btnRankVolume') && 
    str_contains($dash_content, 'btnRankRevenue') && 
    str_contains($dash_content, 'switchCorridorRank'));
assert_test("dashboard.php displays explicit ranking methodology caption", 
    str_contains($dash_content, 'corridorRankCaption'));

// 8. Safe CSV Export
assert_test("dashboard.php implements daily CSV export endpoint with audit log", 
    str_contains($dash_content, 'export=daily_csv') && 
    str_contains($dash_content, 'EXPORT_DASHBOARD_CSV') && 
    str_contains($dash_content, 'fputcsv'));

// 9. RBAC & Diagnostic Access Control
assert_test("dashboard.php gates System Health / Diagnostics link to super_admin only", 
    str_contains($dash_content, '$is_super_admin') && 
    str_contains($dash_content, "\$_SESSION['role'] ?? '') === 'super_admin'"));

// 10. CSS Token & Dark Theme Integration
assert_test("dashboard.css styles attention items and levels", 
    str_contains($css_content, '.attention-item') && 
    str_contains($css_content, '.level-danger') && 
    str_contains($css_content, '.level-warning') && 
    str_contains($css_content, '.level-info'));
assert_test("dashboard.css provides dark mode overrides for attention items", 
    str_contains($css_content, 'html[data-theme="dark"] .attention-item') && 
    str_contains($css_content, 'html[data-theme="dark"] .dash-time-chip'));
assert_test("dashboard.css honors prefers-reduced-motion", 
    str_contains($css_content, 'prefers-reduced-motion: reduce') && 
    str_contains($css_content, '.attention-item'));

// 11. Administrator Guide Documentation
assert_test("Administrator-Guide.md documents authoritative metric definitions table", 
    str_contains($guide_content, '### 2.1 Authoritative Metric Definitions') && 
    str_contains($guide_content, 'Trip Occupancy') && 
    str_contains($guide_content, 'Average Booking Value'));
assert_test("Administrator-Guide.md documents Reporting Date Modes", 
    str_contains($guide_content, '### 2.2 Reporting Date Modes') && 
    str_contains($guide_content, 'mode=journey') && 
    str_contains($guide_content, 'mode=created'));
assert_test("Administrator-Guide.md documents Attention-Needed Panel & CSV Export", 
    str_contains($guide_content, '### 2.3 Operational Alerting') && 
    str_contains($guide_content, 'export=daily_csv'));

echo "\n========================================================\n";
echo "   Audit Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
