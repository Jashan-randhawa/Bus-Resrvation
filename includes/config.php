<?php
/**
 * Central configuration (loaded first by db_con.php, the auth guards and every layout file).
 *
 * BASE_URL is the URL path where the app is served from, without a trailing slash:
 *   ''                  when the app is at the web root (Render / Docker)
 *   '/bus-reservation'  when it lives in a sub-folder (XAMPP / WAMP)
 *
 * It is auto-detected from DOCUMENT_ROOT. To force a value, set the BASE_URL
 * environment variable (an empty value is allowed and means "web root").
 */
if (!defined('BASE_URL')) {
    $envBase = getenv('BASE_URL');
    if ($envBase !== false) {
        define('BASE_URL', rtrim($envBase, '/'));
    } else {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $appRoot = realpath(dirname(__DIR__));
        $base = '';
        if ($docRoot && $appRoot) {
            $docRoot = str_replace('\\', '/', $docRoot);
            $appRoot = str_replace('\\', '/', $appRoot);
            if (strncasecmp($appRoot, $docRoot, strlen($docRoot)) === 0) {
                $base = substr($appRoot, strlen($docRoot));
            }
        }
        define('BASE_URL', rtrim($base, '/'));
    }
}

if (!defined('BUS_SEATS')) {
    define('BUS_SEATS', 36);
}

if (!defined('CURRENCY')) {
    define('CURRENCY', getenv('APP_CURRENCY') ?: '₹');
}

// Application Timezone (F6: default Asia/Kolkata)
$app_tz = getenv('APP_TZ') ?: 'Asia/Kolkata';
date_default_timezone_set($app_tz);
if (!defined('APP_TZ')) {
    define('APP_TZ', $app_tz);
}

// Cancellation Cutoff Window in minutes (U-10: default 120 minutes / 2 hours)
if (!defined('APP_CANCEL_CUTOFF_MIN')) {
    define('APP_CANCEL_CUTOFF_MIN', (int)(getenv('APP_CANCEL_CUTOFF_MIN') ?: 120));
}

// Booking Cutoff Window in minutes (Phase 3.5: default 30 minutes before departure)
if (!defined('APP_BOOKING_CUTOFF_MIN')) {
    define('APP_BOOKING_CUTOFF_MIN', (int)(getenv('APP_BOOKING_CUTOFF_MIN') ?: 30));
}

// Mandatory 2FA enforcement for administrative roles (default: false / optional)
// Set ADMIN_MFA_ENFORCE=true in environment to strictly enforce 2FA before accessing dashboard
if (!defined('ADMIN_MFA_ENFORCE')) {
    $mfa_enforce_env = getenv('ADMIN_MFA_ENFORCE');
    define('ADMIN_MFA_ENFORCE', ($mfa_enforce_env !== false && ($mfa_enforce_env === '1' || strtolower($mfa_enforce_env) === 'true')));
}

// Ticket HMAC verification secret (Issue 5)
if (!defined('TICKET_HMAC_SECRET')) {
    $hmac_env = getenv('TICKET_HMAC_SECRET');
    define('TICKET_HMAC_SECRET', ($hmac_env !== false && $hmac_env !== '') ? $hmac_env : 'busres-ticket-secret-salt');
}

// Global exception handler (M-04 / D-02 / P6)
if (!function_exists('busres_exception_handler')) {
    function busres_exception_handler(Throwable $t): void {
        $ref = bin2hex(random_bytes(4));
        error_log("[$ref] " . get_class($t) . ': ' . $t->getMessage() . "\n" . $t->getTraceAsString());
        if (!headers_sent()) {
            http_response_code(500);
        }
        $is_debug = (getenv('APP_DEBUG') === '1' || getenv('APP_DEBUG') === 'true');
        $is_super = function_exists('is_super_admin') && is_super_admin();
        if ($is_debug && $is_super) {
            echo '<pre style="color:#721c24; background-color:#f8d7da; border:1px solid #f5c6cb; padding:15px; border-radius:4px; font-family:monospace; max-width:800px; margin:2rem auto;">';
            echo htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
            echo '</pre>';
        } else {
            echo '<p style="color:#721c24; background-color:#f8d7da; border:1px solid #f5c6cb; padding:12px; border-radius:4px; font-family:sans-serif; text-align:center; max-width:600px; margin:2rem auto;">Something went wrong. Reference: ' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '</p>';
        }
    }
    set_exception_handler('busres_exception_handler');
}
