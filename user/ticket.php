<?php
// user/ticket.php -- Printable Boarding Pass & Ticket View (U-16, Phase 2.1, 2.2, 2.3, 3.1)
require_once __DIR__ . '/../includes/auth/session-bootstrap.php';
require_once __DIR__ . '/../includes/db_con.php';

$uid = (int)($_SESSION['uid'] ?? 0);
$pnr = trim((string)($_GET['pnr'] ?? ''));
$sig = trim((string)($_GET['sig'] ?? ''));
$is_new_booking = (isset($_GET['new']) && $_GET['new'] === '1');

if ($pnr === '') {
    flash_set('danger', 'Please provide a valid ticket PNR to view.');
    header('Location: ' . (!empty($_SESSION['uid']) ? BASE_URL . '/user/my-bookings.php' : BASE_URL . '/homepage.php#pnr'));
    exit;
}

$booking = db_one($link, 'SELECT * FROM booking WHERE pnr = ?', 's', [$pnr]);
if (!$booking) {
    flash_set('danger', 'Ticket record not found. Please verify the booking reference (PNR).');
    header('Location: ' . (!empty($_SESSION['uid']) ? BASE_URL . '/user/my-bookings.php' : BASE_URL . '/homepage.php#pnr'));
    exit;
}

// Access authorization:
// 1. Valid cryptographic ticket seal (&sig=...)
// 2. OR Authenticated passenger who owns the booking (booking.id == session.uid)
// 3. OR Authenticated administrator
$expected_sig = get_ticket_signature($booking);
$sig_valid = ($sig !== '' && hash_equals($expected_sig, $sig));
$is_owner = ($uid > 0 && (int)$booking['id'] === $uid);
$is_admin = in_array($_SESSION['role'] ?? '', ['super_admin', 'operator', 'viewer'], true);

if (!$sig_valid && !$is_owner && !$is_admin) {
    if (empty($_SESSION['role'])) {
        $req_uri = $_SERVER['REQUEST_URI'] ?? (BASE_URL . '/user/ticket.php?pnr=' . urlencode($pnr));
        header('Location: ' . BASE_URL . '/homepage.php?next=' . urlencode($req_uri));
        exit;
    }
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
$is_valid = (!$is_cancelled && !$is_expired && !$is_past && ($raw_status === 'Confirmed' || $raw_status === ''));
$stamp = $is_valid ? 'VALID PASS' : ($is_cancelled || $is_expired ? 'VOID' : ($raw_status === 'Pending' ? 'PENDING PAYMENT' : 'COMPLETED'));
$sig = get_ticket_signature($booking);
$verify_hash = strtoupper($sig);

$verify_path = (defined('BASE_URL') ? BASE_URL : '') . '/user/verify.php?pnr=' . urlencode($booking['pnr']) . '&sig=' . urlencode($sig);
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$full_verify_url = $proto . $host . $verify_path;
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
@media (max-width: 575.98px) {
    .ticket-void-overlay {
        font-size: 1.6rem;
        border-width: 3px;
        padding: 8px 4px;
        letter-spacing: 0.05em;
    }
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
    <div class="alert alert-success shadow-sm p-3 p-md-4 mb-4 border-0 no-print" role="alert">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="mb-3 mb-md-0">
                <h4 class="alert-heading font-weight-bold mb-1">🎉 Reservation Confirmed!</h4>
                <p class="mb-0 text-dark">
                    Your seat has been reserved. Your Booking Reference (PNR) is 
                    <strong class="font-monospace text-success h5 mb-0"><?= e($booking['pnr']) ?></strong>.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" onclick="window.print()" class="btn btn-success font-weight-bold shadow-sm mr-2 mb-2 mb-sm-0" style="min-height: 44px;">
                    🖨️ Print / Save PDF
                </button>
                <a href="<?= BASE_URL ?>/user/my-bookings.php" class="btn btn-outline-success font-weight-bold" style="min-height: 44px; display: inline-flex; align-items: center;">
                    View My Bookings &rarr;
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="no-print mb-3 mb-md-4 ticket-actions-bar">
    <?php if (!empty($_SESSION['uid'])): ?>
        <a href="<?= BASE_URL ?>/user/my-bookings.php" class="btn btn-outline-secondary btn-back" style="min-height: 44px; display: inline-flex; align-items: center;">
            &larr; Back to My Bookings
        </a>
    <?php else: ?>
        <a href="<?= BASE_URL ?>/homepage.php" class="btn btn-outline-secondary btn-back" style="min-height: 44px; display: inline-flex; align-items: center;">
            &larr; Back to Home
        </a>
    <?php endif; ?>
    <div class="ticket-action-btns">
        <?php if (!$is_cancelled && !$is_expired): ?>
            <button type="button" id="shareTicketBtn" class="btn btn-outline-primary shadow-sm font-weight-bold" style="min-height: 44px; display: none;">
                📤 Share Pass
            </button>
            <button type="button" onclick="window.print()" class="btn btn-primary shadow-sm font-weight-bold" style="min-height: 44px; display: inline-flex; align-items: center;">
                🖨️ <?= $is_past ? 'Print Receipt' : 'Print Ticket' ?>
            </button>
        <?php else: ?>
            <span class="badge badge-secondary px-3 py-2 text-uppercase d-inline-flex align-items-center" style="min-height: 44px;">Ticket Inactive</span>
        <?php endif; ?>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8 col-xl-7">
        <div class="card border ticket-container shadow-sm p-3 p-md-5 bg-white">
            <!-- Phase 2.2: Watermark overlay for cancelled, expired, or completed tickets -->
            <?php if (!$is_valid): ?>
                <div class="ticket-void-overlay font-weight-bold text-uppercase" aria-hidden="true">
                    <?= $is_cancelled ? 'VOID &bull; CANCELLED' : ($is_expired ? 'VOID &bull; EXPIRED' : ($is_past ? 'COMPLETED' : 'PENDING PAYMENT')) ?>
                </div>
            <?php endif; ?>

            <!-- Ticket Header -->
            <div class="ticket-header-row border-bottom pb-3 pb-md-4 mb-3 mb-md-4">
                <div>
                    <h3 class="ticket-brand-title mb-1">🚍 Bus Travel Pass</h3>
                    <p class="ticket-brand-sub mb-0">Official Boarding Pass &amp; Receipt</p>
                </div>
                <div class="d-flex flex-column align-items-start align-items-sm-end">
                    <span class="badge <?= $status_badge ?> px-3 py-1.5 text-uppercase font-weight-bold mb-1">
                        <?= e($status_label) ?>
                    </span>
                    <button type="button" class="ticket-pnr-pill" id="copyPnrBtn" data-pnr="<?= e($booking['pnr']) ?>" title="Tap to copy PNR" aria-label="Copy PNR to clipboard">
                        <span class="pnr-label">PNR:</span>
                        <span class="pnr-val"><?= e($booking['pnr']) ?></span>
                        <svg class="ticket-pnr-copy-icon ml-1" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    </button>
                </div>
            </div>

            <!-- Route Corridor -->
            <div class="ticket-corridor mb-3 mb-md-4">
                <div class="row text-center align-items-center no-gutters">
                    <div class="col-5 px-1">
                        <small class="ticket-city-sub d-block">Origin</small>
                        <div class="ticket-city-name"><?= e($booking['city1']) ?></div>
                    </div>
                    <div class="col-2 px-1 ticket-route-arrow-wrap">
                        <span class="ticket-route-arrow" aria-hidden="true">&rarr;</span>
                    </div>
                    <div class="col-5 px-1">
                        <small class="ticket-city-sub d-block">Destination</small>
                        <div class="ticket-city-name"><?= e($booking['city2']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Trip Meta Grid -->
            <div class="ticket-meta-grid mb-3 mb-md-4">
                <div class="ticket-meta-item">
                    <div class="ticket-meta-label">Passenger</div>
                    <div class="ticket-meta-val"><?= e($booking['name']) ?></div>
                    <div class="ticket-meta-sub">Contact: <?= e($booking['contact']) ?></div>
                </div>
                <div class="ticket-meta-item text-right">
                    <div class="ticket-meta-label justify-content-end">Vehicle Fleet</div>
                    <div class="ticket-meta-val">Bus #<?= e($booking['bus']) ?></div>
                    <div class="ticket-meta-sub">Standard Express</div>
                </div>
                <div class="ticket-meta-item">
                    <div class="ticket-meta-label">Travel Date</div>
                    <div class="ticket-meta-val"><?= e(fmt_date($booking['date'])) ?></div>
                </div>
                <div class="ticket-meta-item text-right">
                    <div class="ticket-meta-label justify-content-end">Departure</div>
                    <div class="ticket-meta-val"><?= e(fmt_time($booking['time'])) ?></div>
                </div>
                <div class="ticket-meta-item">
                    <div class="ticket-meta-label">Assigned Seat</div>
                    <div class="mt-1">
                        <span class="ticket-seat-pill">Seat #<?= e($booking['seat']) ?></span>
                    </div>
                </div>
                <div class="ticket-meta-item text-right">
                    <div class="ticket-meta-label justify-content-end">
                        <?= $is_valid ? 'Ticket Fare' : ($is_past ? 'Paid Fare' : 'Fare (' . e($stamp) . ')') ?>
                    </div>
                    <div class="ticket-fare-val mt-1"><?= CURRENCY ?><?= e(number_format((float)$booking['price'], 2)) ?></div>
                </div>
            </div>

            <!-- Perforated Tear-off Stub -->
            <div class="ticket-perforated-stub ticket-divider ticket-perforated-line"></div>

            <!-- Instructions & Digital Verification Seal (Phase 2.1, 2.2) -->
            <div class="pt-3 d-flex flex-column-reverse flex-md-row justify-content-between align-items-center">
                <div class="mb-3 mb-md-0 text-left flex-grow-1 pr-md-3 w-100">
                    <?php if ($is_valid): ?>
                        <div class="font-weight-bold small text-dark mb-2">Boarding Guidelines:</div>
                        <ul class="ticket-instructions-list">
                            <li>Please arrive at terminal 15 mins prior to departure</li>
                            <li>Carry a valid government photo ID matching ticket name</li>
                            <li>Present this digital boarding pass or QR code at entry</li>
                        </ul>
                    <?php elseif ($is_past): ?>
                        <div class="alert alert-secondary mb-0 small text-left" style="font-size: 12px;">
                            <strong>Trip Completed:</strong> This journey has departed and completed. This document serves as your travel receipt.
                        </div>
                    <?php elseif ($raw_status === 'Pending'): ?>
                        <div class="alert alert-warning mb-0 small text-left" style="font-size: 12px;">
                            <strong>Pending Payment:</strong> This booking is on temporary hold and is not yet confirmed for boarding until payment completes.
                        </div>
                    <?php else: ?>
                        <!-- Phase 2.2: Replaced boarding instructions for void tickets -->
                        <div class="alert alert-danger mb-0 small text-left" style="font-size: 12px;">
                            <strong>Notice:</strong> This reservation was <?= strtolower($status_label) ?> and is not valid for boarding or travel.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="text-center mb-3 mb-md-0 ml-md-3 w-100 w-md-auto d-flex justify-content-center">
                    <!-- Phase 2.1: Digital Security Seal & QR Code (Issue 5) -->
                    <div class="ticket-seal-card shadow-sm ticket-qr-wrap qr-container-high-contrast" style="max-width: 220px; width: 100%;">
                        <div id="ticket-qrcode" class="mb-2"></div>
                        <div class="text-muted font-weight-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.05em;">Digital Pass</div>
                        <div class="font-weight-bold text-primary font-monospace my-1 pnr-code ticket-pnr-highlight" style="font-size: 1.15rem;">
                            <?= e($booking['pnr']) ?>
                        </div>
                        <a href="<?= e($verify_path) ?>" target="_blank" class="badge badge-light border text-muted px-2 py-1 font-monospace text-decoration-none d-inline-block mb-1" style="font-size: 12px;" title="Verify Ticket Authenticity">
                            SEAL: <?= e($verify_hash) ?>
                        </a>
                        <div class="text-muted font-weight-medium" style="font-size: 12px;">
                            <?= e($stamp) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ticket-toast" id="ticketToast">Copied PNR to clipboard!</div>

<script src="<?= BASE_URL ?>/assets/js/vendor/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof QRCode !== 'undefined') {
        var qrEl = document.getElementById('ticket-qrcode');
        if (qrEl) {
            var qrSize = window.innerWidth < 576 ? 120 : 100;
            new QRCode(qrEl, {
                text: <?= json_encode($full_verify_url) ?>,
                width: qrSize,
                height: qrSize,
                colorDark: "#0f172a",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    }

    if (navigator.share) {
        var shareBtn = document.getElementById('shareTicketBtn');
        if (shareBtn) {
            shareBtn.style.display = 'inline-flex';
            shareBtn.style.alignItems = 'center';
            shareBtn.addEventListener('click', function() {
                navigator.share({
                    title: <?= json_encode('Bus Travel Pass - ' . $booking['pnr']) ?>,
                    text: <?= json_encode('Bus Travel Pass for ' . $booking['name'] . ': ' . $booking['city1'] . ' to ' . $booking['city2'] . ' on ' . fmt_date($booking['date']) . ' (Seat #' . $booking['seat'] . ')') ?>,
                    url: window.location.href
                }).catch(function() {});
            });
        }
    }

    // 1-Tap Copy PNR to Clipboard with Toast feedback
    var copyBtn = document.getElementById('copyPnrBtn');
    var toast = document.getElementById('ticketToast');
    function showToast(msg) {
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.add('show');
        setTimeout(function() {
            toast.classList.remove('show');
        }, 2200);
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            var pnr = this.getAttribute('data-pnr') || '';
            if (!pnr) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(pnr).then(function() {
                    showToast('✓ PNR ' + pnr + ' copied!');
                }).catch(function() {
                    showToast('PNR: ' + pnr);
                });
            } else {
                showToast('PNR: ' + pnr);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-user.php'; ?>

