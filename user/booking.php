<?php
// user/booking.php
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/seat-map.php';

$alert = null;
$alert_type = 'info';

$route_id = (int)($_GET['route_id'] ?? 0);
$date = trim((string)($_GET['date'] ?? ''));

// U-02 / U-03: Route must be explicitly specified and valid
if ($route_id <= 0) {
    flash_set('danger', 'Please select a valid bus route to book.');
    header('Location: ' . BASE_URL . '/user/index.php');
    exit;
}

$route = db_one($link, 'SELECT * FROM route WHERE sno = ?', 'i', [$route_id]);
if (!$route) {
    flash_set('danger', 'The selected route could not be found or is no longer available.');
    header('Location: ' . BASE_URL . '/user/index.php');
    exit;
}

// U-03: Validate travel date and departure schedule against business rules
$date_val = validate_travel_datetime($date, (string)$route['time']);
if (!$date_val['ok']) {
    flash_set('danger', $date_val['error']);
    header('Location: ' . BASE_URL . '/user/index.php');
    exit;
}

$bus = (string)$route['busno'];
$from = (string)$route['city1'];
$to = (string)$route['city2'];
$time = (string)$route['time'];
$price = (float)$route['price'];

$bus_capacity = get_bus_capacity($link, $bus);
$bus_layout = get_bus_layout($link, $bus);
$seat_layout = build_seat_layout($bus_capacity, $bus_layout);
$booked_seats = get_booked_seats($link, $bus, $date, $time);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check'])) {
    csrf_verify();
    $post_route_id = (int)($_POST['route_id'] ?? 0);
    $post_time = trim((string)($_POST['time'] ?? ''));
    $post_date = trim((string)($_POST['date'] ?? ''));
    $seat = (int)($_POST['seat'] ?? 0);
    $unm = trim((string)($_POST['unm'] ?? $_SESSION['name'] ?? ''));
    $num = trim((string)($_POST['num'] ?? $_SESSION['phone'] ?? ''));
    $cust_id = (int)($_SESSION['uid'] ?? 0);

    if ($post_route_id <= 0) {
        $alert = 'Invalid route selection. Please search again.';
        $alert_type = 'danger';
    } else {
        $sub_route = db_one($link, 'SELECT * FROM route WHERE sno = ?', 'i', [$post_route_id]);
        if (!$sub_route) {
            $alert = 'Selected route is no longer available. Please select another route.';
            $alert_type = 'danger';
        } elseif ($post_time !== '' && substr($post_time, 0, 5) !== substr((string)$sub_route['time'], 0, 5)) {
            // U-02: Compare posted departure time with route record to prevent booking mismatched schedule
            $alert = 'Schedule changed, please search again.';
            $alert_type = 'danger';
        } else {
            $date_chk = validate_travel_datetime($post_date, (string)$sub_route['time']);
            if (!$date_chk['ok']) {
                $alert = $date_chk['error'];
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
                            <div class="font-weight-bold text-dark"><?= e(fmt_date($date)) ?></div>
                        </div>
                        <div class="col-sm-3 col-6">
                            <small class="text-muted text-uppercase">Time</small>
                            <div class="font-weight-bold text-dark"><?= e(fmt_time($time)) ?></div>
                        </div>
                    </div>
                </div>

                <form action="" method="post" id="booking-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="route_id" value="<?= e($route_id) ?>">
                    <input type="hidden" name="date" value="<?= e($date) ?>">
                    <input type="hidden" name="time" value="<?= e($time) ?>">

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label for="unm" class="font-weight-bold small text-muted">Passenger Full Name</label>
                            <input type="text" id="unm" name="unm" value="<?= e($_SESSION['name'] ?? '') ?>" class="form-control" placeholder="Full name (2-100 characters)" minlength="2" maxlength="100" required autocomplete="name" />
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="num" class="font-weight-bold small text-muted">Contact Phone Number</label>
                            <input type="tel" id="num" name="num" value="<?= e($_SESSION['phone'] ?? '') ?>" class="form-control" placeholder="Phone (10-15 digits)" minlength="10" maxlength="15" pattern="[0-9]{10,15}" inputmode="numeric" required autocomplete="tel" title="Phone number must be between 10 and 15 digits" />
                        </div>
                    </div>

                    <!-- Interactive Visual Seat Selection Map (U-15) -->
                    <div class="form-group mt-3">
                        <label class="font-weight-bold small text-muted mb-2 d-block text-center">Select Your Seat Number</label>
                        <div class="bus-map-wrapper">
                            <?php render_seat_map($seat_layout, $booked_seats, ['bus' => $bus, 'date' => $date, 'time' => $time]); ?>
                        </div>
                    </div>

                    <!-- Sticky Summary Strip & Dynamic Submit Enable (U-04) -->
                    <div class="card bg-light border p-3 mt-4">
                        <div class="row align-items-center">
                            <div class="col-sm-6 mb-2 mb-sm-0">
                                <small class="text-muted text-uppercase d-block font-weight-bold">Selected Seat</small>
                                <div class="h5 mb-0 text-dark" id="summary-seat-display">
                                    <span class="text-muted font-italic font-weight-normal">None selected (click seat above)</span>
                                </div>
                            </div>
                            <div class="col-sm-6 text-sm-right">
                                <small class="text-muted text-uppercase d-block font-weight-bold">Total Fare</small>
                                <div class="h4 font-weight-bold text-success mb-0">
                                    <?= CURRENCY ?><?= e(number_format($price, 2)) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <button type="submit" class="btn btn-primary btn-block btn-lg font-weight-bold shadow-sm" id="submit-booking-btn" name="check" value="1" disabled>
                        Confirm & Reserve Ticket
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/seat-map.js"></script>

<?php require_once __DIR__ . '/../includes/layout/footer-user.php'; ?>