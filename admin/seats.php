<?php
// admin/seats.php -- Real-Time Seat Availability Map (P-07)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$buses = db_all($link, 'SELECT bus_number FROM buses ORDER BY bus_number ASC');

$selected_bus = trim((string)($_GET['bus'] ?? ''));
$selected_date = trim((string)($_GET['date'] ?? ''));
$selected_time = trim((string)($_GET['time'] ?? ''));
$searched = ($selected_bus !== '' && $selected_date !== '' && $selected_time !== '');

$booked_seats = [];
$bus_capacity = 36;

if ($searched) {
    // Read-only seat visualizer; hold release handled via background cron database/expire-holds.php (Issue 31)
    $bus_capacity = get_bus_capacity($link, $selected_bus);
    // Real-time seat allocation for specific departure time (Issue 11)
    // Queries seat locks joined to active bookings:
    // JOIN booking b ON b.sno = sl.booking_id WHERE b.status IN ('Confirmed', 'Pending')
    $booked_seats = get_booked_seats($link, $selected_bus, $selected_date, $selected_time);
}

$title = 'Seat Availability';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Seat Occupancy Visualizer</h1>
        <p class="page-subtitle">Real-time graphic map of allocated and available seating by vehicle and departure date.</p>
    </div>
</div>

<div class="row">
    <!-- Filter Panel -->
    <div class="col-lg-4 col-md-5 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Select Journey</h5>
            </div>
            <div class="card-body p-4">
                <form action="seats.php" method="get">
                    <div class="form-group">
                        <label for="bus" class="font-weight-bold small text-muted">Vehicle Bus</label>
                        <select name="bus" id="bus" class="form-control" required>
                            <option value="">Select Bus</option>
                            <?php foreach ($buses as $row): ?>
                                <option value="<?= e($row['bus_number']) ?>" <?= $selected_bus === $row['bus_number'] ? 'selected' : '' ?>>
                                    <?= e($row['bus_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date" class="font-weight-bold small text-muted">Journey Date</label>
                        <input type="date" id="date" name="date" class="form-control" value="<?= e($selected_date) ?>" required>
                    </div>
                    <div class="form-group mb-4">
                        <label for="time" class="font-weight-bold small text-muted">Departure Time</label>
                        <input type="time" id="time" name="time" class="form-control" value="<?= e($selected_time) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm">
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
                <h5 class="mb-0 font-weight-bold">Seating Layout</h5>
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
                    <div class="seat-legend justify-content-center mb-4">
                        <div class="legend-item">
                            <span class="legend-swatch" style="background-color: var(--c-error, #dc2626);"></span>
                            <span>Booked / Reserved</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-swatch" style="background-color: #ffffff; border: 1.5px solid var(--c-border-dark, #cbd5e1);"></span>
                            <span>Available</span>
                        </div>
                    </div>

                    <?= render_seat_grid($bus_capacity, $booked_seats, 4) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>