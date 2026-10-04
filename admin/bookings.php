<?php
// admin/bookings.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$alert = null;
$alert_type = 'info';

// Check which columns exist in booking table
$booking_cols = db_all($link, 'SHOW COLUMNS FROM booking');
$col_names = array_column($booking_cols, 'Field');
$has_pnr = in_array('pnr', $col_names, true);
$has_status = in_array('status', $col_names, true);

// Handle Add Booking (H-04, H-08)
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

    if ($bus === '' || $unm === '' || $num === '' || $from === '' || $to === '' || $date === '' || $seat < 1 || $seat > 36 || $amount <= 0) {
        $alert = 'Please provide valid booking details with a seat between 1 and 36.';
        $alert_type = 'danger';
    } else {
        $today = date('Y-m-d');
        $max_date = date('Y-m-d', strtotime('+90 days'));
        if ($date < $today) {
            $alert = 'Travel date cannot be in the past.';
            $alert_type = 'danger';
        } elseif ($date > $max_date) {
            $alert = 'Bookings can only be made up to 90 days in advance.';
            $alert_type = 'danger';
        } else {
            // Direct insert inside transaction catching duplicate key 1062 (F3, F4)
            mysqli_begin_transaction($link);
            try {
                // Secure random 10-character PNR token (H-08, O1)
                $pnr = strtoupper(bin2hex(random_bytes(5)));
                $cust_id = 0; // Admin booking

                if ($has_pnr && $has_status) {
                    $insert_sql = 'INSERT INTO booking (id, bus, name, contact, city1, city2, `date`, `time`, seat, price, pnr, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    db_exec($link, $insert_sql, 'isssssssidss', [$cust_id, $bus, $unm, $num, $from, $to, $date, $time, $seat, $amount, $pnr, 'Confirmed']);
                } elseif ($has_pnr) {
                    $insert_sql = 'INSERT INTO booking (id, bus, name, contact, city1, city2, `date`, `time`, seat, price, pnr) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    db_exec($link, $insert_sql, 'isssssssids', [$cust_id, $bus, $unm, $num, $from, $to, $date, $time, $seat, $amount, $pnr]);
                } else {
                    $insert_sql = 'INSERT INTO booking (id, bus, name, contact, city1, city2, `date`, `time`, seat, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    db_exec($link, $insert_sql, 'isssssssid', [$cust_id, $bus, $unm, $num, $from, $to, $date, $time, $seat, $amount]);
                }

                mysqli_commit($link);
                $alert = "Booking confirmed! PNR: {$pnr}, Seat: #{$seat}";
                $alert_type = 'success';
            } catch (mysqli_sql_exception $e) {
                mysqli_rollback($link);
                if ((int)$e->getCode() === 1062) {
                    $alert = "Seat #{$seat} on bus {$bus} for date {$date} ({$time}) is already booked.";
                } else {
                    $alert = 'Booking could not be completed: ' . $e->getMessage();
                }
                $alert_type = 'danger';
            }
        }
    }
}

// Handle Delete Booking (POST with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
    csrf_verify();
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        db_exec($link, 'DELETE FROM booking WHERE sno = ?', 'i', [$delete_id]);
        $alert = 'Booking cancelled / deleted successfully.';
        $alert_type = 'success';
    }
}

$buses = db_all($link, 'SELECT bus_number FROM buses ORDER BY bus_number ASC');
$from_cities = db_all($link, 'SELECT DISTINCT city1 FROM route ORDER BY city1 ASC');
$to_cities = db_all($link, 'SELECT DISTINCT city2 FROM route ORDER BY city2 ASC');
$bookings = db_all($link, 'SELECT * FROM booking ORDER BY sno DESC');

require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="col-lg-10 col-md-12 col-sm-12" style=" float: right ; ">
  <h1 class="text-info">Booking Status</h1>
  <br>

  <?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
      <?= e($alert) ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
  <?php endif; ?>

  <button class="btn btn-danger" data-toggle="modal" data-target="#addBookingModal">Add Booking</button>

  <div class="modal fade" id="addBookingModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <button type="button" class="close text-right pr-3 pt-2" data-dismiss="modal">
          <span>&times;</span>
        </button>
        <div class="modal-header">
          <h5 class="modal-title">Add Booking</h5>
        </div>
        <div class="modal-body">
          <form action="" method="post">
            <?= csrf_field() ?>
            <div class="row">
              <div class="col-md-6 form-group">
                <label for="bus">Bus Number :</label>
                <select name="bus" id="bus" class="form-control" required>
                  <option value="">Select Bus Number</option>
                  <?php foreach ($buses as $row): ?>
                    <option value="<?= e($row['bus_number']) ?>"><?= e($row['bus_number']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label for="unm">Customer Name :</label>
                <input type="text" name="unm" id="unm" class="form-control" placeholder="Enter Customer Name" required />
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label for="num">Contact Number:</label>
                <input type="tel" name="num" id="num" class="form-control" placeholder="Enter Contact Number" required />
              </div>
              <div class="col-md-6 form-group">
                <label for="date">Date :</label>
                <input type="date" min="<?= date('Y-m-d') ?>" name="date" id="date" class="form-control" required />
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label for="from">From :</label>
                <select name="from" id="from" class="form-control" required>
                  <option value="">Select Origin City</option>
                  <?php foreach ($from_cities as $row): ?>
                    <option value="<?= e($row['city1']) ?>"><?= e($row['city1']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label for="to">To :</label>
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
                <label for="time">Time :</label>
                <input type="time" name="time" id="time" class="form-control" required />
              </div>
              <div class="col-md-4 form-group">
                <label for="seat_no">Seat Number (1-36):</label>
                <input type="number" min="1" max="36" name="seat" class="form-control" id="seat_no" placeholder="1 - 36" required>
              </div>
              <div class="col-md-4 form-group">
                <label for="amount">Total Amount:</label>
                <input type="number" step="0.01" min="1" name="amount" class="form-control" id="amount" placeholder="Amount" required>
              </div>
            </div>

            <!-- Seat grid selection -->
            <label>Select Seat on Map:</label>
            <div class="p-2 border rounded mb-3" style="max-height: 200px; overflow-y: auto;">
              <div class="d-flex flex-wrap justify-content-center">
                <?php for ($s = 1; $s <= 36; $s++): ?>
                  <button type="button" class="btn btn-outline-info btn-sm m-1" style="width: 44px; height: 38px;" onclick="document.getElementById('seat_no').value = <?= $s ?>;">
                    <?= $s ?>
                  </button>
                <?php endfor; ?>
              </div>
            </div>

            <div class="form-group">
              <input type="submit" class="btn btn-success btn-block" name="check" value="Confirm Booking" />
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <section class="mt-4">
    <h4 class="text-secondary mb-3">All Bookings</h4>
    <div class="table-responsive">
      <table class="table table-bordered table-striped">
        <thead class="thead-dark">
          <tr>
            <th>PNR</th>
            <th>Bus Number</th>
            <th>Customer Name</th>
            <th>Contact</th>
            <th>From</th>
            <th>To</th>
            <th>Date</th>
            <th>Time</th>
            <th>Seat</th>
            <th>Price</th>
            <th>Edit</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bookings)): ?>
            <tr><td colspan="12" class="text-center text-muted">No bookings found.</td></tr>
          <?php else: ?>
            <?php foreach ($bookings as $row): ?>
              <?php
              $sno = (int)$row['sno'];
              $display_pnr = (string)($row['pnr'] ?? '');
              ?>
              <tr>
                <td><strong><?= e($display_pnr !== '' ? $display_pnr : '-') ?></strong></td>
                <td><?= e($row['bus'] ?? '') ?></td>
                <td><?= e($row['name'] ?? '') ?></td>
                <td><?= e($row['contact'] ?? '') ?></td>
                <td><?= e($row['city1'] ?? '') ?></td>
                <td><?= e($row['city2'] ?? '') ?></td>
                <td><?= e($row['date'] ?? '') ?></td>
                <td><?= e($row['time'] ?? '') ?></td>
                <td><span class="badge badge-info p-2"><?= e($row['seat'] ?? '') ?></span></td>
                <td>$<?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                <td>
                  <a href="<?= BASE_URL ?>/admin/edit/edit-booking.php?id=<?= e($sno) ?>" class="btn btn-warning btn-sm">Edit</a>
                </td>
                <td>
                  <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel / delete this booking?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_id" value="<?= e($sno) ?>">
                    <button type="submit" name="delete_booking" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>