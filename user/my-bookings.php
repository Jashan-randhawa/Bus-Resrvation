<?php
// user/my-bookings.php
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$uid = (int)($_SESSION['uid'] ?? 0);
$phone = trim((string)($_SESSION['phone'] ?? ''));
$now_ts = time();

$alert = null;
$alert_type = 'info';

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
                try {
                    $updated = cancel_booking($link, $cancel_id, $uid);
                    if ($updated > 0) {
                        flash_set('success', 'Booking cancelled successfully and seat has been liberated. Eligible refunds are processed within 3–5 business days.');
                    } else {
                        flash_set('danger', 'Failed to cancel booking or booking was already cancelled.');
                    }
                } catch (Throwable $e) {
                    error_log('[busres] Cancellation error: ' . $e->getMessage());
                    flash_set('danger', 'Failed to cancel booking. Please try again.');
                }
            }
        }
    }
    // Post-Redirect-Get pattern (U-10) avoids form re-submission on refresh
    header('Location: ' . BASE_URL . '/user/my-bookings.php');
    exit;
}

// Phase 5.1: SQL-level counting and pagination
$cancelled_cnt = (int)(db_one($link,
    "SELECT COUNT(*) AS c FROM booking WHERE id = ? AND (status IN ('Cancelled', 'Expired'))",
    'i', [$uid]
)['c'] ?? 0);

$upcoming_cnt = (int)(db_one($link,
    "SELECT COUNT(*) AS c FROM booking WHERE id = ? AND (status NOT IN ('Cancelled', 'Expired') OR status IS NULL) AND (date > CURDATE() OR (date = CURDATE() AND time >= CURTIME()))",
    'i', [$uid]
)['c'] ?? 0);

$past_cnt = (int)(db_one($link,
    "SELECT COUNT(*) AS c FROM booking WHERE id = ? AND (status NOT IN ('Cancelled', 'Expired') OR status IS NULL) AND (date < CURDATE() OR (date = CURDATE() AND time < CURTIME()))",
    'i', [$uid]
)['c'] ?? 0);

$counts = [
    'upcoming'  => $upcoming_cnt,
    'past'      => $past_cnt,
    'cancelled' => $cancelled_cnt
];

// Tab & Pagination resolution (U-16, Phase 5.1)
$current_tab = in_array($_GET['tab'] ?? '', ['upcoming', 'past', 'cancelled'], true) ? $_GET['tab'] : 'upcoming';
$per_page = 10;
$total_records = $counts[$current_tab] ?? 0;
$total_pages = max(1, (int)ceil($total_records / $per_page));
$page = max(1, min($total_pages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $per_page;

if ($current_tab === 'cancelled') {
    $display_records = db_all($link,
        "SELECT * FROM booking WHERE id = ? AND (status IN ('Cancelled', 'Expired')) ORDER BY date DESC, time DESC LIMIT ? OFFSET ?",
        'iii', [$uid, $per_page, $offset]
    );
} elseif ($current_tab === 'past') {
    $display_records = db_all($link,
        "SELECT * FROM booking WHERE id = ? AND (status NOT IN ('Cancelled', 'Expired') OR status IS NULL) AND (date < CURDATE() OR (date = CURDATE() AND time < CURTIME())) ORDER BY date DESC, time DESC LIMIT ? OFFSET ?",
        'iii', [$uid, $per_page, $offset]
    );
} else {
    $display_records = db_all($link,
        "SELECT * FROM booking WHERE id = ? AND (status NOT IN ('Cancelled', 'Expired') OR status IS NULL) AND (date > CURDATE() OR (date = CURDATE() AND time >= CURTIME())) ORDER BY date ASC, time ASC LIMIT ? OFFSET ?",
        'iii', [$uid, $per_page, $offset]
    );
}

$title = 'My Reservations';
require_once __DIR__ . '/../includes/layout/header-user.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">My Travel Bookings</h1>
        <p class="page-subtitle">Track your tickets, view PNR tokens, review route schedules, and manage active reservations.</p>
    </div>
    <a href="<?= BASE_URL ?>/user/index.php" class="btn btn-primary shadow-sm font-weight-bold">
        + Book New Trip
    </a>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Category Tabs (F7) -->
<div class="booking-tabs-wrap mb-4">
    <ul class="nav nav-pills booking-tabs-nav">
        <li class="nav-item">
            <a class="nav-link font-weight-bold <?= $current_tab === 'upcoming' ? 'active' : '' ?>" href="?tab=upcoming">
                Upcoming <span class="badge badge-light ml-1"><?= $counts['upcoming'] ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link font-weight-bold <?= $current_tab === 'past' ? 'active' : '' ?>" href="?tab=past">
                Past <span class="badge badge-light ml-1"><?= $counts['past'] ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link font-weight-bold <?= $current_tab === 'cancelled' ? 'active' : '' ?>" href="?tab=cancelled">
                Cancelled <span class="badge badge-light ml-1"><?= $counts['cancelled'] ?></span>
            </a>
        </li>
    </ul>
</div>

<!-- Desktop Table View (Hidden on mobile <768px for zero horizontal scroll) -->
<div class="data-table-wrapper d-none d-md-block mb-4">
    <div class="table-header">
        <h5 class="mb-0 text-capitalize"><?= e($current_tab) ?> Reservations</h5>
        <span class="record-count"><?= $total_records ?> record(s)</span>
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
                <?php if (empty($display_records)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state py-5">
                                <div class="empty-icon">🎟️</div>
                                <div class="empty-title">No <?= e($current_tab) ?> bookings</div>
                                <div class="empty-text">No travel records found in this category.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($display_records as $row): ?>
                        <?php
                        $sno = (int)$row['sno'];
                        $display_pnr = (string)($row['pnr'] ?? ('#' . $sno));
                        $raw_status = (string)($row['status'] ?? 'Confirmed');
                        $dep_time = !empty($row['time']) ? (string)$row['time'] : '00:00:00';
                        $dep_ts = strtotime($row['date'] . ' ' . $dep_time);
                        $is_past = ($dep_ts <= $now_ts);
                        $can_cancel = false;
                        $cancel_refusal_reason = '';

                        // Status mapping and action permissions (U-09)
                        $badge_info = get_booking_status_badge($raw_status, $is_past);
                        $badge_class = $badge_info['class'];
                        $badge_label = $badge_info['label'];

                        if ($raw_status === 'Pending' || $raw_status === 'Confirmed') {
                            if (!$is_past && ($dep_ts - $now_ts) >= ($cutoff_min * 60)) {
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
                                <!-- U-16: Printable Ticket Link -->
                                <a href="<?= BASE_URL ?>/user/ticket.php?pnr=<?= urlencode($display_pnr) ?>" class="btn btn-sm btn-outline-primary mr-1" title="View Boarding Ticket">
                                    🎟️ Ticket
                                </a>
                                <?php if ($can_cancel): ?>
                                    <button type="button"
                                        class="btn btn-outline-danger btn-sm open-cancel-modal"
                                        data-id="<?= e($sno) ?>"
                                        data-pnr="<?= e($display_pnr) ?>"
                                        data-route="<?= e(($row['city1'] ?? '') . ' → ' . ($row['city2'] ?? '')) ?>"
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

<!-- Mobile Card View (U-16: 0 sideways scroll at 390px) -->
<div class="d-md-none mb-4">
    <?php if (empty($display_records)): ?>
        <div class="card p-4 text-center">
            <div class="empty-icon h2 mb-2">🎟️</div>
            <div class="font-weight-bold text-dark">No <?= e($current_tab) ?> bookings</div>
            <div class="text-muted small">No travel records found in this category.</div>
        </div>
    <?php else: ?>
        <?php foreach ($display_records as $row): ?>
            <?php
            $sno = (int)$row['sno'];
            $display_pnr = (string)($row['pnr'] ?? ('#' . $sno));
            $raw_status = (string)($row['status'] ?? 'Confirmed');
            $dep_time = !empty($row['time']) ? (string)$row['time'] : '00:00:00';
            $dep_ts = strtotime($row['date'] . ' ' . $dep_time);
            $is_past = ($dep_ts <= $now_ts);
            $can_cancel = false;
            $cancel_refusal_reason = '';

            // Status mapping and action permissions (U-09)
            $badge_info = get_booking_status_badge($raw_status, $is_past);
            $badge_class = $badge_info['class'];
            $badge_label = $badge_info['label'];

            if ($raw_status === 'Pending' || $raw_status === 'Confirmed') {
                if (!$is_past && ($dep_ts - $now_ts) >= ($cutoff_min * 60)) {
                    $can_cancel = true;
                }
            }
            ?>
            <div class="booking-card mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <code class="font-weight-bold text-dark booking-card-pnr">PNR: <?= e($display_pnr) ?></code>
                        <div class="h6 font-weight-bold text-dark mt-1 mb-0 booking-card-route">
                            <?= e($row['city1'] ?? '') ?> &rarr; <?= e($row['city2'] ?? '') ?>
                        </div>
                    </div>
                    <span class="badge <?= $badge_class ?> text-uppercase font-weight-bold px-2 py-1"><?= e($badge_label) ?></span>
                </div>
                <div class="small text-muted mb-2">
                    📅 <?= e(fmt_date($row['date'] ?? '')) ?> at <?= e(fmt_time($row['time'] ?? '')) ?> &bull; 🚌 Bus #<?= e($row['bus'] ?? '') ?> &bull; <span class="badge badge-info font-weight-bold">Seat #<?= e($row['seat'] ?? '') ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted font-weight-bold">Fare Paid:</span>
                    <strong class="text-success h6 mb-0"><?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?></strong>
                </div>
                <div class="d-flex gap-2 pt-2 border-top booking-card-actions">
                    <a href="<?= BASE_URL ?>/user/ticket.php?pnr=<?= urlencode($display_pnr) ?>" class="btn btn-outline-primary btn-sm flex-fill font-weight-bold d-flex align-items-center justify-content-center mr-1" style="min-height: 44px;">
                        🎟️ View Ticket
                    </a>
                    <?php if ($can_cancel): ?>
                        <button type="button"
                            class="btn btn-outline-danger btn-sm flex-fill font-weight-bold open-cancel-modal d-flex align-items-center justify-content-center ml-1"
                            style="min-height: 44px;"
                            data-id="<?= e($sno) ?>"
                            data-pnr="<?= e($display_pnr) ?>"
                            data-route="<?= e(($row['city1'] ?? '') . ' → ' . ($row['city2'] ?? '')) ?>"
                            data-schedule="<?= e(fmt_date($row['date'] ?? '') . ' at ' . fmt_time($row['time'] ?? '')) ?>"
                            data-toggle="modal"
                            data-target="#cancelModal">
                            Cancel Booking
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination (U-16: 10 per page, responsive mobile compact) -->
<?php if ($total_pages > 1): ?>
    <nav aria-label="Bookings pagination" class="my-4">
        <!-- Mobile Compact Pagination (<768px) -->
        <div class="d-flex d-md-none justify-content-between align-items-center">
            <a class="btn btn-outline-secondary btn-sm font-weight-bold px-3 <?= $page <= 1 ? 'disabled' : '' ?>" href="?tab=<?= urlencode($current_tab) ?>&page=<?= $page - 1 ?>" style="min-height: 44px; display: inline-flex; align-items: center;">
                &laquo; Prev
            </a>
            <span class="small font-weight-bold text-muted">Page <?= $page ?> of <?= $total_pages ?></span>
            <a class="btn btn-outline-secondary btn-sm font-weight-bold px-3 <?= $page >= $total_pages ? 'disabled' : '' ?>" href="?tab=<?= urlencode($current_tab) ?>&page=<?= $page + 1 ?>" style="min-height: 44px; display: inline-flex; align-items: center;">
                Next &raquo;
            </a>
        </div>

        <!-- Desktop Numbered Pagination (>=768px) -->
        <ul class="pagination pagination-sm shadow-sm justify-content-center d-none d-md-flex mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?tab=<?= urlencode($current_tab) ?>&page=<?= $page - 1 ?>">&laquo; Prev</a>
            </li>
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?tab=<?= urlencode($current_tab) ?>&page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="?tab=<?= urlencode($current_tab) ?>&page=<?= $page + 1 ?>">Next &raquo;</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- Bootstrap Cancellation Modal (U-10, Phase 1.2, Phase 2.3) -->
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
                            <li>Refund handling depends on your payment arrangement with the operator.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer bg-light flex-column-reverse flex-sm-row">
                    <button type="button" class="btn btn-secondary btn-block-mobile" data-dismiss="modal" style="min-height: 44px;">Keep Booking</button>
                    <button type="submit" name="cancel_booking" class="btn btn-danger font-weight-bold btn-block-mobile mb-2 mb-sm-0" style="min-height: 44px;">Confirm Cancellation</button>
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
            document.getElementById('modal-cancel-route').textContent = route;
            document.getElementById('modal-cancel-schedule').textContent = schedule;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-user.php'; ?>