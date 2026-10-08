<?php
// admin/seats.php -- Real-Time Seat Availability Map (P-07)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$buses = db_all($link, 'SELECT bus_number FROM buses ORDER BY bus_number ASC');

$selected_bus = trim((string)($_GET['bus'] ?? ''));
$selected_date = trim((string)($_GET['date'] ?? ''));
$selected_time = trim((string)($_GET['time'] ?? ''));
$searched = ($selected_bus !== '' && $selected_date !== '');

$booked_seats = [];
$bus_capacity = 36;

if ($searched) {
    release_expired_holds($link);
    $bus_capacity = get_bus_capacity($link, $selected_bus);

    if ($selected_time !== '') {
        $booked_seats = get_booked_seats($link, $selected_bus, $selected_date, $selected_time);
    } else {
        try {
            $bus_row = db_one($link, 'SELECT id FROM buses WHERE bus_number = ? LIMIT 1', 's', [$selected_bus]);
            if ($bus_row && !empty($bus_row['id'])) {
                $bus_id = (int)$bus_row['id'];
                $lock_rows = db_all($link, "SELECT sl.seat_no FROM seat_lock sl JOIN booking b ON b.sno = sl.booking_id WHERE sl.bus_id = ? AND sl.travel_date = ? AND b.status IN ('Confirmed', 'Pending')", 'is', [$bus_id, $selected_date]);
                foreach ($lock_rows as $lr) {
                    $booked_seats[(int)$lr['seat_no']] = true;
                }
            }
        } catch (Throwable $e) {}

        if (table_has_column($link, 'booking', 'status')) {
            $rows = db_all($link,
                "SELECT seat FROM booking WHERE bus = ? AND `date` = ? AND (status IS NULL OR status IN ('Confirmed', 'Pending'))",
                'ss', [$selected_bus, $selected_date]
            );
        } else {
            $rows = db_all($link,
                'SELECT seat FROM booking WHERE bus = ? AND `date` = ?',
                'ss', [$selected_bus, $selected_date]
            );
        }
        foreach ($rows as $r) {
            $booked_seats[(int)$r['seat']] = true;
        }
    }
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
                        <label for="time" class="font-weight-bold small text-muted">Departure Time (optional)</label>
                        <input type="time" id="time" name="time" class="form-control" value="<?= e($selected_time) ?>">
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
                        <div class="empty-text">Choose a bus and travel date on the left to render the live interactive seat map.</div>
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