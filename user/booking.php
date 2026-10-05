<?php
// user/booking.php
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$alert = null;
$alert_type = 'info';

$booking_cols = db_all($link, 'SHOW COLUMNS FROM booking');
$col_names = array_column($booking_cols, 'Field');
$has_pnr = in_array('pnr', $col_names, true);
$has_status = in_array('status', $col_names, true);

$route_id = (int)($_GET['route_id'] ?? 0);
$bus = trim((string)($_GET['bus'] ?? ''));
$from = trim((string)($_GET['city1'] ?? ''));
$to = trim((string)($_GET['city2'] ?? ''));
$time = trim((string)($_GET['time'] ?? ''));
$date = trim((string)($_GET['date'] ?? date('Y-m-d')));

$route = null;
if ($route_id > 0) {
    $route = db_one($link, 'SELECT * FROM route WHERE sno = ?', 'i', [$route_id]);
}
if (!$route && $bus !== '' && $from !== '' && $to !== '') {
    $route = db_one($link, 'SELECT * FROM route WHERE busno = ? AND city1 = ? AND city2 = ? LIMIT 1', 'sss', [$bus, $from, $to]);
}

if ($route) {
    $route_id = (int)$route['sno'];
    $bus = (string)$route['busno'];
    $from = (string)$route['city1'];
    $to = (string)$route['city2'];
    $time = (string)$route['time'];
    $price = (float)$route['price'];
} else {
    $price = 0.0;
}

$bus_capacity = get_bus_capacity($link, $bus);
$booked_seats = get_booked_seats($link, $bus, $date, $time);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check'])) {
    csrf_verify();
    $post_route_id = (int)($_POST['route_id'] ?? 0);
    $post_date = trim((string)($_POST['date'] ?? ''));
    $seat = (int)($_POST['seat'] ?? 0);
    $unm = trim((string)($_POST['unm'] ?? $_SESSION['name'] ?? ''));
    $num = trim((string)($_POST['num'] ?? $_SESSION['phone'] ?? ''));
    $cust_id = (int)($_SESSION['uid'] ?? 0);

    $sub_route = null;
    if ($post_route_id > 0) {
        $sub_route = db_one($link, 'SELECT * FROM route WHERE sno = ?', 'i', [$post_route_id]);
    }
    if (!$sub_route) {
        $post_bus = trim((string)($_POST['bus'] ?? ''));
        $post_from = trim((string)($_POST['from'] ?? ''));
        $post_to = trim((string)($_POST['to'] ?? ''));
        if ($post_bus !== '' && $post_from !== '' && $post_to !== '') {
            $sub_route = db_one($link, 'SELECT * FROM route WHERE busno = ? AND city1 = ? AND city2 = ? LIMIT 1', 'sss', [$post_bus, $post_from, $post_to]);
        }
    }

    if (!$sub_route) {
        $alert = 'Selected route is no longer available. Please select another route.';
        $alert_type = 'danger';
    } else {
        $result = create_booking($link, [
            'id'      => $cust_id,
            'bus'     => $sub_route['busno'],
            'city1'   => $sub_route['city1'],
            'city2'   => $sub_route['city2'],
            'date'    => $post_date,
            'time'    => $sub_route['time'],
            'seat'    => $seat,
            'price'   => $sub_route['price'],
            'name'    => $unm,
            'contact' => $num
        ]);

        if ($result['ok']) {
            header('Location: ' . BASE_URL . '/user/my-bookings.php?booked=1&pnr=' . urlencode($result['pnr']));
            exit;
        } else {
            $alert = $result['error'];
            $alert_type = 'danger';
        }
    }
}

$title = 'Select Seat & Book';
require_once __DIR__ . '/../includes/layout/header-user.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/user/index.php">Search Journeys</a></li>
        <li class="breadcrumb-item active" aria-current="page">Reserve Seat (<?= e($from) ?> &rarr; <?= e($to) ?>)</li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 font-weight-bold">Confirm Trip Reservation</h5>
                <span class="badge badge-primary px-3 py-2">Bus #<?= e($bus) ?></span>
            </div>
            <div class="card-body p-4">
                <?php if ($alert): ?>
                    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
                        <?= e($alert) ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Trip Details Strip -->
                <div class="p-3 bg-light rounded border mb-4">
                    <div class="row text-center">
                        <div class="col-sm-3 col-6 mb-2 mb-sm-0">
                            <small class="text-muted text-uppercase">From</small>
                            <div class="font-weight-bold text-dark"><?= e($from) ?></div>
                        </div>
                        <div class="col-sm-3 col-6 mb-2 mb-sm-0">
                            <small class="text-muted text-uppercase">To</small>
                            <div class="font-weight-bold text-dark"><?= e($to) ?></div>
                        </div>
                        <div class="col-sm-3 col-6">
                            <small class="text-muted text-uppercase">Date</small>
                            <div class="font-weight-bold text-dark"><?= e($date) ?></div>
                        </div>
                        <div class="col-sm-3 col-6">
                            <small class="text-muted text-uppercase">Time</small>
                            <div class="font-weight-bold text-dark"><?= e($time) ?></div>
                        </div>
                    </div>
                </div>

                <form action="" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="route_id" value="<?= e($route_id) ?>">
                    <input type="hidden" name="bus" value="<?= e($bus) ?>">
                    <input type="hidden" name="from" value="<?= e($from) ?>">
                    <input type="hidden" name="to" value="<?= e($to) ?>">
                    <input type="hidden" name="date" value="<?= e($date) ?>">

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label for="unm" class="font-weight-bold small text-muted">Passenger Full Name</label>
                            <input type="text" id="unm" name="unm" value="<?= e($_SESSION['name'] ?? '') ?>" class="form-control" placeholder="Full name" required />
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="num" class="font-weight-bold small text-muted">Contact Phone Number</label>
                            <input type="tel" id="num" name="num" value="<?= e($_SESSION['phone'] ?? '') ?>" class="form-control" placeholder="Phone" required />
                        </div>
                    </div>

                    <!-- Interactive Visual Seat Selection Map -->
                    <div class="form-group mt-3">
                        <label class="font-weight-bold small text-muted mb-2">Select Your Seat Number</label>
                        <div class="seat-legend mb-3">
                            <div class="legend-item">
                                <span class="legend-swatch" style="background-color: var(--c-primary-light, #dbeafe); border: 1.5px solid var(--c-primary, #2563eb);"></span>
                                <span>Available</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-swatch" style="background-color: var(--c-success, #16a34a);"></span>
                                <span>Selected</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-swatch" style="background-color: var(--c-error, #dc2626);"></span>
                                <span>Already Booked</span>
                            </div>
                        </div>

                        <div class="seat-grid">
                            <?php for ($s = 1; $s <= $bus_capacity; $s++): ?>
                                <?php $is_booked = isset($booked_seats[$s]); ?>
                                <button type="button"
                                    class="btn seat-btn <?= $is_booked ? 'btn-danger' : 'btn-outline-primary' ?>"
                                    data-seat="<?= $s ?>"
                                    <?= $is_booked ? 'disabled' : '' ?>
                                    onclick="selectSeat(<?= $s ?>)">
                                    <?= $s ?>
                                </button>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="form-row align-items-end mt-4">
                        <div class="col-md-6 form-group mb-md-0">
                            <label for="seat_no" class="font-weight-bold small text-muted">Selected Seat Number</label>
                            <input type="number" min="1" max="<?= $bus_capacity ?>" name="seat" class="form-control" id="seat_no" placeholder="Click seat above" readonly required>
                        </div>
                        <div class="col-md-6 form-group mb-md-0">
                            <label class="font-weight-bold small text-muted">Fare Breakdown</label>
                            <div class="h3 font-weight-bold text-success mb-0">
                                <?= CURRENCY ?><?= e(number_format($price, 2)) ?>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <button type="submit" class="btn btn-primary btn-block btn-lg font-weight-bold shadow-sm" name="check">
                        Confirm & Reserve Ticket
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function selectSeat(seatNum) {
    var seatInput = document.getElementById('seat_no');
    if (seatInput) seatInput.value = seatNum;
    
    var allSeats = document.querySelectorAll('.seat-btn');
    allSeats.forEach(function(btn) {
        if (!btn.disabled) {
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
        }
    });
    var selected = document.querySelector('.seat-btn[data-seat="' + seatNum + '"]');
    if (selected && !selected.disabled) {
        selected.classList.remove('btn-outline-primary');
        selected.classList.add('btn-success');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>