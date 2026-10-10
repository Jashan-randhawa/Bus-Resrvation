<?php
// admin/dashboard.php -- Executive KPI & Operational Summary (P-01, P-02, N-06)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

// Consolidated Executive KPIs via single DB round-trip (P-10, Issues 9, 10)
$has_status = table_has_column($link, 'booking', 'status');
$has_cap = table_has_column($link, 'buses', 'capacity');
$has_bus_arch = table_has_column($link, 'buses', 'archived_at');
$has_route_arch = table_has_column($link, 'route', 'archived_at');
$has_cust_arch = table_has_column($link, 'costumer', 'archived_at');
$has_admin_active = table_has_column($link, 'admin', 'is_active');

$rev_where = $has_status ? "WHERE status = 'Confirmed' OR status IS NULL" : "";
$bus_where = $has_bus_arch ? "WHERE archived_at IS NULL" : "";
$route_where = $has_route_arch ? "WHERE archived_at IS NULL" : "";
$cust_where = $has_cust_arch ? "WHERE archived_at IS NULL" : "";
$admin_where = $has_admin_active ? "WHERE is_active = 1" : "";

$cap_select = $has_cap 
    ? "(SELECT COALESCE(SUM(capacity), 0) FROM buses {$bus_where}) AS total_seats" 
    : "((SELECT COUNT(*) FROM buses {$bus_where}) * " . BUS_SEATS . ") AS total_seats";

$kpi = db_one($link, "
    SELECT
      (SELECT COUNT(*) FROM booking) AS total_bookings,
      (SELECT COALESCE(SUM(price), 0) FROM booking {$rev_where}) AS total_revenue,
      (SELECT COUNT(*) FROM costumer {$cust_where}) AS total_customers,
      (SELECT COUNT(*) FROM buses {$bus_where}) AS total_buses,
      (SELECT COUNT(*) FROM route {$route_where}) AS total_routes,
      (SELECT COUNT(*) FROM admin {$admin_where}) AS total_admins,
      (SELECT COUNT(*) FROM `query`) AS total_queries,
      {$cap_select}
");

$total_bookings = (int)($kpi['total_bookings'] ?? 0);
$total_buses = (int)($kpi['total_buses'] ?? 0);
$total_routes = (int)($kpi['total_routes'] ?? 0);
$total_customers = (int)($kpi['total_customers'] ?? 0);
$total_admins = (int)($kpi['total_admins'] ?? 0);
$total_queries = (int)($kpi['total_queries'] ?? 0);
$total_seats = (int)($kpi['total_seats'] ?? 0);
$total_earnings = number_format((float)($kpi['total_revenue'] ?? 0), 2);

// Time window filter for operational analytics (A12)
$allowed_windows = [7, 30, 90];
$window_days = (int)($_GET['days'] ?? 30);
if (!in_array($window_days, $allowed_windows, true)) {
    $window_days = 30;
}
$window_end = date('Y-m-d');
$window_start = date('Y-m-d', strtotime('-' . ($window_days - 1) . ' days'));

// Daily stats query with explicit date window (Issue 9)
$daily_stats = db_all($link, "
    SELECT 
        `date`,
        COUNT(*) AS daily_bookings,
        SUM(CASE WHEN status = 'Confirmed' OR status IS NULL THEN price ELSE 0 END) AS daily_rev,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS daily_cancelled
    FROM booking
    WHERE `date` BETWEEN ? AND ?
    GROUP BY `date`
    ORDER BY `date` DESC
", 'ss', [$window_start, $window_end]);

// Cancellation metrics strictly within the window (Issue 9)
$cancel_metrics = db_one($link, "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled
    FROM booking
    WHERE `date` BETWEEN ? AND ?
", 'ss', [$window_start, $window_end]);
$all_bks = (int)($cancel_metrics['total'] ?? 0);
$all_cnl = (int)($cancel_metrics['cancelled'] ?? 0);
$cnl_rate = $all_bks > 0 ? round(($all_cnl / $all_bks) * 100, 1) : 0;

// Continuous Daily Series Zero-Fill (Performance Overview)
$by_date = [];
foreach ($daily_stats as $r) {
    $by_date[$r['date']] = $r;
}
$series = [];
for ($i = 0; $i < $window_days; $i++) {
    $d = date('Y-m-d', strtotime("$window_start +$i days"));
    $r = $by_date[$d] ?? null;
    $series[] = [
        'd' => $d,
        'b' => (int)($r['daily_bookings'] ?? 0),
        'c' => (int)($r['daily_cancelled'] ?? 0),
        'r' => (float)($r['daily_rev'] ?? 0),
    ];
}

// Previous-Period Totals Query (shifted window of identical duration)
$prev_end = date('Y-m-d', strtotime("$window_start -1 day"));
$prev_start = date('Y-m-d', strtotime("$prev_end -" . ($window_days - 1) . " days"));
$prev_rev_case = $has_status ? "CASE WHEN status = 'Confirmed' OR status IS NULL THEN price ELSE 0 END" : "price";
$prev_cnl_case = $has_status ? "CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END" : "0";

$prev = db_one($link, "
    SELECT 
        COUNT(*) AS total,
        SUM({$prev_cnl_case}) AS cancelled,
        COALESCE(SUM({$prev_rev_case}), 0) AS revenue
    FROM booking 
    WHERE `date` BETWEEN ? AND ?
", 'ss', [$prev_start, $prev_end]);

$prev_total = (int)($prev['total'] ?? 0);
$prev_cancelled = (int)($prev['cancelled'] ?? 0);
$prev_revenue = (float)($prev['revenue'] ?? 0);
$prev_cnl_rate = $prev_total > 0 ? round(($prev_cancelled / $prev_total) * 100, 1) : 0;

$cur_revenue = (float)array_sum(array_column($series, 'r'));
$avg_per_day = $window_days > 0 ? round($all_bks / $window_days, 1) : 0.0;
$prev_avg_per_day = $window_days > 0 ? round($prev_total / $window_days, 1) : 0.0;

if (!function_exists('perf_pct_change')) {
    function perf_pct_change(float $cur, float $prev): ?float {
        if ($prev <= 0) return null;
        return round((($cur - $prev) / $prev) * 100, 1);
    }
}
if (!function_exists('perf_compact_num')) {
    function perf_compact_num(float $num): string {
        if ($num >= 10000000) return round($num / 10000000, 2) . 'Cr';
        if ($num >= 100000) return round($num / 100000, 2) . 'L';
        if ($num >= 1000) return round($num / 1000, 1) . 'k';
        return number_format($num, 2);
    }
}

$bks_delta = perf_pct_change($all_bks, $prev_total);
$rev_delta = perf_pct_change($cur_revenue, $prev_revenue);
$cnl_rate_delta = round($cnl_rate - $prev_cnl_rate, 1);
$avg_delta = perf_pct_change($avg_per_day, $prev_avg_per_day);

if (!defined('CANCEL_RATE_WARN_THRESHOLD')) {
    define('CANCEL_RATE_WARN_THRESHOLD', 10.0);
}
if (!defined('CANCEL_RATE_DANGER_THRESHOLD')) {
    define('CANCEL_RATE_DANGER_THRESHOLD', 20.0);
}
$cnl_val_class = $cnl_rate >= CANCEL_RATE_DANGER_THRESHOLD 
    ? 'is-bad' 
    : ($cnl_rate >= CANCEL_RATE_WARN_THRESHOLD ? 'is-warn' : 'is-ok');

// Highlights calculation
$days_with_bks = array_filter($series, fn($s) => $s['b'] > 0);
$highlights_parts = [];
if (!empty($days_with_bks)) {
    $busiest = null;
    $top_rev = null;
    $quietest = null;
    foreach ($series as $s) {
        if ($busiest === null || $s['b'] > $busiest['b']) {
            $busiest = $s;
        }
        if ($top_rev === null || $s['r'] > $top_rev['r']) {
            $top_rev = $s;
        }
        if ($s['b'] > 0 && ($quietest === null || $s['b'] < $quietest['b'])) {
            $quietest = $s;
        }
    }
    if ($busiest && $busiest['b'] > 0) {
        $highlights_parts[] = '<strong>Busiest day:</strong> ' . date('d M', strtotime($busiest['d'])) . ' (' . $busiest['b'] . ' bookings)';
    }
    if ($top_rev && $top_rev['r'] > 0) {
        $highlights_parts[] = '<strong>Top revenue:</strong> ' . date('d M', strtotime($top_rev['d'])) . ' (' . CURRENCY . number_format($top_rev['r'], 2) . ')';
    }
    if ($quietest && count($days_with_bks) > 1 && $quietest['d'] !== ($busiest['d'] ?? '')) {
        $highlights_parts[] = '<strong>Quietest day:</strong> ' . date('d M', strtotime($quietest['d'])) . ' (' . $quietest['b'] . ' bookings)';
    }
}
$highlights_html = !empty($highlights_parts) ? implode(' &nbsp;&bull;&nbsp; ', $highlights_parts) : '';

$chart_payload = [
    'labels' => array_column($series, 'd'),
    'bookings' => array_column($series, 'b'),
    'cancelled' => array_column($series, 'c'),
    'revenue' => array_column($series, 'r'),
    'currency' => CURRENCY,
    'window_days' => $window_days,
    'total_bookings' => $all_bks,
    'total_cancelled' => $all_cnl,
    'cancel_rate' => $cnl_rate,
];
$chart_json = json_encode($chart_payload, JSON_HEX_TAG | JSON_HEX_AMP);


// Top 5 Popular Routes (Issue 10: strictly Confirmed or NULL for legacy)
$top_routes_where = $has_status ? "WHERE status = 'Confirmed' OR status IS NULL" : "";
$top_routes = db_all($link, "
    SELECT 
        city1, city2, bus,
        COUNT(*) AS total_tickets,
        SUM(price) AS route_revenue
    FROM booking
    {$top_routes_where}
    GROUP BY city1, city2, bus
    ORDER BY total_tickets DESC
    LIMIT 5
");

// Today's bookings count for Reservations card sub-line
$today_date = date('Y-m-d');
$today_bookings_row = db_one($link, "SELECT COUNT(*) AS today_bks FROM booking WHERE `date` = ?", 's', [$today_date]);
$today_bookings_count = (int)($today_bookings_row['today_bks'] ?? 0);

// Top 5 Popular Routes (Issue 10: strictly Confirmed or NULL for legacy)
$top_routes_where = $has_status ? "WHERE status = 'Confirmed' OR status IS NULL" : "";
$top_routes = db_all($link, "
    SELECT 
        city1, city2, bus,
        COUNT(*) AS total_tickets,
        SUM(price) AS route_revenue
    FROM booking
    {$top_routes_where}
    GROUP BY city1, city2, bus
    ORDER BY total_tickets DESC
    LIMIT 5
");
$max_corridor_tickets = !empty($top_routes) ? max(1, (int)$top_routes[0]['total_tickets']) : 1;

// Today's Scheduled Departures (Plan v2: LIMIT 24 for full-day scrolling)
if (!defined('DEPARTURES_LIMIT')) {
    define('DEPARTURES_LIMIT', 24);
}
$today_departures = db_all($link, "
    SELECT r.busno, r.city1, r.city2, r.`time`,
           (SELECT COUNT(*) FROM booking b WHERE b.bus = r.busno AND b.`date` = ? AND b.`time` = r.`time` AND (b.status = 'Confirmed' OR b.status IS NULL)) AS booked_count,
           COALESCE(bu.capacity, 36) AS total_capacity
    FROM route r
    LEFT JOIN buses bu ON r.busno = bu.bus_number
    WHERE (r.archived_at IS NULL)
    ORDER BY r.`time` ASC
    LIMIT " . DEPARTURES_LIMIT . "
", 's', [$today_date]);

// Process departures for v2 strip with live status chips & seat counts
$now = time();
$departed_count = 0;
$boarding_count = 0;
$upcoming_count = 0;
$next_dep_time = null;
$first_active_idx = 0;
$found_active = false;

$processed_deps = [];
foreach ($today_departures as $idx => $dep) {
    $booked = (int)$dep['booked_count'];
    $cap = (int)$dep['total_capacity'];
    $seats_left = max(0, $cap - $booked);
    $pct = $cap > 0 ? min(100, round(($booked / $cap) * 100)) : 0;
    $bar_class = $pct > 80 ? 'bg-danger' : ($pct > 50 ? 'bg-warning' : 'bg-success');

    $dep_timestamp = strtotime($today_date . ' ' . $dep['time']);
    $diff_minutes = (int)round(($dep_timestamp - $now) / 60);

    if ($diff_minutes < 0) {
        $status_chip = 'Departed';
        $status_class = 'is-departed';
        $departed_count++;
    } elseif ($diff_minutes <= 60) {
        $status_chip = 'Boarding soon';
        $status_class = 'is-boarding';
        $boarding_count++;
        if (!$found_active) {
            $next_dep_time = date('h:i A', $dep_timestamp);
            $first_active_idx = $idx;
            $found_active = true;
        }
    } else {
        $status_chip = 'Upcoming';
        $status_class = 'is-upcoming';
        $upcoming_count++;
        if (!$found_active) {
            $next_dep_time = date('h:i A', $dep_timestamp);
            $first_active_idx = $idx;
            $found_active = true;
        }
    }

    $processed_deps[] = [
        'busno' => $dep['busno'],
        'city1' => $dep['city1'],
        'city2' => $dep['city2'],
        'raw_time' => $dep['time'],
        'formatted_time' => date('h:i A', $dep_timestamp),
        'booked' => $booked,
        'cap' => $cap,
        'seats_left' => $seats_left,
        'pct' => $pct,
        'bar_class' => $bar_class,
        'status_chip' => $status_chip,
        'status_class' => $status_class,
    ];
}
$total_deps = count($processed_deps);
if (!$next_dep_time && $total_deps > 0) {
    $next_dep_time = $processed_deps[0]['formatted_time'];
}
?>

<!-- Dashboard Specific Stylesheet -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">

<!-- Inline SVG Icon Sprite -->
<svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
    <symbol id="icon-wallet" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path>
    </symbol>
    <symbol id="icon-ticket" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"></path><path d="M13 5v2"></path><path d="M13 17v2"></path><path d="M13 11v2"></path>
    </symbol>
    <symbol id="icon-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
    </symbol>
    <symbol id="icon-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
    </symbol>
    <symbol id="icon-bus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M8 6v6"></path><path d="M15 6v6"></path><path d="M2 12h19.6"></path><path d="M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.7 19.1 6 18 6H4C2.9 6 1.9 6.7 1.6 7.8l-1.4 5c-.1.4-.2.8-.2 1.2 0 .4.1.8.2 1.2.3 1.1.8 2.8.8 2.8h3"></path><circle cx="7" cy="18" r="2"></circle><path d="M9 18h5"></path><circle cx="16" cy="18" r="2"></circle>
    </symbol>
    <symbol id="icon-route" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="6" cy="19" r="3"></circle><path d="M9 19h8.5a4.5 4.5 0 0 0 0-9H7a4.5 4.5 0 0 1 0-9H18"></circle><circle cx="18" cy="5" r="3"></circle>
    </symbol>
    <symbol id="icon-seat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 9V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v3"></path><path d="M3 11v5a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2Z"></path><path d="M5 18v2"></path><path d="M19 18v2"></path>
    </symbol>
    <symbol id="icon-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.8 17 5 19 5a1 1 0 0 1 1 1z"></path>
    </symbol>
    <symbol id="icon-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path>
    </symbol>
    <symbol id="icon-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
    </symbol>
</svg>

<!-- 1. Executive Dashboard Header (Plan v2) -->
<div class="dash-header">
    <div>
        <h1 class="dash-header-title">Executive Dashboard</h1>
        <p class="dash-header-sub">Real-time overview of ticket operations, fleet availability, user queries, and revenue.</p>
    </div>
    <div class="dash-header-actions">
        <span class="dash-date-chip">
            <svg width="14" height="14"><use href="#icon-clock"></use></svg>
            <?= e(date('D, d M Y')) ?>
        </span>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.location.reload();" title="Refresh dashboard data">
            <svg width="14" height="14" class="mr-1"><use href="#icon-refresh"></use></svg>
            Refresh
        </button>
        <a href="<?= BASE_URL ?>/admin/diagnostics.php" class="btn btn-outline-primary btn-sm">
            <span class="mr-1">⚡</span> System Health
        </a>
    </div>
</div>

<!-- 2. Unified 8-Card KPI Grid (2 Rows of 4) -->
<div class="kpi-grid">
    <!-- Row 1: Business Metrics -->
    <div class="stat-card stat-revenue">
        <div class="stat-card-top">
            <span class="stat-label">Confirmed Revenue</span>
            <div class="kpi-icon"><svg width="18" height="18"><use href="#icon-wallet"></use></svg></div>
        </div>
        <div>
            <div class="stat-value text-success"><?= CURRENCY ?><?= e($total_earnings) ?></div>
            <span class="stat-subline">Confirmed bookings only</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/bookings.php" class="stat-footer-link">
            <span>Sales Ledger</span>
            <span>&rarr;</span>
        </a>
    </div>

    <div class="stat-card stat-bookings">
        <div class="stat-card-top">
            <span class="stat-label">Reservations</span>
            <div class="kpi-icon"><svg width="18" height="18"><use href="#icon-ticket"></use></svg></div>
        </div>
        <div>
            <div class="stat-value"><?= number_format($total_bookings) ?></div>
            <span class="stat-subline"><?= $today_bookings_count ?> today &bull; All-time total</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/bookings.php" class="stat-footer-link">
            <span>Review Bookings</span>
            <span>&rarr;</span>
        </a>
    </div>

    <div class="stat-card stat-customers">
        <div class="stat-card-top">
            <span class="stat-label">Customers</span>
            <div class="kpi-icon"><svg width="18" height="18"><use href="#icon-users"></use></svg></div>
        </div>
        <div>
            <div class="stat-value"><?= number_format($total_customers) ?></div>
            <span class="stat-subline">Registered, active accounts</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/customers.php" class="stat-footer-link">
            <span>Customer Roster</span>
            <span>&rarr;</span>
        </a>
    </div>

    <div class="stat-card stat-queries">
        <div class="stat-card-top">
            <span class="stat-label">Inquiries</span>
            <div class="kpi-icon"><svg width="18" height="18"><use href="#icon-mail"></use></svg></div>
        </div>
        <div>
            <div class="stat-value"><?= number_format($total_queries) ?></div>
            <span class="stat-subline">Customer inbox messages</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/queries.php" class="stat-footer-link">
            <span>Support Messages</span>
            <span>&rarr;</span>
        </a>
    </div>

    <!-- Row 2: Operational Metrics -->
    <div class="stat-card stat-buses">
        <div class="stat-card-top">
            <span class="stat-label">Active Fleet</span>
            <div class="kpi-icon"><svg width="18" height="18"><use href="#icon-bus"></use></svg></div>
        </div>
        <div>
            <div class="stat-value"><?= number_format($total_buses) ?></div>
            <span class="stat-subline">Active fleet vehicles</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/buses.php" class="stat-footer-link">
            <span>Fleet Catalog</span>
            <span>&rarr;</span>
        </a>
    </div>

    <div class="stat-card stat-routes">
        <div class="stat-card-top">
            <span class="stat-label">Transit Routes</span>
            <div class="kpi-icon"><svg width="18" height="18"><use href="#icon-route"></use></svg></div>
        </div>
        <div>
            <div class="stat-value"><?= number_format($total_routes) ?></div>
            <span class="stat-subline">Active transit corridors</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/routes.php" class="stat-footer-link">
            <span>Manage Routes</span>
            <span>&rarr;</span>
        </a>
    </div>

    <div class="stat-card stat-seats">
        <div class="stat-card-top">
            <span class="stat-label">Fleet Capacity</span>
            <div class="kpi-icon"><svg width="18" height="18"><use href="#icon-seat"></use></svg></div>
        </div>
        <div>
            <div class="stat-value"><?= number_format($total_seats) ?></div>
            <span class="stat-subline">Total fleet seat capacity</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/seats.php" class="stat-footer-link">
            <span>Seat Occupancy Map</span>
            <span>&rarr;</span>
        </a>
    </div>

    <div class="stat-card stat-admins">
        <div class="stat-card-top">
            <span class="stat-label">Administrators</span>
            <div class="kpi-icon"><svg width="18" height="18"><use href="#icon-shield"></use></svg></div>
        </div>
        <div>
            <div class="stat-value"><?= number_format($total_admins) ?></div>
            <span class="stat-subline">Active system administrators</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/add-admin.php" class="stat-footer-link">
            <span>Admin Governance</span>
            <span>&rarr;</span>
        </a>
    </div>
</div>

<!-- Collapsible Methodology & Definitions Note -->
<div class="mb-4">
    <a class="small text-muted text-decoration-none d-inline-flex align-items-center" data-toggle="collapse" href="#kpiDefinitions" role="button" aria-expanded="false" aria-controls="kpiDefinitions">
        <svg class="mr-1" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        <span>KPI Definitions &amp; Methodology &darr;</span>
    </a>
    <div class="collapse mt-2" id="kpiDefinitions">
        <div class="card card-body bg-light border-0 py-2 px-3 small text-muted">
            <strong>Definitions:</strong> Fleet, Transit Routes, and Customers count active (non-archived) records. Administrators count active system accounts. Confirmed Revenue reflects completed reservations only.
        </div>
    </div>
</div>

<!-- 3. Today's Departures (5-Second Scrolling Card Strip - Plan v2) -->
<section class="dash-section dep-strip">
    <div class="dep-carousel" data-interval="5000" aria-roledescription="carousel" aria-label="Today's departures">
    <header class="dash-section-head">
        <div class="dash-head-left">
            <h2 class="dash-section-title">
                <svg width="20" height="20" class="text-primary"><use href="#icon-bus"></use></svg>
                Today's Departures
            </h2>
            <div class="dash-section-sub">
                <span class="mr-2"><?= e(date('l, d F Y', strtotime($today_date))) ?></span>
                <?php if ($total_deps > 0): ?>
                    <span class="dep-summary-pill"><?= $dep_summary_text ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="dash-head-right">
            <?php if ($total_deps > 0): ?>
                <span class="dep-counter" aria-hidden="true"><span class="dep-counter-curr"><?= ($first_active_idx + 1) ?></span> / <?= $total_deps ?></span>
                <div class="dep-nav-btns mr-2">
                    <button type="button" class="dep-prev" aria-label="Previous departure">&lsaquo;</button>
                    <button type="button" class="dep-toggle-pause" aria-label="Pause automatic sliding" title="Pause automatic sliding">
                        <span class="dep-pause-icon" aria-hidden="true">⏸</span>
                    </button>
                    <button type="button" class="dep-next" aria-label="Next departure">&rsaquo;</button>
                    <div class="dep-dots" role="tablist" style="display:none;" aria-hidden="true"></div>
                </div>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/admin/manifest.php" class="btn btn-outline-primary btn-sm font-weight-bold">
                View Full Manifest &rarr;
            </a>
        </div>
    </header>

    <?php if ($total_deps > 0): ?>
        <!-- 5-Second Animated Progress Bar -->
        <div class="dep-progress" aria-hidden="true"><span class="dep-progress-bar"></span></div>
    <?php endif; ?>

    <?php if ($total_deps === 0): ?>
        <div class="card p-4 text-center text-muted border-0 shadow-sm">
            <div class="mb-2"><svg width="36" height="36" class="text-muted"><use href="#icon-bus"></use></svg></div>
            <h6 class="font-weight-bold text-dark">No active departures configured for today.</h6>
            <p class="small text-muted mb-0">Check your <a href="<?= BASE_URL ?>/admin/routes.php">route schedules</a> to configure today's transit corridors.</p>
        </div>
    <?php else: ?>
        <div class="dep-viewport">
            <div class="dep-track" tabindex="0" role="region" aria-label="Today's departures card strip">
                <?php foreach ($processed_deps as $idx => $dep): ?>
                    <article class="dep-card" data-status="<?= $dep['status_class'] ?>" role="group" aria-roledescription="slide" aria-label="Departure <?= ($idx + 1) ?> of <?= $total_deps ?>">
                        <div class="dep-card-top">
                            <div>
                                <span class="dep-time-lbl">DEPARTS</span>
                                <strong class="dep-time"><?= e($dep['formatted_time']) ?></strong>
                            </div>
                            <span class="dep-chip <?= $dep['status_class'] ?>"><?= $dep['status_chip'] ?></span>
                        </div>

                        <div class="dep-route-row">
                            <span class="dep-city" title="<?= e($dep['city1']) ?>"><?= e($dep['city1']) ?></span>
                            <span class="dep-route-line" aria-hidden="true"></span>
                            <span class="dep-city" title="<?= e($dep['city2']) ?>"><?= e($dep['city2']) ?></span>
                        </div>

                        <div class="dep-info-grid">
                            <div class="dep-info-col">
                                <span class="dep-info-lbl">BUS</span>
                                <span class="dep-info-val">🚌 <?= e($dep['busno']) ?></span>
                            </div>
                            <div class="dep-info-col">
                                <span class="dep-info-lbl">SEATS LEFT</span>
                                <span class="dep-info-val"><strong><?= $dep['seats_left'] ?></strong> <small class="text-muted">of <?= $dep['cap'] ?></small></span>
                            </div>
                        </div>

                        <div class="dep-occ-block">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Occupancy</span>
                                <span class="font-weight-bold"><?= $dep['pct'] === 100 ? 'Full (100%)' : ($dep['pct'] . '%') ?></span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar <?= $dep['bar_class'] ?>" role="progressbar" style="width: <?= $dep['pct'] ?>%;" aria-valuenow="<?= $dep['pct'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <div class="dep-card-footer mt-auto">
                            <a href="<?= BASE_URL ?>/admin/manifest.php?bus=<?= urlencode($dep['busno']) ?>&date=<?= urlencode($today_date) ?>&time=<?= urlencode($dep['raw_time']) ?>" class="btn btn-outline-primary btn-sm btn-block font-weight-bold">
                                Manifest &rarr;
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    </div>
</section>

<!-- 4. Performance Overview: Charts & Indicators (Plan v2: Full Width) -->
<section class="dash-section">
    <div class="card perf-card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center">
            <div class="my-1">
                <h6 class="mb-0 font-weight-bold text-dark">
                    <!-- 30-Day Performance Overview -->
                    <?= $window_days ?>-Day Performance Overview
                    <small class="text-muted font-weight-normal">(<?= e(date('d M', strtotime($window_start))) ?> &ndash; <?= e(date('d M Y', strtotime($window_end))) ?>)</small>
                </h6>
            </div>
            <div class="btn-group btn-group-sm my-1" role="group" aria-label="Time window selector">
                <a href="?days=7" class="btn btn-sm <?= $window_days === 7 ? 'btn-primary' : 'btn-outline-secondary' ?>">7d</a>
                <a href="?days=30" class="btn btn-sm <?= $window_days === 30 ? 'btn-primary' : 'btn-outline-secondary' ?>">30d</a>
                <a href="?days=90" class="btn btn-sm <?= $window_days === 90 ? 'btn-primary' : 'btn-outline-secondary' ?>">90d</a>
            </div>
        </div>
        <div class="card-body p-3">
            <!-- 4 Performance Metric Tiles -->
            <div class="perf-tiles mb-2">
                <div class="perf-tile">
                    <span class="perf-label">Reservations</span>
                    <div class="perf-value"><?= number_format($all_bks) ?></div>
                    <div>
                        <?php if ($all_bks === 0 && $prev_total === 0): ?>
                            <span class="text-muted small">No data</span>
                        <?php elseif ($prev_total === 0): ?>
                            <span class="perf-delta is-new">New</span>
                        <?php elseif ($bks_delta > 0): ?>
                            <span class="perf-delta is-up">&#9650; +<?= $bks_delta ?>% vs prev</span>
                        <?php elseif ($bks_delta < 0): ?>
                            <span class="perf-delta is-down">&#9660; <?= $bks_delta ?>% vs prev</span>
                        <?php else: ?>
                            <span class="perf-delta is-flat">0.0% vs prev</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="perf-tile">
                    <span class="perf-label">Confirmed Revenue</span>
                    <div class="perf-value" title="<?= CURRENCY ?><?= number_format($cur_revenue, 2) ?>"><?= CURRENCY ?><?= perf_compact_num($cur_revenue) ?></div>
                    <div>
                        <?php if ($cur_revenue == 0 && $prev_revenue == 0): ?>
                            <span class="text-muted small">No data</span>
                        <?php elseif ($prev_revenue == 0): ?>
                            <span class="perf-delta is-new">New</span>
                        <?php elseif ($rev_delta > 0): ?>
                            <span class="perf-delta is-up">&#9650; +<?= $rev_delta ?>% vs prev</span>
                        <?php elseif ($rev_delta < 0): ?>
                            <span class="perf-delta is-down">&#9660; <?= $rev_delta ?>% vs prev</span>
                        <?php else: ?>
                            <span class="perf-delta is-flat">0.0% vs prev</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="perf-tile">
                    <span class="perf-label">Cancel Rate</span>
                    <div class="perf-value <?= $cnl_val_class ?>"><?= $cnl_rate ?>%</div>
                    <div>
                        <?php if ($all_bks === 0 && $prev_total === 0): ?>
                            <span class="text-muted small">No data</span>
                        <?php elseif ($prev_total === 0): ?>
                            <span class="perf-delta is-new">New</span>
                        <?php elseif ($cnl_rate_delta > 0): ?>
                            <span class="perf-delta is-down">&#9650; +<?= $cnl_rate_delta ?> pts vs prev</span>
                        <?php elseif ($cnl_rate_delta < 0): ?>
                            <span class="perf-delta is-up">&#9660; <?= $cnl_rate_delta ?> pts vs prev</span>
                        <?php else: ?>
                            <span class="perf-delta is-flat">0.0 pts vs prev</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="perf-tile">
                    <span class="perf-label">Avg / Day</span>
                    <div class="perf-value"><?= number_format($avg_per_day, 1) ?></div>
                    <div>
                        <?php if ($all_bks === 0 && $prev_total === 0): ?>
                            <span class="text-muted small">No data</span>
                        <?php elseif ($prev_total === 0): ?>
                            <span class="perf-delta is-new">New</span>
                        <?php elseif ($avg_delta > 0): ?>
                            <span class="perf-delta is-up">&#9650; +<?= $avg_delta ?>% vs prev</span>
                        <?php elseif ($avg_delta < 0): ?>
                            <span class="perf-delta is-down">&#9660; <?= $avg_delta ?>% vs prev</span>
                        <?php else: ?>
                            <span class="perf-delta is-flat">0.0% vs prev</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="small text-muted mb-3" style="font-size: 0.78rem;">
                <span class="mr-1">ℹ️</span> Pending bookings are included in volume; revenue reflects confirmed bookings only.
            </div>

            <!-- Charts Grid: Combo Chart + Donut -->
            <div class="perf-grid mb-3">
                <div class="perf-chart">
                    <canvas id="perfChart" role="img" aria-label="<?= e("{$window_days}-day daily bookings and revenue combo chart") ?>" data-chart="<?= e($chart_json) ?>"></canvas>
                    <noscript>
                        <div class="p-3 text-muted text-center border rounded">Interactive chart requires JavaScript. See table below.</div>
                    </noscript>
                </div>
                <div class="perf-donut">
                    <canvas id="perfDonut" role="img" aria-label="Booking outcome distribution donut chart"></canvas>
                </div>
            </div>

            <!-- Highlights Line -->
            <?php if (!empty($highlights_html)): ?>
                <p class="perf-highlights small mb-3"><span class="mr-1">💡</span> <?= $highlights_html ?></p>
            <?php endif; ?>

            <!-- Daily Breakdown Accordion Button -->
            <div>
                <button type="button" class="btn btn-sm btn-outline-secondary font-weight-medium" data-toggle="collapse" data-target="#dailyTable" aria-expanded="false" aria-controls="dailyTable">
                    <span>📋</span> View daily breakdown &darr;
                </button>
            </div>

            <!-- Collapsible Accessible Breakdown Table -->
            <div id="dailyTable" class="collapse mt-3">
                <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                    <table class="table table-hover table-stack mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Date</th>
                                <th>Reservations</th>
                                <th>Cancelled</th>
                                <th class="text-right">Daily Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daily_stats)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No reservations in the selected timeframe.</td></tr>
                            <?php else: ?>
                                <?php foreach ($daily_stats as $ds): ?>
                                    <tr>
                                        <td data-label="Date"><small class="font-weight-medium text-dark"><?= e(date('d M Y', strtotime($ds['date']))) ?></small></td>
                                        <td data-label="Reservations"><span class="badge badge-primary px-2"><?= (int)$ds['daily_bookings'] ?></span></td>
                                        <td data-label="Cancelled"><span class="badge badge-danger px-2"><?= (int)$ds['daily_cancelled'] ?></span></td>
                                        <td data-label="Daily Revenue" class="text-right font-weight-bold text-success"><?= CURRENCY ?><?= number_format((float)$ds['daily_rev'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 5. Top Transit Corridors (Ranked List - Plan v2) -->
<section class="dash-section">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 font-weight-bold text-dark d-flex align-items-center">
                <svg width="18" height="18" class="mr-2 text-primary"><use href="#icon-route"></use></svg>
                Top Transit Corridors
            </h6>
            <span class="badge badge-light border">Most Traveled</span>
        </div>
        <div class="card-body p-3">
            <?php if (empty($top_routes)): ?>
                <div class="p-4 text-center text-muted">
                    <svg width="32" height="32" class="text-muted mb-2"><use href="#icon-route"></use></svg>
                    <div>No route booking data yet.</div>
                </div>
            <?php else: ?>
                <div class="corridor-list">
                    <?php foreach ($top_routes as $i => $tr): ?>
                        <?php
                        $ticket_cnt = (int)$tr['total_tickets'];
                        $bar_pct = min(100, round(($ticket_cnt / $max_corridor_tickets) * 100));
                        ?>
                        <div class="corridor-row">
                            <span class="corridor-rank"><?= ($i + 1) ?></span>
                            <div class="corridor-main">
                                <div class="corridor-route-header">
                                    <span class="corridor-title"><?= e($tr['city1']) ?> &rarr; <?= e($tr['city2']) ?></span>
                                    <span class="corridor-bus-badge">🚌 <?= e($tr['bus']) ?></span>
                                </div>
                                <div class="corridor-bar-track">
                                    <div class="corridor-bar-fill" style="width: <?= $bar_pct ?>%;"></div>
                                </div>
                            </div>
                            <div class="corridor-stats">
                                <span class="corridor-revenue"><?= CURRENCY ?><?= number_format((float)$tr['route_revenue'], 2) ?></span>
                                <span class="corridor-tickets"><strong><?= $ticket_cnt ?></strong> tickets</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Dashboard Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"
    integrity="sha384-vsrfeLOOY6KuIYKDlmVH5UiBmgIdB1oEf7p01YgWHuqmOHfZr374+odEv96n9tNC"
    crossorigin="anonymous" defer></script>
<script src="<?= BASE_URL ?>/assets/js/perf-chart.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/dep-strip.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/dep-carousel.js" defer></script>


