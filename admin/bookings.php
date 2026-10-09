<?php
// admin/bookings.php -- Booking Management (P-03, P-04, P-05)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$has_status = table_has_column($link, 'booking', 'status');

// Handle Add Booking (O6 / P-03 / P-04, Phase A Item 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check'])) {
    csrf_verify();
    require_role('super_admin', 'operator');
    $trip_id = (int)($_POST['trip_id'] ?? 0);
    $bus = trim((string)($_POST['bus'] ?? ''));
    $unm = trim((string)($_POST['unm'] ?? ''));
    $num = trim((string)($_POST['num'] ?? ''));
    $from = trim((string)($_POST['from'] ?? ''));
    $to = trim((string)($_POST['to'] ?? ''));
    $date = trim((string)($_POST['date'] ?? ''));
    $time = trim((string)($_POST['time'] ?? ''));
    $seat = (int)($_POST['seat'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);

    // If trip_id provided, look up route by primary key (A3)
    $route_pk = table_has_column($link, 'route', 'sno') ? 'sno' : 'id';
    $matched_route = null;
    if ($trip_id > 0) {
        $has_archived_route = table_has_column($link, 'route', 'archived_at');
        $arch_where = $has_archived_route ? " AND archived_at IS NULL" : "";
        $matched_route = db_one($link, "SELECT * FROM route WHERE `{$route_pk}` = ?{$arch_where} LIMIT 1", 'i', [$trip_id]);
        if ($matched_route) {
            $bus = (string)$matched_route['busno'];
            $from = (string)$matched_route['city1'];
            $to = (string)$matched_route['city2'];
            $time = (string)$matched_route['time'];
        }
    }

    // Check active route and server-side tariff (Issues 8, 12)
    if (!$matched_route) {
        $has_archived_route = table_has_column($link, 'route', 'archived_at');
        $route_query = $has_archived_route 
            ? "SELECT price FROM route WHERE busno = ? AND city1 = ? AND city2 = ? AND `time` = ? AND archived_at IS NULL LIMIT 1"
            : "SELECT price FROM route WHERE busno = ? AND city1 = ? AND city2 = ? AND `time` = ? LIMIT 1";
        $matched_route = db_one($link, $route_query, 'ssss', [$bus, $from, $to, $time]);
    }

    if (!$matched_route) {
        $_SESSION['form_old'] = $_POST;
        flash_set('danger', "No active route found for bus '{$bus}' from '{$from}' to '{$to}' departing at {$time}. Please verify schedule.");
    } else {
        $official_tariff = (float)$matched_route['price'];
        $override_reason = trim((string)($_POST['override_reason'] ?? ''));
        $price_override = isset($_POST['price_override']) && $_POST['price_override'] !== '' ? (float)$_POST['price_override'] : null;

        if (is_super_admin() && $price_override !== null && $price_override > 0 && strlen($override_reason) < 10) {
            $_SESSION['form_old'] = $_POST;
            flash_set('danger', 'Price override requires a justification reason of at least 10 characters.');
            $redirect_url = BASE_URL . '/admin/bookings.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
            header('Location: ' . $redirect_url, true, 303);
            exit;
        }

        $booking_params = [
            'id'      => 0, // Admin-created booking
            'bus'     => $bus,
            'city1'   => $from,
            'city2'   => $to,
            'date'    => $date,
            'time'    => $time,
            'seat'    => $seat,
            'name'    => $unm,
            'contact' => $num
        ];

        if (is_super_admin() && $price_override !== null && $price_override > 0 && strlen($override_reason) >= 10) {
            $booking_params['price_override'] = $price_override;
            $booking_params['override_reason'] = $override_reason;
        }

        $res = create_booking($link, $booking_params);

        if ($res['ok']) {
            unset($_SESSION['form_old']);
            $audit_payload = ['pnr' => $res['pnr'], 'bus' => $bus, 'seat' => $seat, 'name' => $unm, 'date' => $date];
            if (!empty($booking_params['price_override'])) {
                $audit_payload['price_override'] = $price_override;
                $audit_payload['official_tariff'] = $official_tariff;
                $audit_payload['override_reason'] = $override_reason;
            }
            try {
                audit($link, 'CREATE', 'booking', null, null, $audit_payload);
            } catch (Throwable $e) {}
            flash_set('success', "Booking confirmed! PNR: {$res['pnr']}, Seat: #{$seat}");
        } else {
            $_SESSION['form_old'] = $_POST;
            flash_set('danger', $res['error']);
        }
    }
    $redirect_url = BASE_URL . '/admin/bookings.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
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
            audit($link, 'CANCEL', 'booking', $delete_id, $old_booking ?: null, ['status' => 'Cancelled']);
            flash_set('success', 'Booking status marked as Cancelled (seat liberated, audit preserved).');
        } else {
            flash_set('info', 'Booking was already cancelled or could not be found.');
        }
    }
    $redirect_url = BASE_URL . '/admin/bookings.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

// Handle Bulk Actions (Phase D Item 12)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    csrf_verify();
    $bulk_action = (string)$_POST['bulk_action'];
    $selected_ids = array_values(array_filter(array_map('intval', (array)($_POST['selected_ids'] ?? [])), fn($v) => $v > 0));

    if (empty($selected_ids)) {
        flash_set('warning', 'Please select at least one reservation to perform bulk operations.');
        $redirect_url = BASE_URL . '/admin/bookings.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
        header('Location: ' . $redirect_url, true, 303);
        exit;
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
        flash_set('success', "Bulk cancel complete: {$cancelled_count} reservation(s) cancelled and seats liberated.");
        $redirect_url = BASE_URL . '/admin/bookings.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
        header('Location: ' . $redirect_url, true, 303);
        exit;
    } elseif ($bulk_action === 'export') {
        $is_viewer = !can_write();
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $types_str = str_repeat('i', count($selected_ids));
        $export_rows = db_all($link, "SELECT sno, pnr, bus, name, contact, city1, city2, `date`, `time`, seat, price, status FROM booking WHERE sno IN ({$placeholders}) ORDER BY sno DESC", $types_str, $selected_ids);
        try {
            audit($link, 'EXPORT', 'booking', null, null, ['format' => 'csv', 'count' => count($export_rows), 'bulk' => true, 'masked' => $is_viewer]);
        } catch (Throwable $e) {}

        $headers = ['Booking ID', 'PNR', 'Bus Number', 'Passenger Name', 'Contact Phone', 'Origin', 'Destination', 'Travel Date', 'Departure Time', 'Seat Number', 'Tariff Paid', 'Status'];
        $cleaned_export = [];
        foreach ($export_rows as $er) {
            $contact = $is_viewer ? mask_phone((string)($er['contact'] ?? '')) : (string)($er['contact'] ?? '');
            $cleaned_export[] = [
                $er['sno'], $er['pnr'], $er['bus'], $er['name'], $contact,
                $er['city1'], $er['city2'], $er['date'], $er['time'], $er['seat'],
                $er['price'], $er['status']
            ];
        }
        export_csv('bookings-selected-' . date('Ymd-His') . '.csv', $headers, $cleaned_export);
    }
}

// Query active trips/routes with vehicle info (A3)
$has_arch_route = table_has_column($link, 'route', 'archived_at');
$has_arch_bus = table_has_column($link, 'buses', 'archived_at');
$has_layout_bus = table_has_column($link, 'buses', 'layout');
$has_cap_bus = table_has_column($link, 'buses', 'capacity');

$trip_where = [];
if ($has_arch_route) {
    $trip_where[] = 'r.archived_at IS NULL';
}
if ($has_arch_bus) {
    $trip_where[] = '(b.archived_at IS NULL OR b.archived_at = "")';
}
$trip_where_sql = !empty($trip_where) ? 'WHERE ' . implode(' AND ', $trip_where) : '';
$cap_select = $has_cap_bus ? 'COALESCE(b.capacity, 36) AS capacity' : '36 AS capacity';
$layout_select = $has_layout_bus ? "COALESCE(b.layout, '2+2') AS layout" : "'2+2' AS layout";

$trip_sql = "SELECT r.*, {$cap_select}, {$layout_select}
             FROM route r 
             LEFT JOIN buses b ON r.busno = b.bus_number 
             {$trip_where_sql}
             ORDER BY r.city1 ASC, r.city2 ASC, r.time ASC";
$active_trips = db_all($link, $trip_sql);

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
    $escaped_search = escape_like($search);
    $s_param = '%' . $escaped_search . '%';
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

// CSV Export (Issues 7, 27)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $is_viewer = !can_write();
    $export_rows = !empty($params) 
        ? db_all($link, "SELECT sno, pnr, bus, name, contact, city1, city2, `date`, `time`, seat, price, status FROM booking {$where_sql} ORDER BY sno DESC", $types, $params)
        : db_all($link, "SELECT sno, pnr, bus, name, contact, city1, city2, `date`, `time`, seat, price, status FROM booking {$where_sql} ORDER BY sno DESC");

    try {
        audit($link, 'EXPORT', 'booking', null, null, [
            'format'  => 'csv',
            'count'   => count($export_rows),
            'masked'  => $is_viewer,
            'filters' => compact('selected_filter', 'search', 'filter_bus', 'filter_date_from', 'filter_date_to')
        ]);
    } catch (Throwable $e) {}

    $headers = ['Booking ID', 'PNR', 'Bus Number', 'Passenger Name', 'Contact Phone', 'Origin', 'Destination', 'Travel Date', 'Departure Time', 'Seat Number', 'Tariff Paid', 'Status'];
    $cleaned_export = [];
    foreach ($export_rows as $er) {
        $contact = $is_viewer ? mask_phone((string)($er['contact'] ?? '')) : (string)($er['contact'] ?? '');
        $cleaned_export[] = [
            $er['sno'], $er['pnr'], $er['bus'], $er['name'], $contact,
            $er['city1'], $er['city2'], $er['date'], $er['time'], $er['seat'],
            $er['price'], $er['status']
        ];
    }
    export_csv('bookings-export-' . date('Ymd-His') . '.csv', $headers, $cleaned_export);
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
$form_old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

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

<!-- Search & Filter Card (Item 8) -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="bookings.php" method="get" class="form-row align-items-end">
            <div class="col-md-3 mb-2 mb-md-0">
                <label for="filter_q" class="small font-weight-bold text-muted mb-1">Search Keyword</label>
                <input type="text" id="filter_q" name="q" class="form-control form-control-sm" placeholder="PNR, name, phone, city..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2 mb-2 mb-md-0">
                <label for="filter_bus" class="small font-weight-bold text-muted mb-1">Fleet Bus</label>
                <select id="filter_bus" name="bus" class="form-control form-control-sm">
                    <option value="">All Buses</option>
                    <?php foreach ($buses as $b_opt): ?>
                        <option value="<?= e($b_opt['bus_number']) ?>" <?= $filter_bus === $b_opt['bus_number'] ? 'selected' : '' ?>>
                            <?= e($b_opt['bus_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 mb-2 mb-md-0">
                <label for="filter_from_date" class="small font-weight-bold text-muted mb-1">From Date</label>
                <input type="date" id="filter_from_date" name="from_date" class="form-control form-control-sm" value="<?= e($filter_date_from) ?>">
            </div>
            <div class="col-md-2 mb-2 mb-md-0">
                <label for="filter_to_date" class="small font-weight-bold text-muted mb-1">To Date</label>
                <input type="date" id="filter_to_date" name="to_date" class="form-control form-control-sm" value="<?= e($filter_date_to) ?>">
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
                'Pending'   => $is_curr ? 'btn-warning text-dark font-weight-bold' : 'btn-outline-warning text-dark',
                'Expired'   => $is_curr ? 'btn-secondary' : 'btn-outline-secondary',
                'Cancelled' => $is_curr ? 'btn-danger' : 'btn-outline-danger',
                default     => $is_curr ? 'btn-dark' : 'btn-outline-secondary',
            };
            ?>
            <a href="?<?= http_build_query($tab_params) ?>" class="btn <?= $btn_style ?>" <?= $is_curr ? 'aria-current="true"' : '' ?>><?= $st ?></a>
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
            <button type="button" id="btnTriggerBulkCancel" class="btn btn-outline-danger btn-sm font-weight-bold" data-toggle="modal" data-target="#bulkCancelModal">
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
        <table class="table table-hover table-stack mb-0">
            <caption class="sr-only">Passenger reservations, ticket statuses, and journey details</caption>
            <thead class="thead-light">
                <tr>
                    <th scope="col" class="th-checkbox text-center">
                        <input type="checkbox" id="selectAllBookings" title="Select all on this page" aria-label="Select all bookings on this page">
                    </th>
                    <th scope="col">PNR</th>
                    <th scope="col">Bus</th>
                    <th scope="col">Passenger</th>
                    <th scope="col">Contact</th>
                    <th scope="col">Route</th>
                    <th scope="col">Date & Time</th>
                    <th scope="col">Seat</th>
                    <th scope="col">Status</th>
                    <th scope="col">Fare</th>
                    <th scope="col" class="text-right">Actions</th>
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
                        if ($is_pending) $badge_class = 'warning text-dark';
                        elseif ($is_expired) $badge_class = 'secondary';
                        elseif ($is_cancelled) $badge_class = 'danger';
                        ?>
                        <tr class="<?= ($is_cancelled || $is_expired) ? 'text-muted' : '' ?>">
                            <td class="text-center" data-label="Select">
                                <input type="checkbox" name="selected_ids[]" value="<?= e($sno) ?>" form="bulkBookingsForm" class="booking-select-cb" aria-label="Select booking <?= e($display_pnr !== '' ? $display_pnr : ('#' . $sno)) ?>">
                            </td>
                            <td data-label="PNR"><code><?= e($display_pnr !== '' ? $display_pnr : ('#' . $sno)) ?></code></td>
                            <td data-label="Bus"><strong><?= e($row['bus'] ?? '') ?></strong></td>
                            <td data-label="Passenger" class="font-weight-medium text-dark"><?= e($row['name'] ?? '') ?></td>
                            <td data-label="Contact"><?= e($row['contact'] ?? '') ?></td>
                            <td data-label="Route"><?= e($row['city1'] ?? '') ?> &rarr; <?= e($row['city2'] ?? '') ?></td>
                            <td data-label="Date & Time"><?= e($row['date'] ?? '') ?><br><small class="text-muted"><?= e($row['time'] ?? '') ?></small></td>
                            <td data-label="Seat"><span class="badge badge-info px-2 py-1">Seat #<?= e((string)$row['seat']) ?></span></td>
                            <td data-label="Status"><span class="badge badge-<?= $badge_class ?>"><?= e($status) ?></span></td>
                            <td data-label="Fare" class="font-weight-bold text-dark"><?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                            <td data-label="Actions" class="text-right">
                                <?php if (can_write()): ?>
                                <a href="<?= BASE_URL ?>/admin/edit/edit-booking.php?id=<?= e($sno) ?>" class="btn btn-outline-secondary btn-sm">Edit</a>
                                <?php endif; ?>
                                <?php if (is_super_admin() && !$is_cancelled && !$is_expired): ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm ml-1 btn-cancel-booking"
                                            data-toggle="modal" data-target="#cancelBookingModal"
                                            data-id="<?= e($sno) ?>"
                                            data-pnr="<?= e($display_pnr !== '' ? $display_pnr : ('#' . $sno)) ?>"
                                            data-name="<?= e($row['name'] ?? '') ?>"
                                            data-seat="<?= e((string)$row['seat']) ?>">
                                        Cancel booking
                                    </button>
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


<!-- Booking Modal (A3) -->
<div class="modal fade" id="addBookingModal" tabindex="-1" role="dialog" aria-labelledby="addBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold" id="addBookingModalLabel">Create Passenger Reservation</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <form action="" method="post" id="adminBookingForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="bus" id="trip_bus" value="<?= e($form_old['bus'] ?? '') ?>">
                    <input type="hidden" name="from" id="trip_from" value="<?= e($form_old['from'] ?? '') ?>">
                    <input type="hidden" name="to" id="trip_to" value="<?= e($form_old['to'] ?? '') ?>">
                    <input type="hidden" name="time" id="trip_time" value="<?= e($form_old['time'] ?? '') ?>">

                    <!-- Trip Dropdown (A3) -->
                    <div class="form-group mb-3">
                        <label for="trip_select" class="font-weight-bold small text-muted">Scheduled Trip <span class="text-danger">*</span></label>
                        <select name="trip_id" id="trip_select" class="form-control" required>
                            <option value="">-- Select Trip (Bus | Origin &rarr; Destination | Departure) --</option>
                            <?php 
                            $route_pk = table_has_column($link, 'route', 'sno') ? 'sno' : 'id';
                            foreach ($active_trips as $row): 
                                $rid = (int)($row[$route_pk] ?? $row['id'] ?? $row['sno'] ?? 0);
                                $is_selected = ((int)($form_old['trip_id'] ?? 0) === $rid)
                                    || (($form_old['bus'] ?? '') === $row['busno'] && ($form_old['from'] ?? '') === $row['city1'] && ($form_old['to'] ?? '') === $row['city2'] && ($form_old['time'] ?? '') === $row['time']);
                            ?>
                                <option value="<?= $rid ?>"
                                        data-bus="<?= e($row['busno']) ?>"
                                        data-from="<?= e($row['city1']) ?>"
                                        data-to="<?= e($row['city2']) ?>"
                                        data-time="<?= e($row['time']) ?>"
                                        data-price="<?= e(number_format((float)$row['price'], 2, '.', '')) ?>"
                                        data-capacity="<?= (int)($row['capacity'] ?? 36) ?>"
                                        data-layout="<?= e($row['layout'] ?? '2+2') ?>"
                                        <?= $is_selected ? 'selected' : '' ?>>
                                    <?= e($row['busno']) ?> &bull; <?= e($row['city1']) ?> &rarr; <?= e($row['city2']) ?> &bull; <?= e($row['time']) ?> &bull; Fare: <?= CURRENCY ?><?= e(number_format((float)$row['price'], 2)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Select active schedule route to configure vehicle, cities, and standard tariff.</small>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label for="date" class="font-weight-bold small text-muted">Travel Date <span class="text-danger">*</span></label>
                            <input type="date" min="<?= date('Y-m-d') ?>" name="date" id="date" class="form-control" value="<?= e($form_old['date'] ?? date('Y-m-d')) ?>" required />
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="amount" class="font-weight-bold small text-muted">Official Fare (<?= CURRENCY ?>)</label>
                            <input type="text" name="amount" class="form-control bg-light" id="amount" value="<?= e((string)($form_old['amount'] ?? '')) ?>" readonly placeholder="0.00" required>
                            <small class="form-text text-muted">Official read-only tariff for this schedule.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label for="unm" class="font-weight-bold small text-muted">Passenger Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="unm" id="unm" class="form-control" value="<?= e($form_old['unm'] ?? '') ?>" placeholder="e.g. Jane Doe" required />
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="num" class="font-weight-bold small text-muted">Contact Phone <span class="text-danger">*</span></label>
                            <input type="tel" name="num" id="num" class="form-control" value="<?= e($form_old['num'] ?? '') ?>" placeholder="e.g. +91 9876543210" required />
                        </div>
                    </div>

                    <?php if (is_super_admin()): ?>
                    <div class="card bg-light border p-3 mb-3" id="superAdminOverrideCard">
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="enablePriceOverride" <?= !empty($form_old['price_override']) ? 'checked' : '' ?>>
                            <label class="custom-control-label font-weight-bold text-dark small" for="enablePriceOverride">
                                Super Admin: Price Override (Custom Tariff)
                            </label>
                        </div>
                        <div id="priceOverrideFields" class="<?= empty($form_old['price_override']) ? 'd-none' : '' ?>">
                            <div class="form-row">
                                <div class="col-md-5 form-group mb-2">
                                    <label for="price_override" class="font-weight-bold small text-muted">Override Fare (<?= CURRENCY ?>)</label>
                                    <input type="number" step="0.01" min="0" name="price_override" id="price_override" class="form-control form-control-sm" value="<?= e((string)($form_old['price_override'] ?? '')) ?>" placeholder="New fare" <?= empty($form_old['price_override']) ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-7 form-group mb-2">
                                    <label for="override_reason" class="font-weight-bold small text-muted">Justification Reason (min 10 chars) <span class="text-danger">*</span></label>
                                    <input type="text" name="override_reason" id="override_reason" class="form-control form-control-sm" minlength="10" value="<?= e($form_old['override_reason'] ?? '') ?>" placeholder="Reason for fare exception" <?= empty($form_old['price_override']) ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-group mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="seat_no" id="seat_label" class="font-weight-bold small text-muted mb-0">Seat Number</label>
                            <small id="seatAvailHelp" class="text-muted">Select trip &amp; date to preview seat availability</small>
                        </div>
                        <input type="number" min="1" max="36" name="seat" class="form-control" id="seat_no" value="<?= e((string)($form_old['seat'] ?? '')) ?>" placeholder="Seat #" required>
                    </div>

                    <label class="font-weight-bold small text-muted mb-2">Seat Visualizer &bull; Click to Assign</label>
                    <div class="p-3 border rounded mb-4 bg-light" style="max-height: 190px; overflow-y: auto;">
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

<!-- Cancel Booking Confirmation Modal (A9) -->
<div class="modal fade" id="cancelBookingModal" tabindex="-1" role="dialog" aria-labelledby="cancelBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold" id="cancelBookingModalLabel">Confirm Reservation Cancellation</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="" id="cancelBookingForm">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_id" id="cancelBookingId" value="">
                <div class="modal-body p-4">
                    <p class="mb-2">Are you sure you want to cancel the following passenger reservation?</p>
                    <div class="bg-light p-3 rounded border mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">PNR / Ticket:</span>
                            <code class="font-weight-bold" id="cancelModalPnr"></code>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Passenger:</span>
                            <span class="font-weight-bold text-dark" id="cancelModalName"></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Allocated Seat:</span>
                            <span class="badge badge-info px-2" id="cancelModalSeat"></span>
                        </div>
                    </div>
                    <p class="small text-danger mb-0">This action will void the ticket, release the seat lock immediately, and record an audit trail event.</p>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Keep Reservation</button>
                    <button type="submit" name="delete_booking" class="btn btn-danger btn-sm font-weight-bold">
                        Cancel booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Cancel Confirmation Modal (A9) -->
<div class="modal fade" id="bulkCancelModal" tabindex="-1" role="dialog" aria-labelledby="bulkCancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold" id="bulkCancelModalLabel">Confirm Bulk Cancellation</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-2">Are you sure you want to cancel all <strong id="bulkCancelCountDisplay" class="text-danger">0</strong> selected reservations?</p>
                <p class="small text-muted mb-0">All selected tickets will be marked as cancelled, their seats liberated, and individual audit logs recorded.</p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Keep Reservations</button>
                <button type="button" class="btn btn-danger btn-sm font-weight-bold" id="confirmBulkCancelBtn">
                    Confirm Bulk Cancellation
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tripSelect = document.getElementById('trip_select');
    var dateInput = document.getElementById('date');
    var seatInput = document.getElementById('seat_no');
    var seatLabel = document.getElementById('seat_label');
    var gridContainer = document.getElementById('seatGridContainer');
    var seatHelp = document.getElementById('seatAvailHelp');
    var amountInput = document.getElementById('amount');

    var tripBus = document.getElementById('trip_bus');
    var tripFrom = document.getElementById('trip_from');
    var tripTo = document.getElementById('trip_to');
    var tripTime = document.getElementById('trip_time');

    var enableOverride = document.getElementById('enablePriceOverride');
    var overrideFields = document.getElementById('priceOverrideFields');
    var overrideInput = document.getElementById('price_override');
    var overrideReason = document.getElementById('override_reason');

    if (enableOverride) {
        enableOverride.addEventListener('change', function() {
            if (this.checked) {
                if (overrideFields) overrideFields.classList.remove('d-none');
                if (overrideInput) overrideInput.disabled = false;
                if (overrideReason) {
                    overrideReason.disabled = false;
                    overrideReason.required = true;
                }
            } else {
                if (overrideFields) overrideFields.classList.add('d-none');
                if (overrideInput) {
                    overrideInput.value = '';
                    overrideInput.disabled = true;
                }
                if (overrideReason) {
                    overrideReason.value = '';
                    overrideReason.disabled = true;
                    overrideReason.required = false;
                }
            }
        });
    }

    var currentBookedSeats = [];

    function rebuildSeatGrid(capacity, bookedSeats) {
        if (!gridContainer || !seatInput) return;
        bookedSeats = bookedSeats || [];
        currentBookedSeats = bookedSeats;
        seatInput.max = capacity;
        if (seatLabel) seatLabel.innerText = 'Seat Number (1-' + capacity + '):';
        seatInput.placeholder = '1 - ' + capacity;
        gridContainer.innerHTML = '';

        var currentSelected = parseInt(seatInput.value, 10) || 0;

        for (var i = 1; i <= capacity; i++) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.style.width = '42px';
            btn.style.height = '36px';
            btn.innerText = i;
            btn.setAttribute('data-seat', i);

            var isTaken = bookedSeats.indexOf(i) !== -1;
            if (isTaken) {
                btn.className = 'btn btn-secondary btn-sm m-1 disabled';
                btn.disabled = true;
                btn.title = 'Seat #' + i + ' is already reserved';
                btn.style.opacity = '0.45';
                btn.style.cursor = 'not-allowed';
            } else if (currentSelected === i) {
                btn.className = 'btn btn-success btn-sm m-1 font-weight-bold shadow-sm';
                btn.title = 'Selected Seat #' + i;
            } else {
                btn.className = 'btn btn-outline-info btn-sm m-1';
                btn.title = 'Seat #' + i + ' (Available)';
            }

            btn.onclick = function() {
                var sNum = parseInt(this.getAttribute('data-seat'), 10);
                if (bookedSeats.indexOf(sNum) !== -1) return;
                seatInput.value = sNum;
                // Re-render highlight
                var allBtns = gridContainer.querySelectorAll('button[data-seat]');
                allBtns.forEach(function(b) {
                    var n = parseInt(b.getAttribute('data-seat'), 10);
                    if (bookedSeats.indexOf(n) === -1) {
                        if (n === sNum) {
                            b.className = 'btn btn-success btn-sm m-1 font-weight-bold shadow-sm';
                        } else {
                            b.className = 'btn btn-outline-info btn-sm m-1';
                        }
                    }
                });
            };

            gridContainer.appendChild(btn);
        }
    }

    function refreshSeatAvailability() {
        if (!tripSelect) return;
        var opt = tripSelect.options[tripSelect.selectedIndex];
        if (!opt || !opt.value) {
            if (gridContainer) gridContainer.innerHTML = '<span class="text-muted small">Please select a trip above to view seating chart.</span>';
            if (seatHelp) seatHelp.innerText = 'Select a trip to view seats';
            return;
        }

        var bus = opt.getAttribute('data-bus') || '';
        var from = opt.getAttribute('data-from') || '';
        var to = opt.getAttribute('data-to') || '';
        var time = opt.getAttribute('data-time') || '';
        var price = opt.getAttribute('data-price') || '0.00';
        var capacity = parseInt(opt.getAttribute('data-capacity') || '36', 10) || 36;
        var dateVal = dateInput ? dateInput.value : '';

        if (tripBus) tripBus.value = bus;
        if (tripFrom) tripFrom.value = from;
        if (tripTo) tripTo.value = to;
        if (tripTime) tripTime.value = time;
        if (amountInput) amountInput.value = price;

        if (!dateVal) {
            rebuildSeatGrid(capacity, []);
            if (seatHelp) seatHelp.innerText = 'Select travel date to check availability';
            return;
        }

        if (seatHelp) seatHelp.innerText = 'Checking seat availability...';

        fetch('api-seats.php?bus=' + encodeURIComponent(bus) + '&date=' + encodeURIComponent(dateVal) + '&time=' + encodeURIComponent(time))
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.ok && Array.isArray(data.booked_seats)) {
                    var booked = data.booked_seats;
                    rebuildSeatGrid(capacity, booked);
                    var freeCount = capacity - booked.length;
                    if (seatHelp) seatHelp.innerHTML = '<span class="text-success font-weight-bold">' + freeCount + ' / ' + capacity + ' seats available</span>';
                    // If currently selected seat is taken, clear it
                    var curr = parseInt(seatInput.value, 10);
                    if (curr && booked.indexOf(curr) !== -1) {
                        seatInput.value = '';
                    }
                } else {
                    rebuildSeatGrid(capacity, []);
                    if (seatHelp) seatHelp.innerText = 'Live availability check unavailable';
                }
            })
            .catch(function() {
                rebuildSeatGrid(capacity, []);
                if (seatHelp) seatHelp.innerText = 'Could not fetch live seat status';
            });
    }

    if (tripSelect) {
        tripSelect.addEventListener('change', refreshSeatAvailability);
    }
    if (dateInput) {
        dateInput.addEventListener('change', refreshSeatAvailability);
    }

    // Input event on seat_no to synchronize visual selection
    if (seatInput) {
        seatInput.addEventListener('input', function() {
            var val = parseInt(this.value, 10) || 0;
            if (gridContainer) {
                var allBtns = gridContainer.querySelectorAll('button[data-seat]');
                allBtns.forEach(function(b) {
                    var n = parseInt(b.getAttribute('data-seat'), 10);
                    if (currentBookedSeats.indexOf(n) === -1) {
                        if (n === val) {
                            b.className = 'btn btn-success btn-sm m-1 font-weight-bold shadow-sm';
                        } else {
                            b.className = 'btn btn-outline-info btn-sm m-1';
                        }
                    }
                });
            }
        });
    }

    // Initial load
    if (tripSelect && tripSelect.value) {
        refreshSeatAvailability();
    } else if (gridContainer) {
        gridContainer.innerHTML = '<span class="text-muted small">Please select a trip above to view seating chart.</span>';
    }

    // Auto open modal if returning from a failed submission
    <?php if (!empty($form_old)): ?>
    if (window.jQuery && jQuery.fn.modal) {
        jQuery('#addBookingModal').modal('show');
    }
    <?php endif; ?>

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

    // Destructive Action Modal Wiring (A9)
    if (window.jQuery) {
        jQuery('.btn-cancel-booking').on('click', function() {
            var $btn = jQuery(this);
            jQuery('#cancelBookingId').val($btn.data('id'));
            jQuery('#cancelModalPnr').text($btn.data('pnr'));
            jQuery('#cancelModalName').text($btn.data('name'));
            jQuery('#cancelModalSeat').text('#' + $btn.data('seat'));
        });

        jQuery('#btnTriggerBulkCancel').on('click', function() {
            var count = jQuery('.booking-select-cb:checked').length;
            jQuery('#bulkCancelCountDisplay').text(count);
        });

        jQuery('#confirmBulkCancelBtn').on('click', function() {
            var form = document.getElementById('bulkBookingsForm');
            if (form) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'bulk_action';
                hidden.value = 'cancel';
                form.appendChild(hidden);
                form.submit();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>