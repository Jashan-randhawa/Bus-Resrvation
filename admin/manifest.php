<?php
// admin/manifest.php -- Passenger Manifest & Trip Sheet (Phase D Item 12)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

// Accessible by all authenticated admin roles
$has_archived = table_has_column($link, 'buses', 'archived_at');
$bus_sql = $has_archived ? "SELECT bus_number, capacity FROM buses WHERE archived_at IS NULL ORDER BY bus_number ASC" : "SELECT bus_number, capacity FROM buses ORDER BY bus_number ASC";
$buses = db_all($link, $bus_sql);

$selected_bus = trim((string)($_GET['bus'] ?? ($buses[0]['bus_number'] ?? '')));
$selected_date = trim((string)($_GET['date'] ?? date('Y-m-d')));
$selected_route = trim((string)($_GET['route'] ?? ''));

// Get bus info
$bus_info = null;
if ($selected_bus !== '') {
    $bus_info = db_one($link, "SELECT bus_number, capacity FROM buses WHERE bus_number = ? LIMIT 1", 's', [$selected_bus]);
}
$capacity = (int)($bus_info['capacity'] ?? 36);

// Available routes for this bus
$routes = [];
if ($selected_bus !== '') {
    $routes = db_all($link, "SELECT DISTINCT city1, city2, `time` FROM route WHERE busno = ? ORDER BY `time` ASC", 's', [$selected_bus]);
}

// Fetch passengers / bookings for selected bus and date
$where_clauses = ["bus = ?", "`date` = ?"];
$params = [$selected_bus, $selected_date];
$types = 'ss';

if (table_has_column($link, 'booking', 'status')) {
    $where_clauses[] = "status != 'Cancelled'";
}

if ($selected_route !== '') {
    // Expected format: "City1 -> City2"
    $parts = explode(' -> ', $selected_route);
    if (count($parts) === 2) {
        $where_clauses[] = "city1 = ? AND city2 = ?";
        $params[] = trim($parts[0]);
        $params[] = trim($parts[1]);
        $types .= 'ss';
    }
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
$passengers = [];
if ($selected_bus !== '') {
    $passengers = db_all($link, 
        "SELECT sno, pnr, bus, name, contact, city1, city2, `date`, `time`, seat, price, status 
         FROM booking 
         {$where_sql} 
         ORDER BY seat ASC, `time` ASC", 
        $types, 
        $params
    );
}

// Metrics
$booked_count = count($passengers);
$vacant_count = max(0, $capacity - $booked_count);
$occupancy_rate = $capacity > 0 ? round(($booked_count / $capacity) * 100, 1) : 0;
$total_revenue = 0.0;
foreach ($passengers as $p) {
    $total_revenue += (float)($p['price'] ?? 0);
}

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    audit($link, 'EXPORT', 'manifest', null, null, [
        'bus' => $selected_bus,
        'date' => $selected_date,
        'passenger_count' => $booked_count
    ]);

    $csv_headers = ['Seat #', 'Passenger Name', 'Contact Phone', 'Origin', 'Destination', 'Departure Time', 'PNR', 'Fare', 'Status'];
    $csv_rows = [];
    foreach ($passengers as $p) {
        $csv_rows[] = [
            $p['seat'],
            $p['name'],
            $p['contact'],
            $p['city1'],
            $p['city2'],
            $p['time'],
            $p['pnr'] ?? ('#' . $p['sno']),
            number_format((float)($p['price'] ?? 0), 2),
            $p['status'] ?? 'Confirmed'
        ];
    }
    export_csv("manifest-{$selected_bus}-{$selected_date}.csv", $csv_headers, $csv_rows);
}

$title = 'Passenger Manifest';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>

<style>
@media print {
    .admin-sidebar, .admin-topbar, .admin-footer, .no-print, .btn, .filter-card {
        display: none !important;
    }
    .admin-main {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .admin-content-wrap {
        padding: 0 !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .print-only {
        display: block !important;
    }
    body {
        font-size: 11pt !important;
        background: #fff !important;
        color: #000 !important;
    }
    table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    th, td {
        border: 1px solid #333 !important;
        padding: 6px 8px !important;
    }
}
.print-only {
    display: none;
}
.manifest-metric-box {
    border-radius: 8px;
    padding: 12px 18px;
    background: var(--surface-bg, #f8f9fa);
    border: 1px solid var(--border-color, #e9ecef);
}
</style>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">Passenger Manifest &amp; Trip Sheet</h1>
        <p class="page-subtitle">View, print, and export complete passenger manifests by bus schedule and travel date.</p>
    </div>
    <div class="d-flex align-items-center">
        <?php if ($selected_bus !== ''): ?>
            <a href="?<?= http_build_query(['bus' => $selected_bus, 'date' => $selected_date, 'route' => $selected_route, 'export' => 'csv']) ?>" class="btn btn-outline-success btn-sm mr-2 shadow-sm font-weight-bold">
                📥 Export Manifest CSV
            </a>
            <button onclick="window.print()" class="btn btn-primary btn-sm shadow-sm font-weight-bold">
                🖨️ Print Manifest
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Card (No Print) -->
<div class="card border-0 shadow-sm mb-4 no-print filter-card">
    <div class="card-body p-3">
        <form action="manifest.php" method="get" class="form-row align-items-end">
            <div class="col-md-3 mb-2 mb-md-0">
                <label class="small font-weight-bold text-muted mb-1">Select Bus</label>
                <select name="bus" class="form-control form-control-sm" required>
                    <option value="">-- Choose Bus --</option>
                    <?php foreach ($buses as $b): ?>
                        <option value="<?= e($b['bus_number']) ?>" <?= $selected_bus === $b['bus_number'] ? 'selected' : '' ?>>
                            <?= e($b['bus_number']) ?> (<?= (int)($b['capacity'] ?? 36) ?> seats)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-2 mb-md-0">
                <label class="small font-weight-bold text-muted mb-1">Travel Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?= e($selected_date) ?>" required>
            </div>
            <div class="col-md-4 mb-2 mb-md-0">
                <label class="small font-weight-bold text-muted mb-1">Filter Route (Optional)</label>
                <select name="route" class="form-control form-control-sm">
                    <option value="">All Corridors for this Bus</option>
                    <?php foreach ($routes as $r): ?>
                        <?php $r_label = $r['city1'] . ' -> ' . $r['city2']; ?>
                        <option value="<?= e($r_label) ?>" <?= $selected_route === $r_label ? 'selected' : '' ?>>
                            <?= e($r_label) ?> (Dep: <?= e($r['time']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <button type="submit" class="btn btn-primary btn-sm btn-block">Load Manifest</button>
            </div>
        </form>
    </div>
</div>

<!-- Official Trip Sheet & Manifest -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <!-- Print Header -->
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
            <div>
                <h3 class="font-weight-bold mb-1">PASSENGER TRIP MANIFEST</h3>
                <div class="text-muted small">Official Dispatch Document &bull; Bus Reservation System</div>
            </div>
            <div class="text-right">
                <div class="font-weight-bold">Date: <?= e(date('d M Y', strtotime($selected_date))) ?></div>
                <div class="text-muted small">Generated: <?= date('Y-m-d H:i') ?></div>
            </div>
        </div>

        <!-- Trip Overview Metrics -->
        <div class="row mb-4">
            <div class="col-6 col-md-3 mb-2">
                <div class="manifest-metric-box">
                    <div class="small text-muted font-weight-bold">ASSIGNED BUS</div>
                    <div class="h5 font-weight-bold mb-0 text-primary"><?= e($selected_bus ?: 'None') ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-2">
                <div class="manifest-metric-box">
                    <div class="small text-muted font-weight-bold">TOTAL CAPACITY</div>
                    <div class="h5 font-weight-bold mb-0 text-dark"><?= $capacity ?> Seats</div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-2">
                <div class="manifest-metric-box">
                    <div class="small text-muted font-weight-bold">BOOKED / VACANT</div>
                    <div class="h5 font-weight-bold mb-0 text-success"><?= $booked_count ?> / <span class="text-muted"><?= $vacant_count ?></span></div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-2">
                <div class="manifest-metric-box">
                    <div class="small text-muted font-weight-bold">OCCUPANCY RATE</div>
                    <div class="h5 font-weight-bold mb-0 text-info"><?= $occupancy_rate ?>%</div>
                </div>
            </div>
        </div>

        <!-- Passenger Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 50px;" class="text-center">Seat</th>
                        <th style="width: 50px;" class="text-center no-print">Board</th>
                        <th>Passenger Name</th>
                        <th>Contact Number</th>
                        <th>Origin &rarr; Destination</th>
                        <th>Time</th>
                        <th>PNR</th>
                        <th class="text-right">Fare</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($passengers)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                No passengers booked on bus <?= e($selected_bus) ?> for <?= e($selected_date) ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($passengers as $p): ?>
                            <tr>
                                <td class="text-center font-weight-bold">#<?= e((string)$p['seat']) ?></td>
                                <td class="text-center no-print">
                                    <input type="checkbox" title="Mark Boarded">
                                </td>
                                <td class="font-weight-medium text-dark"><?= e($p['name']) ?></td>
                                <td><?= e($p['contact']) ?></td>
                                <td><?= e($p['city1']) ?> &rarr; <?= e($p['city2']) ?></td>
                                <td><?= e($p['time']) ?></td>
                                <td><code><?= e($p['pnr'] ?? ('#' . $p['sno'])) ?></code></td>
                                <td class="text-right"><?= CURRENCY ?><?= e(number_format((float)($p['price'] ?? 0), 2)) ?></td>
                                <td class="text-center">
                                    <span class="badge badge-<?= ($p['status'] ?? 'Confirmed') === 'Confirmed' ? 'success' : 'warning' ?>">
                                        <?= e($p['status'] ?? 'Confirmed') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($passengers)): ?>
                <tfoot>
                    <tr class="font-weight-bold bg-light">
                        <td colspan="7" class="text-right">Total Manifest Fare:</td>
                        <td class="text-right"><?= CURRENCY ?><?= e(number_format($total_revenue, 2)) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <!-- Driver / Conductor Sign-off Section -->
        <div class="row mt-5 pt-4 border-top">
            <div class="col-4">
                <div class="small text-muted mb-4 font-weight-bold">LEAD DRIVER SIGNATURE:</div>
                <div style="border-bottom: 1px solid #999; height: 30px;"></div>
                <div class="small text-muted mt-1">Name: ______________________</div>
            </div>
            <div class="col-4">
                <div class="small text-muted mb-4 font-weight-bold">CONDUCTOR SIGNATURE:</div>
                <div style="border-bottom: 1px solid #999; height: 30px;"></div>
                <div class="small text-muted mt-1">Name: ______________________</div>
            </div>
            <div class="col-4">
                <div class="small text-muted mb-4 font-weight-bold">DISPATCH / STATION MANAGER:</div>
                <div style="border-bottom: 1px solid #999; height: 30px;"></div>
                <div class="small text-muted mt-1">Date: ____ / ____ / ________</div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>
