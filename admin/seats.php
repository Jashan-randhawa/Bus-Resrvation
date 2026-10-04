<?php
// admin/seats.php -- Real-Time Seat Availability Map (P-07)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$buses = db_all($link, 'SELECT bus_number FROM buses ORDER BY bus_number ASC');

// Use GET for read-only searches (P-07: no re-submit prompts on refresh)
$selected_bus = trim((string)($_GET['bus'] ?? ''));
$selected_date = trim((string)($_GET['date'] ?? ''));
$selected_time = trim((string)($_GET['time'] ?? ''));
$searched = ($selected_bus !== '' && $selected_date !== '');

$booked_seats = [];
$bus_capacity = 36;

if ($searched) {
    // Sweep expired holds first (O13)
    release_expired_holds($link);
    $bus_capacity = get_bus_capacity($link, $selected_bus);

    if ($selected_time !== '') {
        $booked_seats = get_booked_seats($link, $selected_bus, $selected_date, $selected_time);
    } else {
        // If no departure time specified, look for Confirmed or Pending holds across all trips for that day
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
<div class="admin-content-wrap">
    <div class="mb-4">
        <h2 class="text-info font-weight-bold mb-1">Seat Availability Visualizer</h2>
        <p class="text-muted mb-0">Inspect real-time seat allocations, pending reservations, and available capacity by trip.</p>
    </div>

    <div class="row">
        <!-- Search Controls -->
        <div class="col-lg-5 col-md-6 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white font-weight-bold">
                    Search Trip Occupancy
                </div>
                <div class="card-body">
                    <form action="seats.php" method="get">
                        <div class="form-group">
                            <label for="bus" class="font-weight-bold">Bus Number:</label>
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
                            <label for="date" class="font-weight-bold">Travel Date:</label>
                            <input type="date" id="date" name="date" class="form-control" value="<?= e($selected_date) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="time" class="font-weight-bold">Departure Time (optional):</label>
                            <input type="time" id="time" name="time" class="form-control" value="<?= e($selected_time) ?>">
                        </div>
                        <button type="submit" class="btn btn-info btn-block shadow-sm">
                            Inspect Seat Map
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Seat Visualization Grid -->
        <div class="col-lg-7 col-md-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white font-weight-bold d-flex justify-content-between align-items-center">
                    <span>Seat Allocation Grid</span>
                    <?php if ($searched): ?>
                        <span class="badge badge-light border">
                            Bus: <?= e($selected_bus) ?> (<?= count($booked_seats) ?> / <?= $bus_capacity ?> Booked)
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$searched): ?>
                        <div class="text-center text-muted py-5">
                            <p class="mb-0">Select a bus and travel date on the left to display its live seat map.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex justify-content-center mb-3">
                            <div class="mr-3"><span class="badge badge-danger p-2 mr-1">&nbsp;</span> Booked / Held</div>
                            <div><span class="badge badge-light border p-2 mr-1">&nbsp;</span> Available</div>
                        </div>
                        <div class="d-flex flex-wrap justify-content-center p-3 bg-light rounded border">
                            <?php for ($i = 1; $i <= $bus_capacity; $i++): ?>
                                <?php $is_booked = isset($booked_seats[$i]); ?>
                                <button type="button" class="btn <?= $is_booked ? 'btn-danger' : 'btn-outline-secondary' ?> m-1 font-weight-bold" style="width: 46px; height: 42px;" disabled>
                                    <?= $i ?>
                                </button>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>