<?php
// includes/auth/admin-session.php
require_once __DIR__ . '/session-bootstrap.php';

$idle = defined('SESSION_IDLE_SECONDS') ? SESSION_IDLE_SECONDS : 1800; // 30 minutes
$wasLoggedIn = !empty($_SESSION['admin_id']);
$started_at = (int)($_SESSION['started_at'] ?? 0);
$absolute_lifetime = 8 * 3600; // 8 hours absolute lifetime

if (empty($_SESSION['admin_id']) 
    || (time() - ($_SESSION['last'] ?? $_SESSION['last_seen'] ?? 0)) > $idle
    || ($started_at > 0 && (time() - $started_at) > $absolute_lifetime)) {
    session_unset();
    session_destroy();
    $reason = $wasLoggedIn ? '&error=expired' : '';
    header('Location: ' . BASE_URL . '/homepage.php?login=admin' . $reason);
    exit;
}
$_SESSION['last'] = time();
$_SESSION['last_seen'] = time();

require_role('admin');

// Live role, active status, password version, and MFA check on every request
if (!empty($_SESSION['admin_id'])) {
    require_once dirname(__DIR__) . '/db_con.php';
    $admin_id = (int)$_SESSION['admin_id'];
    $has_is_active = table_has_column($link, 'admin', 'is_active');
    $has_role = table_has_column($link, 'admin', 'role');
    $has_pwd_changed = table_has_column($link, 'admin', 'password_changed_at');
    $has_totp = table_has_column($link, 'admin', 'totp_enabled');

    $select_cols = ['id'];
    if ($has_role) {
        $select_cols[] = 'role';
    }
    if ($has_is_active) {
        $select_cols[] = 'is_active';
    }
    if ($has_pwd_changed) {
        $select_cols[] = 'password_changed_at';
    }
    if ($has_totp) {
        $select_cols[] = 'totp_enabled';
    }

    $admin_check = db_one($link, 'SELECT ' . implode(', ', $select_cols) . ' FROM `admin` WHERE id = ? LIMIT 1', 'i', [$admin_id]);

    if (!$admin_check || ($has_is_active && (int)($admin_check['is_active'] ?? 1) === 0)) {
        logout_all();
        header('Location: ' . BASE_URL . '/homepage.php?login=admin&error=deactivated');
        exit;
    }

    // Issue 1: Invalidate session if password_changed_at differs from session reference
    if ($has_pwd_changed) {
        $db_ref = (string)($admin_check['password_changed_at'] ?? '');
        $sess_ref = (string)($_SESSION['pwd_ref'] ?? '');
        if (!isset($_SESSION['pwd_ref']) || $db_ref !== $sess_ref) {
            logout_all();
            header('Location: ' . BASE_URL . '/homepage.php?login=admin&error=expired');
            exit;
        }
    }

    if ($has_role && isset($admin_check['role'])) {
        $live_role = (string)$admin_check['role'];
        if ($live_role !== ($_SESSION['role'] ?? '')) {
            session_regenerate_id(true);
            $_SESSION['role'] = $live_role;
        }
    }

    // Issue 2: Policy enforcement for super_admin and operator roles
    if ($has_totp && (int)($admin_check['totp_enabled'] ?? 0) === 0) {
        $role = $_SESSION['role'] ?? '';
        if (in_array($role, ['super_admin', 'operator'], true)) {
            $cur_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
            if ($cur_script !== 'profile.php' && $cur_script !== 'mfa.php') {
                header('Location: ' . BASE_URL . '/admin/profile.php?mfa_required=1');
                exit;
            }
        }
    }
}