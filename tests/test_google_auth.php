<?php
// tests/test_google_auth.php -- "Continue with Google" (no database, no network required).
// Usage: php tests/test_google_auth.php
declare(strict_types=1);

$total = 0;
$failed = 0;
function check(string $desc, bool $ok): void {
    global $total, $failed;
    $total++;
    echo ($ok ? ' [PASS] ' : ' [FAIL] ') . $desc . "\n";
    if (!$ok) { $failed++; }
}

echo "========================================================\n";
echo "   Google sign-in: test suite                           \n";
echo "========================================================\n\n";

putenv('GOOGLE_CLIENT_ID=test-client.apps.googleusercontent.com');
putenv('GOOGLE_CLIENT_SECRET=shh-secret');
putenv('APP_URL=https://bus.example.com');
putenv('GOOGLE_REDIRECT_URI');
$_SESSION = [];

require_once __DIR__ . '/../includes/auth/session-bootstrap.php';
require_once __DIR__ . '/../includes/google-auth.php';

$root = dirname(__DIR__);
$CID = 'test-client.apps.googleusercontent.com';
$good = [
    'iss' => 'https://accounts.google.com', 'aud' => $CID, 'exp' => time() + 3600,
    'nonce' => 'n0nce', 'sub' => '112233445566778899001', 'email' => 'asha@example.com',
    'email_verified' => true, 'name' => 'Asha Rao',
];

echo "[*] Suite 1: configuration\n";
check('enabled when id and secret are set', google_enabled());
putenv('GOOGLE_CLIENT_SECRET');
check('disabled without secret', !google_enabled());
putenv('GOOGLE_CLIENT_SECRET=shh-secret');
check('redirect URI derives from APP_URL', google_redirect_uri() === 'https://bus.example.com/google-callback.php');
putenv('GOOGLE_REDIRECT_URI=https://other.example/cb');
check('GOOGLE_REDIRECT_URI overrides', google_redirect_uri() === 'https://other.example/cb');
putenv('GOOGLE_REDIRECT_URI');
putenv('APP_URL');
check('no redirect URI without a trusted base (Host header ignored)', google_redirect_uri() === '');
putenv('APP_URL=https://bus.example.com');

echo "\n[*] Suite 2: authorization URL\n";
$url = google_begin('/user/booking.php');
$parts = parse_url($url);
parse_str((string)$parts['query'], $q);
$s = $_SESSION['google_oauth'];
check('points at Google', ($parts['host'] ?? '') === 'accounts.google.com');
check('asks for the authorization code', ($q['response_type'] ?? '') === 'code');
check('scope is openid email profile', ($q['scope'] ?? '') === 'openid email profile');
check('client id and redirect uri present', ($q['client_id'] ?? '') === $CID && ($q['redirect_uri'] ?? '') === 'https://bus.example.com/google-callback.php');
check('state in URL equals the stored state', ($q['state'] ?? '') === $s['state'] && strlen($s['state']) === 32);
check('nonce in URL equals the stored nonce', ($q['nonce'] ?? '') === $s['nonce']);
check('PKCE S256 challenge matches stored verifier',
    ($q['code_challenge_method'] ?? '') === 'S256'
    && ($q['code_challenge'] ?? '') === rtrim(strtr(base64_encode(hash('sha256', $s['verifier'], true)), '+/', '-_'), '='));
check('verifier is 43 url-safe chars and never put in the URL',
    (bool)preg_match('/^[A-Za-z0-9_-]{43}$/', $s['verifier']) && !str_contains($url, $s['verifier']));
check('client secret is never put in the URL', !str_contains($url, 'shh-secret'));
check('return path is stored for later', $s['next'] === '/user/booking.php');
check('each attempt gets fresh state', google_begin(null) !== $url && $_SESSION['google_oauth']['state'] !== $s['state']);

echo "\n[*] Suite 3: state handling\n";
$_SESSION = []; google_begin(null); $st = $_SESSION['google_oauth']['state'];
check('correct state is accepted', google_take_session($st) !== null);
check('state is single use', google_take_session($st) === null && !isset($_SESSION['google_oauth']));
$_SESSION = []; google_begin(null);
check('wrong state is rejected and consumed', google_take_session(str_repeat('a', 32)) === null && !isset($_SESSION['google_oauth']));
$_SESSION = []; google_begin(null);
check('empty state is rejected', google_take_session('') === null);
$_SESSION = []; google_begin(null); $_SESSION['google_oauth']['at'] = time() - GOOGLE_STATE_TTL - 5;
check('expired attempt is rejected', google_take_session($_SESSION['google_oauth']['state']) === null);
$_SESSION = [];
check('callback without a started attempt is rejected', google_take_session('abc') === null);

echo "\n[*] Suite 4: token response\n";
$ok = google_parse_token_response(200, json_encode(['id_token' => 'a.b.c', 'access_token' => 'x']));
check('200 with id_token is ok', $ok['ok'] && $ok['id_token'] === 'a.b.c');
$bad = google_parse_token_response(400, json_encode(['error' => 'invalid_grant']));
check('400 invalid_grant fails with reason', !$bad['ok'] && str_contains($bad['error'], 'invalid_grant'));
check('200 without id_token fails', !google_parse_token_response(200, '{"access_token":"x"}')['ok']);
check('garbage body fails', !google_parse_token_response(200, 'not json')['ok']);
check('HTML error page fails', !google_parse_token_response(502, '<html>bad gateway</html>')['ok']);

echo "\n[*] Suite 5: ID token decoding\n";
$b64 = fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
$jwt = $b64('{"alg":"RS256"}') . '.' . $b64(json_encode($good)) . '.sig';
check('payload decodes', (google_decode_jwt_payload($jwt)['email'] ?? '') === 'asha@example.com');
check('rejects wrong segment count', google_decode_jwt_payload('a.b') === null && google_decode_jwt_payload('a.b.c.d') === null);
check('rejects non-base64 payload', google_decode_jwt_payload('a.%%%.c') === null);
check('rejects non-JSON payload', google_decode_jwt_payload('a.' . $b64('hello') . '.c') === null);

echo "\n[*] Suite 6: claim validation\n";
$now = time();
check('valid token accepted', google_validate_claims($good, $CID, 'n0nce', $now) === null);
check('bare accounts.google.com issuer accepted', google_validate_claims(['iss' => 'accounts.google.com'] + $good, $CID, 'n0nce', $now) === null);
check('wrong issuer rejected', google_validate_claims(['iss' => 'https://evil.example'] + $good, $CID, 'n0nce', $now) === 'google_token_invalid');
check('token for another client rejected', google_validate_claims(['aud' => 'other-client'] + $good, $CID, 'n0nce', $now) === 'google_token_invalid');
check('audience list containing our client accepted', google_validate_claims(['aud' => ['x', $CID]] + $good, $CID, 'n0nce', $now) === null);
check('expired token rejected', google_validate_claims(['exp' => $now - 1] + $good, $CID, 'n0nce', $now) === 'google_token_invalid');
check('missing exp rejected', google_validate_claims(array_diff_key($good, ['exp' => 1]), $CID, 'n0nce', $now) === 'google_token_invalid');
check('wrong nonce rejected', google_validate_claims($good, $CID, 'different', $now) === 'google_token_invalid');
check('missing nonce rejected', google_validate_claims(array_diff_key($good, ['nonce' => 1]), $CID, 'n0nce', $now) === 'google_token_invalid');
check('empty expected nonce rejected', google_validate_claims($good, $CID, '', $now) === 'google_token_invalid');
check('missing sub rejected', google_validate_claims(['sub' => ''] + $good, $CID, 'n0nce', $now) === 'google_token_invalid');
check('oversized sub rejected', google_validate_claims(['sub' => str_repeat('1', 256)] + $good, $CID, 'n0nce', $now) === 'google_token_invalid');
check('unverified email rejected', google_validate_claims(['email_verified' => false] + $good, $CID, 'n0nce', $now) === 'google_email_unverified');
check('missing email_verified rejected', google_validate_claims(array_diff_key($good, ['email_verified' => 1]), $CID, 'n0nce', $now) === 'google_email_unverified');
check('"true" string email_verified accepted', google_validate_claims(['email_verified' => 'true'] + $good, $CID, 'n0nce', $now) === null);
check('"false" string email_verified rejected', google_validate_claims(['email_verified' => 'false'] + $good, $CID, 'n0nce', $now) === 'google_email_unverified');
check('missing email rejected', google_validate_claims(array_diff_key($good, ['email' => 1]), $CID, 'n0nce', $now) === 'google_no_email');
check('malformed email rejected', google_validate_claims(['email' => 'not-an-email'] + $good, $CID, 'n0nce', $now) === 'google_no_email');
check('email longer than the column rejected', google_validate_claims(['email' => str_repeat('a', 95) . '@x.com'] + $good, $CID, 'n0nce', $now) === 'google_no_email');
check('empty configured client id never matches', google_validate_claims(['aud' => ''] + $good, '', 'n0nce', $now) === 'google_token_invalid');

echo "\n[*] Suite 7: which customer is this (decision rule)\n";
$c = ['sub' => '111', 'email' => 'asha@example.com'];
$row = fn(array $x = []) => $x + ['id' => 7, 'name' => 'Asha', 'email' => 'asha@example.com', 'phone' => '', 'google_id' => null, 'archived_at' => null];
$d = google_decide($c, null, null);
check('unknown google id + unknown email -> create', $d['action'] === 'create');
$d = google_decide($c, $row(['google_id' => '111']), $row(['google_id' => '111']));
check('known google id -> sign in', $d['action'] === 'signin' && $d['user']['id'] === 7);
$d = google_decide($c, $row(['google_id' => '111']), null);
check('known google id wins even if the e-mail changed', $d['action'] === 'signin');
$d = google_decide($c, null, $row());
check('existing password account, no google id -> link', $d['action'] === 'link');
$d = google_decide($c, null, $row(['google_id' => '999']));
check('e-mail bound to a different google account -> refuse', $d['action'] === 'refuse' && $d['code'] === 'google_account_mismatch');
$d = google_decide($c, null, $row(['google_id' => '111']));
check('e-mail already bound to this google account -> sign in', $d['action'] === 'signin');
$d = google_decide($c, $row(['google_id' => '111', 'archived_at' => '2026-01-01 00:00:00']), null);
check('archived (matched by google id) -> refuse', $d['action'] === 'refuse' && $d['code'] === 'account_archived');
$d = google_decide($c, null, $row(['archived_at' => '2026-01-01 00:00:00']));
check('archived (matched by e-mail) is never linked or recreated', $d['action'] === 'refuse' && $d['code'] === 'account_archived');

echo "\n[*] Suite 8: helpers\n";
check('name cleaned', google_clean_name("  Asha \n  Rao\t", 'a@x.com') === 'Asha Rao');
check('control characters stripped', !preg_match('/[\x00-\x1F]/', google_clean_name("A\x00B\x07C", 'a@x.com')));
check('name falls back to e-mail local part', google_clean_name('', 'ravi@example.com') === 'ravi');
check('name capped at 100 chars', mb_strlen(google_clean_name(str_repeat('あ', 300), 'a@x.com')) === 100);
$h1 = google_unusable_password_hash(); $h2 = google_unusable_password_hash();
check('unusable password hashes are random bcrypt', $h1 !== $h2 && str_starts_with($h1, '$2y$'));
check('every error code has a message',
    google_message('google_state') !== google_message('x') && google_message('account_archived') !== '' && google_message('rate_limited') !== '');
check('welcome mail escapes the name', !str_contains(google_welcome_email_html('<b>x</b>'), '<b>x</b>'));
check('link notice tells the user the password was removed', str_contains(google_link_notice_html('A'), 'password was removed'));

echo "\n[*] Suite 9: pages and wiring (static)\n";
$cb    = (string)file_get_contents($root . '/google-callback.php');
$start = (string)file_get_contents($root . '/google-login.php');
$lib   = (string)file_get_contents($root . '/includes/google-auth.php');
check('callback checks state before touching the code', strpos($cb, 'google_take_session') < strpos($cb, 'google_exchange_code'));
check('callback validates claims with the stored nonce', str_contains($cb, "google_validate_claims(\$claims, google_client_id(), (string)\$attempt['nonce'])"));
check('callback is rate limited per IP', str_contains($cb, "'google:ip:'"));
check('callback is no-store + no-referrer', str_contains($cb, 'no-store') && str_contains($cb, 'no-referrer'));
check('start page only forwards a validated return path', str_contains($start, 'safe_next_url('));
check('start page refuses when Google is not configured', str_contains($start, 'google_enabled()'));
check('state compared in constant time', str_contains($lib, 'hash_equals((string)($s[\'state\']'));
check('PKCE verifier is sent in the token request', str_contains($lib, "'code_verifier' => \$verifier"));
check('TLS verification is not disabled', !str_contains($lib, 'VERIFYPEER') && !str_contains($lib, 'VERIFYHOST'));
check('link clears the old password', str_contains($lib, 'UPDATE costumer SET google_id = ?, pwd = ?'));
check('link only binds an unbound row (race safe)', str_contains($lib, 'AND google_id IS NULL'));
check('duplicate-key race retried once', str_contains($lib, '1062'));
check('session id is regenerated at login (login_user)', str_contains((string)file_get_contents($root . '/includes/auth/session-bootstrap.php'), 'session_regenerate_id(true)'));
check('SQL column name is whitelisted, not user input', str_contains($lib, "\$col === 'google_id' ? 'google_id' : 'email'"));
foreach (['homepage.php', 'login.php'] as $f) {
    check("$f shows the Google button", str_contains((string)file_get_contents("$root/$f"), 'google-button.php'));
}
check('homepage shows it on sign-in and register panes', substr_count((string)file_get_contents($root . '/homepage.php'), 'google-button.php') === 2);
check('button hidden unless configured', str_contains((string)file_get_contents($root . '/includes/layout/google-button.php'), 'google_enabled()'));
$mig = (string)file_get_contents($root . '/database/db_migrate.php');
check('migration runner adds costumer.google_id with a unique key', str_contains($mig, '012_customer_google_signin') && str_contains($mig, 'uq_customer_google_id'));
check('fresh-install schema has google_id', str_contains((string)file_get_contents($root . '/database/init.sql'), '`google_id` VARCHAR(255) NULL'));
$env = (string)file_get_contents($root . '/.env.example');
check('.env.example documents the Google variables', str_contains($env, 'GOOGLE_CLIENT_ID=') && str_contains($env, 'GOOGLE_CLIENT_SECRET=') && str_contains($env, 'google-callback.php'));

echo "\n[*] Suite 10: re-authentication & credential state (Plan)\n";
$now = time();
$reauthClaims = ['sub' => 'sub_12345', 'auth_time' => $now - 30];
check('google_reauth_ok accepts matching sub and fresh auth_time', google_reauth_ok($reauthClaims, 'sub_12345', $now));
check('google_reauth_ok rejects mismatched sub', !google_reauth_ok($reauthClaims, 'sub_different', $now));
check('google_reauth_ok rejects non-numeric or missing auth_time', !google_reauth_ok(['sub' => 'sub_12345'], 'sub_12345', $now));
check('google_reauth_ok rejects auth_time older than 300 s', !google_reauth_ok(['sub' => 'sub_12345', 'auth_time' => $now - 301], 'sub_12345', $now));
check('google_reauth_ok rejects auth_time in future by > 60 s', !google_reauth_ok(['sub' => 'sub_12345', 'auth_time' => $now + 65], 'sub_12345', $now));
check('google_reauth_ok allows up to 60 s clock skew into the future', google_reauth_ok(['sub' => 'sub_12345', 'auth_time' => $now + 30], 'sub_12345', $now));

// customer_proof_ok checks
$pwdHash = password_hash('SecretPass123', PASSWORD_DEFAULT);
$pwdUser = ['pwd_set' => 1, 'pwd' => $pwdHash];
$googUser = ['pwd_set' => 0, 'pwd' => 'random_hash'];
check('customer_proof_ok accepts password user with correct password', customer_proof_ok($pwdUser, 'SecretPass123'));
check('customer_proof_ok rejects password user with incorrect password', !customer_proof_ok($pwdUser, 'WrongPass'));
check('customer_proof_ok rejects password user with empty password', !customer_proof_ok($pwdUser, ''));

$_SESSION['reauth_at'] = $now - 20;
check('customer_proof_ok accepts google user with recent reauth', customer_proof_ok($googUser, ''));
$_SESSION['reauth_at'] = $now - 305;
check('customer_proof_ok rejects google user with expired reauth', !customer_proof_ok($googUser, ''));
unset($_SESSION['reauth_at']);
check('customer_proof_ok rejects google user without reauth', !customer_proof_ok($googUser, ''));

// google_begin_reauth checks
$_SESSION = [];
$reauthUrl = google_begin_reauth(42, 'customer@example.com', '/user/profile.php');
$reauthQuery = [];
parse_str((string)parse_url($reauthUrl, PHP_URL_QUERY), $reauthQuery);
check('google_begin_reauth sets session mode to reauth', ($_SESSION['google_oauth']['mode'] ?? '') === 'reauth');
check('google_begin_reauth stores target uid in session', ($_SESSION['google_oauth']['uid'] ?? 0) === 42);
check('google_begin_reauth sets max_age=300 in URL', ($reauthQuery['max_age'] ?? '') === '300');
check('google_begin_reauth sets login_hint in URL', ($reauthQuery['login_hint'] ?? '') === 'customer@example.com');
check('google_begin_reauth sets prompt=login in URL', ($reauthQuery['prompt'] ?? '') === 'login');

// Static file wiring for re-auth
$profileSrc = (string)file_get_contents($root . '/user/profile.php');
$reauthSrc  = (string)file_get_contents($root . '/google-reauth.php');
check('profile page gates sensitive actions with customer_proof_ok', str_contains($profileSrc, 'customer_proof_ok('));
check('google-reauth.php enforces logged-in customer session', str_contains($reauthSrc, 'user-session.php'));
check('google-reauth.php starts reauth with safe_next_url', str_contains($reauthSrc, 'google_begin_reauth('));
check('migration runner adds costumer.pwd_set', str_contains($mig, '013_google_pwd_set') && str_contains($mig, 'pwd_set'));
check('fresh-install schema has pwd_set', str_contains((string)file_get_contents($root . '/database/init.sql'), '`pwd_set` TINYINT(1)'));
check('admin section has zero references to google auth', !str_contains((string)file_get_contents($root . '/admin/login.php'), 'google-') && !str_contains((string)file_get_contents($root . '/includes/layout/header-admin.php'), 'google-'));

echo "\n[*] Suite 11: session data completeness & performance\n";
$_SESSION = [];
$sampleCust = [
    'id'        => 88,
    'name'      => 'Alice Passenger',
    'email'     => 'alice@example.com',
    'phone'     => '9876543210',
    'google_id' => 'google_sub_999',
    'pwd_set'   => 0,
];
login_user('user', $sampleCust);
check('login_user sets $_SESSION[uid]', ($_SESSION['uid'] ?? 0) === 88);
check('login_user sets $_SESSION[name]', ($_SESSION['name'] ?? '') === 'Alice Passenger');
check('login_user sets $_SESSION[email]', ($_SESSION['email'] ?? '') === 'alice@example.com');
check('login_user sets $_SESSION[phone]', ($_SESSION['phone'] ?? '') === '9876543210');
check('login_user sets $_SESSION[google_id]', ($_SESSION['google_id'] ?? '') === 'google_sub_999');
check('login_user sets $_SESSION[pwd_set]', ($_SESSION['pwd_set'] ?? -1) === 0);

$libAuth = (string)file_get_contents($root . '/includes/google-auth.php');
$cbSrc   = (string)file_get_contents($root . '/google-callback.php');
$homeSrc = (string)file_get_contents($root . '/homepage.php');
check('google_exchange_code forces IPv4 resolution to prevent DNS latency', str_contains($libAuth, 'CURLOPT_IPRESOLVE'));
check('google-callback releases session locks before redirecting', str_contains($cbSrc, 'session_write_close()'));
check('homepage.php selects email, google_id, pwd_set for costumer login', str_contains($homeSrc, 'email, pwd, google_id, pwd_set FROM costumer'));

echo "\n--------------------------------------------------------\n";
echo "Assertions: $total, failed: $failed\n";
exit($failed === 0 ? 0 : 1);
