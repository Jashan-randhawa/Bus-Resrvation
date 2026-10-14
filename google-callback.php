<?php
// google-callback.php -- Google redirects here after the visitor approves (or cancels).
require_once __DIR__ . '/includes/auth/session-bootstrap.php';
require_once __DIR__ . '/includes/db_con.php';
require_once __DIR__ . '/includes/google-auth.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
ob_start();

/** Back to the sign-in page with a message. */
$google_fail = static function (string $code): void {
    flash_set('error', google_message($code));
    header('Location: ' . BASE_URL . '/login.php');
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
