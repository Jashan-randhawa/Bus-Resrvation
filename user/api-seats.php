<?php
// user/api-seats.php -- Lightweight API for live seat availability polling (U-15)
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

header('Content-Type: application/json; charset=utf-8');

$bus = trim((string)($_GET['bus'] ?? ''));
$date = trim((string)($_GET['date'] ?? ''));
$time = trim((string)($_GET['time'] ?? ''));

if ($bus === '' || $date === '' || $time === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing required parameters.']);
    exit;
}

// Sweep expired holds before returning availability
release_expired_holds($link);

$booked_map = get_booked_seats($link, $bus, $date, $time);
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
