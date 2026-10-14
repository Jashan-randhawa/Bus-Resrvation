<?php
// google-reauth.php -- Initiates Google re-authentication for sensitive actions (change email/pwd/delete).
require_once __DIR__ . '/includes/auth/user-session.php'; // requires a logged-in customer
require_once __DIR__ . '/includes/db_con.php';
require_once __DIR__ . '/includes/google-auth.php';

header('Cache-Control: no-store');

if (!google_enabled() || google_redirect_uri() === '') {
    flash_set('danger', google_message('google_unavailable'));
    header('Location: ' . BASE_URL . '/user/profile.php');
    exit;
}

$me = db_one($link, 'SELECT email FROM costumer WHERE id = ?', 'i', [(int)$_SESSION['uid']]);
$next = safe_next_url($_GET['next'] ?? null) ?? (BASE_URL . '/user/profile.php');

header('Location: ' . google_begin_reauth((int)$_SESSION['uid'], (string)($me['email'] ?? ''), $next));
exit;
