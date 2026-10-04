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

// Handle Add Booking (O6, H-04, H-08)
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

    $res = create_booking($link, [
        'id'      => 0, // Admin booking
        'bus'     => $bus,
        'city1'   => $from,
        'city2'   => $to,
        'date'    => $date,
        'time'    => $time,
        'seat'    => $seat,
        'price'   => $amount,
        'name'    => $unm,
        'contact' => $num
    ]);

    if ($res['ok']) {
        $alert = "Booking confirmed! PNR: {$res['pnr']}, Seat: #{$seat}";
        $alert_type = 'success';
    } else {
        $alert = $res['error'];
        $alert_type = 'danger';
    }
}

// Handle Cancel / Delete Booking (O4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
    csrf_verify();
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        if ($has_status) {
            db_exec($link, "UPDATE booking SET status = 'Cancelled' WHERE sno = ?", 'i', [$delete_id]);
            $alert = 'Booking status marked as Cancelled (audit record preserved).';
        } else {
            db_exec($link, 'DELETE FROM booking WHERE sno = ?', 'i', [$delete_id]);
            $alert = 'Booking deleted successfully.';
        }
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
            <th>Status</th>
            <th>Price</th>
            <th>Edit</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bookings)): ?>
            <tr><td colspan="13" class="text-center text-muted">No bookings found.</td></tr>
          <?php else: ?>
            <?php foreach ($bookings as $row): ?>
              <?php
              $sno = (int)$row['sno'];
              $display_pnr = (string)($row['pnr'] ?? '');
              $status = (string)($row['status'] ?? 'Confirmed');
              $is_cancelled = ($status === 'Cancelled');
              ?>
              <tr class="<?= $is_cancelled ? 'table-secondary text-muted' : '' ?>">
                <td><strong><?= e($display_pnr !== '' ? $display_pnr : '-') ?></strong></td>
                <td><?= e($row['bus'] ?? '') ?></td>
                <td><?= e($row['name'] ?? '') ?></td>
                <td><?= e($row['contact'] ?? '') ?></td>
                <td><?= e($row['city1'] ?? '') ?></td>
                <td><?= e($row['city2'] ?? '') ?></td>
                <td><?= e($row['date'] ?? '') ?></td>
                <td><?= e($row['time'] ?? '') ?></td>
                <td><span class="badge badge-<?= $is_cancelled ? 'secondary' : 'info' ?> p-2"><?= e($row['seat'] ?? '') ?></span></td>
                <td>
                  <span class="badge badge-<?= $is_cancelled ? 'danger' : 'success' ?> p-2">
                    <?= e($status) ?>
                  </span>
                </td>
                <td>$<?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                <td>
                  <a href="<?= BASE_URL ?>/admin/edit/edit-booking.php?id=<?= e($sno) ?>" class="btn btn-warning btn-sm">Edit</a>
                </td>
                <td>
                  <?php if ($is_cancelled): ?>
                    <span class="badge badge-secondary p-2">Cancelled</span>
                  <?php else: ?>
                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="delete_id" value="<?= e($sno) ?>">
                      <button type="submit" name="delete_booking" class="btn btn-danger btn-sm">Cancel</button>
                    </form>
                  <?php endif; ?>
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