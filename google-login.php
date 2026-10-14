<?php
// google-login.php -- Starts "Continue with Google": sends the visitor to Google's consent screen.
require_once __DIR__ . '/includes/auth/session-bootstrap.php';
require_once __DIR__ . '/includes/google-auth.php';

header('Cache-Control: no-store');

if (!google_enabled() || google_redirect_uri() === '') {
    flash_set('error', google_message('google_unavailable'));
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$next = safe_next_url($_GET['next'] ?? null);
header('Location: ' . google_begin($next));
exit;
