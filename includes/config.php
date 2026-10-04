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

// Application Timezone (F6: default Asia/Kolkata)
date_default_timezone_set(getenv('APP_TZ') ?: 'Asia/Kolkata');

// Global exception handler (M-04)
if (!function_exists('busres_exception_handler')) {
    function busres_exception_handler(Throwable $t): void {
        $where = $t->getFile() . ':' . $t->getLine();
        error_log('[busres] ' . get_class($t) . ': ' . $t->getMessage() . ' @ ' . $where);
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo '<p style="color:#721c24; background-color:#f8d7da; border:1px solid #f5c6cb; padding:12px; border-radius:4px; font-family:sans-serif; text-align:center; max-width:600px; margin:2rem auto;">Something went wrong. Please try again in a moment.</p>';
    }
    set_exception_handler('busres_exception_handler');
}
