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
