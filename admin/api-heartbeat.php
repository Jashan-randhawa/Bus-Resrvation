<?php
// admin/api-heartbeat.php -- Administrative Session Heartbeat Endpoint (A15)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');

require_once __DIR__ . '/../includes/auth/admin-session.php';

// Touch session activity timestamps
$idle = defined('SESSION_IDLE_SECONDS') ? SESSION_IDLE_SECONDS : 1800;
$_SESSION['last'] = time();
$_SESSION['last_seen'] = time();

echo json_encode([
    'ok' => true,
    'remaining' => $idle,
    'timestamp' => time()
]);
exit;
