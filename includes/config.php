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

// Global exception handler (M-04 / D-02)
if (!function_exists('busres_exception_handler')) {
    function busres_exception_handler(Throwable $t): void {
        $where = $t->getFile() . ':' . $t->getLine();
        error_log('[busres] ' . get_class($t) . ': ' . $t->getMessage() . ' @ ' . $where);
        if (!headers_sent()) {
            http_response_code(500);
        }
        $is_debug = (getenv('APP_DEBUG') === '1' || getenv('APP_DEBUG') === 'true');
        $is_admin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') || !empty($_SESSION['admin']);
        if ($is_debug || $is_admin) {
            echo '<div style="color:#721c24; background-color:#f8d7da; border:1px solid #f5c6cb; padding:15px; border-radius:4px; font-family:monospace; max-width:800px; margin:2rem auto;">';
            echo '<strong style="display:block; margin-bottom:8px;">[Application Exception: ' . htmlspecialchars(get_class($t), ENT_QUOTES, 'UTF-8') . ']</strong>';
            echo '<p style="margin:4px 0;"><strong>Message:</strong> ' . htmlspecialchars($t->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p style="margin:4px 0;"><strong>Location:</strong> ' . htmlspecialchars($where, ENT_QUOTES, 'UTF-8') . '</p>';
            echo '</div>';
        } else {
            echo '<p style="color:#721c24; background-color:#f8d7da; border:1px solid #f5c6cb; padding:12px; border-radius:4px; font-family:sans-serif; text-align:center; max-width:600px; margin:2rem auto;">Something went wrong. Please try again in a moment.</p>';
        }
    }
    set_exception_handler('busres_exception_handler');
}
