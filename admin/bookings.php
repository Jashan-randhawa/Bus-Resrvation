<?php
// admin/bookings.php -- Booking Management (P-03, P-04, P-05)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$alert = null;
$alert_type = 'info';

$has_pnr = table_has_column($link, 'booking', 'pnr');
$has_status = table_has_column($link, 'booking', 'status');

// Handle Add Booking (O6 / P-03 / P-04)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check'])) {
    csrf_verify();
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
            $alert = "Booking confirmed! PNR: {$res['pnr']}, Seat: #{$seat}";
            $alert_type = 'success';
        } else {
            $alert = $res['error'];
            $alert_type = 'danger';
        }
    }
}

// Handle Cancel Booking (O4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
    csrf_verify();
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        if ($has_status) {
            db_exec($link, "UPDATE booking SET status = 'Cancelled' WHERE sno = ?", 'i', [$delete_id]);
            $alert = 'Booking status marked as Cancelled (seat liberated, audit preserved).';
        } else {
            db_exec($link, 'DELETE FROM booking WHERE sno = ?', 'i', [$delete_id]);
            $alert = 'Booking record deleted.';
        }
        $alert_type = 'success';
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

// Status filtering (P-05)
$selected_filter = trim((string)($_GET['filter_status'] ?? 'All'));
if ($has_status && in_array($selected_filter, ['Confirmed', 'Pending', 'Expired', 'Cancelled'], true)) {
    $bookings = db_all($link, 'SELECT * FROM booking WHERE status = ? ORDER BY sno DESC', 's', [$selected_filter]);
} else {
    $bookings = db_all($link, 'SELECT * FROM booking ORDER BY sno DESC');
}

$title = 'Bookings';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="admin-content-wrap">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="text-info font-weight-bold mb-1">Booking Management</h2>
            <p class="text-muted mb-0">Search, reserve, inspect passenger tickets, and manage reservation statuses.</p>
        </div>
        <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addBookingModal">
            + New Reservation
        </button>
    </div>

    <?php if ($alert): ?>
        <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
            <?= e($alert) ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Status filter navigation (P-05) -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-2 d-flex flex-wrap align-items-center justify-content-between">
            <span class="font-weight-bold text-muted small text-uppercase">Filter Status:</span>
            <div class="btn-group btn-group-sm mt-1 mt-md-0" role="group">
                <a href="bookings.php" class="btn <?= $selected_filter === 'All' ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
                <a href="bookings.php?filter_status=Confirmed" class="btn <?= $selected_filter === 'Confirmed' ? 'btn-success' : 'btn-outline-success' ?>">Confirmed</a>
                <a href="bookings.php?filter_status=Pending" class="btn <?= $selected_filter === 'Pending' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' ?>">Pending (Hold)</a>
                <a href="bookings.php?filter_status=Expired" class="btn <?= $selected_filter === 'Expired' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Expired</a>
                <a href="bookings.php?filter_status=Cancelled" class="btn <?= $selected_filter === 'Cancelled' ? 'btn-danger' : 'btn-outline-danger' ?>">Cancelled</a>
            </div>
        </div>
    </div>

    <!-- Booking Modal (P-03: Capacity-Aware) -->
    <div class="modal fade" id="addBookingModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title font-weight-bold">Create Passenger Reservation</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form action="" method="post" id="adminBookingForm">
                        <?= csrf_field() ?>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="bus" class="font-weight-bold">Bus Number :</label>
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
                                <label for="unm" class="font-weight-bold">Passenger Name :</label>
                                <input type="text" name="unm" id="unm" class="form-control" placeholder="Enter Full Name" required />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="num" class="font-weight-bold">Contact Phone :</label>
                                <input type="tel" name="num" id="num" class="form-control" placeholder="Mobile / Phone Number" required />
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="date" class="font-weight-bold">Travel Date :</label>
                                <input type="date" min="<?= date('Y-m-d') ?>" name="date" id="date" class="form-control" required />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="from" class="font-weight-bold">From City :</label>
                                <select name="from" id="from" class="form-control" required>
                                    <option value="">Select Origin City</option>
                                    <?php foreach ($from_cities as $row): ?>
                                        <option value="<?= e($row['city1']) ?>"><?= e($row['city1']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="to" class="font-weight-bold">To City :</label>
                                <select name="to" id="to" class="form-control" required>
                                    <option value="">Select Destination City</option>
                                    <?php foreach ($to_cities as $row): ?>
                                        <option value="<?= e($row['city2']) ?>"><?= e($row['city2']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="time" class="font-weight-bold">Departure Time :</label>
                                <input type="time" name="time" id="time" class="form-control" required />
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="seat_no" id="seat_label" class="font-weight-bold">Seat Number (1-36):</label>
                                <input type="number" min="1" max="36" name="seat" class="form-control" id="seat_no" placeholder="Seat #" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="amount" class="font-weight-bold">Price (<?= CURRENCY ?>):</label>
                                <input type="number" step="0.01" min="1" name="amount" class="form-control" id="amount" placeholder="Ticket Price" required>
                            </div>
                        </div>

                        <!-- Dynamic capacity seat grid selection (P-03) -->
                        <label class="font-weight-bold">Seat Selection Grid:</label>
                        <div class="p-2 border rounded mb-3 bg-light" style="max-height: 180px; overflow-y: auto;">
                            <div class="d-flex flex-wrap justify-content-center" id="seatGridContainer">
                                <!-- Rebuilt by JavaScript when bus changes -->
                            </div>
                        </div>

                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success" name="check">Confirm Reservation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white font-weight-bold">
            All Reservations (<?= count($bookings) ?> records)
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>PNR</th>
                        <th>Bus</th>
                        <th>Passenger</th>
                        <th>Contact</th>
                        <th>Route</th>
                        <th>Date & Time</th>
                        <th>Seat</th>
                        <th>Status</th>
                        <th>Fare</th>
                        <th style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No reservations match the selected filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $row): ?>
                            <?php
                            $sno = (int)$row['sno'];
                            $display_pnr = (string)($row['pnr'] ?? '');
                            $status = (string)($row['status'] ?? 'Confirmed');
                            $is_cancelled = ($status === 'Cancelled');
                            $is_expired = ($status === 'Expired');
                            $is_pending = ($status === 'Pending');

                            // Status badge coloring (P-05)
                            $badge_class = 'success';
                            if ($is_pending) $badge_class = 'warning text-dark';
                            elseif ($is_expired) $badge_class = 'secondary';
                            elseif ($is_cancelled) $badge_class = 'danger';
                            ?>
                            <tr class="<?= ($is_cancelled || $is_expired) ? 'text-muted' : '' ?>">
                                <td><code><?= e($display_pnr !== '' ? $display_pnr : ('#' . $sno)) ?></code></td>
                                <td><strong><?= e($row['bus'] ?? '') ?></strong></td>
                                <td><?= e($row['name'] ?? '') ?></td>
                                <td><?= e($row['contact'] ?? '') ?></td>
                                <td><?= e($row['city1'] ?? '') ?> &rarr; <?= e($row['city2'] ?? '') ?></td>
                                <td><?= e($row['date'] ?? '') ?><br><small class="text-muted"><?= e($row['time'] ?? '') ?></small></td>
                                <td><span class="badge badge-info p-2">Seat #<?= e((string)$row['seat']) ?></span></td>
                                <td>
                                    <span class="badge badge-<?= $badge_class ?> p-2">
                                        <?= e($status) ?>
                                    </span>
                                </td>
                                <td><?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/edit/edit-booking.php?id=<?= e($sno) ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <?php if (!$is_cancelled && !$is_expired): ?>
                                        <form method="post" action="" style="display:inline;" onsubmit="return confirm('Cancel this reservation and liberate the seat?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="delete_id" value="<?= e($sno) ?>">
                                            <button type="submit" name="delete_booking" class="btn btn-outline-danger btn-sm">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Dynamic capacity-aware seat grid (P-03)
document.addEventListener('DOMContentLoaded', function() {
    var busSelect = document.getElementById('bus');
    var seatInput = document.getElementById('seat_no');
    var seatLabel = document.getElementById('seat_label');
    var gridContainer = document.getElementById('seatGridContainer');

    function rebuildSeatGrid(capacity) {
        seatInput.max = capacity;
        seatLabel.innerText = 'Seat Number (1-' + capacity + '):';
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
        // Initial setup
        var initialOpt = busSelect.options[busSelect.selectedIndex];
        var initialCap = parseInt(initialOpt ? initialOpt.getAttribute('data-capacity') : '36', 10) || 36;
        rebuildSeatGrid(initialCap);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>