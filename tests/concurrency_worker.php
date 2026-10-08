<?php
// tests/concurrency_worker.php -- Concurrent worker process for Test I (Suite 14)
// Fires a booking attempt at an exact synchronized timestamp.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI execution only.\n";
    exit(1);
}

if ($argc < 7) {
    echo json_encode(['ok' => false, 'error' => 'Usage: php concurrency_worker.php <bus> <date> <time> <seat> <race_start> <racer_name>']);
    exit(1);
}

$bus = (string)$argv[1];
$date = (string)$argv[2];
$time = (string)$argv[3];
$seat = (int)$argv[4];
$race_start = (float)$argv[5];
$racer_name = (string)$argv[6];

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/auth/session-bootstrap.php';
require_once __DIR__ . '/../includes/helpers.php';

// Synchronize to shared start time
$now = microtime(true);
if ($race_start > $now) {
    $remaining_us = (int)(($race_start - $now) * 1000000);
    if ($remaining_us > 2000) {
        usleep($remaining_us - 1500);
    }
    while (microtime(true) < $race_start) {
        // tight spin for high precision
    }
}

$result = create_booking($link, [
    'bus' => $bus,
    'city1' => 'CityX',
    'city2' => 'CityY',
    'date' => $date,
    'time' => $time,
    'seat' => $seat,
    'price' => 45.0,
    'name' => $racer_name,
    'contact' => '9876543210',
    'id' => 0,
    'status' => 'Confirmed'
]);

echo json_encode($result);
exit($result['ok'] ? 0 : 2);
