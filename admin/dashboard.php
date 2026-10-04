<?php
// admin/dashboard.php -- Executive KPI & Operational Summary (P-01, P-02, N-06)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$total_bookings = (int)(db_one($link, 'SELECT COUNT(*) AS c FROM booking')['c'] ?? 0);
$total_buses = (int)(db_one($link, 'SELECT COUNT(*) AS c FROM buses')['c'] ?? 0);
$total_routes = (int)(db_one($link, 'SELECT COUNT(*) AS c FROM route')['c'] ?? 0);
$total_customers = (int)(db_one($link, 'SELECT COUNT(*) AS c FROM costumer')['c'] ?? 0);
$total_admins = (int)(db_one($link, 'SELECT COUNT(*) AS c FROM admin')['c'] ?? 0);
$total_queries = (int)(db_one($link, 'SELECT COUNT(*) AS c FROM `query`')['c'] ?? 0);

// P-01: Capacity-aware total seats
if (table_has_column($link, 'buses', 'capacity')) {
    $seats_row = db_one($link, 'SELECT COALESCE(SUM(capacity), 0) AS total FROM buses');
    $total_seats = (int)($seats_row['total'] ?? 0);
} else {
    $total_seats = $total_buses * BUS_SEATS;
}

// P-02: Earnings excluding Cancelled & Expired bookings, formatted with CURRENCY
if (table_has_column($link, 'booking', 'status')) {
    $earnings_row = db_one($link, "SELECT COALESCE(SUM(price), 0) AS cost FROM booking WHERE status = 'Confirmed' OR status IS NULL");
} else {
    $earnings_row = db_one($link, 'SELECT COALESCE(SUM(price), 0) AS cost FROM booking');
}
$total_earnings = number_format((float)($earnings_row['cost'] ?? 0), 2);
?>
<div class="dashboard-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-dark font-weight-bold mb-1">System Overview</h2>
            <p class="text-muted mb-0">Live summary of reservations, fleet status, customer inquiries, and system health.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/diagnostics.php" class="btn btn-outline-info btn-sm shadow-sm">
            <span class="badge badge-success mr-1">&bull;</span> System Diagnostics
        </a>
    </div>

    <div class="row">
        <!-- Bookings Card -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="badge badge-info p-2 mb-2 font-weight-bold">Bookings</span>
                    <h6 class="text-muted font-weight-normal mt-2">Total Reservations</h6>
                    <h2 class="font-weight-bold mb-0"><?= e($total_bookings) ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0 text-right pb-3">
                    <a href="<?= BASE_URL ?>/admin/bookings.php" class="text-info font-weight-bold text-decoration-none">
                        View Details &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Buses Card -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="badge badge-success p-2 mb-2 font-weight-bold">Fleet</span>
                    <h6 class="text-muted font-weight-normal mt-2">Active Buses</h6>
                    <h2 class="font-weight-bold mb-0"><?= e($total_buses) ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0 text-right pb-3">
                    <a href="<?= BASE_URL ?>/admin/buses.php" class="text-success font-weight-bold text-decoration-none">
                        Manage Fleet &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Routes Card -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="badge badge-danger p-2 mb-2 font-weight-bold">Routes</span>
                    <h6 class="text-muted font-weight-normal mt-2">Configured Routes</h6>
                    <h2 class="font-weight-bold mb-0"><?= e($total_routes) ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0 text-right pb-3">
                    <a href="<?= BASE_URL ?>/admin/routes.php" class="text-danger font-weight-bold text-decoration-none">
                        Manage Routes &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Fleet Capacity Card (P-01) -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="badge badge-warning p-2 mb-2 font-weight-bold text-white">Seats</span>
                    <h6 class="text-muted font-weight-normal mt-2">Total Fleet Capacity</h6>
                    <h2 class="font-weight-bold mb-0"><?= e($total_seats) ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0 text-right pb-3">
                    <a href="<?= BASE_URL ?>/admin/seats.php" class="text-warning font-weight-bold text-decoration-none">
                        Seat Map &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Customers Card (N-03) -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="badge badge-primary p-2 mb-2 font-weight-bold">Customers</span>
                    <h6 class="text-muted font-weight-normal mt-2">Registered Accounts</h6>
                    <h2 class="font-weight-bold mb-0"><?= e($total_customers) ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0 text-right pb-3">
                    <a href="<?= BASE_URL ?>/admin/customers.php" class="text-primary font-weight-bold text-decoration-none">
                        View Customers &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Customer Queries Card (N-06) -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="badge badge-secondary p-2 mb-2 font-weight-bold">Queries</span>
                    <h6 class="text-muted font-weight-normal mt-2">Inquiries & Feedback</h6>
                    <h2 class="font-weight-bold mb-0"><?= e($total_queries) ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0 text-right pb-3">
                    <a href="<?= BASE_URL ?>/admin/queries.php" class="text-secondary font-weight-bold text-decoration-none">
                        View Inquiries &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Administrators Card -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="badge badge-dark p-2 mb-2 font-weight-bold">Administrators</span>
                    <h6 class="text-muted font-weight-normal mt-2">Admin Accounts</h6>
                    <h2 class="font-weight-bold mb-0"><?= e($total_admins) ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0 text-right pb-3">
                    <a href="<?= BASE_URL ?>/admin/add-admin.php" class="text-dark font-weight-bold text-decoration-none">
                        Manage Admins &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Confirmed Revenue Card (P-02) -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body">
                    <span class="badge badge-success p-2 mb-2 font-weight-bold">Revenue</span>
                    <h6 class="text-muted font-weight-normal mt-2">Confirmed Revenue</h6>
                    <h2 class="font-weight-bold text-success mb-0"><?= CURRENCY ?><?= e($total_earnings) ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0 text-right pb-3">
                    <a href="<?= BASE_URL ?>/admin/bookings.php" class="text-success font-weight-bold text-decoration-none">
                        Sales Ledger &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>