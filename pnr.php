<?php
/**
 * pnr.php - Customer Ticket & PNR Status Verification
 * Connects ticket check to PWA head partial and loads PNR verification.
 */
require_once __DIR__ . '/includes/config.php';
$raw_input = trim((string)($_GET['pnr'] ?? $_GET['ticket'] ?? $_GET['text'] ?? $_GET['url'] ?? ''));
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

    if ($pnr !== '') {
        header('Location: ' . BASE_URL . '/homepage.php?pnr=' . urlencode($pnr) . '#pnr');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check PNR Status - Bus Reservation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <?php require 'assets/partials/_pwa-head.php'; ?>
</head>
<body class="bg-light">
    <div class="container py-5 text-center">
        <h2>Bus Ticket PNR Verification</h2>
        <p class="text-muted">Redirecting to passenger enquiry portal...</p>
        <a href="homepage.php#pnr" class="btn btn-primary">Go to PNR Lookup</a>
    </div>
    <script>window.location.replace("homepage.php#pnr");</script>
</body>
</html>
