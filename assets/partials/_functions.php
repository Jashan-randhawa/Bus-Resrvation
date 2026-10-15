<?php
/**
 * assets/partials/_functions.php
 *
 * Database connectivity and helper functions.
 * Implements a 3-tier credential priority system:
 *  Tier 1: Cloud Environment Variables (getenv)
 *  Tier 2: Untracked local configuration (_db-local.php for cPanel / shared hosts)
 *  Tier 3: Local XAMPP/WAMP fallback defaults
 */

function db_connect()
{
    // Tier 2: Load optional untracked config file (for cPanel / InfinityFree / Hostinger)
    $local_config = __DIR__ . '/_db-local.php';
    if (file_exists($local_config)) {
        require_once $local_config;
    }

    // Tier 1 & Tier 3: Read Environment Variables first, fallback to XAMPP defaults
    $servername = getenv('DB_HOST') ?: (defined('LOCAL_DB_HOST') ? LOCAL_DB_HOST : 'localhost');
    $username   = getenv('DB_USER') ?: (defined('LOCAL_DB_USER') ? LOCAL_DB_USER : 'root');
    $password   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (defined('LOCAL_DB_PASS') ? LOCAL_DB_PASS : '');
    $database   = getenv('DB_NAME') ?: (defined('LOCAL_DB_NAME') ? LOCAL_DB_NAME : (defined('DB_NAME') ? DB_NAME : 'sbtbsphp'));
    $port       = (int)(getenv('DB_PORT') ?: (defined('LOCAL_DB_PORT') ? LOCAL_DB_PORT : 3306));

    $conn = mysqli_init();

    // Optional SSL support for cloud MySQL providers that require encrypted connections
    if (getenv('DB_SSL') === 'true' || (defined('LOCAL_DB_SSL') && LOCAL_DB_SSL)) {
        $ca = getenv('DB_SSL_CA') ?: '/etc/ssl/certs/ca-certificates.crt';
        if (file_exists($ca)) {
            mysqli_ssl_set($conn, null, null, $ca, null, null);
        } else {
            mysqli_ssl_set($conn, null, null, null, null, null);
        }
        $connected = @mysqli_real_connect($conn, $servername, $username, $password, $database, $port, null, MYSQLI_CLIENT_SSL);
    } else {
        $connected = @mysqli_real_connect($conn, $servername, $username, $password, $database, $port);
    }

    if (!$connected) {
        error_log("Database connection failed: " . mysqli_connect_error());
        die("Database connection error. Please check your server configuration.");
    }

    // Ensure proper character encoding for names, routes, and currency symbols
    mysqli_set_charset($conn, "utf8mb4");

    return $conn;
}
