<?php
// admin/bookings.php -- Booking Management (P-03, P-04, P-05)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$alert = null;
$alert_type = 'info';

$has_pnr = table_has_column($link, 'booking', 'pnr');
$has_status = table_has_column($link, 'booking', 'status');

// Handle Add Booking (O6 / P-03 / P-04, Phase A Item 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check'])) {
    csrf_verify();
    require_role('super_admin', 'operator');
    $bus = trim((string)($_POST['bus'] ?? ''));
    $unm = trim((string)($_POST['unm'] ?? ''));
    $num = trim((string)($_POST['num'] ?? ''));
    $from = trim((string)($_POST['from'] ?? ''));
    $to = trim((string)($_POST['to'] ?? ''));
    $date = trim((string)($_POST['date'] ?? ''));
    $time = trim((string)($_POST['time'] ?? ''));
    $seat = (int)($_POST['seat'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);

    // P-04: Route existence and price validation
    $matched_route = db_one($link,
        "SELECT price FROM route WHERE busno = ? AND city1 = ? AND city2 = ? AND `time` = ? LIMIT 1",
        'ssss', [$bus, $from, $to, $time]
    );

    if (!$matched_route) {
        $alert = "No active route found for bus '{$bus}' from '{$from}' to '{$to}' departing at {$time}. Please verify schedule.";
        $alert_type = 'danger';
    } else {
        if ($amount <= 0) {
            $amount = (float)$matched_route['price'];
        }

        $res = create_booking($link, [
            'id'      => 0, // Admin-created booking
            'bus'     => $bus,
            'city1'   => $from,
            'city2'   => $to,
            'date'    => $date,
            'time'    => $time,
            'seat'    => $seat,
            'price'   => $amount,
            'name'    => $unm,
            'contact' => $num
        ]);

        if ($res['ok']) {
            audit($link, 'CREATE', 'booking', null, null, ['pnr' => $res['pnr'], 'bus' => $bus, 'seat' => $seat, 'name' => $unm, 'date' => $date]);
            $alert = "Booking confirmed! PNR: {$res['pnr']}, Seat: #{$seat}";
            $alert_type = 'success';
        } else {
            $alert = $res['error'];
            $alert_type = 'danger';
        }
    }
}

// Handle Cancel Booking (O4, Phase A Item 3)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
    csrf_verify();
    require_role('super_admin');
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        $old_booking = db_one($link, "SELECT sno, pnr, bus, seat, date, time, status, name, contact FROM booking WHERE sno = ?", 'i', [$delete_id]);
        $changed = cancel_booking($link, $delete_id);
        if ($changed > 0) {
            $alert = 'Booking status marked as Cancelled (seat liberated, audit preserved).';
            $alert_type = 'success';
            audit($link, 'CANCEL', 'booking', $delete_id, $old_booking ?: null, ['status' => 'Cancelled']);
        } else {
            $alert = 'Booking was already cancelled or could not be found.';
            $alert_type = 'info';
        }
    }
}

// Handle Bulk Actions (Phase D Item 12)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    csrf_verify();
    $bulk_action = (string)$_POST['bulk_action'];
    $selected_ids = array_values(array_filter(array_map('intval', (array)($_POST['selected_ids'] ?? [])), fn($v) => $v > 0));

    if (empty($selected_ids)) {
        $alert = 'Please select at least one reservation to perform bulk operations.';
        $alert_type = 'warning';
    } elseif ($bulk_action === 'cancel') {
        require_role('super_admin');
        $cancelled_count = 0;
        foreach ($selected_ids as $sid) {
            $old_booking = db_one($link, "SELECT sno, pnr, bus, seat, date, time, status, name, contact FROM booking WHERE sno = ?", 'i', [$sid]);
            if ($old_booking && ($old_booking['status'] ?? '') !== 'Cancelled') {
                $changed = cancel_booking($link, $sid);
                if ($changed > 0) {
                    audit($link, 'CANCEL', 'booking', $sid, $old_booking, ['status' => 'Cancelled', 'bulk' => true]);
                    $cancelled_count++;
                }
            }
        }
        $alert = "Bulk cancel complete: {$cancelled_count} reservation(s) cancelled and seats liberated.";
        $alert_type = 'success';
    } elseif ($bulk_action === 'export') {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $types_str = str_repeat('i', count($selected_ids));
        $export_rows = db_all($link, "SELECT sno, pnr, bus, name, contact, city1, city2, `date`, `time`, seat, price, status FROM booking WHERE sno IN ({$placeholders}) ORDER BY sno DESC", $types_str, $selected_ids);
        audit($link, 'EXPORT', 'booking', null, null, ['format' => 'csv', 'count' => count($export_rows), 'bulk' => true]);
        $headers = ['Booking ID', 'PNR', 'Bus Number', 'Passenger Name', 'Contact Phone', 'Origin', 'Destination', 'Travel Date', 'Departure Time', 'Seat Number', 'Tariff Paid', 'Status'];
        export_csv('bookings-selected-' . date('Ymd-His') . '.csv', $headers, $export_rows);
    }
}

// Query buses with capacity (P-03)
if (table_has_column($link, 'buses', 'capacity')) {
    $buses = db_all($link, 'SELECT bus_number, capacity FROM buses ORDER BY bus_number ASC');
} else {
    $buses = db_all($link, 'SELECT bus_number, 36 AS capacity FROM buses ORDER BY bus_number ASC');
}

$from_cities = db_all($link, 'SELECT DISTINCT city1 FROM route ORDER BY city1 ASC');
$to_cities = db_all($link, 'SELECT DISTINCT city2 FROM route ORDER BY city2 ASC');

// Multi-field search and filters (Item 8)
$search = trim((string)($_GET['q'] ?? ''));
$filter_bus = trim((string)($_GET['bus'] ?? ''));
$filter_date_from = trim((string)($_GET['from_date'] ?? ''));
$filter_date_to = trim((string)($_GET['to_date'] ?? ''));
$selected_filter = trim((string)($_GET['filter_status'] ?? 'All'));

$where_clauses = [];
$params = [];
$types = '';

if ($has_status && in_array($selected_filter, ['Confirmed', 'Pending', 'Expired', 'Cancelled'], true)) {
    $where_clauses[] = 'status = ?';
    $params[] = $selected_filter;
    $types .= 's';
}

if ($search !== '') {
    $where_clauses[] = '(pnr LIKE ? OR name LIKE ? OR contact LIKE ? OR city1 LIKE ? OR city2 LIKE ?)';
    $s_param = '%' . $search . '%';
    $params = array_merge($params, [$s_param, $s_param, $s_param, $s_param, $s_param]);
    $types .= 'sssss';
}

if ($filter_bus !== '') {
    $where_clauses[] = 'bus = ?';
    $params[] = $filter_bus;
    $types .= 's';
}

if ($filter_date_from !== '') {
    $where_clauses[] = '`date` >= ?';
    $params[] = $filter_date_from;
    $types .= 's';
}

if ($filter_date_to !== '') {
    $where_clauses[] = '`date` <= ?';
    $params[] = $filter_date_to;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// CSV Export (Item 7)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    audit($link, 'EXPORT', 'booking', null, null, ['format' => 'csv', 'filters' => compact('selected_filter', 'search', 'filter_bus', 'filter_date_from', 'filter_date_to')]);
    $export_rows = !empty($params) 
        ? db_all($link, "SELECT sno, pnr, bus, name, contact, city1, city2, `date`, `time`, seat, price, status FROM booking {$where_sql} ORDER BY sno DESC", $types, $params)
        : db_all($link, "SELECT sno, pnr, bus, name, contact, city1, city2, `date`, `time`, seat, price, status FROM booking {$where_sql} ORDER BY sno DESC");

    $headers = ['Booking ID', 'PNR', 'Bus Number', 'Passenger Name', 'Contact Phone', 'Origin', 'Destination', 'Travel Date', 'Departure Time', 'Seat Number', 'Tariff Paid', 'Status'];
    export_csv('bookings-export-' . date('Ymd-His') . '.csv', $headers, $export_rows);
}

// 25-item Pagination (P-05, P-10)
$count_sql = "SELECT COUNT(*) AS c FROM booking {$where_sql}";
$count_res = !empty($params) ? db_one($link, $count_sql, $types, $params) : db_one($link, $count_sql);
$total_count = (int)($count_res['c'] ?? 0);
$pagination = paginate($total_count, 25);

$query_sql = "SELECT * FROM booking {$where_sql} ORDER BY sno DESC LIMIT ? OFFSET ?";
$query_params = array_merge($params, [$pagination['per_page'], $pagination['offset']]);
$query_types = $types . 'ii';
$bookings = db_all($link, $query_sql, $query_types, $query_params);

// Retain all current filter params for pagination links
$keep_params = array_filter([
    'filter_status' => $selected_filter !== 'All' ? $selected_filter : null,
    'q'             => $search !== '' ? $search : null,
    'bus'           => $filter_bus !== '' ? $filter_bus : null,
    'from_date'     => $filter_date_from !== '' ? $filter_date_from : null,
    'to_date'       => $filter_date_to !== '' ? $filter_date_to : null,
], fn($v) => $v !== null);

$title = 'Bookings';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Booking Management</h1>
        <p class="page-subtitle">Track passenger tickets, review reservation statuses, or create manual administrative bookings.</p>
    </div>
    <div class="d-flex align-items-center">
        <a href="?<?= http_build_query(array_merge($keep_params, ['export' => 'csv'])) ?>" class="btn btn-outline-success btn-sm mr-2 shadow-sm font-weight-bold">
            📥 Export CSV
        </a>
        <?php if (can_write()): ?>
        <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addBookingModal">
            + New Reservation
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Search & Filter Card (Item 8) -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="bookings.php" method="get" class="form-row align-items-end">
            <div class="col-md-3 mb-2 mb-md-0">
                <label class="small font-weight-bold text-muted mb-1">Search Keyword</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="PNR, name, phone, city..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2 mb-2 mb-md-0">
                <label class="small font-weight-bold text-muted mb-1">Fleet Bus</label>
                <select name="bus" class="form-control form-control-sm">
                    <option value="">All Buses</option>
                    <?php foreach ($buses as $b_opt): ?>
                        <option value="<?= e($b_opt['bus_number']) ?>" <?= $filter_bus === $b_opt['bus_number'] ? 'selected' : '' ?>>
                            <?= e($b_opt['bus_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 mb-2 mb-md-0">
                <label class="small font-weight-bold text-muted mb-1">From Date</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($filter_date_from) ?>">
            </div>
            <div class="col-md-2 mb-2 mb-md-0">
                <label class="small font-weight-bold text-muted mb-1">To Date</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($filter_date_to) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-center">
                <button type="submit" class="btn btn-primary btn-sm px-3 mr-2">Filter</button>
                <?php if (!empty($keep_params)): ?>
                    <a href="bookings.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Status Filter Tabs -->
<div class="filter-bar">
    <span class="filter-label">Filter Status:</span>
    <div class="btn-group btn-group-sm" role="group">
        <?php foreach (['All', 'Confirmed', 'Pending', 'Expired', 'Cancelled'] as $st): ?>
            <?php 
            $tab_params = array_merge($keep_params, ['filter_status' => $st]);
            $is_curr = ($selected_filter === $st);
            $btn_style = match($st) {
                'Confirmed' => $is_curr ? 'btn-success' : 'btn-outline-success',
                'Pending'   => $is_curr ? 'btn-warning text-white' : 'btn-outline-warning',
                'Expired'   => $is_curr ? 'btn-secondary' : 'btn-outline-secondary',
                'Cancelled' => $is_curr ? 'btn-danger' : 'btn-outline-danger',
                default     => $is_curr ? 'btn-dark' : 'btn-outline-secondary',
            };
            ?>
            <a href="?<?= http_build_query($tab_params) ?>" class="btn <?= $btn_style ?>"><?= $st ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Bulk Action Bar (Item 12) -->
<form id="bulkBookingsForm" method="post" action="" class="mb-3 d-none">
    <?= csrf_field() ?>
    <div class="alert alert-light border d-flex flex-wrap align-items-center justify-content-between p-2 shadow-sm mb-0">
        <div class="font-weight-bold text-dark my-1">
            <span id="selectedCount" class="badge badge-primary mr-1">0</span> reservation(s) selected
        </div>
        <div class="my-1">
            <button type="submit" name="bulk_action" value="export" class="btn btn-outline-success btn-sm font-weight-bold mr-2">
                📥 Export Selected
            </button>
            <?php if (is_super_admin()): ?>
            <button type="submit" name="bulk_action" value="cancel" class="btn btn-outline-danger btn-sm font-weight-bold" onclick="return confirm('Are you sure you want to cancel all selected reservations?');">
                🚫 Bulk Cancel
            </button>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Bookings Table Container -->
<div class="data-table-wrapper">
    <div class="table-header">
        <h5 class="mb-0">Reservations List</h5>
        <span class="record-count"><?= $pagination['total_records'] ?> record(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th style="width: 40px;" class="text-center">
                        <input type="checkbox" id="selectAllBookings" title="Select all on this page">
                    </th>
                    <th>PNR</th>
                    <th>Bus</th>
                    <th>Passenger</th>
                    <th>Contact</th>
                    <th>Route</th>
                    <th>Date & Time</th>
                    <th>Seat</th>
                    <th>Status</th>
                    <th>Fare</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="11">
                            <div class="empty-state py-5">
                                <div class="empty-icon">🎟️</div>
                                <div class="empty-title">No bookings found</div>
                                <div class="empty-text">No passenger tickets match the current criteria or filter.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $row): ?>
                        <?php
                        $sno = (int)$row['sno'];
                        $display_pnr = (string)($row['pnr'] ?? '');
                        $status = (string)($row['status'] ?? 'Confirmed');
                        $is_cancelled = ($status === 'Cancelled');
                        $is_expired = ($status === 'Expired');
                        $is_pending = ($status === 'Pending');

                        $badge_class = 'success';
                        if ($is_pending) $badge_class = 'warning text-white';
                        elseif ($is_expired) $badge_class = 'secondary';
                        elseif ($is_cancelled) $badge_class = 'danger';
                        ?>
                        <tr class="<?= ($is_cancelled || $is_expired) ? 'text-muted' : '' ?>">
                            <td class="text-center">
                                <input type="checkbox" name="selected_ids[]" value="<?= e($sno) ?>" form="bulkBookingsForm" class="booking-select-cb">
                            </td>
                            <td><code><?= e($display_pnr !== '' ? $display_pnr : ('#' . $sno)) ?></code></td>
                            <td><strong><?= e($row['bus'] ?? '') ?></strong></td>
                            <td class="font-weight-medium text-dark"><?= e($row['name'] ?? '') ?></td>
                            <td><?= e($row['contact'] ?? '') ?></td>
                            <td><?= e($row['city1'] ?? '') ?> &rarr; <?= e($row['city2'] ?? '') ?></td>
                            <td><?= e($row['date'] ?? '') ?><br><small class="text-muted"><?= e($row['time'] ?? '') ?></small></td>
                            <td><span class="badge badge-info px-2 py-1">Seat #<?= e((string)$row['seat']) ?></span></td>
                            <td><span class="badge badge-<?= $badge_class ?>"><?= e($status) ?></span></td>
                            <td class="font-weight-bold text-dark"><?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                            <td class="text-right">
                                <?php if (can_write()): ?>
                                <a href="<?= BASE_URL ?>/admin/edit/edit-booking.php?id=<?= e($sno) ?>" class="btn btn-outline-secondary btn-sm">Edit</a>
                                <?php endif; ?>
                                <?php if (is_super_admin() && !$is_cancelled && !$is_expired): ?>
                                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('Cancel this reservation and liberate the seat?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_id" value="<?= e($sno) ?>">
                                        <button type="submit" name="delete_booking" class="btn btn-outline-danger btn-sm ml-1">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($pagination, $keep_params) ?>
</div>


<!-- Booking Modal -->
<div class="modal fade" id="addBookingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold">Create Passenger Reservation</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4">
                <form action="" method="post" id="adminBookingForm">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label for="bus" class="font-weight-bold small text-muted">Assigned Bus</label>
                            <select name="bus" id="bus" class="form-control" required>
                                <option value="" data-capacity="36">Select Bus Number</option>
                                <?php foreach ($buses as $row): ?>
                                    <option value="<?= e($row['bus_number']) ?>" data-capacity="<?= (int)($row['capacity'] ?? 36) ?>">
                                        <?= e($row['bus_number']) ?> (<?= (int)($row['capacity'] ?? 36) ?> seats)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="unm" class="font-weight-bold small text-muted">Passenger Name</label>
                            <input type="text" name="unm" id="unm" class="form-control" placeholder="Full name" required />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label for="num" class="font-weight-bold small text-muted">Contact Phone</label>
                            <input type="tel" name="num" id="num" class="form-control" placeholder="Phone number" required />
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="date" class="font-weight-bold small text-muted">Travel Date</label>
                            <input type="date" min="<?= date('Y-m-d') ?>" name="date" id="date" class="form-control" required />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label for="from" class="font-weight-bold small text-muted">Departure City</label>
                            <select name="from" id="from" class="form-control" required>
                                <option value="">Select Origin City</option>
                                <?php foreach ($from_cities as $row): ?>
                                    <option value="<?= e($row['city1']) ?>"><?= e($row['city1']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="to" class="font-weight-bold small text-muted">Destination City</label>
                            <select name="to" id="to" class="form-control" required>
                                <option value="">Select Destination City</option>
                                <?php foreach ($to_cities as $row): ?>
                                    <option value="<?= e($row['city2']) ?>"><?= e($row['city2']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label for="time" class="font-weight-bold small text-muted">Departure Time</label>
                            <input type="time" name="time" id="time" class="form-control" required />
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="seat_no" id="seat_label" class="font-weight-bold small text-muted">Seat Number</label>
                            <input type="number" min="1" max="36" name="seat" class="form-control" id="seat_no" placeholder="Seat #" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="amount" class="font-weight-bold small text-muted">Price (<?= CURRENCY ?>)</label>
                            <input type="number" step="0.01" min="1" name="amount" class="form-control" id="amount" placeholder="0.00" required>
                        </div>
                    </div>

                    <label class="font-weight-bold small text-muted mb-2">Click Seat to Assign</label>
                    <div class="p-3 border rounded mb-4 bg-light" style="max-height: 180px; overflow-y: auto;">
                        <div class="d-flex flex-wrap justify-content-center" id="seatGridContainer"></div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-secondary mr-2" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="check">Confirm Reservation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var busSelect = document.getElementById('bus');
    var seatInput = document.getElementById('seat_no');
    var seatLabel = document.getElementById('seat_label');
    var gridContainer = document.getElementById('seatGridContainer');

    function rebuildSeatGrid(capacity) {
        if (!seatInput || !gridContainer) return;
        seatInput.max = capacity;
        if (seatLabel) seatLabel.innerText = 'Seat Number (1-' + capacity + '):';
        seatInput.placeholder = '1 - ' + capacity;
        gridContainer.innerHTML = '';
        for (var i = 1; i <= capacity; i++) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-info btn-sm m-1';
            btn.style.width = '42px';
            btn.style.height = '36px';
            btn.innerText = i;
            btn.setAttribute('data-seat', i);
            btn.onclick = function() {
                seatInput.value = this.getAttribute('data-seat');
            };
            gridContainer.appendChild(btn);
        }
    }

    if (busSelect) {
        busSelect.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            var cap = parseInt(opt ? opt.getAttribute('data-capacity') : '36', 10) || 36;
            rebuildSeatGrid(cap);
        });
        var initialOpt = busSelect.options[busSelect.selectedIndex];
        var initialCap = parseInt(initialOpt ? initialOpt.getAttribute('data-capacity') : '36', 10) || 36;
        rebuildSeatGrid(initialCap);
    }

    // Bulk selection logic
    var selectAll = document.getElementById('selectAllBookings');
    var checkboxes = document.querySelectorAll('.booking-select-cb');
    var bulkForm = document.getElementById('bulkBookingsForm');
    var countSpan = document.getElementById('selectedCount');

    function updateBulkState() {
        var checked = document.querySelectorAll('.booking-select-cb:checked');
        if (countSpan) countSpan.textContent = checked.length;
        if (bulkForm) {
            if (checked.length > 0) {
                bulkForm.classList.remove('d-none');
            } else {
                bulkForm.classList.add('d-none');
            }
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) {
                cb.checked = selectAll.checked;
            });
            updateBulkState();
        });
    }

    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', updateBulkState);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>