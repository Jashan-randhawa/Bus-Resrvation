<?php
// admin/seats.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$buses = db_all($link, 'SELECT bus_number FROM buses ORDER BY bus_number ASC');

$selected_bus = trim((string)($_POST['bus'] ?? ''));
$selected_date = trim((string)($_POST['date'] ?? ''));
$selected_time = trim((string)($_POST['time'] ?? ''));
$booked_seats = [];

// Available departure times for selected bus (if any)
$route_times = db_all($link, 'SELECT DISTINCT time FROM route ORDER BY time ASC');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    csrf_verify();
    if ($selected_bus !== '' && $selected_date !== '') {
        if ($selected_time !== '') {
            $booked_seats = get_booked_seats($link, $selected_bus, $selected_date, $selected_time);
        } else {
            // If no specific time selected, check any active booking for bus and date
            $rows = db_all($link,
                "SELECT seat FROM booking WHERE bus = ? AND `date` = ? AND (status IS NULL OR status != 'Cancelled')",
                'ss', [$selected_bus, $selected_date]
            );
            foreach ($rows as $r) {
                $booked_seats[(int)$r['seat']] = true;
            }
        }
    }
}

require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="col-lg-10 col-md-10 col-sm-12" style="float: right;">
    <h1 class="text-info mb-4">Bus Seat Status</h1>
    <div class="card col-lg-6 col-md-8 col-sm-12">
        <div class="card-body">
            <form action="" method="post">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="bus">Bus Number:</label>
                    <select name="bus" id="bus" class="form-control" required>
                        <option value="">Select Bus Number</option>
                        <?php foreach ($buses as $row): ?>
                            <option value="<?= e($row['bus_number']) ?>" <?= $selected_bus === $row['bus_number'] ? 'selected' : '' ?>>
                                <?= e($row['bus_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Travel Date:</label>
                    <input type="date" id="date" name="date" class="form-control" value="<?= e($selected_date) ?>" required>
                </div>
                <div class="form-group">
                    <label for="time">Departure Time (optional):</label>
                    <input type="time" id="time" name="time" class="form-control" value="<?= e($selected_time) ?>">
                </div>
                <div class="form-group">
                    <input type="submit" name="submit" value="Check Seat Availability" class="btn btn-primary btn-block">
                </div>
            </form>
        </div>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])): ?>
    <section class="mt-4">
        <div class="card col-lg-8 col-md-10 col-sm-12">
            <div class="card-header bg-light">
                <h5>Seat Map for Bus: <strong><?= e($selected_bus) ?></strong> on Date: <strong><?= e($selected_date) ?></strong> <?= $selected_time !== '' ? 'at <strong>' . e($selected_time) . '</strong>' : '' ?></h5>
                <span class="badge badge-danger p-2 mr-2">Red = Booked</span>
                <span class="badge badge-info p-2">Blue = Available</span>
            </div>
            <div class="card-body">
                <div class="container" style="max-width: 480px;">
                    <?php for ($row_idx = 0; $row_idx < 6; $row_idx++): ?>
                        <div class="row mb-3 justify-content-between">
                            <div class="col-5 d-flex justify-content-start">
                                <?php for ($col_idx = 1; $col_idx <= 2; $col_idx++): ?>
                                    <?php
                                    $seat_no = ($row_idx * 5) + $col_idx;
                                    $is_booked = isset($booked_seats[$seat_no]);
                                    ?>
                                    <button type="button" class="btn <?= $is_booked ? 'btn-danger' : 'btn-info' ?> mr-2" style="width: 50px; height: 42px;" <?= $is_booked ? 'disabled' : '' ?>>
                                        <?= e($seat_no) ?>
                                    </button>
                                <?php endfor; ?>
                            </div>
                            <div class="col-7 d-flex justify-content-end">
                                <?php for ($col_idx = 3; $col_idx <= 5; $col_idx++): ?>
                                    <?php
                                    $seat_no = ($row_idx * 5) + $col_idx;
                                    $is_booked = isset($booked_seats[$seat_no]);
                                    ?>
                                    <button type="button" class="btn <?= $is_booked ? 'btn-danger' : 'btn-info' ?> mr-2" style="width: 50px; height: 42px;" <?= $is_booked ? 'disabled' : '' ?>>
                                        <?= e($seat_no) ?>
                                    </button>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                    <!-- Last Row (Seats 31 to 36) -->
                    <div class="row mb-3 justify-content-center">
                        <div class="col-12 d-flex justify-content-between">
                            <?php for ($seat_no = 31; $seat_no <= 36; $seat_no++): ?>
                                <?php $is_booked = isset($booked_seats[$seat_no]); ?>
                                <button type="button" class="btn <?= $is_booked ? 'btn-danger' : 'btn-info' ?> btn-sm" style="width: 48px; height: 42px;" <?= $is_booked ? 'disabled' : '' ?>>
                                    <?= e($seat_no) ?>
                                </button>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>