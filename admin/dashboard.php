<?php
// admin/dashboard.php -- Executive KPI & Operational Summary (P-01, P-02, N-06)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

// Consolidated Executive KPIs via single DB round-trip (P-10)
$has_status = table_has_column($link, 'booking', 'status');
$has_cap = table_has_column($link, 'buses', 'capacity');

$rev_where = $has_status ? "WHERE status = 'Confirmed' OR status IS NULL" : "";
$cap_select = $has_cap ? "(SELECT COALESCE(SUM(capacity), 0) FROM buses) AS total_seats" : "((SELECT COUNT(*) FROM buses) * " . BUS_SEATS . ") AS total_seats";

$kpi = db_one($link, "
    SELECT
      (SELECT COUNT(*) FROM booking) AS total_bookings,
      (SELECT COALESCE(SUM(price), 0) FROM booking {$rev_where}) AS total_revenue,
      (SELECT COUNT(*) FROM costumer) AS total_customers,
      (SELECT COUNT(*) FROM buses) AS total_buses,
      (SELECT COUNT(*) FROM route) AS total_routes,
      (SELECT COUNT(*) FROM admin) AS total_admins,
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