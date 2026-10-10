<?php
// admin/dashboard.php -- Executive KPI & Operational Summary (P-01, P-02, N-06)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$is_super_admin = (($_SESSION['role'] ?? '') === 'super_admin');

// Time window filter for operational analytics (A12)
$allowed_windows = [7, 30, 90];
$window_days = (int)($_GET['days'] ?? 30);
if (!in_array($window_days, $allowed_windows, true)) {
    $window_days = 30;
}
$allowed_modes = ['journey', 'created'];
$reporting_mode = (string)($_GET['mode'] ?? 'journey');
if (!in_array($reporting_mode, $allowed_modes, true)) {
    $reporting_mode = 'journey';
}

$window_end = date('Y-m-d');
$window_start = date('Y-m-d', strtotime('-' . ($window_days - 1) . ' days'));
$prev_end = date('Y-m-d', strtotime("$window_start -1 day"));
$prev_start = date('Y-m-d', strtotime("$prev_end -" . ($window_days - 1) . " days"));

// Consolidated Executive KPIs schema checks (P-10, Issues 9, 10)
$has_status = table_has_column($link, 'booking', 'status');
$has_cap = table_has_column($link, 'buses', 'capacity');
$has_bus_arch = table_has_column($link, 'buses', 'archived_at');
$has_route_arch = table_has_column($link, 'route', 'archived_at');
$has_cust_arch = table_has_column($link, 'costumer', 'archived_at');
$has_admin_active = table_has_column($link, 'admin', 'is_active');
$has_created_at = table_has_column($link, 'booking', 'created_at');

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

// Support Inquiries breakdown (new vs total)
$new_queries_row = db_one($link, "SELECT COUNT(*) AS cnt FROM `query` WHERE status = 'new'");
$new_queries_count = (int)($new_queries_row['cnt'] ?? 0);

// Daily stats & cancellation metrics within window (Issue 9)
$prev_rev_case = $has_status ? "CASE WHEN status = 'Confirmed' OR status IS NULL THEN price ELSE 0 END" : "price";
$prev_cnl_case = $has_status ? "CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END" : "0";
$confirmed_case = $has_status ? "CASE WHEN status = 'Confirmed' OR status IS NULL THEN 1 ELSE 0 END" : "1";
$pending_case = $has_status ? "CASE WHEN status = 'Pending' THEN 1 ELSE 0 END" : "0";
$expired_case = $has_status ? "CASE WHEN status = 'Expired' THEN 1 ELSE 0 END" : "0";

if ($reporting_mode === 'created' && $has_created_at) {
    // Mode: Booking creation date
    $daily_stats = db_all($link, "
        SELECT 
            DATE(created_at) AS `date`,
            COUNT(*) AS daily_bookings,
            SUM(CASE WHEN status = 'Confirmed' OR status IS NULL THEN 1 ELSE 0 END) AS daily_confirmed,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS daily_pending,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS daily_cancelled,
            SUM(CASE WHEN status = 'Expired' THEN 1 ELSE 0 END) AS daily_expired,
            SUM(CASE WHEN status = 'Confirmed' OR status IS NULL THEN price ELSE 0 END) AS daily_rev
        FROM booking
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) DESC
    ", 'ss', [$window_start, $window_end]);

    $cancel_metrics = db_one($link, "
        SELECT 
            COUNT(*) AS total,
            SUM({$confirmed_case}) AS confirmed_count,
            SUM({$pending_case}) AS pending_count,
            SUM({$prev_cnl_case}) AS cancelled,
            SUM({$expired_case}) AS expired_count
        FROM booking
        WHERE DATE(created_at) BETWEEN ? AND ?
    ", 'ss', [$window_start, $window_end]);

    $prev = db_one($link, "
        SELECT 
            COUNT(*) AS total,
            SUM({$confirmed_case}) AS confirmed_count,
            SUM({$pending_case}) AS pending_count,
            SUM({$prev_cnl_case}) AS cancelled,
            SUM({$expired_case}) AS expired_count,
            COALESCE(SUM({$prev_rev_case}), 0) AS revenue
        FROM booking 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ", 'ss', [$prev_start, $prev_end]);
} else {
    // Mode: Journey date (Travel / fleet utilization perspective)
    $daily_stats = db_all($link, "
        SELECT 
            `date`,
            COUNT(*) AS daily_bookings,
            SUM(CASE WHEN status = 'Confirmed' OR status IS NULL THEN 1 ELSE 0 END) AS daily_confirmed,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS daily_pending,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS daily_cancelled,
            SUM(CASE WHEN status = 'Expired' THEN 1 ELSE 0 END) AS daily_expired,
            SUM(CASE WHEN status = 'Confirmed' OR status IS NULL THEN price ELSE 0 END) AS daily_rev
        FROM booking
        WHERE `date` BETWEEN ? AND ?
        GROUP BY `date`
        ORDER BY `date` DESC
    ", 'ss', [$window_start, $window_end]);

    $cancel_metrics = db_one($link, "
        SELECT 
            COUNT(*) AS total,
            SUM({$confirmed_case}) AS confirmed_count,
            SUM({$pending_case}) AS pending_count,
            SUM({$prev_cnl_case}) AS cancelled,
            SUM({$expired_case}) AS expired_count
        FROM booking
        WHERE `date` BETWEEN ? AND ?
    ", 'ss', [$window_start, $window_end]);

    $prev = db_one($link, "
        SELECT 
            COUNT(*) AS total,
            SUM({$confirmed_case}) AS confirmed_count,
            SUM({$pending_case}) AS pending_count,
            SUM({$prev_cnl_case}) AS cancelled,
            SUM({$expired_case}) AS expired_count,
            COALESCE(SUM({$prev_rev_case}), 0) AS revenue
        FROM booking 
        WHERE `date` BETWEEN ? AND ?
    ", 'ss', [$prev_start, $prev_end]);
}

$all_bks = (int)($cancel_metrics['total'] ?? 0);
$all_cnl = (int)($cancel_metrics['cancelled'] ?? 0);
$cur_confirmed_bks = (int)($cancel_metrics['confirmed_count'] ?? 0);
$cur_pending_bks = (int)($cancel_metrics['pending_count'] ?? 0);
$cur_expired_bks = (int)($cancel_metrics['expired_count'] ?? 0);
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
        'cf' => (int)($r['daily_confirmed'] ?? 0),
        'p' => (int)($r['daily_pending'] ?? 0),
        'c' => (int)($r['daily_cancelled'] ?? 0),
        'x' => (int)($r['daily_expired'] ?? 0),
        'r' => (float)($r['daily_rev'] ?? 0),
    ];
}

$cur_revenue = (float)array_sum(array_column($series, 'r'));
$avg_booking_val = $cur_confirmed_bks > 0 ? round($cur_revenue / $cur_confirmed_bks, 2) : 0.0;

// Safe CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'daily_csv') {
    require_role('admin');
    audit($link, 'EXPORT_DASHBOARD_CSV', 'reporting', null, null, [
        'window_days' => $window_days,
        'mode' => $reporting_mode
    ]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dashboard_daily_' . $window_days . 'd_' . $reporting_mode . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Total Bookings', 'Confirmed', 'Pending', 'Cancelled', 'Expired', 'Confirmed Revenue', 'Cancel Rate %']);
    // Export ordered newest first
    $export_series = array_reverse($series);
    foreach ($export_series as $s) {
        $r_pct = $s['b'] > 0 ? round(($s['c'] / $s['b']) * 100, 1) : 0.0;
        fputcsv($out, [
            $s['d'],
            $s['b'],
            $s['cf'],
            $s['p'],
            $s['c'],
            $s['x'],
            number_format($s['r'], 2, '.', ''),
            $r_pct . '%'
        ]);
    }
    fclose($out);
    exit;
}

$prev_total = (int)($prev['total'] ?? 0);
$prev_cancelled = (int)($prev['cancelled'] ?? 0);
$prev_revenue = (float)($prev['revenue'] ?? 0);
$prev_confirmed_bks = (int)($prev['confirmed_count'] ?? 0);
$prev_pending_bks = (int)($prev['pending_count'] ?? 0);
$prev_avg_booking_val = $prev_confirmed_bks > 0 ? round($prev_revenue / $prev_confirmed_bks, 2) : 0.0;
$prev_cnl_rate = $prev_total > 0 ? round(($prev_cancelled / $prev_total) * 100, 1) : 0;
$prev_confirmed_pct = $prev_total > 0 ? round(($prev_confirmed_bks / $prev_total) * 100, 1) : 0.0;
$prev_pending_pct = $prev_total > 0 ? round(($prev_pending_bks / $prev_total) * 100, 1) : 0.0;

$cur_confirmed_pct = $all_bks > 0 ? round(($cur_confirmed_bks / $all_bks) * 100, 1) : 0.0;
$cur_pending_pct = $all_bks > 0 ? round(($cur_pending_bks / $all_bks) * 100, 1) : 0.0;
$cur_expired_pct = $all_bks > 0 ? round(($cur_expired_bks / $all_bks) * 100, 1) : 0.0;

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
$confirmed_delta = perf_pct_change($cur_confirmed_bks, $prev_confirmed_bks);
$pending_delta = perf_pct_change($cur_pending_bks, $prev_pending_bks);
$rev_delta = perf_pct_change($cur_revenue, $prev_revenue);
$cnl_rate_delta = round($cnl_rate - $prev_cnl_rate, 1);
$avg_delta = perf_pct_change($avg_per_day, $prev_avg_per_day);
$avg_val_delta = perf_pct_change($avg_booking_val, $prev_avg_booking_val);

if (!defined('CANCEL_RATE_WARN_THRESHOLD')) {
    define('CANCEL_RATE_WARN_THRESHOLD', 10.0);
}
if (!defined('CANCEL_RATE_DANGER_THRESHOLD')) {
    define('CANCEL_RATE_DANGER_THRESHOLD', 20.0);
}
$cnl_val_class = $cnl_rate >= CANCEL_RATE_DANGER_THRESHOLD 
    ? 'is-bad' 
    : ($cnl_rate >= CANCEL_RATE_WARN_THRESHOLD ? 'is-warn' : 'is-ok');

// Booking Lead Time calculation (days between booking creation and travel date)
$lead_time_info = null;
if ($has_created_at) {
    $lead_date_clause = ($reporting_mode === 'created') ? "DATE(created_at) BETWEEN ? AND ?" : "`date` BETWEEN ? AND ?";
    $lead_row = db_one($link, "
        SELECT 
            AVG(DATEDIFF(`date`, DATE(created_at))) AS avg_lead,
            MIN(DATEDIFF(`date`, DATE(created_at))) AS min_lead,
            MAX(DATEDIFF(`date`, DATE(created_at))) AS max_lead,
            COUNT(*) AS sample_count
        FROM booking
        WHERE {$lead_date_clause}
          AND (status = 'Confirmed' OR status IS NULL)
          AND created_at IS NOT NULL
          AND DATEDIFF(`date`, DATE(created_at)) >= 0
    ", 'ss', [$window_start, $window_end]);
    if ($lead_row && $lead_row['avg_lead'] !== null && (int)$lead_row['sample_count'] > 0) {
        $lead_time_info = [
            'avg' => round((float)$lead_row['avg_lead'], 1),
            'min' => (int)$lead_row['min_lead'],
            'max' => (int)$lead_row['max_lead'],
        ];
    }
}

// Key Insights calculation from verified metrics
$days_with_bks = array_filter($series, static fn($s) => $s['b'] > 0);
$insights_list = [];

if (!empty($days_with_bks)) {
    $busiest = null;
    $top_rev = null;
    foreach ($series as $s) {
        if ($busiest === null || $s['b'] > $busiest['b']) {
            $busiest = $s;
        }
        if ($top_rev === null || $s['r'] > $top_rev['r']) {
            $top_rev = $s;
        }
    }
    if ($busiest && $busiest['b'] > 0) {
        $insights_list[] = [
            'key' => 'busiest_day',
            'label' => 'Busiest Day',
            'val' => date('d M Y', strtotime($busiest['d'])),
            'sub' => number_format($busiest['b']) . ' total reservations (' . number_format($busiest['cf']) . ' confirmed)',
            'icon' => '📅'
        ];
    }
    if ($top_rev && $top_rev['r'] > 0) {
        $insights_list[] = [
            'key' => 'top_revenue_day',
            'label' => 'Highest Revenue Day',
            'val' => date('d M Y', strtotime($top_rev['d'])),
            'sub' => CURRENCY . number_format($top_rev['r'], 2) . ' confirmed fare total',
            'icon' => '💰'
        ];
    }
}

// Average daily reservations
$insights_list[] = [
    'key' => 'avg_daily',
    'label' => 'Average Daily Volume',
    'val' => number_format($avg_per_day, 1) . ' / day',
    'sub' => $window_days . '-day pace across ' . number_format($all_bks) . ' total bookings',
    'icon' => '📊'
];

// Lead Time (when supported)
if ($lead_time_info) {
    $insights_list[] = [
        'key' => 'lead_time',
        'label' => 'Average Booking Lead Time',
        'val' => $lead_time_info['avg'] . ' days',
        'sub' => 'Advance reservations (range: ' . $lead_time_info['min'] . ' to ' . $lead_time_info['max'] . ' days)',
        'icon' => '⏱️'
    ];
}

// Cancellation trend
$c_trend_msg = $prev_total > 0
    ? ($cnl_rate_delta > 0 
        ? '+' . $cnl_rate_delta . ' pts vs previous ' . $window_days . 'd' 
        : ($cnl_rate_delta < 0 ? $cnl_rate_delta . ' pts vs previous ' . $window_days . 'd' : 'Unchanged vs previous period'))
    : 'No previous period comparison';

$insights_list[] = [
    'key' => 'cnl_trend',
    'label' => 'Cancellation Trend',
    'val' => $cnl_rate . '%',
    'sub' => $c_trend_msg . ' (' . number_format($all_cnl) . ' of ' . number_format($all_bks) . ' cancelled)',
    'icon' => '📉'
];

// 3 Verified Key Insights strictly from actual data & thresholds
$insight_vol_title = 'Booking volume';
$insight_vol_body = '';
if ($prev_total > 0 && $bks_delta !== null) {
    if ($bks_delta > 0) {
        $insight_vol_title = 'Bookings increased';
        $insight_vol_body = "Total bookings are {$bks_delta}% higher compared with the previous {$window_days} days, showing steady growth in demand.";
    } elseif ($bks_delta < 0) {
        $abs_delta = abs($bks_delta);
        $insight_vol_title = 'Bookings decreased';
        $insight_vol_body = "Total bookings are {$abs_delta}% lower compared with the previous {$window_days} days (" . number_format($all_bks) . " vs " . number_format($prev_total) . ").";
    } else {
        $insight_vol_title = 'Booking volume steady';
        $insight_vol_body = "Total bookings matched the previous {$window_days} days at " . number_format($all_bks) . " reservations.";
    }
} elseif ($all_bks > 0) {
    $insight_vol_title = 'Booking volume recorded';
    $insight_vol_body = "Total bookings reached " . number_format($all_bks) . " for the current {$window_days}-day period.";
} else {
    $insight_vol_title = 'No bookings recorded';
    $insight_vol_body = "No reservations were placed during the selected {$window_days}-day timeframe.";
}

$insight_conf_title = 'Confirmation rate';
$insight_conf_body = '';
if ($all_bks > 0) {
    if ($prev_total > 0) {
        $conf_diff = round($cur_confirmed_pct - $prev_confirmed_pct, 1);
        if (abs($conf_diff) <= 1.5) {
            $insight_conf_title = 'Confirmation rate is stable';
            $insight_conf_body = "{$cur_confirmed_pct}% of bookings are confirmed, which is consistent with the recent period.";
        } elseif ($conf_diff > 1.5) {
            $insight_conf_title = 'Confirmation rate improved';
            $insight_conf_body = "{$cur_confirmed_pct}% of bookings are confirmed, up {$conf_diff} percentage points compared with the previous {$window_days} days.";
        } else {
            $abs_conf = abs($conf_diff);
            $insight_conf_title = 'Confirmation rate declined';
            $insight_conf_body = "{$cur_confirmed_pct}% of bookings are confirmed, down {$abs_conf} percentage points compared with the previous {$window_days} days.";
        }
    } else {
        $insight_conf_title = 'Confirmation distribution';
        $insight_conf_body = "{$cur_confirmed_pct}% of bookings are confirmed (" . number_format($cur_confirmed_bks) . " of " . number_format($all_bks) . " total reservations).";
    }
} else {
    $insight_conf_title = 'Confirmation baseline';
    $insight_conf_body = "No reservations in this timeframe to calculate confirmation rate.";
}

$insight_cnl_title = 'Cancellation rate';
$insight_cnl_body = '';
if ($all_bks > 0) {
    if ($cnl_rate < CANCEL_RATE_WARN_THRESHOLD) {
        $insight_cnl_title = 'Cancellation rate is within range';
        $insight_cnl_body = "{$cnl_rate}% of bookings were cancelled, which is within the expected range for this period.";
    } elseif ($cnl_rate < CANCEL_RATE_DANGER_THRESHOLD) {
        $insight_cnl_title = 'Cancellation rate is elevated';
        $insight_cnl_body = "{$cnl_rate}% of bookings were cancelled, exceeding the " . CANCEL_RATE_WARN_THRESHOLD . "% warning threshold.";
    } else {
        $insight_cnl_title = 'Cancellation rate requires attention';
        $insight_cnl_body = "{$cnl_rate}% of bookings were cancelled, exceeding the " . CANCEL_RATE_DANGER_THRESHOLD . "% critical threshold.";
    }
} else {
    $insight_cnl_title = 'Cancellation rate is within range';
    $insight_cnl_body = "0.0% cancellations recorded during the current {$window_days}-day period.";
}

$highlights_html = !empty($insights_list) ? implode(' &nbsp;&bull;&nbsp; ', array_map(static fn($in) => '<strong>' . e($in['label']) . ':</strong> ' . e($in['val']) . ' <span class="text-muted">(' . e($in['sub']) . ')</span>', array_slice($insights_list, 0, 3))) : '';

$chart_payload = [
    'labels' => array_column($series, 'd'),
    'bookings' => array_column($series, 'b'),
    'confirmed' => array_column($series, 'cf'),
    'pending' => array_column($series, 'p'),
    'cancelled' => array_column($series, 'c'),
    'expired' => array_column($series, 'x'),
    'revenue' => array_column($series, 'r'),
    'currency' => CURRENCY,
    'window_days' => $window_days,
    'total_bookings' => $all_bks,
    'total_confirmed' => $cur_confirmed_bks,
    'total_pending' => $cur_pending_bks,
    'total_cancelled' => $all_cnl,
    'total_expired' => $cur_expired_bks,
    'cancel_rate' => $cnl_rate,
];
$chart_json = json_encode($chart_payload, JSON_HEX_TAG | JSON_HEX_AMP);

// Top 5 Popular Routes by Ticket Volume (Issue 10: strictly Confirmed or NULL for legacy)
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

// Top 5 Corridors by Route Revenue
$top_routes_revenue = db_all($link, "
    SELECT 
        city1, city2, bus,
        COUNT(*) AS total_tickets,
        SUM(price) AS route_revenue
    FROM booking
    {$top_routes_where}
    GROUP BY city1, city2, bus
    ORDER BY route_revenue DESC
    LIMIT 5
");
$max_corridor_revenue = !empty($top_routes_revenue) ? max(1.0, (float)$top_routes_revenue[0]['route_revenue']) : 1.0;

// Today's bookings count for Reservations card sub-line
$today_date = date('Y-m-d');
$today_bookings_row = db_one($link, "SELECT COUNT(*) AS today_bks FROM booking WHERE `date` = ?", 's', [$today_date]);
$today_bookings_count = (int)($today_bookings_row['today_bks'] ?? 0);

// Today's Scheduled Departures (LIMIT 24)
if (!defined('DEPARTURES_LIMIT')) {
    define('DEPARTURES_LIMIT', 24);
}
$today_departures = db_all($link, "
    SELECT r.sno AS route_sno, r.busno, r.city1, r.city2, r.`time`,
           (SELECT COUNT(*) FROM booking b 
            WHERE b.bus = r.busno 
              AND b.`date` = ? 
              AND b.`time` = r.`time` 
              AND (b.city1 = r.city1 AND b.city2 = r.city2)
              AND (b.status IN ('Confirmed', 'Pending') OR b.status IS NULL)) AS booked_count,
           COALESCE(bu.capacity, 36) AS total_capacity,
           bu.id AS bus_id
    FROM route r
    LEFT JOIN buses bu ON r.busno = bu.bus_number
    WHERE (r.archived_at IS NULL)
    ORDER BY r.`time` ASC
    LIMIT " . DEPARTURES_LIMIT . "
", 's', [$today_date]);

// Process departures for live status chips & seat counts
$now = time();
$departed_count = 0;
$boarding_count = 0;
$upcoming_count = 0;
$next_dep_time = null;
$first_active_idx = 0;
$found_active = false;

$processed_deps = [];
$today_total_capacity = 0;
$today_total_booked = 0;

foreach ($today_departures as $idx => $dep) {
    $booked = (int)$dep['booked_count'];
    $cap = (int)$dep['total_capacity'];
    $seats_left = max(0, $cap - $booked);
    $pct = $cap > 0 ? min(100, round(($booked / $cap) * 100)) : 0;
    $bar_class = $pct > 80 ? 'bg-danger' : ($pct > 50 ? 'bg-warning' : 'bg-success');
    $is_overbooked = $cap > 0 && ($booked > $cap);

    $today_total_capacity += $cap;
    $today_total_booked += $booked;

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
        'route_sno' => $dep['route_sno'],
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
        'is_overbooked' => $is_overbooked,
    ];
}
$total_deps = count($processed_deps);

// Exclude departed trips from the primary compact list (ordered nearest-first)
$active_departures = array_values(array_filter($processed_deps, static function ($d) {
    return $d['status_chip'] !== 'Departed';
}));
$total_active_deps = count($active_departures);

if (!$next_dep_time && $total_active_deps > 0) {
    $next_dep_time = $active_departures[0]['formatted_time'];
}

$dep_summary_text = $total_active_deps > 0 
    ? "{$total_active_deps} upcoming departures today &bull; Next: " . ($next_dep_time ?? 'None')
    : ($total_deps > 0 ? "All {$total_deps} departures completed today" : "No departures scheduled today");

$overall_trip_occupancy = $today_total_capacity > 0 ? min(100, round(($today_total_booked / $today_total_capacity) * 100, 1)) : 0.0;

// Attention-Needed Operational Action Center Items
$attention_items = [];

// 1. Overbooked Departures (Critical Integrity Alert)
$overbooked_deps = array_filter($processed_deps, static fn($d) => $d['is_overbooked']);
if (!empty($overbooked_deps)) {
    foreach ($overbooked_deps as $od) {
        $attention_items[] = [
            'type' => 'overbooked',
            'level' => 'danger',
            'priority' => 'critical',
            'priority_rank' => 1,
            'icon' => 'icon-shield',
            'title' => 'Overbooked Trip: ' . $od['busno'] . ' (' . $od['formatted_time'] . ')',
            'desc' => 'Reserved seats (' . $od['booked'] . ') exceed vehicle capacity (' . $od['cap'] . ') on ' . $od['city1'] . ' &rarr; ' . $od['city2'] . '.',
            'action_label' => 'Inspect Manifest &rarr;',
            'action_url' => BASE_URL . '/admin/manifest.php?bus=' . urlencode($od['busno']) . '&date=' . urlencode($today_date) . '&time=' . urlencode($od['raw_time']),
        ];
    }
}

// 2. System & Concurrency Integrity Check (Critical if error, Warning if index notice)
$concurrency_diag = booking_concurrency_status($link);
if ($concurrency_diag['status'] !== 'OK') {
    $diag_errors = array_merge($concurrency_diag['errors'] ?? [], $concurrency_diag['warnings'] ?? []);
    $attention_items[] = [
        'type' => 'integrity',
        'level' => 'danger',
        'priority' => 'critical',
        'priority_rank' => 1,
        'icon' => 'icon-shield',
        'title' => 'Seat-Lock Concurrency Warning',
        'desc' => !empty($diag_errors) ? e(implode('; ', array_slice($diag_errors, 0, 2))) : 'Index verification required.',
        'action_label' => $is_super_admin ? 'Run Diagnostics &rarr;' : 'Notify Administrator',
        'action_url' => $is_super_admin ? BASE_URL . '/admin/diagnostics.php' : '#',
    ];
}

// 3. Departures Boarding Soon (High Priority, within 60 mins)
$boarding_deps = array_filter($processed_deps, static fn($d) => $d['status_chip'] === 'Boarding soon');
if (!empty($boarding_deps)) {
    $first_boarding = reset($boarding_deps);
    $attention_items[] = [
        'type' => 'boarding',
        'level' => 'warning',
        'priority' => 'high',
        'priority_rank' => 2,
        'icon' => 'icon-clock',
        'title' => count($boarding_deps) . ' Departure(s) Boarding Soon',
        'desc' => 'Next: ' . $first_boarding['city1'] . ' &rarr; ' . $first_boarding['city2'] . ' (' . $first_boarding['busno'] . ') at ' . $first_boarding['formatted_time'] . '.',
        'action_label' => 'View Manifest &rarr;',
        'action_url' => BASE_URL . '/admin/manifest.php?bus=' . urlencode($first_boarding['busno']) . '&date=' . urlencode($today_date) . '&time=' . urlencode($first_boarding['raw_time']),
    ];
}

// 4. Unanswered Customer Inquiries (High Priority)
if ($new_queries_count > 0) {
    $attention_items[] = [
        'type' => 'inquiry',
        'level' => 'warning',
        'priority' => 'high',
        'priority_rank' => 2,
        'icon' => 'icon-mail',
        'title' => $new_queries_count . ' Unanswered Customer ' . ($new_queries_count === 1 ? 'Inquiry' : 'Inquiries'),
        'desc' => 'Customers waiting for administrative response in support inbox.',
        'action_label' => 'Open Inbox &rarr;',
        'action_url' => BASE_URL . '/admin/queries.php?filter=new',
    ];
}

// 5. Low-Occupancy Trips departing today (< 30%) (Attention / Operational Improvement)
$low_occ_deps = array_filter($processed_deps, static fn($d) => $d['status_chip'] !== 'Departed' && $d['cap'] > 0 && ($d['pct'] < 30));
if (!empty($low_occ_deps)) {
    $first_low = reset($low_occ_deps);
    $attention_items[] = [
        'type' => 'low_occupancy',
        'level' => 'info',
        'priority' => 'attention',
        'priority_rank' => 3,
        'icon' => 'icon-bus',
        'title' => count($low_occ_deps) . ' Low-Occupancy Departure(s) Today',
        'desc' => 'E.g. ' . $first_low['city1'] . ' &rarr; ' . $first_low['city2'] . ' (' . $first_low['formatted_time'] . ') at ' . $first_low['pct'] . '% capacity.',
        'action_label' => 'Review Seats &rarr;',
        'action_url' => BASE_URL . '/admin/seats.php?bus=' . urlencode($first_low['busno']) . '&date=' . urlencode($today_date) . '&time=' . urlencode($first_low['raw_time']),
    ];
}

// Sort attention items by priority rank (Critical first, High second, Attention third)
usort($attention_items, static function($a, $b) {
    return ($a['priority_rank'] ?? 2) <=> ($b['priority_rank'] ?? 2);
});
?>

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

<!-- 1. Executive Dashboard Header -->
<div class="dash-header">
    <div>
        <h1 class="dash-header-title">Executive Dashboard</h1>
        <p class="dash-header-sub">Operational intelligence, revenue metrics, fleet availability, and real-time dispatch alerts.</p>
    </div>
    <div class="dash-header-actions">
        <!-- Date mode switcher -->
        <div class="btn-group btn-group-sm mr-2 dash-mode-toggle" role="group" aria-label="Reporting date filter mode">
            <a href="?days=<?= $window_days ?>&mode=journey" class="btn btn-sm <?= $reporting_mode === 'journey' ? 'btn-primary' : 'btn-outline-secondary' ?>" title="Filter by physical travel departure date">
                Journey Date
            </a>
            <a href="?days=<?= $window_days ?>&mode=created" class="btn btn-sm <?= $reporting_mode === 'created' ? 'btn-primary' : 'btn-outline-secondary' ?>" title="Filter by transaction reservation timestamp">
                Booking Date
            </a>
        </div>

        <span class="dash-date-chip" title="Selected reporting timeframe">
            <svg width="14" height="14"><use href="#icon-clock"></use></svg>
            <?= e(date('d M Y', strtotime($window_start))) ?> &ndash; <?= e(date('d M Y', strtotime($window_end))) ?>
        </span>

        <span class="dash-time-chip" title="Last page generation timestamp">
            <span>⏱️</span> <?= date('h:i:s A') ?>
        </span>

        <button type="button" class="btn btn-outline-secondary btn-sm font-weight-medium" onclick="window.location.reload();" title="Refresh dashboard data">
            <svg width="14" height="14" class="mr-1"><use href="#icon-refresh"></use></svg>
            Refresh
        </button>

        <?php if ($is_super_admin): ?>
            <a href="<?= BASE_URL ?>/admin/diagnostics.php" class="btn btn-outline-primary btn-sm font-weight-medium" title="Inspect system health diagnostics">
                <span class="mr-1">⚡</span> System Health
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 2. Primary KPI Row (4 Cards) -->
<div class="kpi-grid mb-3">
    <!-- Confirmed Revenue -->
    <div class="stat-card stat-revenue">
        <div class="stat-card-top">
            <span class="stat-label">Confirmed Revenue</span>
            <div class="kpi-icon"><svg width="18" height="18"><use href="#icon-wallet"></use></svg></div>
        </div>
        <div>
            <div class="stat-value text-success"><?= CURRENCY ?><?= perf_compact_num($cur_revenue) ?></div>
            <span class="stat-subline">
                <?= $window_days ?>d window &bull; 
                <?php if ($prev_revenue > 0 && $rev_delta !== null): ?>
                    <span class="<?= $rev_delta >= 0 ? 'text-success' : 'text-danger' ?>"><?= $rev_delta >= 0 ? '+' : '' ?><?= $rev_delta ?>% vs prev</span>
                <?php else: ?>
                    All-time: <?= CURRENCY ?><?= e($total_earnings) ?>
                <?php endif; ?>
            </span>
        </div>
        <a href="<?= BASE_URL ?>/admin/bookings.php" class="stat-footer-link">
            <span>Sales Ledger</span>
            <span>&rarr;</span>
        </a>
    </div>

    <!-- Reservations -->
    <div class="stat-card stat-bookings">
        <div class="stat-card-top">
            <span class="stat-label">Reservations</span>
            <div class="kpi-icon"><svg width="18" height="18"><use href="#icon-ticket"></use></svg></div>
        </div>
        <div>
            <div class="stat-value"><?= number_format($all_bks) ?></div>
            <span class="stat-subline">
                <?= $window_days ?>d window &bull; 
                <?php if ($prev_total > 0 && $bks_delta !== null): ?>
                    <span class="<?= $bks_delta >= 0 ? 'text-success' : 'text-danger' ?>"><?= $bks_delta >= 0 ? '+' : '' ?><?= $bks_delta ?>% vs prev</span>
                <?php else: ?>
                    <?= $today_bookings_count ?> today
                <?php endif; ?>
            </span>
        </div>
        <a href="<?= BASE_URL ?>/admin/bookings.php" class="stat-footer-link">
            <span>Review Bookings</span>
            <span>&rarr;</span>
        </a>
    </div>

    <!-- Trip Occupancy -->
    <div class="stat-card stat-seats">
        <div class="stat-card-top">
            <span class="stat-label">Trip Occupancy</span>
            <div class="kpi-icon"><svg width="18" height="18"><use href="#icon-seat"></use></svg></div>
        </div>
        <div>
            <div class="stat-value <?= $overall_trip_occupancy >= 70 ? 'text-success' : ($overall_trip_occupancy >= 40 ? 'text-warning' : '') ?>">
                <?= $overall_trip_occupancy ?>%
            </div>
            <span class="stat-subline">Today's load &bull; <?= $today_total_booked ?> of <?= $today_total_capacity ?> seats</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/seats.php" class="stat-footer-link">
            <span>Seat Occupancy Map</span>
            <span>&rarr;</span>
        </a>
    </div>

    <!-- Cancellation Rate -->
    <div class="stat-card stat-admins">
        <div class="stat-card-top">
            <span class="stat-label">Cancellation Rate</span>
            <div class="kpi-icon"><svg width="18" height="18"><use href="#icon-shield"></use></svg></div>
        </div>
        <div>
            <div class="stat-value <?= $cnl_val_class ?>"><?= $cnl_rate ?>%</div>
            <span class="stat-subline">
                <?= number_format($all_cnl) ?> of <?= number_format($all_bks) ?> bookings
                <?php if ($prev_total > 0): ?>
                    &bull; <span class="<?= $cnl_rate_delta <= 0 ? 'text-success' : 'text-danger' ?>"><?= $cnl_rate_delta > 0 ? '+' : '' ?><?= $cnl_rate_delta ?> pts</span>
                <?php endif; ?>
            </span>
        </div>
        <a href="<?= BASE_URL ?>/admin/bookings.php" class="stat-footer-link">
            <span>Cancellation Policy</span>
            <span>&rarr;</span>
        </a>
    </div>
</div>

<!-- 3. Secondary Operational Metrics (6 Cards) -->
<div class="kpi-grid mb-3" style="grid-template-columns: repeat(6, 1fr);">
    <div class="stat-card">
        <div class="stat-card-top">
            <span class="stat-label">Confirmed Bookings</span>
            <div class="kpi-icon"><svg width="16" height="16"><use href="#icon-ticket"></use></svg></div>
        </div>
        <div>
            <div class="stat-value" style="font-size: 1.25rem;"><?= number_format($cur_confirmed_bks) ?></div>
            <span class="stat-subline">Completed checkouts</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-top">
            <span class="stat-label">Avg Booking Value</span>
            <div class="kpi-icon"><svg width="16" height="16"><use href="#icon-wallet"></use></svg></div>
        </div>
        <div>
            <div class="stat-value" style="font-size: 1.25rem;"><?= CURRENCY ?><?= number_format($avg_booking_val, 2) ?></div>
            <span class="stat-subline">Per confirmed ticket</span>
        </div>
    </div>

    <div class="stat-card stat-customers">
        <div class="stat-card-top">
            <span class="stat-label">Active Customers</span>
            <div class="kpi-icon"><svg width="16" height="16"><use href="#icon-users"></use></svg></div>
        </div>
        <div>
            <div class="stat-value" style="font-size: 1.25rem;"><?= number_format($total_customers) ?></div>
            <span class="stat-subline">Registered profiles</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/customers.php" class="stat-footer-link"><span>Directory &rarr;</span></a>
    </div>

    <div class="stat-card stat-buses">
        <div class="stat-card-top">
            <span class="stat-label">Active Fleet</span>
            <div class="kpi-icon"><svg width="16" height="16"><use href="#icon-bus"></use></svg></div>
        </div>
        <div>
            <div class="stat-value" style="font-size: 1.25rem;"><?= number_format($total_buses) ?></div>
            <span class="stat-subline">Total capacity <?= number_format($total_seats) ?></span>
        </div>
        <a href="<?= BASE_URL ?>/admin/buses.php" class="stat-footer-link"><span>Fleet &rarr;</span></a>
    </div>

    <div class="stat-card stat-routes">
        <div class="stat-card-top">
            <span class="stat-label">Transit Routes</span>
            <div class="kpi-icon"><svg width="16" height="16"><use href="#icon-route"></use></svg></div>
        </div>
        <div>
            <div class="stat-value" style="font-size: 1.25rem;"><?= number_format($total_routes) ?></div>
            <span class="stat-subline">Active corridors</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/routes.php" class="stat-footer-link"><span>Routes &rarr;</span></a>
    </div>

    <div class="stat-card stat-queries">
        <div class="stat-card-top">
            <span class="stat-label">Support Inquiries</span>
            <div class="kpi-icon"><svg width="16" height="16"><use href="#icon-mail"></use></svg></div>
        </div>
        <div>
            <div class="stat-value <?= $new_queries_count > 0 ? 'text-warning' : '' ?>" style="font-size: 1.25rem;"><?= number_format($new_queries_count) ?></div>
            <span class="stat-subline">Unanswered (<?= number_format($total_queries) ?> total)</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/queries.php" class="stat-footer-link"><span>Inbox &rarr;</span></a>
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
            <strong>Definitions:</strong> Fleet, Transit Routes, and Customers count active (non-archived) records. Administrators count active system accounts. Confirmed Revenue reflects completed reservations only. Cancellation rate denominator reflects all booking attempts (Confirmed, Pending, Cancelled, Expired) in the active window. Trip occupancy measures seats reserved by Confirmed and Pending passengers against scheduled vehicle capacity.
        </div>
    </div>
</div>

<!-- 4. Today's Departures (Compact Operational List) -->
<section class="dash-section dep-section" aria-label="Today's Departures">
    <div class="card dep-card-wrap border-0 shadow-sm">
        <header class="dash-section-head p-3 pb-2 d-flex flex-wrap justify-content-between align-items-center">
            <div class="dash-head-left my-1">
                <h2 class="dash-section-title mb-1 d-flex align-items-center">
                    <svg width="20" height="20" class="text-primary mr-2" aria-hidden="true"><use href="#icon-bus"></use></svg>
                    Today's Departures
                </h2>
                <div class="dash-section-sub d-flex align-items-center flex-wrap">
                    <span class="mr-2 text-muted small font-weight-bold"><?= e(date('l, d F Y', strtotime($today_date))) ?></span>
                    <span class="dep-summary-pill"><?= $dep_summary_text ?></span>
                </div>
            </div>
            <div class="dash-head-right my-1 d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>/admin/routes.php" class="btn btn-outline-secondary btn-sm mr-2 font-weight-bold">
                    Manage Routes
                </a>
                <a href="<?= BASE_URL ?>/admin/manifest.php" class="btn btn-outline-primary btn-sm font-weight-bold">
                    View Full Manifest &rarr;
                </a>
            </div>
        </header>

        <?php if ($total_deps === 0): ?>
            <div class="p-4 text-center text-muted border-top">
                <div class="mb-2"><svg width="36" height="36" class="text-muted" aria-hidden="true"><use href="#icon-bus"></use></svg></div>
                <h6 class="font-weight-bold text-dark mb-1">No active departures configured for today.</h6>
                <p class="small text-muted mb-0">Check your <a href="<?= BASE_URL ?>/admin/routes.php">route schedules</a> to configure today's transit corridors, or view the <a href="<?= BASE_URL ?>/admin/manifest.php">manifest</a>.</p>
            </div>
        <?php elseif ($total_active_deps === 0): ?>
            <div class="p-4 text-center text-muted border-top">
                <div class="mb-2"><svg width="36" height="36" class="text-success" aria-hidden="true"><use href="#icon-bus"></use></svg></div>
                <h6 class="font-weight-bold text-dark mb-1">All scheduled departures have departed for today.</h6>
                <p class="small text-muted mb-0">All <?= $total_deps ?> trips scheduled for <?= e(date('d M Y', strtotime($today_date))) ?> have concluded. You can review passenger records on the <a href="<?= BASE_URL ?>/admin/manifest.php">full manifest</a> or check <a href="<?= BASE_URL ?>/admin/routes.php">route schedules</a>.</p>
            </div>
        <?php else: ?>
            <div class="dep-table-container">
                <!-- Desktop Column Headers -->
                <div class="dep-table-head" role="row" aria-hidden="true">
                    <div class="dep-th col-time">Departure</div>
                    <div class="dep-th col-route">Origin &rarr; Destination</div>
                    <div class="dep-th col-bus">Bus</div>
                    <div class="dep-th col-status">Status</div>
                    <div class="dep-th col-occupancy">Occupancy &amp; Remaining</div>
                    <div class="dep-th col-action text-right">Manifest</div>
                </div>

                <!-- Scrollable List of Departures -->
                <div class="dep-list-scroll" role="list" aria-label="Upcoming departures today">
                    <?php foreach ($active_departures as $dep): ?>
                        <div class="dep-row" role="listitem">
                            <!-- 1. Departure Time -->
                            <div class="dep-col col-time">
                                <span class="dep-time-lbl d-md-none">Time</span>
                                <span class="dep-row-time"><?= e($dep['formatted_time']) ?></span>
                            </div>

                            <!-- 2. Route Corridor -->
                            <div class="dep-col col-route">
                                <span class="dep-time-lbl d-md-none">Route</span>
                                <div class="dep-route-corridor">
                                    <span class="dep-city-origin" title="<?= e($dep['city1']) ?>"><?= e($dep['city1']) ?></span>
                                    <span class="dep-route-arrow" aria-hidden="true">&rarr;</span>
                                    <span class="dep-city-dest" title="<?= e($dep['city2']) ?>"><?= e($dep['city2']) ?></span>
                                </div>
                            </div>

                            <!-- 3. Bus Number -->
                            <div class="dep-col col-bus">
                                <span class="dep-time-lbl d-md-none">Bus</span>
                                <span class="dep-bus-badge" title="Bus Vehicle">
                                    <svg width="12" height="12" class="mr-1 text-muted" aria-hidden="true"><use href="#icon-bus"></use></svg>
                                    <?= e($dep['busno']) ?>
                                </span>
                            </div>

                            <!-- 4. Status Chip -->
                            <div class="dep-col col-status">
                                <span class="dep-time-lbl d-md-none">Status</span>
                                <span class="dep-chip <?= $dep['status_class'] ?>">
                                    <?= $dep['status_chip'] ?>
                                </span>
                            </div>

                            <!-- 5. Seat Occupancy & Progress -->
                            <div class="dep-col col-occupancy">
                                <div class="dep-occ-details">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="dep-occ-counts">
                                            <strong><?= $dep['booked'] ?></strong><span class="text-muted">/<?= $dep['cap'] ?></span>
                                            <span class="text-muted ml-1">(<?= $dep['pct'] ?>%)</span>
                                        </span>
                                        <span class="dep-seats-remaining">
                                            <?php if ($dep['is_overbooked']): ?>
                                                <span class="text-danger font-weight-bold">0 left <small class="font-weight-bold">(OVERBOOKED)</small></span>
                                            <?php else: ?>
                                                <span class="text-muted"><strong class="text-dark"><?= $dep['seats_left'] ?></strong> left</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="dep-progress-slim" role="progressbar" aria-valuenow="<?= $dep['pct'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Occupancy: <?= $dep['pct'] ?>%">
                                        <div class="dep-progress-fill <?= $dep['bar_class'] ?>" style="width: <?= min(100, $dep['pct']) ?>%;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- 6. Manifest Link Action -->
                            <div class="dep-col col-action text-md-right">
                                <a href="<?= BASE_URL ?>/admin/manifest.php?bus=<?= urlencode($dep['busno']) ?>&date=<?= urlencode($today_date) ?>&time=<?= urlencode($dep['raw_time']) ?>" class="btn btn-outline-primary btn-sm font-weight-bold dep-manifest-btn" title="View passenger manifest for <?= e($dep['busno']) ?> at <?= e($dep['formatted_time']) ?>">
                                    Manifest &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- 5. Performance Overview: Privacy-First Business Analytics Dashboard -->
<section class="dash-section" aria-label="Performance Overview">
    <div class="card perf-card border-0 shadow-sm">
        <header class="perf-header">
            <div class="perf-header-main">
                <div class="perf-title-row">
                    <div class="perf-header-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </div>
                    <div>
                        <h2 class="perf-title mb-0">Performance Overview</h2>
                        <p class="perf-subtitle mb-0">Booking activity and revenue trends</p>
                    </div>
                </div>
                <div class="perf-header-meta">
                    <span class="badge badge-light border text-muted font-weight-bold mr-2">
                        <?= $reporting_mode === 'created' ? 'Booking Date' : 'Journey Date' ?>
                    </span>
                    <span class="perf-date-text font-weight-bold text-dark">
                        <?= e(date('d M', strtotime($window_start))) ?> &ndash; <?= e(date('d M Y', strtotime($window_end))) ?>
                    </span>
                    <span class="perf-compare-text text-muted small ml-1">
                        &bull; Compared with <?= e(date('d M', strtotime($prev_start))) ?> &ndash; <?= e(date('d M Y', strtotime($prev_end))) ?>
                    </span>
                </div>
            </div>
            <div class="perf-range-seg" role="group" aria-label="Reporting duration selector">
                <a href="?days=7&mode=<?= $reporting_mode ?>" class="perf-range-btn <?= $window_days === 7 ? 'is-active' : '' ?>" aria-label="7 days period" <?= $window_days === 7 ? 'aria-current="true"' : '' ?>>7 days</a>
                <a href="?days=30&mode=<?= $reporting_mode ?>" class="perf-range-btn <?= $window_days === 30 ? 'is-active' : '' ?>" aria-label="30 days period" <?= $window_days === 30 ? 'aria-current="true"' : '' ?>>30 days</a>
                <a href="?days=90&mode=<?= $reporting_mode ?>" class="perf-range-btn <?= $window_days === 90 ? 'is-active' : '' ?>" aria-label="90 days period" <?= $window_days === 90 ? 'aria-current="true"' : '' ?>>90 days</a>
            </div>
        </header>

        <div class="card-body p-3">
            <!-- 4 Responsive Performance KPI Cards -->
            <div class="perf-tiles mb-3">
                <!-- KPI 1: Total Bookings -->
                <div class="perf-tile is-blue" data-metric="total_bookings" title="Total Bookings / Reservations">
                    <div class="perf-tile-inner">
                        <div class="perf-kpi-icon is-blue" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"></path>
                                <path d="M13 5v2m0 4v2m0 4v2"></path>
                            </svg>
                        </div>
                        <div class="perf-kpi-body">
                            <span class="perf-label">Total Bookings</span>
                            <!-- Reservations -->
                            <div class="perf-val-row">
                                <span class="perf-value"><?= number_format($all_bks) ?></span>
                                <?php if ($all_bks === 0 && $prev_total === 0): ?>
                                    <span class="perf-delta is-flat">0.0%</span>
                                <?php elseif ($prev_total === 0): ?>
                                    <span class="perf-delta is-new">New</span>
                                <?php elseif ($bks_delta > 0): ?>
                                    <span class="perf-delta is-up">&uarr; <?= $bks_delta ?>%</span>
                                <?php elseif ($bks_delta < 0): ?>
                                    <span class="perf-delta is-down">&darr; <?= abs($bks_delta) ?>%</span>
                                <?php else: ?>
                                    <span class="perf-delta is-flat">0.0%</span>
                                <?php endif; ?>
                            </div>
                            <span class="perf-subtext">vs previous <?= $window_days ?> days</span>
                        </div>
                    </div>
                </div>

                <!-- KPI 2: Confirmed Bookings -->
                <div class="perf-tile is-green" data-metric="confirmed_bookings" title="Confirmed Bookings &amp; Revenue: <?= CURRENCY ?><?= perf_compact_num($cur_revenue) ?>">
                    <div class="perf-tile-inner">
                        <div class="perf-kpi-icon is-green" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        <div class="perf-kpi-body">
                            <span class="perf-label">Confirmed Bookings</span>
                            <!-- Confirmed Revenue -->
                            <div class="perf-val-row">
                                <span class="perf-value"><?= number_format($cur_confirmed_bks) ?></span>
                                <?php if ($cur_confirmed_bks === 0 && $prev_confirmed_bks === 0): ?>
                                    <span class="perf-delta is-flat">0.0%</span>
                                <?php elseif ($prev_confirmed_bks === 0): ?>
                                    <span class="perf-delta is-new">New</span>
                                <?php elseif ($confirmed_delta !== null && $confirmed_delta > 0): ?>
                                    <span class="perf-delta is-up">&uarr; <?= $confirmed_delta ?>%</span>
                                <?php elseif ($confirmed_delta !== null && $confirmed_delta < 0): ?>
                                    <span class="perf-delta is-down">&darr; <?= abs($confirmed_delta) ?>%</span>
                                <?php else: ?>
                                    <span class="perf-delta is-flat">0.0%</span>
                                <?php endif; ?>
                            </div>
                            <span class="perf-subtext"><?= $cur_confirmed_pct ?>% of total bookings</span>
                        </div>
                    </div>
                </div>

                <!-- KPI 3: Pending Bookings -->
                <div class="perf-tile is-amber" data-metric="pending_bookings" title="Pending Bookings awaiting confirmation">
                    <div class="perf-tile-inner">
                        <div class="perf-kpi-icon is-amber" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <div class="perf-kpi-body">
                            <span class="perf-label">Pending Bookings</span>
                            <div class="perf-val-row">
                                <span class="perf-value"><?= number_format($cur_pending_bks) ?></span>
                                <?php if ($cur_pending_bks === 0 && $prev_pending_bks === 0): ?>
                                    <span class="perf-delta is-flat">0.0%</span>
                                <?php elseif ($prev_pending_bks === 0): ?>
                                    <span class="perf-delta is-new">New</span>
                                <?php elseif ($pending_delta !== null && $pending_delta > 0): ?>
                                    <span class="perf-delta is-up">&uarr; <?= $pending_delta ?>%</span>
                                <?php elseif ($pending_delta !== null && $pending_delta < 0): ?>
                                    <span class="perf-delta is-down">&darr; <?= abs($pending_delta) ?>%</span>
                                <?php else: ?>
                                    <span class="perf-delta is-flat">0.0%</span>
                                <?php endif; ?>
                            </div>
                            <span class="perf-subtext"><?= $cur_pending_pct ?>% of total bookings</span>
                        </div>
                    </div>
                </div>

                <!-- KPI 4: Cancellation Rate -->
                <div class="perf-tile is-red" data-metric="cancellation_rate" title="Cancellation Rate: <?= $cnl_rate ?>% (<?= number_format($all_cnl) ?> of <?= number_format($all_bks) ?>)">
                    <div class="perf-tile-inner">
                        <div class="perf-kpi-icon is-red" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </div>
                        <div class="perf-kpi-body">
                            <span class="perf-label">Cancellation Rate</span>
                            <!-- Avg Confirmed Value -->
                            <div class="perf-val-row">
                                <span class="perf-value <?= $cnl_val_class ?>"><?= $cnl_rate ?>%</span>
                                <?php if ($all_bks === 0 && $prev_total === 0): ?>
                                    <span class="perf-delta is-flat">0.0%</span>
                                <?php elseif ($prev_total === 0): ?>
                                    <span class="perf-delta is-new">New</span>
                                <?php elseif ($cnl_rate_delta > 0): ?>
                                    <span class="perf-delta is-down">&uarr; <?= $cnl_rate_delta ?>%</span>
                                <?php elseif ($cnl_rate_delta < 0): ?>
                                    <span class="perf-delta is-up">&darr; <?= abs($cnl_rate_delta) ?>%</span>
                                <?php else: ?>
                                    <span class="perf-delta is-flat">0.0%</span>
                                <?php endif; ?>
                            </div>
                            <span class="perf-subtext">of total bookings</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Grid: Booking & Revenue Trends (2fr) + Booking Status Donut (1fr) -->
            <div class="perf-grid mb-3">
                <!-- Large Combo Trend Chart -->
                <div class="perf-chart-box">
                    <div class="perf-box-header d-flex justify-content-between align-items-center flex-wrap mb-2">
                        <div class="d-flex align-items-center my-1">
                            <svg width="18" height="18" class="text-primary mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="18" y1="20" x2="18" y2="10"></line>
                                <line x1="12" y1="20" x2="12" y2="4"></line>
                                <line x1="6" y1="20" x2="6" y2="14"></line>
                            </svg>
                            <span class="perf-box-title">Booking &amp; Revenue Trends</span>
                        </div>
                        <div class="d-flex align-items-center flex-wrap my-1" style="gap: 12px;">
                            <div class="perf-chart-legend" aria-hidden="true">
                                <span class="perf-legend-item">
                                    <span class="perf-legend-box" style="background-color: #3b82f6;"></span>
                                    <span>Bookings</span>
                                </span>
                                <span class="perf-legend-item">
                                    <span class="perf-legend-dot-line">
                                        <span class="perf-legend-line" style="background-color: #1d4ed8;"></span>
                                        <span class="perf-legend-dot" style="background-color: #1d4ed8;"></span>
                                    </span>
                                    <span>Revenue (<?= CURRENCY ?>)</span>
                                </span>
                            </div>
                            <div class="perf-interval-wrap">
                                <select id="perfIntervalSelect" class="form-control form-control-sm perf-interval-select" aria-label="Select aggregation interval">
                                    <option value="daily" selected>Daily</option>
                                    <option value="weekly">Weekly</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="perf-chart-container position-relative">
                        <canvas id="perfChart" role="img" aria-label="<?= e("{$window_days}-day daily bookings and revenue combo chart") ?>" data-chart="<?= e($chart_json) ?>"></canvas>
                        <div id="perfTrendsEmpty" class="perf-empty-state" style="display: none;">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-muted mb-2" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="3"></rect>
                                <path d="M7 15l3-3 3 2 4-5"></path>
                            </svg>
                            <div class="font-weight-bold text-dark">No booking activity recorded</div>
                            <div class="text-muted small">No reservation volume or revenue found for the selected timeframe.</div>
                        </div>
                        <noscript>
                            <div class="p-3 text-muted text-center border rounded">Interactive chart requires JavaScript. See table below.</div>
                        </noscript>
                    </div>
                </div>

                <!-- Booking Status Donut Chart Panel -->
                <div class="perf-donut-box">
                    <div class="perf-box-header d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <svg width="18" height="18" class="text-primary mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M12 2a10 10 0 0 1 10 10h-10z"></path>
                            </svg>
                            <span class="perf-box-title">Booking Status</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-link text-muted p-0 perf-info-btn" title="Distribution of terminal outcome statuses across all reservations in the selected window" aria-label="Status distribution info">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="perf-donut-container position-relative">
                        <canvas id="perfDonut" role="img" aria-label="Booking outcome distribution chart"></canvas>
                    </div>
                    <!-- Accessible Status Legend with Counts & Percentages -->
                    <div class="perf-status-legend mt-2" role="list" aria-label="Booking status distribution">
                        <div class="perf-status-legend-item" role="listitem">
                            <div class="perf-status-dot-row">
                                <span class="perf-status-dot is-confirmed" aria-hidden="true"></span>
                                <span class="perf-status-name">Confirmed</span>
                            </div>
                            <span class="perf-status-value"><?= number_format($cur_confirmed_bks) ?> (<?= $cur_confirmed_pct ?>%)</span>
                        </div>
                        <div class="perf-status-legend-item" role="listitem">
                            <div class="perf-status-dot-row">
                                <span class="perf-status-dot is-pending" aria-hidden="true"></span>
                                <span class="perf-status-name">Pending</span>
                            </div>
                            <span class="perf-status-value"><?= number_format($cur_pending_bks) ?> (<?= $cur_pending_pct ?>%)</span>
                        </div>
                        <div class="perf-status-legend-item" role="listitem">
                            <div class="perf-status-dot-row">
                                <span class="perf-status-dot is-cancelled" aria-hidden="true"></span>
                                <span class="perf-status-name">Cancelled</span>
                            </div>
                            <span class="perf-status-value"><?= number_format($all_cnl) ?> (<?= $cnl_rate ?>%)</span>
                        </div>
                        <?php if ($cur_expired_bks > 0): ?>
                            <div class="perf-status-legend-item" role="listitem">
                                <div class="perf-status-dot-row">
                                    <span class="perf-status-dot is-expired" aria-hidden="true"></span>
                                    <span class="perf-status-name">Expired</span>
                                </div>
                                <span class="perf-status-value"><?= number_format($cur_expired_bks) ?> (<?= $cur_expired_pct ?>%)</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Key Insights Section (Privacy-First) -->
            <div class="perf-insights-card mb-3">
                <div class="perf-insights-header d-flex justify-content-between align-items-center flex-wrap">
                    <div class="d-flex align-items-center my-1">
                        <div class="perf-insights-icon mr-2" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-1 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"></path>
                                <path d="M9 18h6"></path>
                                <path d="M10 22h4"></path>
                            </svg>
                        </div>
                        <h3 class="perf-insights-title mb-0">Key Insights</h3>
                    </div>
                    <div class="perf-privacy-badge my-1" title="Strict privacy controls active: passenger names, phone numbers, emails, PNRs, and individual booking references are excluded from this overview.">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        <span>Aggregate analytics only &bull; Personal booking details hidden</span>
                    </div>
                </div>
                <div class="perf-insights-grid mt-3">
                    <div class="perf-insight-item">
                        <div class="perf-insight-icon-wrap is-blue" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                <polyline points="17 6 23 6 23 12"></polyline>
                            </svg>
                        </div>
                        <div class="perf-insight-content">
                            <h4 class="perf-insight-heading"><?= e($insight_vol_title) ?></h4>
                            <p class="perf-insight-desc"><?= e($insight_vol_body) ?></p>
                        </div>
                    </div>
                    <div class="perf-insight-item">
                        <div class="perf-insight-icon-wrap is-green" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        <div class="perf-insight-content">
                            <h4 class="perf-insight-heading"><?= e($insight_conf_title) ?></h4>
                            <p class="perf-insight-desc"><?= e($insight_conf_body) ?></p>
                        </div>
                    </div>
                    <div class="perf-insight-item">
                        <div class="perf-insight-icon-wrap is-amber" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="20" x2="18" y2="10"></line>
                                <line x1="12" y1="20" x2="12" y2="4"></line>
                                <line x1="6" y1="20" x2="6" y2="14"></line>
                            </svg>
                        </div>
                        <div class="perf-insight-content">
                            <h4 class="perf-insight-heading"><?= e($insight_cnl_title) ?></h4>
                            <p class="perf-insight-desc"><?= e($insight_cnl_body) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Breakdown Accordion Button & CSV Export -->
            <div class="d-flex flex-wrap justify-content-between align-items-center pt-2 border-top">
                <button type="button" class="btn btn-sm btn-outline-secondary font-weight-medium my-1" data-toggle="collapse" data-target="#dailyTable" aria-expanded="false" aria-controls="dailyTable">
                    <span>📋</span> View Detailed Daily Breakdown &darr;
                </button>
                <a href="?days=<?= $window_days ?>&mode=<?= $reporting_mode ?>&export=daily_csv" class="btn btn-sm btn-outline-primary font-weight-medium my-1" title="Download comma-separated values report for current window">
                    <span>📥</span> Export Daily CSV
                </a>
            </div>

            <!-- Collapsible Detailed Breakdown Table (Strictly Aggregated, 0 PII) -->
            <div id="dailyTable" class="collapse mt-3">
                <div class="table-responsive perf-table-scroll">
                    <table class="table table-hover table-stack mb-0 perf-table">
                        <thead class="thead-light">
                            <tr>
                                <th>Date (<?= $reporting_mode === 'created' ? 'Booking' : 'Journey' ?>)</th>
                                <th class="text-right">Total Bookings</th>
                                <th class="text-right">Confirmed</th>
                                <th class="text-right">Pending</th>
                                <th class="text-right">Cancelled</th>
                                <th class="text-right">Expired</th>
                                <th class="text-right">Confirmed Revenue</th>
                                <th class="text-right">Cancel Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daily_stats)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No reservations recorded in the selected timeframe.</td></tr>
                            <?php else: ?>
                                <?php foreach ($daily_stats as $ds): ?>
                                    <?php 
                                    $d_bks = (int)$ds['daily_bookings'];
                                    $d_cf = (int)($ds['daily_confirmed'] ?? 0);
                                    $d_p = (int)($ds['daily_pending'] ?? 0);
                                    $d_cnl = (int)$ds['daily_cancelled'];
                                    $d_x = (int)($ds['daily_expired'] ?? 0);
                                    $d_rate = $d_bks > 0 ? round(($d_cnl / $d_bks) * 100, 1) : 0.0;
                                    ?>
                                    <tr>
                                        <td data-label="Date"><span class="font-weight-medium text-dark"><?= e(date('d M Y', strtotime($ds['date']))) ?></span></td>
                                        <td data-label="Total Bookings" class="text-right"><span class="badge badge-light border px-2 font-weight-bold text-dark"><?= $d_bks ?></span></td>
                                        <td data-label="Confirmed" class="text-right"><span class="badge badge-success px-2"><?= $d_cf ?></span></td>
                                        <td data-label="Pending" class="text-right"><span class="badge badge-warning px-2"><?= $d_p ?></span></td>
                                        <td data-label="Cancelled" class="text-right"><span class="badge badge-danger px-2"><?= $d_cnl ?></span></td>
                                        <td data-label="Expired" class="text-right"><span class="badge badge-secondary px-2"><?= $d_x ?></span></td>
                                        <td data-label="Confirmed Revenue" class="text-right font-weight-bold text-success"><?= CURRENCY ?><?= number_format((float)$ds['daily_rev'], 2) ?></td>
                                        <td data-label="Cancel Rate" class="text-right font-weight-bold text-muted"><?= $d_rate ?>%</td>
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

<!-- 6. Route Intelligence: Top Transit Corridors -->
<section class="dash-section">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center">
            <h6 class="mb-0 font-weight-bold text-dark d-flex align-items-center my-1">
                <svg width="18" height="18" class="mr-2 text-primary"><use href="#icon-route"></use></svg>
                Top Transit Corridors
            </h6>
            <div class="corridor-nav-tabs my-1">
                <button type="button" class="btn btn-sm btn-primary active" id="btnRankVolume" onclick="switchCorridorRank('volume')">
                    By Reservation Volume
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRankRevenue" onclick="switchCorridorRank('revenue')">
                    By Route Revenue
                </button>
            </div>
        </div>
        <div class="card-body p-3">
            <div class="corridor-meta-note mb-3">
                <span id="corridorRankCaption">Ranked by total confirmed ticket reservations.</span>
            </div>

            <!-- Volume View -->
            <div id="corridorVolumeList" class="corridor-list">
                <?php if (empty($top_routes)): ?>
                    <div class="p-4 text-center text-muted">
                        <svg width="32" height="32" class="text-muted mb-2"><use href="#icon-route"></use></svg>
                        <div>No route booking data yet.</div>
                    </div>
                <?php else: ?>
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
                <?php endif; ?>
            </div>

            <!-- Revenue View (Hidden by default) -->
            <div id="corridorRevenueList" class="corridor-list" style="display: none;">
                <?php if (empty($top_routes_revenue)): ?>
                    <div class="p-4 text-center text-muted">
                        <div>No route revenue data available.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($top_routes_revenue as $i => $tr): ?>
                        <?php
                        $rev_val = (float)$tr['route_revenue'];
                        $bar_pct = min(100, round(($rev_val / $max_corridor_revenue) * 100));
                        ?>
                        <div class="corridor-row">
                            <span class="corridor-rank"><?= ($i + 1) ?></span>
                            <div class="corridor-main">
                                <div class="corridor-route-header">
                                    <span class="corridor-title"><?= e($tr['city1']) ?> &rarr; <?= e($tr['city2']) ?></span>
                                    <span class="corridor-bus-badge">🚌 <?= e($tr['bus']) ?></span>
                                </div>
                                <div class="corridor-bar-track">
                                    <div class="corridor-bar-fill bg-success" style="width: <?= $bar_pct ?>%;"></div>
                                </div>
                            </div>
                            <div class="corridor-stats">
                                <span class="corridor-revenue text-success font-weight-bold"><?= CURRENCY ?><?= number_format($rev_val, 2) ?></span>
                                <span class="corridor-tickets"><?= (int)$tr['total_tickets'] ?> tickets</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- 7. Attention-Needed Operational Panel -->
<section class="dash-section" aria-label="Attention Needed Items">
    <div class="card attention-card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="mb-0 font-weight-bold text-dark d-flex align-items-center">
                    <span class="attention-header-dot mr-2" aria-hidden="true"></span>
                    Attention Needed
                    <!-- Attention-Needed Items compatibility -->
                </h6>
                <p class="text-muted small mb-0 mt-0">Issues requiring review or action</p>
            </div>
            <?php if (!empty($attention_items)): ?>
                <span class="badge attention-count-badge font-weight-bold"><?= count($attention_items) ?> issue<?= count($attention_items) === 1 ? '' : 's' ?></span>
            <?php else: ?>
                <span class="badge badge-success font-weight-bold">All Clear</span>
            <?php endif; ?>
        </div>
        <div class="card-body p-3">
            <?php if (empty($attention_items)): ?>
                <div class="attention-empty">
                    <div class="attention-empty-icon mb-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    </div>
                    <h6 class="font-weight-bold text-dark mb-1">All clear</h6>
                    <p class="small text-muted mb-0">No outstanding issues require attention.</p>
                </div>
            <?php else: ?>
                <div class="attention-list">
                    <?php foreach ($attention_items as $item): ?>
                        <div class="attention-item attention-row priority-<?= e($item['priority'] ?? 'attention') ?> level-<?= e($item['level']) ?>">
                            <div class="attention-indicator-wrap" aria-hidden="true">
                                <span class="attention-dot"></span>
                            </div>
                            <div class="attention-content">
                                <div class="attention-meta-line">
                                    <span class="attention-badge priority-badge-<?= e($item['priority'] ?? 'attention') ?>">
                                        <?= $item['priority'] === 'critical' ? 'Critical' : ($item['priority'] === 'high' ? 'High Priority' : 'Attention') ?>
                                    </span>
                                    <span class="attention-title"><?= e($item['title']) ?></span>
                                </div>
                                <div class="attention-desc"><?= e($item['desc']) ?></div>
                            </div>
                            <?php if (!empty($item['action_url'])): ?>
                                <div class="attention-action-wrap">
                                    <a href="<?= e($item['action_url']) ?>" class="attention-action-link">
                                        <?= e($item['action_label']) ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Corridor Switcher Script -->
<script>
function switchCorridorRank(type) {
    var volList = document.getElementById('corridorVolumeList');
    var revList = document.getElementById('corridorRevenueList');
    var btnVol = document.getElementById('btnRankVolume');
    var btnRev = document.getElementById('btnRankRevenue');
    var caption = document.getElementById('corridorRankCaption');

    if (type === 'revenue') {
        volList.style.display = 'none';
        revList.style.display = 'block';
        btnVol.className = 'btn btn-sm btn-outline-secondary';
        btnRev.className = 'btn btn-sm btn-primary active';
        caption.textContent = 'Ranked by total gross route revenue from confirmed bookings.';
    } else {
        volList.style.display = 'block';
        revList.style.display = 'none';
        btnVol.className = 'btn btn-sm btn-primary active';
        btnRev.className = 'btn btn-sm btn-outline-secondary';
        caption.textContent = 'Ranked by total confirmed ticket reservations.';
    }
}
</script>

<!-- Dashboard Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"
    integrity="sha384-vsrfeLOOY6KuIYKDlmVH5UiBmgIdB1oEf7p01YgWHuqmOHfZr374+odEv96n9tNC"
    crossorigin="anonymous" defer></script>
<script src="<?= BASE_URL ?>/assets/js/perf-chart.js" defer></script>