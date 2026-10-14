<?php
// includes/google-auth.php -- "Continue with Google" for customers (OAuth 2.0 / OpenID Connect).
//
// Authorization-code flow, server side, same account rules as the AI Fitness Tracker:
//   * state (CSRF), nonce (replay) and PKCE S256 are generated per attempt and kept in the session
//   * the code is exchanged server-to-server over TLS, so the ID token comes straight from Google;
//     issuer, audience, expiry, nonce and "email_verified" are still checked
//   * match by Google account id first, then by e-mail; unknown e-mails create a customer
//   * first-time link of an EXISTING password account clears that password (the bus app does not
//     verify e-mails at registration, so whoever set the password may not own the address)
//   * archived customers are refused; a different Google account on the same e-mail is refused
declare(strict_types=1);

require_once __DIR__ . '/password-reset.php'; // pr_app_url(), pr_after_response(), pr_mail_configured(), pr_email_shell()

const GOOGLE_AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const GOOGLE_STATE_TTL = 600; // seconds the user has to finish at Google

function google_client_id(): string {
    return trim((string)getenv('GOOGLE_CLIENT_ID'));
}

function google_client_secret(): string {
    return trim((string)getenv('GOOGLE_CLIENT_SECRET'));
}

function google_enabled(): bool {
    return google_client_id() !== '' && google_client_secret() !== '';
}

/** Must match an "Authorized redirect URI" in Google Cloud Console exactly. */
function google_redirect_uri(): string {
    $explicit = trim((string)getenv('GOOGLE_REDIRECT_URI'));
    if ($explicit !== '') {
        return $explicit;
    }
    $base = pr_app_url();
    return $base === '' ? '' : $base . '/google-callback.php';
}

function google_b64url(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function google_b64url_decode(string $s): ?string {
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) {
        $s .= str_repeat('=', 4 - $pad);
    }
    $out = base64_decode($s, true);
    return $out === false ? null : $out;
}

/* ------------------------------------------------------------------ */
/* Step 1: send the visitor to Google                                 */
/* ------------------------------------------------------------------ */

/** Builds the Google URL and stores state / nonce / PKCE verifier in the session. */
function google_begin(?string $next): string {
    $state    = bin2hex(random_bytes(16));
    $nonce    = bin2hex(random_bytes(16));
    $verifier = google_b64url(random_bytes(32)); // 43 chars
    $_SESSION['google_oauth'] = [
        'state'    => $state,
        'nonce'    => $nonce,
        'verifier' => $verifier,
        'next'     => $next,
        'at'       => time(),
    ];
    $params = [
        'client_id'             => google_client_id(),
        'redirect_uri'          => google_redirect_uri(),
        'response_type'         => 'code',
        'scope'                 => 'openid email profile',
        'state'                 => $state,
        'nonce'                 => $nonce,
        'code_challenge'        => google_b64url(hash('sha256', $verifier, true)),
        'code_challenge_method' => 'S256',
        'prompt'                => 'select_account',
    ];
    return GOOGLE_AUTH_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/** Returns and CONSUMES the stored attempt if $state matches and is fresh; otherwise null. */
function google_take_session(string $state): ?array {
    $s = $_SESSION['google_oauth'] ?? null;
    unset($_SESSION['google_oauth']); // single use, even when validation fails
    if (!is_array($s) || $state === '') {
        return null;
    }
    if ((time() - (int)($s['at'] ?? 0)) > GOOGLE_STATE_TTL) {
        return null;
    }
    if (!hash_equals((string)($s['state'] ?? ''), $state)) {
        return null;
    }
    return $s;
}

/* ------------------------------------------------------------------ */
/* Step 2: exchange the code and check the ID token                   */
/* ------------------------------------------------------------------ */

/** Pure: turns Google's token endpoint response into ['ok','id_token','error']. */
function google_parse_token_response(int $httpStatus, string $body): array {
    $j = json_decode($body, true);
    if ($httpStatus !== 200 || !is_array($j) || empty($j['id_token']) || !is_string($j['id_token'])) {
        $why = is_array($j) ? (string)($j['error'] ?? 'unknown') : 'bad_response';
        return ['ok' => false, 'id_token' => '', 'error' => 'token_exchange_failed:' . $why];
    }
    return ['ok' => true, 'id_token' => $j['id_token'], 'error' => ''];
}

/** Server-to-server code exchange (TLS verified by cURL defaults). */
function google_exchange_code(string $code, string $verifier): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'id_token' => '', 'error' => 'curl_missing'];
    }
    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => google_client_id(),
            'client_secret' => google_client_secret(),
            'redirect_uri'  => google_redirect_uri(),
            'grant_type'    => 'authorization_code',
            'code_verifier' => $verifier,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_IPRESOLVE      => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 1,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno  = curl_errno($ch);
    curl_close($ch);
    if ($body === false || $errno !== 0) {
        return ['ok' => false, 'id_token' => '', 'error' => 'token_exchange_failed:network'];
    }
    return google_parse_token_response($status, (string)$body);
}

/** Payload of a JWT (no signature check: the token came directly from Google over TLS). */
function google_decode_jwt_payload(string $jwt): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    $json = google_b64url_decode($parts[1]);
    if ($json === null) {
        return null;
    }
    $claims = json_decode($json, true);
    return is_array($claims) ? $claims : null;
}

/**
 * Validates the ID-token claims.
 * @return string|null error code, or null when the token is acceptable
 */
function google_validate_claims(array $c, string $clientId, string $nonce, ?int $now = null): ?string {
    $now ??= time();

    if (!in_array($c['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)) {
        return 'google_token_invalid';
    }
    $aud = $c['aud'] ?? null;
    $audOk = is_string($aud) ? hash_equals($clientId, $aud) : (is_array($aud) && in_array($clientId, $aud, true));
    if ($clientId === '' || !$audOk) {
        return 'google_token_invalid';
    }
    if (!isset($c['exp']) || !is_numeric($c['exp']) || (int)$c['exp'] <= $now) {
        return 'google_token_invalid';
    }
    if ($nonce === '' || !is_string($c['nonce'] ?? null) || !hash_equals($nonce, $c['nonce'])) {
        return 'google_token_invalid';
    }
    $sub = $c['sub'] ?? null;
    if (!is_string($sub) || $sub === '' || strlen($sub) > 255) {
        return 'google_token_invalid';
    }
    $email = $c['email'] ?? null;
    if (!is_string($email) || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        return 'google_no_email';
    }
    $verified = $c['email_verified'] ?? false;
    if ($verified !== true && $verified !== 'true') {
        return 'google_email_unverified';
    }
    return null;
}

/* ------------------------------------------------------------------ */
/* Step 3: which customer is this?                                    */
/* ------------------------------------------------------------------ */

/**
 * Pure decision rule.
 * @param array{sub:string,email:string} $claims
 * @param array|null $byGoogleId customer row whose google_id = sub
 * @param array|null $byEmail    customer row whose email = claims email
 * @return array{action:string,code:string,user:?array}  action: signin | link | create | refuse
 */
function google_decide(array $claims, ?array $byGoogleId, ?array $byEmail): array {
    $sub = (string)$claims['sub'];

    if ($byGoogleId) {
        if (!empty($byGoogleId['archived_at'])) {
            return ['action' => 'refuse', 'code' => 'account_archived', 'user' => null];
        }
        return ['action' => 'signin', 'code' => '', 'user' => $byGoogleId];
    }
    if ($byEmail) {
        if (!empty($byEmail['archived_at'])) {
            return ['action' => 'refuse', 'code' => 'account_archived', 'user' => null];
        }
        $bound = (string)($byEmail['google_id'] ?? '');
        if ($bound !== '' && !hash_equals($bound, $sub)) {
            return ['action' => 'refuse', 'code' => 'google_account_mismatch', 'user' => null];
        }
        if ($bound !== '') {
            return ['action' => 'signin', 'code' => '', 'user' => $byEmail];
        }
        return ['action' => 'link', 'code' => '', 'user' => $byEmail];
    }
    return ['action' => 'create', 'code' => '', 'user' => null];
}

/** User-facing text for an error code. Never reveals whether an address is registered. */
function google_message(string $code): string {
    return match ($code) {
        'google_email_unverified'  => 'Your Google email address is not verified, so it cannot be used to sign in.',
        'google_no_email'          => 'Google did not share an email address for this account.',
        'google_account_mismatch'  => 'This email is already connected to a different Google account.',
        'account_archived'         => 'This account is no longer active. Please contact support.',
        'google_state'             => 'Your Google sign-in session expired. Please try again.',
        'google_denied'            => 'Google sign-in was cancelled.',
        'google_unavailable'       => 'Google sign-in is not available right now. Please sign in with your email and password.',
        'rate_limited'             => 'Too many sign-in attempts. Please wait a few minutes and try again.',
        'google_reauth_failed'     => 'We could not confirm your identity with Google. Please try again.',
        default                    => 'We could not sign you in with Google. Please try again.',
    };
}

/** Display name from the Google profile, safe for the 100-char name column. */
function google_clean_name(string $name, string $email): string {
    $name = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '';
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if ($name === '') {
        $name = (string)strstr($email, '@', true);
    }
    if ($name === '') {
        $name = 'Passenger';
    }
    return mb_substr($name, 0, 100);
}

/** A bcrypt hash of random bytes nobody knows: the account has no usable password. */
function google_unusable_password_hash(): string {
    return password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
}

function google_find_customer(mysqli $link, string $col, string $val): ?array {
    $col = $col === 'google_id' ? 'google_id' : 'email'; // fixed whitelist, never user input
    return db_one($link,
        'SELECT id, name, email, phone, google_id, pwd_set, archived_at FROM costumer WHERE ' . $col . ' = ? LIMIT 1',
        's', [$val]);
}

function google_welcome_email_html(string $name): string {
    return pr_email_shell('Welcome aboard!',
        '<p>Hello ' . e($name) . ',</p>'
        . '<p>Your account was created with Google sign-in. You are ready to book bus tickets.</p>'
        . '<p style="font-size:13px;color:#4b5563;">Please add your phone number on the booking form when you reserve a seat.</p>');
}

function google_link_notice_html(string $name): string {
    return pr_email_shell('Google sign-in connected',
        '<p>Hello ' . e($name) . ',</p>'
        . '<p>Your Google account is now connected to your Bus Reservation account.</p>'
        . '<p style="font-size:13px;color:#4b5563;">For your security the old password was removed. '
        . 'You can sign in with Google, or use <strong>Forgot password</strong> to set a new one. '
        . 'If this was not you, contact support right away.</p>');
}

/**
 * Applies the decision (DB + session). Call only with claims that passed google_validate_claims().
 *
 * @return array{ok:bool,code:string,dest:string,flash:string}
 */
function google_complete_login(mysqli $link, array $claims, ?string $next, int $attempt = 1): array {
    $fail = static fn(string $code): array => ['ok' => false, 'code' => $code, 'dest' => '', 'flash' => ''];

    if (!table_has_column($link, 'costumer', 'google_id')) {
        error_log('[busres] Google sign-in: costumer.google_id is missing. Run php database/db_migrate.php');
        return $fail('google_unavailable');
    }

    $sub   = (string)$claims['sub'];
    $email = strtolower((string)$claims['email']);
    $googleName = google_clean_name((string)($claims['name'] ?? ''), $email);

    $decision = google_decide(
        ['sub' => $sub, 'email' => $email],
        google_find_customer($link, 'google_id', $sub),
        google_find_customer($link, 'email', $email)
    );
    if ($decision['action'] === 'refuse') {
        return $fail($decision['code']);
    }

    $user  = $decision['user'];
    $flash = '';
    $notice = null; // [subject, html] sent after the redirect

    if ($decision['action'] === 'link') {
        // Clear the old password: the address was never verified when that password was set.
        db_exec($link,
            'UPDATE costumer SET google_id = ?, pwd = ?, pwd_set = 0 WHERE id = ? AND google_id IS NULL',
            'ssi', [$sub, google_unusable_password_hash(), (int)$user['id']]);
        $user['google_id'] = $sub;
        $user['pwd_set'] = 0;
        if ((empty($user['name']) || $user['name'] === 'User' || $user['name'] === 'Passenger') && $googleName !== '') {
            db_exec($link, 'UPDATE costumer SET name = ? WHERE id = ?', 'si', [$googleName, (int)$user['id']]);
            $user['name'] = $googleName;
        }
        $notice = ['Google sign-in connected - Bus Reservation', google_link_notice_html((string)$user['name'])];
        $flash  = 'Google account connected. Welcome back, ' . $user['name'] . '.';
    } elseif ($decision['action'] === 'create') {
        $name = $googleName;
        try {
            db_exec($link,
                'INSERT INTO costumer (name, email, pwd, phone, google_id, pwd_set) VALUES (?,?,?,?,?,0)',
                'sssss', [$name, $email, google_unusable_password_hash(), '', $sub]);
        } catch (Throwable $e) {
            // Two tabs finishing at once: the unique key rejects the second insert; re-read and decide again.
            if ($attempt < 2 && (int)($e instanceof mysqli_sql_exception ? $e->getCode() : 0) === 1062) {
                return google_complete_login($link, $claims, $next, $attempt + 1);
            }
            error_log('[busres] Google sign-in create failed: ' . $e->getMessage());
            return $fail('google_failed');
        }
        $user = ['id' => (int)mysqli_insert_id($link), 'name' => $name, 'email' => $email, 'phone' => '', 'google_id' => $sub, 'pwd_set' => 0];
        $notice = ['Welcome to Bus Reservation!', google_welcome_email_html($name)];
        $flash  = 'Account created with Google. Welcome, ' . $name . '.';
    } elseif ($decision['action'] === 'signin') {
        if ((empty($user['name']) || $user['name'] === 'User' || $user['name'] === 'Passenger') && $googleName !== '') {
            db_exec($link, 'UPDATE costumer SET name = ? WHERE id = ?', 'si', [$googleName, (int)$user['id']]);
            $user['name'] = $googleName;
        }
        $flash = 'Welcome back, ' . $user['name'] . '.';
    }

    $row = [
        'id'        => (int)$user['id'],
        'name'      => (string)$user['name'],
        'email'     => (string)($user['email'] ?? $email),
        'phone'     => (string)($user['phone'] ?? ''),
        'google_id' => (string)($user['google_id'] ?? $sub),
        'pwd_set'   => (int)($user['pwd_set'] ?? 0),
    ];
    unset($_SESSION['google_oauth']);
    if (!login_user('user', $row)) {
        return $fail('google_failed');
    }
    $_SESSION['email']     = (string)($user['email'] ?? $email);
    $_SESSION['google_id'] = (string)($user['google_id'] ?? $sub);
    $_SESSION['pwd_set']   = (int)($user['pwd_set'] ?? 0);

    try {
        audit($link, 'LOGIN', 'customer', (int)$user['id'], null, ['method' => 'google', 'action' => $decision['action']]);
    } catch (Throwable $e) {
        error_log('[busres] Google sign-in audit failed: ' . $e->getMessage());
    }

    if ($notice !== null && pr_mail_configured()) {
        [$subject, $html] = $notice;
        pr_after_response(static function () use ($email, $subject, $html): void {
            $r = send_app_mail($email, $subject, $html);
            if (empty($r['ok'])) {
                error_log('[busres] Google sign-in e-mail failed: ' . ($r['error'] ?? 'unknown'));
            }
        });
    }

    $dest = ($next !== null && $next !== '') ? $next : (BASE_URL . '/user/index.php');
    return ['ok' => true, 'code' => '', 'dest' => $dest, 'flash' => $flash];
}

/* ------------------------------------------------------------------ */
/* Step 4: Re-authentication for sensitive actions                    */
/* ------------------------------------------------------------------ */

const GOOGLE_REAUTH_TTL = 300; // seconds a Google re-verification stays valid

/** Adds max_age and login_hint for a re-auth round trip, and remembers the intent in the session. */
function google_begin_reauth(int $uid, string $email, string $next): string {
    $url = google_begin($next); // reuses state, nonce, PKCE
    $_SESSION['google_oauth']['mode'] = 'reauth';
    $_SESSION['google_oauth']['uid']  = $uid;
    // Rebuild the URL with the extra parameters.
    $parts = parse_url($url, PHP_URL_QUERY);
    parse_str((string)$parts, $q);
    $q['max_age']    = (string)GOOGLE_REAUTH_TTL;
    $q['login_hint'] = $email;
    $q['prompt']     = 'login';
    return GOOGLE_AUTH_URL . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
}

/** Pure check: did the ID token prove this customer is present just now? */
function google_reauth_ok(array $claims, string $expectedSub, int $now): bool {
    $authTime = $claims['auth_time'] ?? null;
    return is_string($claims['sub'] ?? null)
        && hash_equals($expectedSub, (string)$claims['sub'])
        && is_numeric($authTime)
        && ($now - (int)$authTime) <= GOOGLE_REAUTH_TTL
        && ($now - (int)$authTime) >= -60; // allow 60 s of clock skew
}

/** True when a sensitive action may proceed for this customer. */
function customer_proof_ok(array $user, string $password): bool {
    if ((int)($user['pwd_set'] ?? 1) === 1) {
        return $password !== '' && password_verify($password, (string)($user['pwd'] ?? ''));
    }
    $at = (int)($_SESSION['reauth_at'] ?? 0);
    return $at > 0 && (time() - $at) <= GOOGLE_REAUTH_TTL;
}
