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

// Handle Cancel Booking (converted from insecure GET to POST with CSRF and IDOR authorization)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    csrf_verify();
    $cancel_id = (int)($_POST['cancel_id'] ?? 0);
    if ($cancel_id > 0) {
        // Enforce IDOR protection: User can ONLY cancel their own booking
        $deleted = db_exec(
            $link,
            'DELETE FROM booking WHERE sno = ? AND (id = ? OR contact = ?)',
            'iis',
            [$cancel_id, $uid, $phone]
        );
        if ($deleted > 0) {
            $alert = 'Booking cancelled successfully.';
            $alert_type = 'success';
        } else {
            $alert = 'Booking could not be cancelled or does not belong to your account.';
            $alert_type = 'danger';
        }
    }
}

// Fetch user's bookings securely
$bookings = db_all(
    $link,
    'SELECT * FROM booking WHERE (id = ? OR contact = ?) ORDER BY sno DESC',
    'is',
    [$uid, $phone]
);

require_once __DIR__ . '/../includes/layout/header-user.php';
?>
<div class="col-lg-10 col-md-10 col-sm-12" style="float: right;">
  <section class="mt-4">
    <h2 class="text-info mb-3">Your Bookings</h2>

    <?php if ($alert): ?>
      <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= $alert ?>
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
            <th>Fare</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bookings)): ?>
            <tr>
              <td colspan="11" class="text-center text-muted py-4">
                You have no active bookings. <a href="<?= BASE_URL ?>/user/index.php" class="btn btn-sm btn-info ml-2">Book a Trip</a>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($bookings as $row): ?>
              <?php
              $sno = (int)$row['sno'];
              $display_pnr = !empty($row['pnr']) ? $row['pnr'] : (string)$sno;
              ?>
              <tr>
                <td><strong><?= e($display_pnr) ?></strong></td>
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
                  <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="cancel_id" value="<?= e($sno) ?>">
                    <button type="submit" name="cancel_booking" class="btn btn-danger btn-sm">Cancel Booking</button>
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