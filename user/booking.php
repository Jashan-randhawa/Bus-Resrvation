<?php
// user/booking.php
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

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
                        
                        <!-- Real-Life Coach Bus Legend -->
                        <div class="seat-legend mb-4">
                            <div class="legend-item">
                                <div class="legend-seat-mini available">
                                    <span class="mini-headrest"></span>
                                    <span class="mini-cushion"></span>
                                </div>
                                <span>Available Seat</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-seat-mini selected">
                                    <span class="mini-headrest"></span>
                                    <span class="mini-cushion">✓</span>
                                </div>
                                <span>Selected Seat</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-seat-mini booked">
                                    <span class="mini-headrest"></span>
                                    <span class="mini-cushion">✕</span>
                                </div>
                                <span>Already Booked</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-badge">🛞</span>
                                <span>Driver Area</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-badge">🚪</span>
                                <span>Entrance Door</span>
                            </div>
                        </div>

                        <!-- Real-Life Coach Bus Vehicle Shell -->
                        <div class="bus-vehicle-container">
                            <!-- Aerodynamic Front Roof & Windshield -->
                            <div class="bus-front-roof">
                                <div class="bus-side-mirror mirror-left" title="Rearview Mirror Left"></div>
                                <div class="bus-side-mirror mirror-right" title="Rearview Mirror Right"></div>

                                <div class="bus-windshield">
                                    <div class="windshield-glare"></div>
                                    <div class="windshield-display">
                                        <span class="led-blink">●</span>
                                        <span class="led-route">EXPRESS COACH &bull; <?= e($bus) ?> &bull; <?= e($route['city1']) ?> ➔ <?= e($route['city2']) ?></span>
                                    </div>
                                    <div class="windshield-wipers">
                                        <span class="wiper wiper-left"></span>
                                        <span class="wiper wiper-right"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Interior Passenger Cabin -->
                            <div class="bus-cabin">
                                <!-- Cockpit Area: Boarding steps on left, Driver cockpit on right -->
                                <div class="bus-cabin-front">
                                    <!-- Boarding Door -->
                                    <div class="cabin-entry-gate" title="Passenger Entrance Door">
                                        <div class="entry-steps-tread">
                                            <span class="step-tread"></span>
                                            <span class="step-tread"></span>
                                        </div>
                                        <span class="entry-icon">🚪</span>
                                        <span class="entry-text">ENTRANCE</span>
                                    </div>

                                    <!-- Front Aisle Guide -->
                                    <div class="cabin-aisle-guide">
                                        <span class="guide-arrow">▲</span>
                                        <span>FRONT OF BUS</span>
                                    </div>

                                    <!-- Driver Cockpit with Realistic Steering Wheel -->
                                    <div class="cabin-driver-deck" title="Driver Cabin">
                                        <div class="driver-steering-icon">
                                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="9"></circle>
                                                <circle cx="12" cy="12" r="2.5"></circle>
                                                <path d="M12 3v6.5"></path>
                                                <path d="M4.5 16.5l5.5-3"></path>
                                                <path d="M19.5 16.5l-5.5-3"></path>
                                            </svg>
                                        </div>
                                        <div class="driver-deck-label">
                                            <span class="driver-tag-main">DRIVER</span>
                                            <span class="driver-tag-sub">CABIN</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cabin Window Sills Indicator -->
                                <div class="cabin-window-strip">
                                    <span class="window-sill"><span class="win-icon">🪟</span> Window</span>
                                    <span class="aisle-title"><span class="aisle-icon">🚶</span> Central Aisle</span>
                                    <span class="window-sill">Window <span class="win-icon">🪟</span></span>
                                </div>

                                <!-- 2+2 Real Coach Seating Matrix -->
                                <?php
                                $num_rows = (int)ceil($bus_capacity / 4);
                                ?>
                                <div role="radiogroup" aria-label="Bus seat selection" class="bus-seat-matrix">
                                    <?php for ($r = 1; $r <= $num_rows; $r++): ?>
                                        <?php
                                        $s1 = ($r - 1) * 4 + 1;
                                        $s2 = ($r - 1) * 4 + 2;
                                        $s3 = ($r - 1) * 4 + 3;
                                        $s4 = ($r - 1) * 4 + 4;
                                        ?>
                                        <div class="seat-row" data-row="<?= $r ?>">
                                            <!-- Left Pair (Window + Aisle) -->
                                            <div class="seat-pair left-pair">
                                                <?php if ($s1 <= $bus_capacity): ?>
                                                    <?php $bk1 = isset($booked_seats[$s1]); ?>
                                                    <div class="seat-box <?= $bk1 ? 'seat-box-booked' : 'seat-box-available' ?>">
                                                        <input type="radio" name="seat" id="seat-<?= $s1 ?>" value="<?= $s1 ?>"
                                                            class="seat-radio"
                                                            <?= $bk1 ? 'disabled' : '' ?>
                                                            aria-label="Seat <?= $s1 ?>, Window seat, <?= $bk1 ? 'Already Booked' : 'Available' ?>"
                                                            <?= ($s1 === 1 && !$bk1) ? 'required' : '' ?>>
                                                        <label for="seat-<?= $s1 ?>" class="seat-label <?= $bk1 ? 'seat-booked' : 'seat-available' ?>" title="Seat #<?= $s1 ?> (Window) &bull; <?= $bk1 ? 'Already Booked' : 'Available for Reservation' ?>">
                                                            <span class="seat-headrest"><span class="headrest-cover"></span></span>
                                                            <span class="seat-armrest armrest-left"></span>
                                                            <span class="seat-armrest armrest-right"></span>
                                                            <span class="seat-cushion">
                                                                <span class="seat-num"><?= $s1 ?></span>
                                                                <span class="seat-type-tag"><?= $bk1 ? '<span class="seat-booked-icon">✕</span>' : 'WIN' ?></span>
                                                            </span>
                                                        </label>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($s2 <= $bus_capacity): ?>
                                                    <?php $bk2 = isset($booked_seats[$s2]); ?>
                                                    <div class="seat-box <?= $bk2 ? 'seat-box-booked' : 'seat-box-available' ?>">
                                                        <input type="radio" name="seat" id="seat-<?= $s2 ?>" value="<?= $s2 ?>"
                                                            class="seat-radio"
                                                            <?= $bk2 ? 'disabled' : '' ?>
                                                            aria-label="Seat <?= $s2 ?>, Aisle seat, <?= $bk2 ? 'Already Booked' : 'Available' ?>">
                                                        <label for="seat-<?= $s2 ?>" class="seat-label <?= $bk2 ? 'seat-booked' : 'seat-available' ?>" title="Seat #<?= $s2 ?> (Aisle) &bull; <?= $bk2 ? 'Already Booked' : 'Available for Reservation' ?>">
                                                            <span class="seat-headrest"><span class="headrest-cover"></span></span>
                                                            <span class="seat-armrest armrest-left"></span>
                                                            <span class="seat-armrest armrest-right"></span>
                                                            <span class="seat-cushion">
                                                                <span class="seat-num"><?= $s2 ?></span>
                                                                <span class="seat-type-tag"><?= $bk2 ? '<span class="seat-booked-icon">✕</span>' : 'AIS' ?></span>
                                                            </span>
                                                        </label>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Center Aisle Runner -->
                                            <div class="aisle-gap" title="Aisle Corridor &bull; Row <?= $r ?>">
                                                <div class="aisle-runner">
                                                    <span class="aisle-row-marker">R<?= $r ?></span>
                                                    <span class="aisle-dot"></span>
                                                </div>
                                            </div>

                                            <!-- Right Pair (Aisle + Window) -->
                                            <div class="seat-pair right-pair">
                                                <?php if ($s3 <= $bus_capacity): ?>
                                                    <?php $bk3 = isset($booked_seats[$s3]); ?>
                                                    <div class="seat-box <?= $bk3 ? 'seat-box-booked' : 'seat-box-available' ?>">
                                                        <input type="radio" name="seat" id="seat-<?= $s3 ?>" value="<?= $s3 ?>"
                                                            class="seat-radio"
                                                            <?= $bk3 ? 'disabled' : '' ?>
                                                            aria-label="Seat <?= $s3 ?>, Aisle seat, <?= $bk3 ? 'Already Booked' : 'Available' ?>">
                                                        <label for="seat-<?= $s3 ?>" class="seat-label <?= $bk3 ? 'seat-booked' : 'seat-available' ?>" title="Seat #<?= $s3 ?> (Aisle) &bull; <?= $bk3 ? 'Already Booked' : 'Available for Reservation' ?>">
                                                            <span class="seat-headrest"><span class="headrest-cover"></span></span>
                                                            <span class="seat-armrest armrest-left"></span>
                                                            <span class="seat-armrest armrest-right"></span>
                                                            <span class="seat-cushion">
                                                                <span class="seat-num"><?= $s3 ?></span>
                                                                <span class="seat-type-tag"><?= $bk3 ? '<span class="seat-booked-icon">✕</span>' : 'AIS' ?></span>
                                                            </span>
                                                        </label>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($s4 <= $bus_capacity): ?>
                                                    <?php $bk4 = isset($booked_seats[$s4]); ?>
                                                    <div class="seat-box <?= $bk4 ? 'seat-box-booked' : 'seat-box-available' ?>">
                                                        <input type="radio" name="seat" id="seat-<?= $s4 ?>" value="<?= $s4 ?>"
                                                            class="seat-radio"
                                                            <?= $bk4 ? 'disabled' : '' ?>
                                                            aria-label="Seat <?= $s4 ?>, Window seat, <?= $bk4 ? 'Already Booked' : 'Available' ?>">
                                                        <label for="seat-<?= $s4 ?>" class="seat-label <?= $bk4 ? 'seat-booked' : 'seat-available' ?>" title="Seat #<?= $s4 ?> (Window) &bull; <?= $bk4 ? 'Already Booked' : 'Available for Reservation' ?>">
                                                            <span class="seat-headrest"><span class="headrest-cover"></span></span>
                                                            <span class="seat-armrest armrest-left"></span>
                                                            <span class="seat-armrest armrest-right"></span>
                                                            <span class="seat-cushion">
                                                                <span class="seat-num"><?= $s4 ?></span>
                                                                <span class="seat-type-tag"><?= $bk4 ? '<span class="seat-booked-icon">✕</span>' : 'WIN' ?></span>
                                                            </span>
                                                        </label>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>

                                <!-- Rear Bulkhead / Emergency Exit -->
                                <div class="bus-cabin-rear">
                                    <div class="rear-bulkhead">
                                        <span class="emergency-icon">🚨</span>
                                        <span class="emergency-text">REAR EMERGENCY EXIT</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bus Rear Bumper & Taillights -->
                            <div class="bus-rear-bumper">
                                <span class="rear-light light-red"></span>
                                <span class="bus-plate">EXP &bull; <?= e($bus) ?></span>
                                <span class="rear-light light-red"></span>
                            </div>
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

<script>
// U-04 & U-15: Radio selection handler, keyboard support, summary update
document.addEventListener('DOMContentLoaded', function() {
    var seatRadios = document.querySelectorAll('input[name="seat"]');
    var submitBtn = document.getElementById('submit-booking-btn');
    var summarySeat = document.getElementById('summary-seat-display');

    function onSeatSelected(seatNum, seatType) {
        if (summarySeat) {
            summarySeat.innerHTML = '<span class="badge badge-success px-2 py-1 mr-1">Seat #' + seatNum + '</span> <small class="text-muted font-weight-normal">(' + seatType + ')</small>';
        }
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    }

    seatRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.checked) {
                var label = document.querySelector('label[for="' + this.id + '"]');
                var typeTag = label ? label.querySelector('.seat-type-tag')?.textContent : '';
                var typeStr = typeTag === 'WIN' ? 'Window' : (typeTag === 'AIS' ? 'Aisle' : 'Standard');
                onSeatSelected(this.value, typeStr);
            }
        });
    });

    // U-05: Double click lock
    var bookingForm = document.getElementById('booking-form');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            var selectedRadio = document.querySelector('input[name="seat"]:checked');
            if (!selectedRadio) {
                e.preventDefault();
                alert('Please select an available seat from the seat map before confirming.');
                return false;
            }
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>Booking...';
                var checkHidden = document.createElement('input');
                checkHidden.type = 'hidden';
                checkHidden.name = 'check';
                checkHidden.value = '1';
                bookingForm.appendChild(checkHidden);
            }
        });
    }

    // U-15: Live seat refresh polling every 30 seconds
    var pollBus = <?= json_encode($bus) ?>;
    var pollDate = <?= json_encode($date) ?>;
    var pollTime = <?= json_encode($time) ?>;

    setInterval(function() {
        var apiUrl = 'api-seats.php?bus=' + encodeURIComponent(pollBus) + '&date=' + encodeURIComponent(pollDate) + '&time=' + encodeURIComponent(pollTime);
        fetch(apiUrl)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.ok && Array.isArray(data.booked_seats)) {
                    data.booked_seats.forEach(function(sNum) {
                        var radio = document.getElementById('seat-' + sNum);
                        var label = document.querySelector('label[for="seat-' + sNum + '"]');
                        if (radio && !radio.disabled) {
                            if (radio.checked) {
                                radio.checked = false;
                                if (submitBtn) submitBtn.disabled = true;
                                if (summarySeat) {
                                    summarySeat.innerHTML = '<span class="text-danger font-weight-bold">Seat #' + sNum + ' was just taken! Please select another.</span>';
                                }
                                alert('Seat #' + sNum + ' was just reserved by another passenger. Please pick another seat.');
                            }
                            radio.disabled = true;
                            if (label) {
                                label.className = 'seat-label seat-booked';
                                label.innerHTML = '<span class="seat-headrest"><span class="headrest-cover"></span></span><span class="seat-armrest armrest-left"></span><span class="seat-armrest armrest-right"></span><span class="seat-cushion"><span class="seat-num">' + sNum + '</span><span class="seat-type-tag"><span class="seat-booked-icon">✕</span></span></span>';
                            }
                        }
                    });
                }
            })
            .catch(function(err) {
                // Silently ignore network hiccup during background poll
            });
    }, 30000);
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-user.php'; ?>