<?php
// user/ticket.php -- Printable Boarding Pass & Ticket View (U-16, Phase 2.1, 2.2, 2.3, 3.1)
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$uid = (int)($_SESSION['uid'] ?? 0);
$pnr = trim((string)($_GET['pnr'] ?? ''));
$is_new_booking = (isset($_GET['new']) && $_GET['new'] === '1');

if ($pnr === '') {
    flash_set('danger', 'Please provide a valid ticket PNR to view.');
    header('Location: ' . BASE_URL . '/user/my-bookings.php');
    exit;
}

$booking = db_one($link, 'SELECT * FROM booking WHERE pnr = ? AND id = ?', 'si', [$pnr, $uid]);
if (!$booking) {
    flash_set('danger', 'Ticket not found or does not belong to your account.');
    header('Location: ' . BASE_URL . '/user/my-bookings.php');
    exit;
}

$title = 'Boarding Ticket: ' . $pnr;
require_once __DIR__ . '/../includes/layout/header-user.php';

$raw_status = (string)($booking['status'] ?? 'Confirmed');
$is_cancelled = ($raw_status === 'Cancelled');
$is_expired = ($raw_status === 'Expired');
$dep_time = !empty($booking['time']) ? (string)$booking['time'] : '00:00:00';
$dep_ts = strtotime($booking['date'] . ' ' . $dep_time);
$is_past = ($dep_ts <= time());

if ($is_cancelled) {
    $status_label = 'Cancelled';
    $status_badge = 'badge-danger';
} elseif ($is_expired) {
    $status_label = 'Expired';
    $status_badge = 'badge-secondary';
} elseif ($is_past) {
    $status_label = 'Completed';
    $status_badge = 'badge-secondary';
} elseif ($raw_status === 'Pending') {
    $status_label = 'Pending Hold';
    $status_badge = 'badge-warning text-dark';
} else {
    $status_label = 'Confirmed';
    $status_badge = 'badge-success';
}

$is_active = (!$is_cancelled && !$is_expired);
$verify_hash = strtoupper(substr(hash('crc32b', $booking['pnr'] . $booking['date']), 0, 6));
?>

<style>
.ticket-container {
    position: relative;
    overflow: hidden;
}
.ticket-void-overlay {
    position: absolute;
    top: 36%;
    left: 8%;
    right: 8%;
    text-align: center;
    font-size: 3.2rem;
    font-weight: 900;
    color: rgba(220, 38, 38, 0.4);
    border: 6px dashed rgba(220, 38, 38, 0.4);
    border-radius: 16px;
    padding: 16px 10px;
    transform: rotate(-18deg);
    pointer-events: none;
    z-index: 10;
    letter-spacing: 0.12em;
    user-select: none;
}
@media print {
    .ticket-void-overlay {
        color: #dc2626 !important;
        border-color: #dc2626 !important;
        opacity: 0.8 !important;
    }
}
</style>

<!-- Phase 3.1: Celebratory Confirmation Banner for new bookings -->
<?php if ($is_new_booking): ?>
    <div class="alert alert-success shadow-sm p-4 mb-4 border-0 no-print" role="alert">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="mb-2 mb-md-0">
                <h4 class="alert-heading font-weight-bold mb-1">🎉 Reservation Confirmed!</h4>
                <p class="mb-0 text-dark">
                    Your seat has been reserved. Your Booking Reference (PNR) is 
                    <strong class="font-monospace text-success h5 mb-0"><?= e($booking['pnr']) ?></strong>.
                </p>
            </div>
            <div>
                <button type="button" onclick="window.print()" class="btn btn-success font-weight-bold shadow-sm mr-2">
                    🖨️ Print / Save PDF
                </button>
                <a href="<?= BASE_URL ?>/user/my-bookings.php" class="btn btn-outline-success font-weight-bold">
                    View My Bookings &rarr;
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="no-print mb-4 d-flex justify-content-between align-items-center">
    <a href="<?= BASE_URL ?>/user/my-bookings.php" class="btn btn-outline-secondary">
        &larr; Back to My Bookings
    </a>
    <div>
        <!-- Phase 2.2: Only show Print button if ticket is active/valid -->
        <?php if ($is_active): ?>
            <button type="button" onclick="window.print()" class="btn btn-primary shadow-sm font-weight-bold">
                🖨️ Print Ticket
            </button>
        <?php else: ?>
            <span class="badge badge-secondary px-3 py-2 text-uppercase">Ticket Inactive</span>
        <?php endif; ?>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="card border ticket-container shadow-sm p-4 p-md-5 bg-white">
            <!-- Phase 2.2: Watermark overlay for cancelled or expired tickets -->
            <?php if (!$is_active): ?>
                <div class="ticket-void-overlay font-weight-bold text-uppercase" aria-hidden="true">
                    <?= $is_cancelled ? 'VOID &bull; CANCELLED' : 'VOID &bull; EXPIRED' ?>
                </div>
            <?php endif; ?>

            <!-- Ticket Header -->
            <div class="d-flex justify-content-between align-items-center border-bottom pb-4 mb-4">
                <div>
                    <h3 class="font-weight-bold mb-1 text-primary">🚍 Bus Travel Pass</h3>
                    <p class="text-muted small mb-0">Official Boarding Pass &amp; Receipt</p>
                </div>
                <div class="text-right">
                    <span class="badge <?= $status_badge ?> px-3 py-2 text-uppercase font-weight-bold">
                        <?= e($status_label) ?>
                    </span>
                    <div class="text-muted small mt-1">PNR: <strong class="text-dark font-weight-bold font-monospace"><?= e($booking['pnr']) ?></strong></div>
                </div>
            </div>

            <!-- Route Details -->
            <div class="p-3 bg-light rounded border mb-4">
                <div class="row text-center align-items-center">
                    <div class="col-5">
                        <small class="text-muted text-uppercase d-block font-weight-bold">Origin</small>
                        <h4 class="font-weight-bold mb-0 text-dark"><?= e($booking['city1']) ?></h4>
                    </div>
                    <div class="col-2 text-primary font-weight-bold h4 mb-0">
                        &rarr;
                    </div>
                    <div class="col-5">
                        <small class="text-muted text-uppercase d-block font-weight-bold">Destination</small>
                        <h4 class="font-weight-bold mb-0 text-dark"><?= e($booking['city2']) ?></h4>
                    </div>
                </div>
            </div>

            <!-- Trip Meta Grid -->
            <div class="row mb-4">
                <div class="col-sm-6 mb-3">
                    <small class="text-muted text-uppercase font-weight-bold d-block">Passenger Name</small>
                    <div class="font-weight-bold text-dark h5 mb-0"><?= e($booking['name']) ?></div>
                    <small class="text-muted">Contact: <?= e($booking['contact']) ?></small>
                </div>
                <div class="col-sm-6 mb-3 text-sm-right">
                    <small class="text-muted text-uppercase font-weight-bold d-block">Bus Number</small>
                    <div class="font-weight-bold text-dark h5 mb-0">Bus #<?= e($booking['bus']) ?></div>
                </div>
                <div class="col-sm-6 mb-3">
                    <small class="text-muted text-uppercase font-weight-bold d-block">Travel Date</small>
                    <div class="font-weight-bold text-dark"><?= e(fmt_date($booking['date'])) ?></div>
                </div>
                <div class="col-sm-6 mb-3 text-sm-right">
                    <small class="text-muted text-uppercase font-weight-bold d-block">Departure Time</small>
                    <div class="font-weight-bold text-dark"><?= e(fmt_time($booking['time'])) ?></div>
                </div>
                <div class="col-sm-6 mb-3">
                    <small class="text-muted text-uppercase font-weight-bold d-block">Assigned Seat</small>
                    <div class="h3 font-weight-bold text-primary mb-0">Seat #<?= e($booking['seat']) ?></div>
                </div>
                <div class="col-sm-6 mb-3 text-sm-right">
                    <!-- Phase 2.3: Honest fare description -->
                    <small class="text-muted text-uppercase font-weight-bold d-block">
                        <?= $is_active ? 'Ticket Fare' : 'Fare (Void / Cancelled)' ?>
                    </small>
                    <div class="h3 font-weight-bold text-success mb-0"><?= CURRENCY ?><?= e(number_format((float)$booking['price'], 2)) ?></div>
                </div>
            </div>

            <!-- Instructions & Digital Verification Seal (Phase 2.1, 2.2) -->
            <div class="border-top pt-4 d-flex flex-column flex-sm-row justify-content-between align-items-center">
                <div class="mb-3 mb-sm-0 text-center text-sm-left flex-grow-1 pr-sm-3">
                    <?php if ($is_active): ?>
                        <div class="font-weight-bold small text-dark mb-1">Boarding Instructions:</div>
                        <ul class="text-muted small pl-3 mb-0 text-left">
                            <li>Please arrive at the terminal at least 15 minutes before departure.</li>
                            <li>Carry a valid government photo ID matching the passenger name.</li>
                            <li>Present this digital ticket or printed pass with PNR at boarding.</li>
                        </ul>
                    <?php else: ?>
                        <!-- Phase 2.2: Replaced boarding instructions for void tickets -->
                        <div class="alert alert-danger mb-0 small text-left">
                            <strong>Notice:</strong> This reservation was <?= strtolower($status_label) ?> and is not valid for boarding or travel.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="text-center ml-sm-4 mt-3 mt-sm-0">
                    <!-- Phase 2.1: Digital Security Seal & Verification Badge -->
                    <div class="p-3 bg-light border rounded text-center shadow-sm" style="min-width: 150px;">
                        <div class="text-muted font-weight-bold text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Digital Pass</div>
                        <div class="font-weight-bold text-primary font-monospace my-1" style="font-size: 1.25rem;">
                            <?= e($booking['pnr']) ?>
                        </div>
                        <div class="badge badge-light border text-muted px-2 py-1 font-monospace" style="font-size: 11px;">
                            SEAL: <?= e($verify_hash) ?>
                        </div>
                        <div class="text-muted small mt-1 font-weight-medium" style="font-size: 10px;">
                            <?= $is_active ? 'VALID PASS' : 'VOID' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-user.php'; ?>
