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

// Today's Scheduled Departures (A12)
$today_date = date('Y-m-d');
$today_departures = db_all($link, "
    SELECT r.busno, r.city1, r.city2, r.`time`,
           (SELECT COUNT(*) FROM booking b WHERE b.bus = r.busno AND b.`date` = ? AND b.`time` = r.`time` AND (b.status = 'Confirmed' OR b.status IS NULL)) AS booked_count,
           COALESCE(bu.capacity, 36) AS total_capacity
    FROM route r
    LEFT JOIN buses bu ON r.busno = bu.bus_number
    WHERE (r.archived_at IS NULL)
    ORDER BY r.`time` ASC
    LIMIT 8
", 's', [$today_date]);
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Executive Dashboard</h1>
        <p class="page-subtitle">Real-time overview of ticket operations, fleet availability, user queries, and revenue.</p>
    </div>
    <div class="d-flex align-items-center">
        <a href="<?= BASE_URL ?>/admin/diagnostics.php" class="btn btn-outline-primary btn-sm">
            <span class="mr-1">⚡</span> System Health
        </a>
    </div>
</div>

<!-- Primary 4 KPI Stat Cards (A12) -->
<div class="row">
    <!-- Confirmed Revenue -->
    <div class="col-12 col-sm-6 col-lg-3 mb-4">
        <div class="stat-card stat-revenue h-100 d-flex flex-column justify-content-between bg-white border shadow-sm">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Confirmed Revenue</span>
                    <span class="badge badge-success badge-sm">Audited</span>
                </div>
                <div class="stat-value text-success"><?= CURRENCY ?><?= e($total_earnings) ?></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/bookings.php" class="stat-link stat-link-sm text-success text-decoration-none">
                    Sales Ledger &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Total Reservations -->
    <div class="col-12 col-sm-6 col-lg-3 mb-4">
        <div class="stat-card stat-bookings h-100 d-flex flex-column justify-content-between shadow-sm">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Reservations</span>
                    <span class="badge badge-primary badge-sm">Total</span>
                </div>
                <div class="stat-value"><?= e($total_bookings) ?></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/bookings.php" class="stat-link stat-link-sm text-primary text-decoration-none">
                    Review Bookings &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Active Fleet Buses -->
    <div class="col-12 col-sm-6 col-lg-3 mb-4">
        <div class="stat-card stat-fleet h-100 d-flex flex-column justify-content-between shadow-sm">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Active Fleet</span>
                    <span class="badge badge-success badge-sm">Active</span>
                </div>
                <div class="stat-value"><?= e($total_buses) ?></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/buses.php" class="stat-link stat-link-sm text-success text-decoration-none">
                    Fleet Catalog &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Active Transit Routes -->
    <div class="col-12 col-sm-6 col-lg-3 mb-4">
        <div class="stat-card stat-routes h-100 d-flex flex-column justify-content-between shadow-sm">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Transit Routes</span>
                    <span class="badge badge-warning text-dark badge-sm">Active</span>
                </div>
                <div class="stat-value"><?= e($total_routes) ?></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/routes.php" class="stat-link stat-link-sm stat-link-warn text-decoration-none">
                    Manage Routes &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Operational Metrics Strip (A12) -->
<div class="row mb-3">
    <!-- Fleet Capacity -->
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-0 shadow-sm p-3 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <span class="small font-weight-bold text-muted">Fleet Capacity</span>
                <span class="badge badge-info px-2">Seats</span>
            </div>
            <div class="h4 font-weight-bold mb-1 mt-1"><?= e($total_seats) ?></div>
            <a href="<?= BASE_URL ?>/admin/seats.php" class="small stat-link stat-link-info font-weight-bold text-decoration-none">Seat Map &rarr;</a>
        </div>
    </div>

    <!-- Customers -->
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-0 shadow-sm p-3 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <span class="small font-weight-bold text-muted">Customers</span>
                <span class="badge badge-light border">Registered</span>
            </div>
            <div class="h4 font-weight-bold mb-1 mt-1"><?= e($total_customers) ?></div>
            <a href="<?= BASE_URL ?>/admin/customers.php" class="small text-secondary font-weight-bold text-decoration-none">Customer Roster &rarr;</a>
        </div>
    </div>

    <!-- Customer Inquiries -->
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-0 shadow-sm p-3 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <span class="small font-weight-bold text-muted">Inquiries</span>
                <span class="badge badge-secondary">Received</span>
            </div>
            <div class="h4 font-weight-bold mb-1 mt-1"><?= e($total_queries) ?></div>
            <a href="<?= BASE_URL ?>/admin/queries.php" class="small text-secondary font-weight-bold text-decoration-none">Messages &rarr;</a>
        </div>
    </div>

    <!-- Administrators -->
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-0 shadow-sm p-3 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <span class="small font-weight-bold text-muted">Administrators</span>
                <span class="badge badge-dark">System</span>
            </div>
            <div class="h4 font-weight-bold mb-1 mt-1"><?= e($total_admins) ?></div>
            <a href="<?= BASE_URL ?>/admin/add-admin.php" class="small text-dark font-weight-bold text-decoration-none">Admin Accounts &rarr;</a>
        </div>
    </div>
</div>

<!-- Definitions Footnote (Issue 10) -->
<div class="row">
    <div class="col-12">
        <p class="text-muted small mb-4">
            <span class="mr-1">&bull; <strong>Definitions:</strong></span>
            Fleet, Transit Routes, and Customers count active (non-archived) records. Administrators count active system accounts. Confirmed Revenue reflects completed reservations only.
        </p>
    </div>
</div>

<!-- Today's Scheduled Departures (A12) -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 font-weight-bold text-dark">📋 Today's Scheduled Departures</h6>
                    <small class="text-muted font-weight-normal"><?= e(date('l, d F Y', strtotime($today_date))) ?></small>
                </div>
                <a href="<?= BASE_URL ?>/admin/manifest.php" class="btn btn-outline-primary btn-sm font-weight-bold">
                    View Full Manifest &rarr;
                </a>
            </div>
            <div class="card-body p-3">
                <?php if (empty($today_departures)): ?>
                    <div class="p-4 text-center text-muted">
                        No active departures configured for today.
                    </div>
                <?php else: ?>
                    <div class="dep-carousel" data-interval="3000" aria-roledescription="carousel" aria-label="Today's Departures">
                        <div class="dep-viewport">
                            <div class="dep-track">
                                <?php 
                                $total_deps = count($today_departures);
                                foreach ($today_departures as $idx => $dep): 
                                    $booked = (int)$dep['booked_count'];
                                    $cap = (int)$dep['total_capacity'];
                                    $pct = $cap > 0 ? min(100, round(($booked / $cap) * 100)) : 0;
                                    $bar_class = $pct > 80 ? 'bg-danger' : ($pct > 50 ? 'bg-warning' : 'bg-success');
                                ?>
                                    <article class="dep-card" role="group" aria-roledescription="slide" aria-label="Departure <?= ($idx + 1) ?> of <?= $total_deps ?>">
                                        <header class="dep-card-header mb-2">
                                            <span class="dep-time font-weight-bold">
                                                <span aria-hidden="true">⏰</span> <?= e($dep['time']) ?>
                                            </span>
                                            <span class="badge badge-light border">🚌 <?= e($dep['busno']) ?></span>
                                        </header>
                                        <div class="dep-route font-weight-medium text-dark mb-3" title="<?= e($dep['city1']) ?> &rarr; <?= e($dep['city2']) ?>">
                                            <?= e($dep['city1']) ?> &rarr; <?= e($dep['city2']) ?>
                                        </div>
                                        <div class="dep-occupancy mb-3">
                                            <div class="d-flex justify-content-between small text-muted mb-1">
                                                <span><?= $booked ?> / <?= $cap ?> seats</span>
                                                <span class="font-weight-bold"><?= $pct ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar <?= $bar_class ?>" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="dep-card-action mt-auto">
                                            <a href="<?= BASE_URL ?>/admin/manifest.php?bus=<?= urlencode($dep['busno']) ?>&date=<?= urlencode($today_date) ?>&time=<?= urlencode($dep['time']) ?>" class="btn btn-outline-primary btn-sm btn-block font-weight-bold">
                                                Manifest &rarr;
                                            </a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="dep-carousel-controls">
                            <button type="button" class="dep-prev" aria-label="Previous departures">&lsaquo;</button>
                            <div class="dep-dots" role="tablist" aria-label="Departure carousel slides"></div>
                            <button type="button" class="dep-next" aria-label="Next departures">&rsaquo;</button>
                            <button type="button" class="dep-toggle-pause" aria-label="Pause automatic sliding" title="Pause automatic sliding">
                                <span class="dep-pause-icon" aria-hidden="true">⏸</span>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Analytics & Trends Section (Item 11, A12) -->
<div class="row">
    <!-- Top Routes -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 font-weight-bold text-dark">Top Transit Corridors</h6>
                <span class="badge badge-light border">Most Traveled</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-stack mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Route Corridor</th>
                                <th>Bus</th>
                                <th>Bookings</th>
                                <th class="text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_routes)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No route booking data available yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($top_routes as $tr): ?>
                                    <tr>
                                        <td data-label="Route Corridor" class="font-weight-medium text-dark">
                                            <?= e($tr['city1']) ?> &rarr; <?= e($tr['city2']) ?>
                                        </td>
                                        <td data-label="Bus"><span class="badge badge-light border">🚌 <?= e($tr['bus']) ?></span></td>
                                        <td data-label="Bookings"><strong><?= (int)$tr['total_tickets'] ?></strong> tickets</td>
                                        <td data-label="Revenue" class="text-right font-weight-bold text-success">
                                            <?= CURRENCY ?><?= number_format((float)$tr['route_revenue'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 30-Day Performance Overview with 7d/30d/90d selector (A12) -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center">
                <div class="my-1">
                    <h6 class="mb-0 font-weight-bold text-dark">
                        30-Day Performance Overview
                        <small class="text-muted font-weight-normal">(<?= e(date('d M', strtotime($window_start))) ?> &ndash; <?= e(date('d M Y', strtotime($window_end))) ?>)</small>
                    </h6>
                </div>
                <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center my-1 gap-2">
                    <div class="btn-group btn-group-sm w-100 w-sm-auto" role="group" aria-label="Time window selector">
                        <a href="?days=7" class="btn btn-sm flex-fill <?= $window_days === 7 ? 'btn-primary' : 'btn-outline-secondary' ?>">7d</a>
                        <a href="?days=30" class="btn btn-sm flex-fill <?= $window_days === 30 ? 'btn-primary' : 'btn-outline-secondary' ?>">30d</a>
                        <a href="?days=90" class="btn btn-sm flex-fill <?= $window_days === 90 ? 'btn-primary' : 'btn-outline-secondary' ?>">90d</a>
                    </div>
                    <span class="badge badge-info align-self-start align-self-sm-center">Cancel Rate: <?= $cnl_rate ?>%</span>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Visual Trend Bars -->
                <?php if (!empty($daily_stats)): ?>
                    <?php
                    $max_daily = max(1, max(array_map(fn($d) => (int)$d['daily_bookings'], $daily_stats)));
                    ?>
                    <div class="p-3 bg-light border-bottom">
                        <div class="small font-weight-bold text-muted mb-2">Booking Volume Visualizer (Recent <?= min(14, count($daily_stats)) ?> Days)</div>
                        <div class="d-flex align-items-end justify-content-between" style="height: 60px; gap: 4px;">
                            <?php foreach (array_reverse(array_slice($daily_stats, 0, 14)) as $stat): ?>
                                <?php 
                                $b_cnt = (int)$stat['daily_bookings'];
                                $h_pct = max(12, round(($b_cnt / $max_daily) * 100));
                                ?>
                                <div class="d-flex flex-column align-items-center flex-grow-1" title="<?= e($stat['date']) ?>: <?= $b_cnt ?> bookings">
                                    <div class="bg-primary rounded-top w-100" style="height: <?= $h_pct ?>%; min-height: 4px;"></div>
                                    <span class="text-muted" style="font-size: 0.65rem;"><?= date('d', strtotime($stat['date'])) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="table-responsive" style="max-height: 240px; overflow-y: auto;">
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
</div>
<script src="<?= BASE_URL ?>/assets/js/dep-carousel.js" defer></script>
