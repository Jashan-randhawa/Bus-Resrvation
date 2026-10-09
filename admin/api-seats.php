<?php
// admin/api-seats.php -- Live seat availability endpoint for administrative reservation modal
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!can_write()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Permission denied.']);
    exit;
}

$bus = trim((string)($_GET['bus'] ?? ''));
$date = trim((string)($_GET['date'] ?? ''));
$time = trim((string)($_GET['time'] ?? ''));

if ($bus === '' || $date === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing required parameters (bus, date).', 'booked_seats' => []]);
    exit;
}

// Sweep expired holds before returning booked status
release_expired_holds($link);

$booked_map = get_booked_seats($link, $bus, $date, $time !== '' ? $time : null);
$booked_seats = array_map('intval', array_keys($booked_map));
sort($booked_seats);

echo json_encode([
    'ok' => true,
    'bus' => $bus,
    'date' => $date,
    'time' => $time,
    'booked_seats' => $booked_seats,
    'server_time' => date('Y-m-d H:i:s')
]);
