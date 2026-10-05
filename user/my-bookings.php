<?php
// user/my-bookings.php
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$uid = (int)($_SESSION['uid'] ?? 0);
$phone = trim((string)($_SESSION['phone'] ?? ''));

$alert = null;
$alert_type = 'info';

// U-08: Verify PNR from database for signed-in customer before displaying confirmation
if (!empty($_GET['booked']) && !empty($_GET['pnr'])) {
    $pnr_param = trim((string)$_GET['pnr']);
    $verified_booking = db_one($link, 'SELECT pnr FROM booking WHERE pnr = ? AND id = ?', 'si', [$pnr_param, $uid]);
    if ($verified_booking && !empty($verified_booking['pnr'])) {
        $alert = 'Your reservation was confirmed! Your unique PNR is ' . $verified_booking['pnr'];
        $alert_type = 'success';
    }
}

$has_status = table_has_column($link, 'booking', 'status');
$cutoff_min = defined('APP_CANCEL_CUTOFF_MIN') ? (int)APP_CANCEL_CUTOFF_MIN : 120;
$cutoff_display = ($cutoff_min >= 60 && $cutoff_min % 60 === 0) ? ($cutoff_min / 60) . ' hours' : $cutoff_min . ' minutes';

// Handle Cancellation Request with Strict Cut-off and PRG Pattern (U-10)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    csrf_verify();
    $cancel_id = (int)($_POST['cancel_id'] ?? 0);
    if ($cancel_id > 0) {
        $existing = db_one($link, 'SELECT `date`, `time`, status, pnr FROM booking WHERE sno = ? AND id = ?', 'ii', [$cancel_id, $uid]);

        if (!$existing) {
            flash_set('danger', 'Booking not found or does not belong to your account.');
        } elseif (($existing['status'] ?? '') === 'Cancelled') {
            flash_set('info', 'This booking has already been cancelled.');
        } elseif (($existing['status'] ?? '') === 'Expired') {
            flash_set('info', 'This seat hold has already expired.');
        } else {
            $dep_time = !empty($existing['time']) ? (string)$existing['time'] : '00:00:00';
            $dep_ts = strtotime($existing['date'] . ' ' . $dep_time);
            $now_ts = time();

            if ($dep_ts <= $now_ts) {
                flash_set('danger', 'Cannot cancel a booking for a trip that has already departed.');
            } elseif (($dep_ts - $now_ts) < ($cutoff_min * 60)) {
                $rem_min = max(1, (int)floor(($dep_ts - $now_ts) / 60));
                flash_set('danger', "Cancellation refused: trips can only be cancelled at least {$cutoff_display} before departure. This trip departs in {$rem_min} minutes.");
            } else {
                if ($has_status) {
                    $updated = db_exec($link, "UPDATE booking SET status = 'Cancelled' WHERE sno = ? AND id = ?", 'ii', [$cancel_id, $uid]);
                } else {
                    $updated = db_exec($link, 'DELETE FROM booking WHERE sno = ? AND id = ?', 'ii', [$cancel_id, $uid]);
                }

                if ($updated > 0) {
                    flash_set('success', 'Booking cancelled successfully and seat has been liberated. Eligible refunds are processed within 3–5 business days.');
                } else {
                    flash_set('danger', 'Failed to cancel booking. Please try again.');
                }
            }
        }
    }
    // Post-Redirect-Get pattern (U-10) avoids form re-submission on refresh
    header('Location: ' . BASE_URL . '/user/my-bookings.php');
    exit;
}

$bookings = db_all(
    $link,
    'SELECT * FROM booking WHERE id = ? ORDER BY `date` DESC, `time` DESC, sno DESC',
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
                        $display_pnr = (string)($row['pnr'] ?? ('#' . $sno));
                        $raw_status = (string)($row['status'] ?? 'Confirmed');
                        $dep_time = !empty($row['time']) ? (string)$row['time'] : '00:00:00';
                        $dep_ts = strtotime($row['date'] . ' ' . $dep_time);
                        $now_ts = time();
                        $is_past = ($dep_ts <= $now_ts);
                        $can_cancel = false;
                        $cancel_refusal_reason = '';

                        // Status mapping and action permissions (U-09)
                        if ($raw_status === 'Cancelled') {
                            $badge_class = 'badge-danger';
                            $badge_label = 'Cancelled';
                        } elseif ($raw_status === 'Expired') {
                            $badge_class = 'badge-secondary';
                            $badge_label = 'Expired';
                        } elseif ($is_past) {
                            $badge_class = 'badge-secondary';
                            $badge_label = 'Completed';
                        } elseif ($raw_status === 'Pending') {
                            $badge_class = 'badge-warning text-dark';
                            $badge_label = 'Pending Hold';
                            if (($dep_ts - $now_ts) >= ($cutoff_min * 60)) {
                                $can_cancel = true;
                            } else {
                                $cancel_refusal_reason = 'Past cutoff window';
                            }
                        } else {
                            $badge_class = 'badge-success';
                            $badge_label = 'Confirmed';
                            if (($dep_ts - $now_ts) >= ($cutoff_min * 60)) {
                                $can_cancel = true;
                            } else {
                                $cancel_refusal_reason = 'Past cutoff window';
                            }
                        }
                        ?>
                        <tr class="<?= ($raw_status === 'Cancelled' || $raw_status === 'Expired' || $is_past) ? 'text-muted' : '' ?>">
                            <td><code><?= e($display_pnr) ?></code></td>
                            <td><strong><?= e($row['bus'] ?? '') ?></strong></td>
                            <td class="font-weight-medium text-dark"><?= e($row['name'] ?? '') ?></td>
                            <td><?= e($row['city1'] ?? '') ?> &rarr; <?= e($row['city2'] ?? '') ?></td>
                            <td><?= e(fmt_date($row['date'] ?? '')) ?><br><small class="text-muted"><?= e(fmt_time($row['time'] ?? '')) ?></small></td>
                            <td><span class="badge badge-info px-2 py-1">Seat #<?= e($row['seat'] ?? '') ?></span></td>
                            <td>
                                <span class="badge <?= $badge_class ?>">
                                    <?= e($badge_label) ?>
                                </span>
                            </td>
                            <td class="font-weight-bold text-dark"><?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                            <td class="text-right">
                                <?php if ($can_cancel): ?>
                                    <button type="button"
                                        class="btn btn-outline-danger btn-sm open-cancel-modal"
                                        data-id="<?= e($sno) ?>"
                                        data-pnr="<?= e($display_pnr) ?>"
                                        data-route="<?= e(($row['city1'] ?? '') . ' &rarr; ' . ($row['city2'] ?? '')) ?>"
                                        data-schedule="<?= e(fmt_date($row['date'] ?? '') . ' at ' . fmt_time($row['time'] ?? '')) ?>"
                                        data-toggle="modal"
                                        data-target="#cancelModal">
                                        Cancel
                                    </button>
                                <?php elseif ($raw_status === 'Cancelled'): ?>
                                    <span class="badge badge-light border text-muted">Cancelled</span>
                                <?php elseif ($raw_status === 'Expired'): ?>
                                    <span class="badge badge-light border text-muted">Expired</span>
                                <?php elseif ($is_past): ?>
                                    <span class="badge badge-light border text-muted">Departed</span>
                                <?php else: ?>
                                    <span class="badge badge-light border text-muted" title="<?= e($cancel_refusal_reason) ?>">Non-refundable</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bootstrap Cancellation Modal (U-10) -->
<div class="modal fade" id="cancelModal" tabindex="-1" role="dialog" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="cancel_id" id="modal-cancel-id" value="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title font-weight-bold" id="cancelModalLabel">Confirm Reservation Cancellation</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-2">Are you sure you want to cancel booking for ticket <code id="modal-cancel-pnr" class="font-weight-bold"></code>?</p>
                    <div class="p-3 bg-light rounded border mb-3 small">
                        <div><strong>Route:</strong> <span id="modal-cancel-route"></span></div>
                        <div><strong>Schedule:</strong> <span id="modal-cancel-schedule"></span></div>
                    </div>
                    <div class="alert alert-warning py-2 px-3 small mb-0">
                        <div class="font-weight-bold mb-1">Cancellation Policy:</div>
                        <ul class="mb-0 pl-3">
                            <li>Cancellations are permitted up to <strong><?= e($cutoff_display) ?></strong> before departure.</li>
                            <li>The seat will immediately become available for other passengers.</li>
                            <li>Eligible refunds will be processed to the original payment method within 3–5 business days.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Keep Booking</button>
                    <button type="submit" name="cancel_booking" class="btn btn-danger font-weight-bold">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var cancelButtons = document.querySelectorAll('.open-cancel-modal');
    cancelButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var pnr = this.getAttribute('data-pnr');
            var route = this.getAttribute('data-route');
            var schedule = this.getAttribute('data-schedule');

            document.getElementById('modal-cancel-id').value = id;
            document.getElementById('modal-cancel-pnr').textContent = pnr;
            document.getElementById('modal-cancel-route').innerHTML = route;
            document.getElementById('modal-cancel-schedule').textContent = schedule;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>