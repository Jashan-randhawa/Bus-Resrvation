<?php
// user/ticket.php -- Printable Boarding Pass & Ticket View (U-16)
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$uid = (int)($_SESSION['uid'] ?? 0);
$pnr = trim((string)($_GET['pnr'] ?? ''));

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
?>

<div class="no-print mb-4 d-flex justify-content-between align-items-center">
    <a href="<?= BASE_URL ?>/user/my-bookings.php" class="btn btn-outline-secondary">
        &larr; Back to My Bookings
    </a>
    <div>
        <button type="button" onclick="window.print()" class="btn btn-primary shadow-sm font-weight-bold">
            🖨️ Print Ticket
        </button>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="card border ticket-container shadow-sm p-4 p-md-5 bg-white">
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
                    <small class="text-muted text-uppercase font-weight-bold d-block">Fare Paid</small>
                    <div class="h3 font-weight-bold text-success mb-0"><?= CURRENCY ?><?= e(number_format((float)$booking['price'], 2)) ?></div>
                </div>
            </div>

            <!-- QR Verification & Barcode representation -->
            <div class="border-top pt-4 d-flex flex-column flex-sm-row justify-content-between align-items-center">
                <div class="mb-3 mb-sm-0 text-center text-sm-left">
                    <div class="font-weight-bold small text-dark mb-1">Boarding Instructions:</div>
                    <ul class="text-muted small pl-3 mb-0 text-left">
                        <li>Please arrive at the terminal at least 15 minutes before departure.</li>
                        <li>Carry a valid government photo ID matching passenger name.</li>
                        <li>Show this digital or printed pass with PNR at boarding.</li>
                    </ul>
                </div>
                <div class="text-center ml-sm-4">
                    <!-- Clean SVG QR Code Representation for PNR verification -->
                    <div style="background: #ffffff; padding: 6px; border: 1px solid #cbd5e1; border-radius: 8px; display: inline-block;">
                        <svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <rect width="100" height="100" fill="#ffffff" />
                            <!-- Corner marker Top-Left -->
                            <rect x="6" y="6" width="24" height="24" fill="#000000" />
                            <rect x="9" y="9" width="18" height="18" fill="#ffffff" />
                            <rect x="12" y="12" width="12" height="12" fill="#000000" />
                            <!-- Corner marker Top-Right -->
                            <rect x="70" y="6" width="24" height="24" fill="#000000" />
                            <rect x="73" y="9" width="18" height="18" fill="#ffffff" />
                            <rect x="76" y="12" width="12" height="12" fill="#000000" />
                            <!-- Corner marker Bottom-Left -->
                            <rect x="6" y="70" width="24" height="24" fill="#000000" />
                            <rect x="9" y="73" width="18" height="18" fill="#ffffff" />
                            <rect x="12" y="76" width="12" height="12" fill="#000000" />
                            <!-- Data matrix pattern elements -->
                            <rect x="36" y="8" width="6" height="6" fill="#000000" />
                            <rect x="48" y="14" width="6" height="6" fill="#000000" />
                            <rect x="58" y="8" width="6" height="6" fill="#000000" />
                            <rect x="36" y="24" width="6" height="6" fill="#000000" />
                            <rect x="44" y="32" width="12" height="12" fill="#000000" />
                            <rect x="62" y="24" width="6" height="6" fill="#000000" />
                            <rect x="16" y="38" width="6" height="6" fill="#000000" />
                            <rect x="26" y="44" width="6" height="6" fill="#000000" />
                            <rect x="70" y="38" width="8" height="8" fill="#000000" />
                            <rect x="84" y="44" width="6" height="6" fill="#000000" />
                            <rect x="36" y="52" width="6" height="6" fill="#000000" />
                            <rect x="48" y="58" width="8" height="8" fill="#000000" />
                            <rect x="62" y="52" width="6" height="6" fill="#000000" />
                            <rect x="36" y="72" width="6" height="6" fill="#000000" />
                            <rect x="48" y="82" width="6" height="6" fill="#000000" />
                            <rect x="62" y="72" width="6" height="6" fill="#000000" />
                            <rect x="74" y="78" width="14" height="14" fill="#000000" />
                        </svg>
                    </div>
                    <div class="text-muted small mt-1 font-monospace" style="font-size: 11px;">SCAN AT GATE</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>
