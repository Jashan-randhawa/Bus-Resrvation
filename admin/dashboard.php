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

<div class="row">
    <!-- Bookings Card -->
    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
        <div class="stat-card stat-bookings h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Reservations</span>
                    <span class="badge badge-primary">Total</span>
                </div>
                <div class="stat-value"><?= e($total_bookings) ?></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/bookings.php" class="stat-link text-primary text-decoration-none">
                    Review Bookings &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Buses Card -->
    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
        <div class="stat-card stat-fleet h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Fleet</span>
                    <span class="badge badge-success">Active</span>
                </div>
                <div class="stat-value"><?= e($total_buses) ?></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/buses.php" class="stat-link text-success text-decoration-none">
                    Fleet Catalog &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Routes Card -->
    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
        <div class="stat-card stat-routes h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Transit Routes</span>
                    <span class="badge badge-warning">Active</span>
                </div>
                <div class="stat-value"><?= e($total_routes) ?></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/routes.php" class="stat-link text-warning text-decoration-none">
                    Manage Routes &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Seats Capacity Card -->
    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
        <div class="stat-card stat-seats h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Fleet Capacity</span>
                    <span class="badge badge-info">Seats</span>
                </div>
                <div class="stat-value"><?= e($total_seats) ?></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/seats.php" class="stat-link text-info text-decoration-none">
                    Seat Visualizer &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Customers Card -->
    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
        <div class="stat-card stat-customers h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Customers</span>
                    <span class="badge badge-light border">Registered</span>
                </div>
                <div class="stat-value"><?= e($total_customers) ?></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/customers.php" class="stat-link text-secondary text-decoration-none">
                    Customer Roster &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Customer Queries Card -->
    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
        <div class="stat-card stat-queries h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Inquiries</span>
                    <span class="badge badge-secondary">Received</span>
                </div>
                <div class="stat-value"><?= e($total_queries) ?></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/queries.php" class="stat-link text-secondary text-decoration-none">
                    Customer Messages &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Administrators Card -->
    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
        <div class="stat-card stat-admins h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Administrators</span>
                    <span class="badge badge-dark">System</span>
                </div>
                <div class="stat-value"><?= e($total_admins) ?></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/add-admin.php" class="stat-link text-dark text-decoration-none">
                    Admin Accounts &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Revenue Card -->
    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
        <div class="stat-card stat-revenue h-100 d-flex flex-column justify-content-between bg-white">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Confirmed Revenue</span>
                    <span class="badge badge-success">Audited</span>
                </div>
                <div class="stat-value text-success"><?= CURRENCY ?><?= e($total_earnings) ?></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/bookings.php" class="stat-link text-success text-decoration-none">
                    Sales Ledger &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Definitions Footnote (Issue 10) -->
<div class="row">
    <div class="col-12">
        <p class="text-muted small mt-n2 mb-4">
            <span class="mr-1">&bull; <strong>Definitions:</strong></span>
            Fleet, Transit Routes, and Customers count active (non-archived) records. Administrators count active system accounts. Confirmed Revenue reflects completed reservations only.
        </p>
    </div>
</div>

<?php
// Item 11 & Issue 9: 30-day Trends & Analytics
$window_days = 30;
$window_end = date('Y-m-d');
$window_start = date('Y-m-d', strtotime('-' . ($window_days - 1) . ' days'));

// 30-day booking & revenue daily totals with explicit date window
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

// Cancellation metrics strictly within the 30-day window (Issue 9)
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
?>

<!-- Analytics Section (Item 11) -->
<div class="row mt-2">
    <!-- Top Routes -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 font-weight-bold text-dark">Top Transit Corridors</h6>
                <span class="badge badge-light border">Most Traveled</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
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
                                        <td class="font-weight-medium text-dark">
                                            <?= e($tr['city1']) ?> &rarr; <?= e($tr['city2']) ?>
                                        </td>
                                        <td><span class="badge badge-light border">🚌 <?= e($tr['bus']) ?></span></td>
                                        <td><strong><?= (int)$tr['total_tickets'] ?></strong> tickets</td>
                                        <td class="text-right font-weight-bold text-success">
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

    <!-- 30-Day Operational Health -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 font-weight-bold text-dark">30-Day Performance Overview <small class="text-muted font-weight-normal">(<?= e(date('d M', strtotime($window_start))) ?> &ndash; <?= e(date('d M Y', strtotime($window_end))) ?>)</small></h6>
                <span class="badge badge-info">Cancellation Rate: <?= $cnl_rate ?>%</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-hover mb-0">
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
                                <tr><td colspan="4" class="text-center text-muted py-4">No reservations in the past 30 days.</td></tr>
                            <?php else: ?>
                                <?php foreach ($daily_stats as $ds): ?>
                                    <tr>
                                        <td><small class="font-weight-medium text-dark"><?= e(date('d M Y', strtotime($ds['date']))) ?></small></td>
                                        <td><span class="badge badge-primary px-2"><?= (int)$ds['daily_bookings'] ?></span></td>
                                        <td><span class="badge badge-danger px-2"><?= (int)$ds['daily_cancelled'] ?></span></td>
                                        <td class="text-right font-weight-bold text-success"><?= CURRENCY ?><?= number_format((float)$ds['daily_rev'], 2) ?></td>
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