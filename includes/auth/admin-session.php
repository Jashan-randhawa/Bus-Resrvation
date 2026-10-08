<?php
// includes/auth/admin-session.php
require_once __DIR__ . '/session-bootstrap.php';

$idle = defined('SESSION_IDLE_SECONDS') ? SESSION_IDLE_SECONDS : 1800; // 30 minutes
$wasLoggedIn = !empty($_SESSION['admin_id']);
if (empty($_SESSION['admin_id']) || (time() - ($_SESSION['last'] ?? $_SESSION['last_seen'] ?? 0)) > $idle) {
    session_unset();
    session_destroy();
    $reason = $wasLoggedIn ? '&error=expired' : '';
    header('Location: ' . BASE_URL . '/homepage.php?login=admin' . $reason);
    exit;
}
$_SESSION['last'] = time();
$_SESSION['last_seen'] = time();

require_role('admin');

// Item 2: Live role and active check on every request
if (!empty($_SESSION['admin_id'])) {
    require_once dirname(__DIR__) . '/db_con.php';
    $admin_id = (int)$_SESSION['admin_id'];
    $has_is_active = table_has_column($link, 'admin', 'is_active');
    $has_role = table_has_column($link, 'admin', 'role');

    $select_cols = ['id'];
    if ($has_role) {
        $select_cols[] = 'role';
    }
    if ($has_is_active) {
        $select_cols[] = 'is_active';
    }

    $admin_check = db_one($link, 'SELECT ' . implode(', ', $select_cols) . ' FROM `admin` WHERE id = ? LIMIT 1', 'i', [$admin_id]);

    if (!$admin_check || ($has_is_active && (int)($admin_check['is_active'] ?? 1) === 0)) {
        logout_all();
        header('Location: ' . BASE_URL . '/homepage.php?login=admin&error=deactivated');
        exit;
    }

    if ($has_role && isset($admin_check['role'])) {
        $live_role = (string)$admin_check['role'];
        if ($live_role !== ($_SESSION['role'] ?? '')) {
            session_regenerate_id(true);
            $_SESSION['role'] = $live_role;
        }
    }
}