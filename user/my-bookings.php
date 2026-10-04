<?php
// user/my-bookings.php
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$uid = (int)($_SESSION['uid'] ?? 0);
$phone = trim((string)($_SESSION['phone'] ?? ''));

$alert = null;
$alert_type = 'info';

if (!empty($_GET['booked']) && !empty($_GET['pnr'])) {
    $alert = 'Your booking was successful! Your PNR is ' . e($_GET['pnr']);
    $alert_type = 'success';
}

// Check if status column exists in booking table (O4)
$booking_cols = db_all($link, 'SHOW COLUMNS FROM booking');
$has_status = in_array('status', array_column($booking_cols, 'Field'), true);

// Handle Cancel Booking (O4, F7: strictly verify user account ID and ensure trip is not in the past)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    csrf_verify();
    $cancel_id = (int)($_POST['cancel_id'] ?? 0);
    if ($cancel_id > 0) {
        $today = date('Y-m-d');
        $existing = db_one($link, 'SELECT `date`, status FROM booking WHERE sno = ? AND id = ?', 'ii', [$cancel_id, $uid]);

        if (!$existing) {
            $alert = 'Booking not found or does not belong to your account.';
            $alert_type = 'danger';
        } elseif (($existing['status'] ?? '') === 'Cancelled') {
            $alert = 'This booking has already been cancelled.';
            $alert_type = 'info';
        } elseif ($existing['date'] < $today) {
            $alert = 'Cannot cancel a booking for a trip that has already departed.';
            $alert_type = 'danger';
        } else {
            if ($has_status) {
                // O4: Soft cancellation preserves audit trail and frees the seat
                $updated = db_exec($link, "UPDATE booking SET status = 'Cancelled' WHERE sno = ? AND id = ?", 'ii', [$cancel_id, $uid]);
            } else {
                $updated = db_exec($link, 'DELETE FROM booking WHERE sno = ? AND id = ?', 'ii', [$cancel_id, $uid]);
            }

            if ($updated > 0) {
                $alert = 'Booking cancelled successfully.';
                $alert_type = 'success';
            } else {
                $alert = 'Failed to cancel booking. Please try again.';
                $alert_type = 'danger';
            }
        }
    }
}

// Fetch user's bookings securely strictly by account ID (F7)
$bookings = db_all(
    $link,
    'SELECT * FROM booking WHERE id = ? ORDER BY sno DESC',
    'i',
    [$uid]
);

require_once __DIR__ . '/../includes/layout/header-user.php';
?>
<div class="col-lg-10 col-md-10 col-sm-12" style="float: right;">
  <section class="mt-4">
    <h2 class="text-info mb-3">Your Bookings</h2>

    <?php if ($alert): ?>
      <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
      </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-bordered table-striped">
        <thead class="thead-dark">
          <tr>
            <th>PNR</th>
            <th>Bus Number</th>
            <th>Passenger Name</th>
            <th>Contact</th>
            <th>From</th>
            <th>To</th>
            <th>Date</th>
            <th>Time</th>
            <th>Seat Number</th>
            <th>Status</th>
            <th>Fare</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bookings)): ?>
            <tr>
              <td colspan="12" class="text-center text-muted py-4">
                You have no active bookings. <a href="<?= BASE_URL ?>/user/index.php" class="btn btn-sm btn-info ml-2">Book a Trip</a>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($bookings as $row): ?>
              <?php
              $sno = (int)$row['sno'];
              $display_pnr = (string)($row['pnr'] ?? '');
              $status = (string)($row['status'] ?? 'Confirmed');
              $is_cancelled = ($status === 'Cancelled');
              ?>
              <tr class="<?= $is_cancelled ? 'table-secondary text-muted' : '' ?>">
                <td><strong><?= e($display_pnr) ?></strong></td>
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
                  <?php if ($is_cancelled): ?>
                    <span class="badge badge-secondary p-2">Cancelled</span>
                  <?php else: ?>
                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="cancel_id" value="<?= e($sno) ?>">
                      <button type="submit" name="cancel_booking" class="btn btn-danger btn-sm">Cancel</button>
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