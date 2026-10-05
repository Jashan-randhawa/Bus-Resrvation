<?php
// user/api-heartbeat.php -- Keeps user session active on demand (U-14)
require_once __DIR__ . '/../includes/auth/user-session.php';
header('Content-Type: application/json; charset=utf-8');

$_SESSION['last_seen'] = time();

echo json_encode([
    'ok' => true,
    'last_seen' => $_SESSION['last_seen'],
    'message' => 'Session extended successfully.'
]);
