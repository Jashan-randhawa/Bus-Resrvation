<?php
// user/my-bookings.php
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$uid = (int)($_SESSION['uid'] ?? 0);
$phone = trim((string)($_SESSION['phone'] ?? ''));

$alert = null;
$alert_type = 'info';

if (!empty($_GET['booked']) && !empty($_GET['pnr'])) {
    $alert = 'Your reservation was confirmed! Your unique PNR is ' . e($_GET['pnr']);
    $alert_type = 'success';
}

$booking_cols = db_all($link, 'SHOW COLUMNS FROM booking');
$has_status = in_array('status', array_column($booking_cols, 'Field'), true);

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
                $updated = db_exec($link, "UPDATE booking SET status = 'Cancelled' WHERE sno = ? AND id = ?", 'ii', [$cancel_id, $uid]);
            } else {
                $updated = db_exec($link, 'DELETE FROM booking WHERE sno = ? AND id = ?', 'ii', [$cancel_id, $uid]);
            }

            if ($updated > 0) {
                $alert = 'Booking cancelled successfully and seat has been liberated.';
                $alert_type = 'success';
            } else {
                $alert = 'Failed to cancel booking. Please try again.';
                $alert_type = 'danger';
            }
        }
    }
}

$bookings = db_all(
    $link,
    'SELECT * FROM booking WHERE id = ? ORDER BY sno DESC',
    'i',
    [$uid]
);

$title = 'My Reservations';
require_once __DIR__ . '/../includes/layout/header-user.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">My Travel Bookings</h1>
        <p class="page-subtitle">Track your tickets, view PNR tokens, review route schedules, and manage active reservations.</p>
    </div>
    <a href="<?= BASE_URL ?>/user/index.php" class="btn btn-primary shadow-sm">
        + Book New Trip
    </a>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<div class="data-table-wrapper">
    <div class="table-header">
        <h5 class="mb-0">Your Reserved Tickets</h5>
        <span class="record-count"><?= count($bookings) ?> ticket(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>PNR</th>
                    <th>Bus</th>
                    <th>Passenger</th>
                    <th>Route</th>
                    <th>Date & Departure</th>
                    <th>Seat</th>
                    <th>Status</th>
                    <th>Tariff</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state py-5">
                                <div class="empty-icon">🎟️</div>
                                <div class="empty-title">No travel tickets yet</div>
                                <div class="empty-text">You haven't reserved any tickets. Click '+ Book New Trip' to browse active routes.</div>
                            </div>
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
                        <tr class="<?= $is_cancelled ? 'text-muted' : '' ?>">
                            <td><code><?= e($display_pnr ?: ('#' . $sno)) ?></code></td>
                            <td><strong><?= e($row['bus'] ?? '') ?></strong></td>
                            <td class="font-weight-medium text-dark"><?= e($row['name'] ?? '') ?></td>
                            <td><?= e($row['city1'] ?? '') ?> &rarr; <?= e($row['city2'] ?? '') ?></td>
                            <td><?= e($row['date'] ?? '') ?><br><small class="text-muted"><?= e($row['time'] ?? '') ?></small></td>
                            <td><span class="badge badge-info px-2 py-1">Seat #<?= e($row['seat'] ?? '') ?></span></td>
                            <td>
                                <span class="badge badge-<?= $is_cancelled ? 'danger' : 'success' ?>">
                                    <?= e($status) ?>
                                </span>
                            </td>
                            <td class="font-weight-bold text-dark"><?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                            <td class="text-right">
                                <?php if ($is_cancelled): ?>
                                    <span class="badge badge-light border text-muted">Cancelled</span>
                                <?php else: ?>
                                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="cancel_id" value="<?= e($sno) ?>">
                                        <button type="submit" name="cancel_booking" class="btn btn-outline-danger btn-sm">
                                            Cancel
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>