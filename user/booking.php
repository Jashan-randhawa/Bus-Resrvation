<?php
// user/booking.php
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$alert = null;
$alert_type = 'info';

// Detect columns in booking table
$booking_cols = db_all($link, 'SHOW COLUMNS FROM booking');
$col_names = array_column($booking_cols, 'Field');
$has_pnr = in_array('pnr', $col_names, true);
$has_status = in_array('status', $col_names, true);

// Get route information from GET parameters or route_id
$route_id = (int)($_GET['route_id'] ?? 0);
$bus = trim((string)($_GET['bus'] ?? ''));
$from = trim((string)($_GET['city1'] ?? ''));
$to = trim((string)($_GET['city2'] ?? ''));
$time = trim((string)($_GET['time'] ?? ''));
$date = trim((string)($_GET['date'] ?? date('Y-m-d')));

// Server-side Route and Price Resolution (F1, F2)
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
    // F2: Price cannot be trusted from client if route is missing
    $price = 0.0;
}

// Fetch currently booked seats for this trip: bus + date + time (O5, F3)
$booked_seats = get_booked_seats($link, $bus, $date, $time);

// Handle Booking Submission (O6, H-04, H-08, F1-F5)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check'])) {
    csrf_verify();
    $post_route_id = (int)($_POST['route_id'] ?? 0);
    $post_date = trim((string)($_POST['date'] ?? ''));
    $seat = (int)($_POST['seat'] ?? 0);
    $unm = trim((string)($_POST['unm'] ?? $_SESSION['name'] ?? ''));
    $num = trim((string)($_POST['num'] ?? $_SESSION['phone'] ?? ''));
    $cust_id = (int)($_SESSION['uid'] ?? 0);

    // Resolve route strictly from DB
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

require_once __DIR__ . '/../includes/layout/header-user.php';
?>
<div class="col-lg-10 col-md-10 col-sm-12 col-xs-12" style="float: right;">
    <section class="col-lg-8 col-md-10 col-sm-12 mx-auto my-4">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0">Complete Your Bus Booking</h4>
            </div>
            <div class="card-body">
                <?php if ($alert): ?>
                    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
                        <?= e($alert) ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <form action="" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="route_id" value="<?= e($route_id) ?>">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="bus">Bus Number :</label>
                            <input type="text" id="bus" name="bus" class="form-control" value="<?= e($bus) ?>" readonly required />
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="unm">Passenger Name :</label>
                            <input type="text" id="unm" name="unm" value="<?= e($_SESSION['name'] ?? '') ?>" class="form-control" placeholder="Enter passenger name" required />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="num">Contact Number :</label>
                            <input type="tel" id="num" name="num" value="<?= e($_SESSION['phone'] ?? '') ?>" class="form-control" placeholder="Enter contact number" required />
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="date">Travel Date :</label>
                            <input type="date" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+90 days')) ?>" name="date" id="date" value="<?= e($date) ?>" class="form-control" required />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="from">From :</label>
                            <input type="text" id="from" name="from" class="form-control" value="<?= e($from) ?>" readonly required />
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="to">To :</label>
                            <input type="text" id="to" name="to" class="form-control" value="<?= e($to) ?>" readonly required />
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="time">Departure Time :</label>
                            <input type="time" id="time" name="time" class="form-control" value="<?= e($time) ?>" readonly required />
                        </div>
                    </div>

                    <!-- Interactive Seat Selection Map -->
                    <div class="form-group mt-3">
                        <label class="font-weight-bold">Select Your Seat (1 - 36) :</label>
                        <div class="d-flex mb-2">
                            <span class="badge badge-info p-2 mr-2">Blue = Available</span>
                            <span class="badge badge-danger p-2 mr-2">Red = Already Booked</span>
                            <span class="badge badge-success p-2">Green = Selected</span>
                        </div>
                        <div class="p-3 border rounded bg-light" style="max-width: 460px; margin: auto;">
                            <?php for ($r = 0; $r < 6; $r++): ?>
                                <div class="row mb-2 justify-content-between">
                                    <div class="col-5 d-flex justify-content-start">
                                        <?php for ($c = 1; $c <= 2; $c++): ?>
                                            <?php
                                            $s = ($r * 5) + $c;
                                            $is_booked = isset($booked_seats[$s]);
                                            ?>
                                            <button type="button"
                                                class="btn <?= $is_booked ? 'btn-danger' : 'btn-info' ?> mr-2 seat-btn"
                                                style="width: 46px; height: 40px;"
                                                data-seat="<?= $s ?>"
                                                <?= $is_booked ? 'disabled' : '' ?>
                                                onclick="selectSeat(<?= $s ?>)">
                                                <?= $s ?>
                                            </button>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="col-7 d-flex justify-content-end">
                                        <?php for ($c = 3; $c <= 5; $c++): ?>
                                            <?php
                                            $s = ($r * 5) + $c;
                                            $is_booked = isset($booked_seats[$s]);
                                            ?>
                                            <button type="button"
                                                class="btn <?= $is_booked ? 'btn-danger' : 'btn-info' ?> mr-2 seat-btn"
                                                style="width: 46px; height: 40px;"
                                                data-seat="<?= $s ?>"
                                                <?= $is_booked ? 'disabled' : '' ?>
                                                onclick="selectSeat(<?= $s ?>)">
                                                <?= $s ?>
                                            </button>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                            <!-- Back Row Seats 31 - 36 -->
                            <div class="row mb-2 justify-content-center">
                                <div class="col-12 d-flex justify-content-between">
                                    <?php for ($s = 31; $s <= 36; $s++): ?>
                                        <?php $is_booked = isset($booked_seats[$s]); ?>
                                        <button type="button"
                                            class="btn <?= $is_booked ? 'btn-danger' : 'btn-info' ?> btn-sm seat-btn"
                                            style="width: 44px; height: 40px;"
                                            data-seat="<?= $s ?>"
                                            <?= $is_booked ? 'disabled' : '' ?>
                                            onclick="selectSeat(<?= $s ?>)">
                                            <?= $s ?>
                                        </button>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6 form-group">
                            <label for="seat_no">Selected Seat Number :</label>
                            <input type="number" min="1" max="36" name="seat" class="form-control" id="seat_no" placeholder="Click a seat above" readonly required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="amount">Total Ticket Fare ($) :</label>
                            <input type="text" class="form-control" id="amount" value="<?= e(number_format($price, 2)) ?>" readonly>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <input type="submit" class="btn btn-success btn-block btn-lg" name="check" value="Confirm and Book Ticket" />
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<script>
function selectSeat(seatNum) {
    document.getElementById('seat_no').value = seatNum;
    var allSeats = document.querySelectorAll('.seat-btn');
    allSeats.forEach(function(btn) {
        if (!btn.disabled) {
            btn.classList.remove('btn-success');
            btn.classList.add('btn-info');
        }
    });
    var selected = document.querySelector('.seat-btn[data-seat="' + seatNum + '"]');
    if (selected && !selected.disabled) {
        selected.classList.remove('btn-info');
        selected.classList.add('btn-success');
    }
}
</script>
<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>