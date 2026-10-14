<?php
// google-callback.php -- Google redirects here after the visitor approves (or cancels).
require_once __DIR__ . '/includes/auth/session-bootstrap.php';
require_once __DIR__ . '/includes/db_con.php';
require_once __DIR__ . '/includes/google-auth.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
ob_start();

$is_reauth = (isset($_SESSION['google_oauth']['mode']) && $_SESSION['google_oauth']['mode'] === 'reauth');

/** Back to the caller with a message. */
$google_fail = static function (string $code) use (&$is_reauth): void {
    flash_set('error', google_message($code));
    $dest = $is_reauth ? (BASE_URL . '/user/profile.php') : (BASE_URL . '/login.php');
    header('Location: ' . $dest);
    exit;
};

if (!google_enabled()) {
    $google_fail('google_unavailable');
}

$ip = client_ip();
if (throttle_blocked($link, 'google:ip:' . $ip, 20, 900)) {
    $google_fail('rate_limited');
}
throttle_hit($link, 'google:ip:' . $ip);

$state_in = (string)($_GET['state'] ?? '');
$code_in  = (string)($_GET['code'] ?? '');
$error_in = (string)($_GET['error'] ?? '');

// State is checked (and consumed) first, so a forged callback is rejected before anything else.
$attempt = google_take_session($state_in);
if ($attempt === null) {
    $google_fail('google_state');
}
if (($attempt['mode'] ?? '') === 'reauth') {
    $is_reauth = true;
}
if ($error_in !== '' || $code_in === '') {
    $google_fail('google_denied');
}

$token = google_exchange_code($code_in, (string)$attempt['verifier']);
if (!$token['ok']) {
    error_log('[busres] Google sign-in: ' . $token['error']);
    $google_fail('google_failed');
}

$claims = google_decode_jwt_payload($token['id_token']);
$bad = $claims === null
    ? 'google_token_invalid'
    : google_validate_claims($claims, google_client_id(), (string)$attempt['nonce']);
if ($bad !== null) {
    if ($bad === 'google_token_invalid') {
        error_log('[busres] Google sign-in: ID token rejected');
    }
    $google_fail($bad);
}

// Re-authentication branch: proves identity for sensitive profile operations without logging in a new user
if (($attempt['mode'] ?? '') === 'reauth') {
    $uid = (int)($attempt['uid'] ?? 0);
    $me = db_one($link, 'SELECT id, google_id, archived_at FROM costumer WHERE id = ? LIMIT 1', 'i', [$uid]);
    if (!$me || empty($_SESSION['uid']) || (int)$_SESSION['uid'] !== $uid
        || empty($me['google_id']) || !empty($me['archived_at'])
        || !google_reauth_ok($claims, (string)$me['google_id'], time())) {
        $google_fail('google_reauth_failed');
    }
    $_SESSION['reauth_at'] = time();
    session_regenerate_id(true);
    throttle_clear($link, 'google:ip:' . $ip);
    try {
        audit($link, 'LOGIN', 'customer', $uid, null, ['method' => 'google_reauth', 'action' => 'reauth_ok']);
    } catch (Throwable $e) {
        error_log('[busres] Google re-auth audit failed: ' . $e->getMessage());
    }
    flash_set('success', 'Google identity confirmed.');
    header('Location: ' . (safe_next_url($attempt['next'] ?? null) ?? (BASE_URL . '/user/profile.php')));
    exit;
}

$result = google_complete_login($link, $claims, $attempt['next'] ?? null);
if (!$result['ok']) {
    $google_fail($result['code']);
}

throttle_clear($link, 'google:ip:' . $ip);
if ($result['flash'] !== '') {
    flash_set('success', $result['flash']);
}
header('Location: ' . $result['dest']);
exit;
