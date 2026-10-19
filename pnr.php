<?php
/**
 * pnr.php - Customer Ticket & PNR Status Verification
 * Connects ticket check to PWA head partial and loads PNR verification.
 */
require_once __DIR__ . '/includes/config.php';
$raw_input = trim((string)($_GET['pnr'] ?? $_GET['ticket'] ?? $_GET['text'] ?? $_GET['url'] ?? ''));
$raw_phone = trim((string)($_GET['phone4'] ?? $_GET['phone'] ?? ''));
$pnr = '';

if ($raw_input !== '') {
    // 1. If passed via shared URL containing pnr query parameter
    if (preg_match('/[?&]pnr=([A-Za-z0-9_-]+)/i', $raw_input, $url_matches)) {
        $pnr = $url_matches[1];
    }
    // 2. If passed via protocol handler or message text containing PNR token
    elseif (preg_match('/(?:web\+[a-z]+:\/\/(?:[^\/]*\/)*|PNR[:\s]*)?([A-Za-z0-9_-]{6,12})/i', $raw_input, $matches)) {
        $pnr = $matches[1];
    } else {
        $pnr = $raw_input;
    }
}

$phone4 = preg_replace('/\D/', '', $raw_phone);
if (strlen($phone4) > 4) {
    $phone4 = substr($phone4, -4);
}

if ($pnr !== '') {
    $redirect_target = BASE_URL . '/homepage.php?pnr=' . urlencode($pnr);
    if ($phone4 !== '') {
        $redirect_target .= '&phone4=' . urlencode($phone4);
    }
    header('Location: ' . $redirect_target . '#pnr');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check PNR Status - Bus Reservation</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css?v=4.6.2">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <?php require 'assets/partials/_pwa-head.php'; ?>
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="container py-5 text-center" style="max-width: 520px;">
        <div class="card shadow-sm border-0 rounded-lg p-4">
            <div class="mb-3 text-primary" style="font-size: 2.5rem;">🎫</div>
            <h3 class="font-weight-bold mb-2">Bus Ticket PNR Verification</h3>
            <p class="text-muted mb-4">Redirecting you to the secure passenger enquiry portal...</p>
            <a href="homepage.php#pnr" class="btn btn-primary btn-block py-2 font-weight-bold">
                Go to PNR Lookup &rarr;
            </a>
        </div>
    </div>
    <script>window.location.replace("homepage.php#pnr");</script>
</body>
</html>
