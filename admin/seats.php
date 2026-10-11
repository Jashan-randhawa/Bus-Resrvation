<?php
// admin/seats.php -- Real-Time Seat Availability Map (P-07)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/seat-map.php';

$buses = db_all($link, 'SELECT bus_number, capacity, layout FROM buses ORDER BY bus_number ASC');

// Active scheduled departures for quick-select (A8)
$has_route_arch = table_has_column($link, 'route', 'archived_at');
$route_arch_sql = $has_route_arch ? 'WHERE archived_at IS NULL' : '';
$active_trips = db_all($link, "SELECT busno, city1, city2, `time` FROM route {$route_arch_sql} ORDER BY busno ASC, `time` ASC");

$selected_bus = trim((string)($_GET['bus'] ?? ''));
$selected_date = trim((string)($_GET['date'] ?? ''));
$selected_time = trim((string)($_GET['time'] ?? ''));
$searched = ($selected_bus !== '' && $selected_date !== '' && $selected_time !== '');

$booked_seats = [];
$seat_status_map = [];
$bus_capacity = 36;
$bus_layout = '2+2';

if ($searched) {
    // Read-only seat visualizer; hold release handled via background cron database/expire-holds.php (Issue 31)
    $bus_row = db_one($link, 'SELECT id, capacity, layout FROM buses WHERE bus_number = ? LIMIT 1', 's', [$selected_bus]);
    $bus_capacity = (int)($bus_row['capacity'] ?? get_bus_capacity($link, $selected_bus));
    $bus_layout = (string)($bus_row['layout'] ?? '2+2');

    // Real-time seat allocation for specific departure time (Issue 11)
    // Queries seat locks joined to active bookings:
    // JOIN booking b ON b.sno = sl.booking_id WHERE b.status IN ('Confirmed', 'Pending')
    $booked_seats = get_booked_seats($link, $selected_bus, $selected_date, $selected_time);

    // Differentiate Confirmed vs Pending hold (A8)
    $has_status = table_has_column($link, 'booking', 'status');
    if ($has_status) {
        $booking_status_rows = db_all($link,
            "SELECT seat, status FROM booking WHERE bus = ? AND `date` = ? AND `time` = ? AND (status IS NULL OR status IN ('Confirmed', 'Pending'))",
            'sss', [$selected_bus, $selected_date, $selected_time]
        );
        foreach ($booking_status_rows as $bsr) {
            $seat_num = (int)$bsr['seat'];
            $seat_status_map[$seat_num] = ($bsr['status'] === 'Pending') ? 'Pending' : 'Confirmed';
        }
    }
    if ($bus_row && !empty($bus_row['id'])) {
        $bus_id = (int)$bus_row['id'];
        $lock_status_rows = db_all($link,
            "SELECT sl.seat_no, b.status 
             FROM seat_lock sl 
             JOIN booking b ON b.sno = sl.booking_id 
             WHERE sl.bus_id = ? AND sl.travel_date = ? AND b.`time` = ? 
               AND b.status IN ('Confirmed', 'Pending')",
            'iss', [$bus_id, $selected_date, $selected_time]
        );
        foreach ($lock_status_rows as $lsr) {
            $seat_num = (int)$lsr['seat_no'];
            if (!isset($seat_status_map[$seat_num])) {
                $seat_status_map[$seat_num] = ($lsr['status'] === 'Pending') ? 'Pending' : 'Confirmed';
            }
        }
    }
    foreach ($booked_seats as $s_k => $s_v) {
        $s_num = is_numeric($s_v) ? (int)$s_v : (int)$s_k;
        if (!isset($seat_status_map[$s_num])) {
            $seat_status_map[$s_num] = 'Confirmed';
        }
    }
    $seat_layout = build_seat_layout($bus_capacity, $bus_layout);
}

$title = 'Seat Availability';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/seat-map.css">

<div class="page-header">
    <div>
        <h1 class="page-title">Seat Occupancy Visualizer</h1>
        <p class="page-subtitle">Real-time graphic map of allocated and available seating by vehicle and departure date.</p>
    </div>
</div>

<div class="row">
    <!-- Filter Panel -->
    <div class="col-lg-4 col-md-5 mb-4">
        <div class="journey-select-card">
            <div class="journey-select-head">
                <div class="journey-select-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </div>
                <h5 class="journey-select-title">Select Journey</h5>
            </div>
            <div class="journey-select-body">
                <form action="seats.php" method="get">
                    <!-- Scheduled Departure Quick-Select (A8) -->
                    <div class="form-group mb-3">
                        <label for="scheduled_trip" class="journey-field-label">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                            </svg>
                            Scheduled Departure Quick-Select
                        </label>
                        <div class="journey-dropdown-wrap">
                            <select id="scheduled_trip" class="form-control journey-select-control journey-dropdown-select">
                                <option value="">-- Choose Scheduled Trip --</option>
                                <?php foreach ($active_trips as $t): ?>
                                    <option value="" data-bus="<?= e($t['busno']) ?>" data-time="<?= e($t['time']) ?>">
                                        <?= e($t['busno']) ?> (<?= e($t['city1']) ?> &rarr; <?= e($t['city2']) ?> at <?= e($t['time']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Vehicle Bus -->
                    <div class="form-group mb-3">
                        <label for="bus" class="journey-field-label">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="16" rx="2"></rect>
                                <path d="M4 11h16"></path>
                                <circle cx="8" cy="15" r="1"></circle>
                                <circle cx="16" cy="15" r="1"></circle>
                            </svg>
                            Vehicle Bus
                        </label>
                        <div class="journey-dropdown-wrap">
                            <select name="bus" id="bus" class="form-control journey-select-control journey-dropdown-select" required>
                                <option value="">Select Bus</option>
                                <?php foreach ($buses as $row): ?>
                                    <option value="<?= e($row['bus_number']) ?>" <?= $selected_bus === $row['bus_number'] ? 'selected' : '' ?>>
                                        <?= e($row['bus_number']) ?> (<?= e((string)($row['capacity'] ?? 36)) ?> seats &bull; <?= e((string)($row['layout'] ?? '2+2')) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Journey Date -->
                    <div class="form-group mb-3">
                        <label for="date" class="journey-field-label">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Journey Date
                        </label>
                        <input type="date" id="date" name="date" class="form-control journey-select-control" value="<?= e($selected_date) ?>" required>
                    </div>

                    <!-- Departure Time -->
                    <div class="form-group mb-4">
                        <label for="time" class="journey-field-label">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            Departure Time
                        </label>
                        <input type="time" id="time" name="time" class="form-control journey-select-control" value="<?= e($selected_time) ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary journey-submit-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        Load Seat Map
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Visualization Grid -->
    <div class="col-lg-8 col-md-7 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 font-weight-bold d-inline-block">Seating Layout</h5>
                    <?php if ($searched): ?>
                        <span class="badge badge-secondary ml-2 font-weight-normal"><?= e($bus_layout) ?> Pattern</span>
                    <?php endif; ?>
                </div>
                <?php if ($searched): ?>
                    <span class="badge badge-light border px-2 py-1">
                        <?= count($booked_seats) ?> of <?= $bus_capacity ?> Booked (<?= max(0, $bus_capacity - count($booked_seats)) ?> Available)
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <?php if (!$searched): ?>
                    <div class="empty-state py-5">
                        <div class="empty-icon">🪑</div>
                        <div class="empty-title">Select Trip Parameters</div>
                        <div class="empty-text">Choose a bus, travel date, and departure time on the left to render the live interactive seat map.</div>
                    </div>
                <?php else: ?>
                    <div class="bus-map-wrapper">
                        <?php render_seat_map($seat_layout, $booked_seats, [
                            'bus'             => $selected_bus,
                            'date'            => $selected_date,
                            'time'            => $selected_time,
                            'admin_mode'      => true,
                            'status_map'      => $seat_status_map,
                            'confirmed_count' => count(array_filter($seat_status_map, fn($s) => $s === 'Confirmed')),
                            'pending_count'   => count(array_filter($seat_status_map, fn($s) => $s === 'Pending')),
                            'available_count' => max(0, $bus_capacity - count($booked_seats)),
                        ]); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tripSelect = document.getElementById('scheduled_trip');
    var busSelect = document.getElementById('bus');
    var timeInput = document.getElementById('time');
    if (tripSelect && busSelect && timeInput) {
        tripSelect.addEventListener('change', function() {
            var opt = tripSelect.options[tripSelect.selectedIndex];
            var b = opt.getAttribute('data-bus');
            var t = opt.getAttribute('data-time');
            if (b) busSelect.value = b;
            if (t) timeInput.value = t;
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>