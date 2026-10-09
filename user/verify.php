<?php
// user/verify.php -- Public Ticket QR & Seal Verification (Issue 5)
require_once __DIR__ . '/../includes/auth/session-bootstrap.php';
require_once __DIR__ . '/../includes/db_con.php';

$pnr = trim((string)($_GET['pnr'] ?? ''));
$sig = trim((string)($_GET['sig'] ?? ''));

$title = 'Verify Ticket';
$booking = null;
$is_verified = false;
$error_message = '';

if ($pnr === '' || $sig === '') {
    $error_message = 'Missing ticket reference (PNR) or cryptographic verification signature.';
} else {
    $booking = db_one($link, 'SELECT * FROM booking WHERE pnr = ?', 's', [$pnr]);
    if (!$booking) {
        $error_message = 'Ticket record not found. Please verify the PNR number.';
    } else {
        $expected_sig = get_ticket_signature($booking);
        if (!hash_equals($expected_sig, $sig)) {
            $error_message = 'Invalid digital seal. The cryptographic signature does not match the ticket record.';
        } else {
            $is_verified = true;
        }
    }
}

$raw_status = (string)($booking['status'] ?? 'Confirmed');
$is_cancelled = ($raw_status === 'Cancelled');
$is_expired = ($raw_status === 'Expired');
$dep_time = !empty($booking['time']) ? (string)$booking['time'] : '00:00:00';
$dep_ts = !empty($booking['date']) ? strtotime($booking['date'] . ' ' . $dep_time) : 0;
$is_past = ($dep_ts <= time());

$is_valid_for_travel = ($is_verified && !$is_cancelled && !$is_expired && !$is_past && ($raw_status === 'Confirmed' || $raw_status === ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | Bus Service</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/design-system.css">
    <style>
        body { background: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .verify-card { max-width: 540px; width: 100%; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; background: #fff; overflow: hidden; }
        .status-badge-lg { font-size: 1.1rem; padding: 8px 16px; border-radius: 8px; font-weight: 700; display: inline-block; }
    </style>
</head>
<body>
    <div class="verify-card">
        <div class="p-4 text-center border-bottom bg-light">
            <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="Logo" style="width: 40px; height: 40px;" class="mb-2">
            <h5 class="font-weight-bold mb-0 text-dark">Digital Pass Verification</h5>
            <small class="text-muted">Bus Reservation Conductor &amp; Passenger Verification</small>
        </div>

        <div class="p-4">
            <?php if (!$is_verified): ?>
                <div class="alert alert-danger text-center mb-4">
                    <div class="h3 mb-2">⚠️</div>
                    <h6 class="font-weight-bold mb-1">Verification Failed</h6>
                    <p class="small mb-0"><?= e($error_message) ?></p>
                </div>
            <?php else: ?>
                <div class="text-center mb-4">
                    <?php if ($is_valid_for_travel): ?>
                        <div class="status-badge-lg bg-success text-white shadow-sm mb-2">
                            ✓ VALID BOARDING PASS
                        </div>
                        <div class="text-success small font-weight-bold">Cryptographically Authenticated</div>
                    <?php elseif ($is_cancelled): ?>
                        <div class="status-badge-lg bg-danger text-white shadow-sm mb-2">
                            ✕ VOID &bull; CANCELLED
                        </div>
                        <div class="text-danger small font-weight-bold">This reservation has been cancelled</div>
                    <?php elseif ($is_expired): ?>
                        <div class="status-badge-lg bg-secondary text-white shadow-sm mb-2">
                            ✕ VOID &bull; EXPIRED
                        </div>
                        <div class="text-muted small font-weight-bold">Seat hold reservation expired</div>
                    <?php elseif ($is_past): ?>
                        <div class="status-badge-lg bg-secondary text-white shadow-sm mb-2">
                            ✓ TRIP COMPLETED
                        </div>
                        <div class="text-muted small font-weight-bold">This journey departed in the past</div>
                    <?php else: ?>
                        <div class="status-badge-lg bg-warning text-dark shadow-sm mb-2">
                            ⏳ PENDING PAYMENT
                        </div>
                        <div class="text-warning small font-weight-bold">Hold active, awaiting payment confirmation</div>
                    <?php endif; ?>
                </div>

                <div class="bg-light p-3 rounded mb-3 border">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted text-uppercase d-block font-weight-bold" style="font-size: 11px;">PNR</small>
                            <code class="font-weight-bold h6 text-dark mb-0"><?= e($booking['pnr']) ?></code>
                        </div>
                        <div class="col-6 mb-2 text-right">
                            <small class="text-muted text-uppercase d-block font-weight-bold" style="font-size: 11px;">Bus</small>
                            <span class="font-weight-bold text-dark">Bus #<?= e($booking['bus']) ?></span>
                        </div>
                        <div class="col-12 mb-2">
                            <small class="text-muted text-uppercase d-block font-weight-bold" style="font-size: 11px;">Route</small>
                            <strong class="text-dark"><?= e($booking['city1']) ?> &rarr; <?= e($booking['city2']) ?></strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted text-uppercase d-block font-weight-bold" style="font-size: 11px;">Passenger</small>
                            <span class="text-dark font-weight-medium"><?= e($booking['name']) ?></span>
                        </div>
                        <div class="col-6 mb-2 text-right">
                            <small class="text-muted text-uppercase d-block font-weight-bold" style="font-size: 11px;">Seat</small>
                            <span class="badge badge-primary px-2 py-1">Seat #<?= e($booking['seat']) ?></span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted text-uppercase d-block font-weight-bold" style="font-size: 11px;">Date</small>
                            <span class="text-dark small"><?= e(fmt_date($booking['date'])) ?></span>
                        </div>
                        <div class="col-6 text-right">
                            <small class="text-muted text-uppercase d-block font-weight-bold" style="font-size: 11px;">Time</small>
                            <span class="text-dark small"><?= e(fmt_time($booking['time'])) ?></span>
                        </div>
                    </div>
                </div>

                <div class="text-center small text-muted font-monospace">
                    HMAC-SHA256 SEAL: <?= e(strtoupper($sig)) ?>
                </div>
            <?php endif; ?>

            <div class="mt-4 text-center">
                <a href="<?= BASE_URL ?>/homepage.php" class="btn btn-outline-secondary btn-sm">
                    &larr; Return to Homepage
                </a>
            </div>
        </div>
    </div>
</body>
</html>
