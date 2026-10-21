<?php
// tests/test_route_ui.php -- Verification Suite for Bus Route Schedule UI Modernization
declare(strict_types=1);

$total_assertions = 0;
$failed_assertions = 0;

function assert_route_ui(string $desc, bool $condition): void {
    global $total_assertions, $failed_assertions;
    $total_assertions++;
    if ($condition) {
        echo " [PASS] {$desc}\n";
    } else {
        echo " [FAIL] {$desc}\n";
        $failed_assertions++;
    }
}

echo "========================================================\n";
echo "   Bus Route Schedule UI Modernization Test Suite       \n";
echo "========================================================\n\n";

$routes_content = file_get_contents(__DIR__ . '/../admin/routes.php');
$edit_route_content = file_get_contents(__DIR__ . '/../admin/edit/edit-route.php');
$admin_css = file_get_contents(__DIR__ . '/../assets/css/admin.css');
$design_css = file_get_contents(__DIR__ . '/../assets/css/design-system.css');

// -------------------------------------------------------------
// Suite 1: KPI Operational Metrics & Header
// -------------------------------------------------------------
echo "[*] Suite 1: KPI Operational Metrics & Header\n";

assert_route_ui("routes.php defines operational KPI cards for active routes, fleet, cities, and average tariff",
    str_contains($routes_content, 'stat-routes') &&
    str_contains($routes_content, 'stat-fleet') &&
    str_contains($routes_content, 'stat-bookings') &&
    str_contains($routes_content, 'stat-revenue')
);

assert_route_ui("routes.php queries aggregate statistics (total_active_routes, allocated_buses, avg_tariff)",
    str_contains($routes_content, 'total_active_routes') &&
    str_contains($routes_content, 'allocated_buses') &&
    str_contains($routes_content, 'avg_tariff')
);

// -------------------------------------------------------------
// Suite 2: Smart Filter & Search Toolbar
// -------------------------------------------------------------
echo "\n[*] Suite 2: Smart Filter & Search Toolbar\n";

assert_route_ui("routes.php implements .filter-bar with keyword search input",
    str_contains($routes_content, 'class="filter-bar') &&
    str_contains($routes_content, 'name="q"') &&
    str_contains($routes_content, 'Search cities or bus...')
);

assert_route_ui("routes.php provides dropdown filtering by origin city and assigned bus",
    str_contains($routes_content, 'name="origin"') &&
    str_contains($routes_content, 'All Origins') &&
    str_contains($routes_content, 'name="bus"') &&
    str_contains($routes_content, 'All Buses')
);

assert_route_ui("routes.php supports multi-criteria sorting (departure, tariff, route corridor)",
    str_contains($routes_content, 'name="sort"') &&
    str_contains($routes_content, 'time_asc') &&
    str_contains($routes_content, 'price_asc') &&
    str_contains($routes_content, 'route_asc')
);

assert_route_ui("routes.php implements .routes-filter-form with responsive columns",
    str_contains($routes_content, 'class="routes-filter-form"') &&
    str_contains($routes_content, 'filter-search-col') &&
    str_contains($routes_content, 'filter-select-col') &&
    str_contains($routes_content, 'filter-actions-col')
);

assert_route_ui("admin.css styles .routes-filter-form with responsive flex layout across viewports",
    str_contains($admin_css, '.filter-bar .routes-filter-form') &&
    str_contains($admin_css, '.filter-search-col') &&
    str_contains($admin_css, '.filter-select-col') &&
    str_contains($admin_css, '@media (max-width: 767.98px)')
);

assert_route_ui("design-system.css provides 36px height and unclipped padding for form-control-sm and selects",
    str_contains($design_css, '.form-control-sm') &&
    str_contains($design_css, 'select.form-control-sm') &&
    str_contains($design_css, 'height: 36px')
);

// -------------------------------------------------------------
// Suite 3: Desktop Corridor Table & Conductor Shortcuts
// -------------------------------------------------------------
echo "\n[*] Suite 3: Desktop Corridor Table & Conductor Shortcuts\n";

assert_route_ui("routes.php formats route corridor with origin, directional arrow, and destination",
    str_contains($routes_content, 'class="route-corridor"') &&
    str_contains($routes_content, 'class="route-city font-weight-bold') &&
    str_contains($routes_content, 'class="route-arrow text-primary')
);

assert_route_ui("routes.php integrates direct link to Passenger Manifest for each route departure",
    str_contains($routes_content, 'admin/manifest.php?bus=') &&
    str_contains($routes_content, '📋 Manifest')
);

assert_route_ui("routes.php displays bus fleet capacity and layout metadata in route listings",
    str_contains($routes_content, 'bus_capacity') &&
    str_contains($routes_content, 'bus_layout')
);

// -------------------------------------------------------------
// Suite 4: Mobile Responsive Stacked Cards (<768px)
// -------------------------------------------------------------
echo "\n[*] Suite 4: Mobile Responsive Stacked Cards\n";

assert_route_ui("routes.php provides dedicated .route-card-mobile container for mobile viewports",
    str_contains($routes_content, 'class="d-md-none') &&
    str_contains($routes_content, 'route-card-mobile')
);

assert_route_ui("routes.php mobile cards feature .route-meta-strip with fleet, departure, and tariff stats",
    str_contains($routes_content, 'route-meta-strip') &&
    str_contains($routes_content, 'FLEET BUS') &&
    str_contains($routes_content, 'DEPARTS') &&
    str_contains($routes_content, 'TARIFF')
);

assert_route_ui("routes.php mobile card actions provide 44px min-height touch targets",
    str_contains($routes_content, 'style="min-height: 44px;"')
);

// -------------------------------------------------------------
// Suite 5: Interactive City Swap & Modal Experience
// -------------------------------------------------------------
echo "\n[*] Suite 5: Interactive City Swap & Modal Experience\n";

assert_route_ui("routes.php modal contains interactive swap cities button (#swapModalCitiesBtn)",
    str_contains($routes_content, 'id="swapModalCitiesBtn"') &&
    str_contains($routes_content, "getElementById('swapModalCitiesBtn')")
);

assert_route_ui("routes.php modal bus selector displays capacity and seating layout in option labels",
    str_contains($routes_content, 'seats') &&
    str_contains($routes_content, '$b_lyt')
);

assert_route_ui("routes.php modal actions feature 44px touch targets and .btn-block-mobile stacking",
    str_contains($routes_content, 'btn-block-mobile') &&
    str_contains($routes_content, 'flex-column-reverse flex-sm-row')
);

assert_route_ui("edit-route.php includes interactive swap button (#swapEditCitiesBtn) when schedule is unlocked",
    str_contains($edit_route_content, 'id="swapEditCitiesBtn"') &&
    str_contains($edit_route_content, "getElementById('swapEditCitiesBtn')")
);

// -------------------------------------------------------------
// Suite 6: Stylesheet Architecture & Dark Theme Support
// -------------------------------------------------------------
echo "\n[*] Suite 6: Stylesheet Architecture & Dark Theme Support\n";

assert_route_ui("admin.css defines .route-corridor, .route-city, and .route-arrow styles",
    str_contains($admin_css, '.route-corridor') &&
    str_contains($admin_css, '.route-city') &&
    str_contains($admin_css, '.route-arrow')
);

assert_route_ui("admin.css defines .route-card-mobile with permanent clean light mode",
    str_contains($admin_css, '.route-card-mobile') &&
    !str_contains($admin_css, 'html[data-theme="dark"] .route-card-mobile')
);

assert_route_ui("admin.css styles #swapModalCitiesBtn with smooth rotation animation",
    str_contains($admin_css, '#swapModalCitiesBtn') &&
    str_contains($admin_css, 'rotate(180deg)')
);

// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo "\n========================================================\n";
echo "   Route UI Test Results: " . ($total_assertions - $failed_assertions) . " Passed, {$failed_assertions} Failed\n";
echo "========================================================\n";

if ($failed_assertions > 0) {
    exit(1);
}
