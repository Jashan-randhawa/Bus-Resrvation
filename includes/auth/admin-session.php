<?php
// includes/auth/admin-session.php
require_once __DIR__ . '/session-bootstrap.php';

$idle = defined('SESSION_IDLE_SECONDS') ? SESSION_IDLE_SECONDS : 1800; // 30 minutes
if (empty($_SESSION['admin_id']) || (time() - ($_SESSION['last'] ?? $_SESSION['last_seen'] ?? 0)) > $idle) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/homepage.php?login=admin');
    exit;
}
$_SESSION['last'] = time();
$_SESSION['last_seen'] = time();

require_role('admin');