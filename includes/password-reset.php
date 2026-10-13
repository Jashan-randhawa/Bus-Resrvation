<?php
// includes/password-reset.php -- Email reset-link flow for customers.
//
// Design mirrors the AI Fitness Tracker reset flow, ported to PHP / MySQL:
//   * a random token is e-mailed as a link; only its SHA-256 hash is stored
//   * links are single use and expire after PASSWORD_RESET_TTL_MIN (default 10)
//   * the response is identical whether or not the e-mail has an account
//   * per-e-mail request throttle, 60 s debounce, per-IP lockout on bad tokens
//   * if the e-mail cannot be sent the stored token is deleted again
//   * current password cannot be reused; a "password changed" notice is sent
//   * the reset link is NEVER shown on a web page, it only travels by e-mail
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

const PR_REQUEST_LIMIT   = 3;     // reset e-mails per address ...
const PR_REQUEST_WINDOW  = 900;   // ... per 15 minutes
const PR_FAIL_LIMIT      = 5;     // bad tokens per IP ...
const PR_FAIL_WINDOW     = 900;   // ... per 15 minutes
const PR_DEBOUNCE_SECS   = 60;    // no second e-mail inside this window

/** Link lifetime in minutes (env PASSWORD_RESET_TTL_MIN, clamped 5..60, default 10). */
function pr_ttl_minutes(): int {
    $v = (int)(getenv('PASSWORD_RESET_TTL_MIN') ?: 10);
    return max(5, min(60, $v));
}

/** SHA-256 of the plain token; this is the only form that is stored. */
function pr_hash_token(string $token): string {
    return hash('sha256', $token);
}

/** 64 hex chars, 256 bits of entropy. */
function pr_new_token(): string {
    return bin2hex(random_bytes(32));
}

function pr_is_token_shape(string $token): bool {
    return (bool)preg_match('/^[a-f0-9]{64}$/', $token);
}

/**
 * Public base URL used inside e-mails, without trailing slash.
 * Order: APP_URL, Render's RENDER_EXTERNAL_URL, then (development only) the request host.
 * The request Host header is not trusted in production because a forged Host
 * header could point the e-mailed link at an attacker's domain.
 */
function pr_app_url(): string {
    // APP_URL is the full public root of the app (include any sub-folder, e.g. https://x.com/bus).
    $v = rtrim(trim((string)getenv('APP_URL')), '/');
    if ($v !== '') {
        return $v;
    }
    // Render sets RENDER_EXTERNAL_URL to the service origin; the app lives at BASE_URL below it.
    $v = rtrim(trim((string)getenv('RENDER_EXTERNAL_URL')), '/');
    if ($v !== '') {
        return $v . (defined('BASE_URL') ? BASE_URL : '');
    }
    if (strtolower((string)getenv('APP_ENV')) === 'development') {
        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $host   = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = $https ? 'https' : 'http';
        $base   = defined('BASE_URL') ? BASE_URL : '';
        return $scheme . '://' . $host . $base;
    }
    return '';
}

/** Absolute reset link for an e-mail, or '' when no trusted base URL is configured. */
function pr_build_reset_url(string $plainToken): string {
    $base = pr_app_url();
    if ($base === '') {
        return '';
    }
    return $base . '/reset-password.php?code=' . rawurlencode($plainToken);
}

function pr_mail_configured(): bool {
    $on = getenv('MAIL_ENABLED');
    return $on === '1' || strtolower((string)$on) === 'true';
}

/* ------------------------------------------------------------------ */
/* E-mail templates                                                    */
/* ------------------------------------------------------------------ */

function pr_email_shell(string $heading, string $bodyHtml): string {
    $brand = e(getenv('MAIL_FROM_NAME') ?: 'Bus Reservation System');
    return '<!DOCTYPE html><html lang="en"><body style="margin:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;">'
        . '<tr><td style="padding:24px 32px;border-bottom:1px solid #e5e7eb;font-size:18px;font-weight:bold;">&#128652; ' . $brand . '</td></tr>'
        . '<tr><td style="padding:28px 32px;font-size:15px;line-height:1.6;">'
        . '<h2 style="margin:0 0 12px;font-size:20px;">' . e($heading) . '</h2>' . $bodyHtml
        . '</td></tr>'
        . '<tr><td style="padding:16px 32px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">This is an automated message, please do not reply.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function pr_reset_email_html(string $link, int $ttlMinutes, string $name = ''): string {
    $hello = $name !== '' ? 'Hello ' . e($name) . ',' : 'Hello,';
    $safe  = e($link);
    $body  = '<p>' . $hello . '</p>'
        . '<p>We received a request to reset the password for your account. Click the button below to choose a new password.</p>'
        . '<p style="margin:24px 0;"><a href="' . $safe . '" style="background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;display:inline-block;">Reset password</a></p>'
        . '<p style="font-size:13px;color:#4b5563;">Or copy this link into your browser:<br><span style="word-break:break-all;">' . $safe . '</span></p>'
        . '<p style="font-size:13px;color:#4b5563;">This link expires in <strong>' . (int)$ttlMinutes . ' minutes</strong> and can be used only once.</p>'
        . '<p style="font-size:13px;color:#4b5563;">If you did not request this, you can ignore this e-mail. Your password will not change.</p>';
    return pr_email_shell('Reset your password', $body);
}

function pr_notice_email_html(string $name = ''): string {
    $hello = $name !== '' ? 'Hello ' . e($name) . ',' : 'Hello,';
    $body  = '<p>' . $hello . '</p>'
        . '<p>The password for your account was just changed using a reset link.</p>'
        . '<p style="font-size:13px;color:#4b5563;">If this was not you, request a new reset link immediately and contact support.</p>';
    return pr_email_shell('Your password was changed', $body);
}

/* ------------------------------------------------------------------ */
/* Running work after the response has been sent                      */
/* ------------------------------------------------------------------ */

/**
 * Sends the buffered page to the browser, then runs $work. The visitor therefore
 * waits the same time whether or not the address has an account (no timing
 * oracle) and never waits for SMTP. Safe to call before header()+exit.
 */
function pr_after_response(callable $work): void {
    register_shutdown_function(static function () use ($work): void {
        try {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (!headers_sent()) {
                $out = '';
                while (ob_get_level() > 0) {
                    $out = (string)ob_get_clean() . $out;
                }
                header('Connection: close');
                header('Content-Length: ' . strlen($out));
                echo $out;
                flush();
            }
            ignore_user_abort(true);
            $work();
        } catch (Throwable $e) {
            error_log('[busres] pr_after_response: ' . $e->getMessage());
        }
    });
}

/* ------------------------------------------------------------------ */
/* Step 1: request a reset link                                       */
/* ------------------------------------------------------------------ */

/**
 * Foreground part of "forgot password".
 *
 * @return array{status:string,message:string}
 *   status: 'invalid' (bad e-mail format), 'rate_limited', or 'accepted'.
 *   'accepted' is returned for existing AND unknown addresses alike.
 */
function pr_request_reset(mysqli $link, string $email, string $ip): array {
    $email = strtolower(trim($email));
    if ($email === '' || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'invalid', 'message' => 'Please enter a valid email address.'];
    }

    ensure_password_resets_table($link);

    $key = 'pwreset:email:' . $email;
    if (throttle_blocked($link, $key, PR_REQUEST_LIMIT, PR_REQUEST_WINDOW)
        || throttle_blocked($link, 'pwreset:ip:' . $ip, PR_REQUEST_LIMIT * 4, PR_REQUEST_WINDOW)) {
        return [
            'status'  => 'rate_limited',
            'message' => 'Too many reset requests. Please wait about 15 minutes and try again.',
        ];
    }
    // Counted for every address, existing or not, so the limiter reveals nothing.
    throttle_hit($link, $key);
    throttle_hit($link, 'pwreset:ip:' . $ip);

    return [
        'status'  => 'accepted',
        'message' => 'If an account exists with this email address, a password reset link has been sent.',
    ];
}

/**
 * Background part: look up the customer, store the hashed token and e-mail the link.
 * Never throws and never reveals anything to the visitor.
 *
 * @return string outcome: sent | unknown_email | debounced | not_configured | email_failed | error
 */
function pr_issue_and_send(mysqli $link, string $email): string {
    $email = strtolower(trim($email));
    try {
        $user = db_one($link,
            'SELECT id, name, email FROM costumer WHERE email = ? AND archived_at IS NULL',
            's', [$email]);
        if (!$user) {
            return 'unknown_email';
        }

        // 60 s debounce: keep the live link, do not send a duplicate e-mail.
        $recent = db_one($link,
            'SELECT 1 AS n FROM password_resets WHERE email = ? AND expires_at > NOW() AND created_at > (NOW() - INTERVAL ' . PR_DEBOUNCE_SECS . ' SECOND)',
            's', [$email]);
        if ($recent) {
            return 'debounced';
        }

        $token = pr_new_token();
        $ttl   = pr_ttl_minutes();
        db_exec($link, 'DELETE FROM password_resets WHERE email = ?', 's', [$email]);
        db_exec($link,
            'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
            'ssi', [$email, pr_hash_token($token), $ttl]);

        $link_url = pr_build_reset_url($token);
        $drop = static function () use ($link, $email): void {
            try { db_exec($link, 'DELETE FROM password_resets WHERE email = ?', 's', [$email]); }
            catch (Throwable $e) { error_log('[busres] pr cleanup: ' . $e->getMessage()); }
        };

        if ($link_url === '') {
            error_log('[busres] password reset: set APP_URL (or RENDER_EXTERNAL_URL); refusing to build a link from the Host header.');
            $drop();
            return 'not_configured';
        }

        if (!pr_mail_configured()) {
            if (strtolower((string)getenv('APP_ENV')) === 'development') {
                // Dev convenience (same idea as the fitness tracker's "dev-log" provider): server log only.
                error_log('[busres] DEV reset link for ' . mask_email($email) . ': ' . $link_url);
                return 'sent';
            }
            error_log('[busres] password reset: MAIL_ENABLED is false, no e-mail sent.');
            $drop();
            return 'not_configured';
        }

        $res = send_app_mail($user['email'], 'Reset your password - Bus Reservation',
            pr_reset_email_html($link_url, $ttl, (string)$user['name']));
        if (empty($res['ok'])) {
            error_log('[busres] password reset e-mail failed for ' . mask_email($email) . ': ' . ($res['error'] ?? 'unknown'));
            $drop(); // no orphaned live token when delivery failed
            return 'email_failed';
        }
        pr_audit($link, 'PASSWORD_RESET_REQUESTED', (int)$user['id']);
        return 'sent';
    } catch (Throwable $e) {
        error_log('[busres] pr_issue_and_send: ' . $e->getMessage());
        return 'error';
    }
}

/* ------------------------------------------------------------------ */
/* Step 2: validate token and set the new password                    */
/* ------------------------------------------------------------------ */

/** Live (unexpired) reset row for a plain token, or null. */
function pr_find_by_token(mysqli $link, string $token): ?array {
    $token = trim($token);
    if (!pr_is_token_shape($token)) {
        return null;
    }
    ensure_password_resets_table($link);
    $hash = pr_hash_token($token);
    $row = db_one($link,
        'SELECT id, email, token, expires_at FROM password_resets WHERE token = ? AND expires_at > NOW()',
        's', [$hash]);
    if (!$row || !hash_equals((string)$row['token'], $hash)) {
        return null;
    }
    return $row;
}

function pr_locked_out(mysqli $link, string $ip): bool {
    return throttle_blocked($link, 'pwreset:fail:' . $ip, PR_FAIL_LIMIT, PR_FAIL_WINDOW);
}

/** Check a token on page load (counts failures per IP). */
function pr_validate_token(mysqli $link, string $token, string $ip): array {
    if (pr_locked_out($link, $ip)) {
        return ['valid' => false, 'message' => 'Too many attempts. Please try again later.'];
    }
    $row = pr_find_by_token($link, $token);
    if (!$row) {
        throttle_hit($link, 'pwreset:fail:' . $ip);
        return ['valid' => false, 'message' => 'This password reset link is invalid or has expired. Please request a new one.'];
    }
    return ['valid' => true, 'message' => ''];
}

/**
 * Complete the reset.
 *
 * @return array{ok:bool,message:string}
 */
function pr_complete_reset(mysqli $link, string $token, string $newPwd, string $confirmPwd, string $ip): array {
    if (pr_locked_out($link, $ip)) {
        return ['ok' => false, 'message' => 'Too many failed attempts. Please try again in 15 minutes.'];
    }
    $row = pr_find_by_token($link, $token);
    if (!$row) {
        throttle_hit($link, 'pwreset:fail:' . $ip);
        return ['ok' => false, 'message' => 'This password reset link is invalid or has expired. Please request a new one.'];
    }

    $errors = validate_new_password($newPwd, 'user');
    if ($newPwd !== $confirmPwd) {
        $errors[] = 'Password and confirmation password do not match.';
    }
    if ($errors) {
        return ['ok' => false, 'message' => implode(' ', $errors)];
    }

    $user = db_one($link,
        'SELECT id, name, email, pwd FROM costumer WHERE email = ? AND archived_at IS NULL',
        's', [$row['email']]);
    if (!$user) {
        db_exec($link, 'DELETE FROM password_resets WHERE email = ?', 's', [$row['email']]);
        return ['ok' => false, 'message' => 'This password reset link is invalid or has expired. Please request a new one.'];
    }
    if (password_verify($newPwd, (string)$user['pwd'])) {
        return ['ok' => false, 'message' => 'You cannot reuse your current password. Please choose a new one.'];
    }

    db_exec($link, 'UPDATE costumer SET pwd = ? WHERE id = ?', 'si', [password_hash($newPwd, PASSWORD_DEFAULT), (int)$user['id']]);
    db_exec($link, 'DELETE FROM password_resets WHERE email = ?', 's', [$row['email']]); // single use
    throttle_clear($link, 'pwreset:fail:' . $ip);
    pr_audit($link, 'PASSWORD_RESET_SUCCESS', (int)$user['id']);

    if (pr_mail_configured()) {
        $to = (string)$user['email'];
        $name = (string)$user['name'];
        pr_after_response(static function () use ($to, $name): void {
            $r = send_app_mail($to, 'Your password was changed - Bus Reservation', pr_notice_email_html($name));
            if (empty($r['ok'])) {
                error_log('[busres] password-changed notice failed: ' . ($r['error'] ?? 'unknown'));
            }
        });
    }
    return ['ok' => true, 'message' => 'Your password has been reset. Please sign in with your new password.'];
}

/** Audit trail entry; audit() maps unknown actions to OTHER, the event name is kept in new_value. */
function pr_audit(mysqli $link, string $event, int $customerId): void {
    try {
        audit($link, 'OTHER', 'customer', $customerId, null, ['event' => $event]);
    } catch (Throwable $e) {
        error_log('[busres] pr_audit: ' . $e->getMessage());
    }
}
