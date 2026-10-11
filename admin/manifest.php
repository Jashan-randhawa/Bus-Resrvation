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
$selected_route = trim((string)($_GET['route'] ?? ''));

// Date validation (Issue 32)
$raw_date = trim((string)($_GET['date'] ?? ''));
$date_error = null;
if ($raw_date !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $raw_date);
    if ($d && $d->format('Y-m-d') === $raw_date) {
        $selected_date = $raw_date;
    } else {
        $date_error = "Invalid journey date format '{$raw_date}'. Please enter date as YYYY-MM-DD.";
        $selected_date = date('Y-m-d');
    }
} else {
    $selected_date = date('Y-m-d');
}

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

// Fetch confirmed passengers for selected bus and date (Issue 4)
$where_clauses = ["bus = ?", "`date` = ?"];
$params = [$selected_bus, $selected_date];
$types = 'ss';

if (table_has_column($link, 'booking', 'status')) {
    // Only confirmed passengers count toward manifest and revenue
    $where_clauses[] = "(status IS NULL OR status = 'Confirmed')";
}

$parts = [];
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

// Separate query for pending / unconfirmed holds (Issue 4)
$unconfirmed_holds = [];
if ($selected_bus !== '' && table_has_column($link, 'booking', 'status')) {
    $hold_clauses = ["bus = ?", "`date` = ?", "status = 'Pending'"];
    $hold_params = [$selected_bus, $selected_date];
    $hold_types = 'ss';
    if ($selected_route !== '' && count($parts) === 2) {
        $hold_clauses[] = "city1 = ? AND city2 = ?";
        $hold_params[] = trim($parts[0]);
        $hold_params[] = trim($parts[1]);
        $hold_types .= 'ss';
    }
    $unconfirmed_holds = db_all($link, 
        "SELECT sno, seat, hold_expires_at FROM booking WHERE " . implode(' AND ', $hold_clauses),
        $hold_types,
        $hold_params
    );
}
$unconfirmed_hold_count = count($unconfirmed_holds);

// Metrics (calculated strictly on confirmed passengers)
$booked_count = count($passengers);
$vacant_count = max(0, $capacity - $booked_count);
$occupancy_rate = $capacity > 0 ? round(($booked_count / $capacity) * 100, 1) : 0;
$total_revenue = 0.0;
foreach ($passengers as $p) {
    $total_revenue += (float)($p['price'] ?? 0);
}

// CSV Export (Issues 4, 7)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $is_viewer = !can_write();
    try {
        audit($link, 'EXPORT', 'manifest', null, null, [
            'bus' => $selected_bus,
            'date' => $selected_date,
            'passenger_count' => $booked_count,
            'masked' => $is_viewer
        ]);
    } catch (Throwable $e) {}

    $csv_headers = ['Seat #', 'Passenger Name', 'Contact Phone', 'Origin', 'Destination', 'Departure Time', 'PNR', 'Fare', 'Status'];
    $csv_rows = [];
    foreach ($passengers as $p) {
        $contact = $is_viewer ? mask_phone((string)($p['contact'] ?? '')) : (string)($p['contact'] ?? '');
        $csv_rows[] = [
            $p['seat'],
            $p['name'],
            $contact,
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
    .admin-sidebar, .admin-topbar, .admin-sidebar-footer, .no-print, .btn, .filter-card {
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
    <div class="d-flex align-items-center flex-wrap gap-2 manifest-header-actions">
        <?php if ($selected_bus !== ''): ?>
            <a href="?<?= http_build_query(['bus' => $selected_bus, 'date' => $selected_date, 'route' => $selected_route, 'export' => 'csv']) ?>" class="btn btn-outline-success btn-sm mr-2 shadow-sm font-weight-bold d-inline-flex align-items-center">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="mr-1.5" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export Manifest CSV
            </a>
            <button onclick="window.print()" class="btn btn-primary btn-sm shadow-sm font-weight-bold mr-2 d-inline-flex align-items-center">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="mr-1.5" aria-hidden="true">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Print Manifest
            </button>
            <button type="button" id="clearBoardingBtn" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold d-inline-flex align-items-center" title="Reset all boarding checks for this manifest">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="mr-1.5" aria-hidden="true">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                Reset Boarding
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Card (No Print) -->
<div class="card manifest-filter-card no-print filter-card">
    <div class="manifest-filter-body">
        <form action="manifest.php" method="get" class="form-row align-items-end">
            <div class="col-md-3 mb-3 mb-md-0">
                <label for="manifest_bus" class="manifest-field-label">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="6" width="18" height="12" rx="2"></rect>
                        <circle cx="7" cy="18" r="2"></circle>
                        <circle cx="17" cy="18" r="2"></circle>
                        <line x1="3" y1="11" x2="21" y2="11"></line>
                    </svg>
                    Select Bus
                </label>
                <div class="manifest-select-wrap">
                    <select id="manifest_bus" name="bus" class="form-control manifest-control manifest-dropdown-select" required>
                        <option value="">-- Choose Bus --</option>
                        <?php foreach ($buses as $b): ?>
                            <option value="<?= e($b['bus_number']) ?>" <?= $selected_bus === $b['bus_number'] ? 'selected' : '' ?>>
                                <?= e($b['bus_number']) ?> (<?= (int)($b['capacity'] ?? 36) ?> seats)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <label for="manifest_date" class="manifest-field-label">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    Travel Date
                </label>
                <input type="date" id="manifest_date" name="date" class="form-control manifest-control" value="<?= e($selected_date) ?>" required>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <label for="manifest_route" class="manifest-field-label">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
                    </svg>
                    Filter Route (Optional)
                </label>
                <div class="manifest-select-wrap">
                    <select id="manifest_route" name="route" class="form-control manifest-control manifest-dropdown-select">
                        <option value="">All Corridors for this Bus</option>
                        <?php foreach ($routes as $r): ?>
                            <?php $r_label = $r['city1'] . ' -> ' . $r['city2']; ?>
                            <option value="<?= e($r_label) ?>" <?= $selected_route === $r_label ? 'selected' : '' ?>>
                                <?= e($r_label) ?> (Dep: <?= e($r['time']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary manifest-submit-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Load Manifest
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Official Trip Sheet & Manifest -->
<div class="card manifest-sheet-card border-0 shadow-sm mb-4">
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
                    <div class="manifest-metric-label">ASSIGNED BUS</div>
                    <div class="manifest-metric-val text-primary"><?= e($selected_bus ?: 'None') ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-2">
                <div class="manifest-metric-box">
                    <div class="manifest-metric-label">TOTAL CAPACITY</div>
                    <div class="manifest-metric-val text-dark"><?= $capacity ?> Seats</div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-2">
                <div class="manifest-metric-box">
                    <div class="manifest-metric-label">BOOKED / VACANT</div>
                    <div class="manifest-metric-val text-success"><?= $booked_count ?> / <span class="text-muted"><?= $vacant_count ?></span></div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-2">
                <div class="manifest-metric-box">
                    <div class="manifest-metric-label">OCCUPANCY RATE</div>
                    <div class="manifest-metric-val stat-link-info"><?= $occupancy_rate ?>%</div>
                </div>
            </div>
        </div>

        <!-- Passenger Table -->
        <?php if (!empty($date_error)): ?>
            <div class="alert alert-warning py-2 mb-3">
                <?= e($date_error) ?>
            </div>
        <?php endif; ?>

        <?php if ($unconfirmed_hold_count > 0): ?>
            <div class="alert alert-info py-2 px-3 mb-3 d-flex justify-content-between align-items-center">
                <div>
                    <strong>Unconfirmed Holds:</strong> <?= $unconfirmed_hold_count ?> seat(s) currently held pending checkout. (Excluded from passenger sheet & revenue).
                </div>
                <span class="badge badge-warning text-dark font-weight-bold">Temporary Holds</span>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-bordered table-sm table-stack mb-0">
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
                                <td data-label="Seat" class="text-center font-weight-bold">#<?= e((string)$p['seat']) ?></td>
                                <td data-label="Board" class="text-center no-print">
                                    <input type="checkbox" class="board-checkbox" data-key="manifest_boarded_<?= e($selected_bus) ?>_<?= e($selected_date) ?>_<?= e((string)$p['sno']) ?>" title="Mark Boarded" aria-label="Mark <?= e($p['name']) ?> (Seat #<?= e((string)$p['seat']) ?>) boarded">
                                </td>
                                <td data-label="Passenger" class="font-weight-medium text-dark"><?= e($p['name']) ?></td>
                                <td data-label="Contact"><?= e($p['contact']) ?></td>
                                <td data-label="Route"><?= e($p['city1']) ?> &rarr; <?= e($p['city2']) ?></td>
                                <td data-label="Time"><?= e($p['time']) ?></td>
                                <td data-label="PNR"><code><?= e($p['pnr'] ?? ('#' . $p['sno'])) ?></code></td>
                                <td data-label="Fare" class="text-right"><?= CURRENCY ?><?= e(number_format((float)($p['price'] ?? 0), 2)) ?></td>
                                <td data-label="Status" class="text-center">
                                    <span class="badge badge-<?= ($p['status'] ?? 'Confirmed') === 'Confirmed' ? 'success' : 'warning text-dark' ?>">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    var checkboxes = document.querySelectorAll('.board-checkbox');
    checkboxes.forEach(function(cb) {
        var key = cb.getAttribute('data-key');
        if (key && localStorage.getItem(key) === 'true') {
            cb.checked = true;
            var tr = cb.closest('tr');
            if (tr) tr.classList.add('table-success');
        }
        cb.addEventListener('change', function() {
            if (key) {
                localStorage.setItem(key, this.checked ? 'true' : 'false');
            }
            var tr = this.closest('tr');
            if (tr) {
                tr.classList.toggle('table-success', this.checked);
            }
        });
    });

    var clearBtn = document.getElementById('clearBoardingBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (confirm('Reset all boarding checkmarks for this manifest?')) {
                checkboxes.forEach(function(cb) {
                    var key = cb.getAttribute('data-key');
                    if (key) localStorage.removeItem(key);
                    cb.checked = false;
                    var tr = cb.closest('tr');
                    if (tr) tr.classList.remove('table-success');
                });
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>
