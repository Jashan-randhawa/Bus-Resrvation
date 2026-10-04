<?php
// includes/db_con.php
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$hostname = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$db_name  = getenv('DB_NAME') ?: 'majorproject';
$db_port  = (int)(getenv('DB_PORT') ?: 3306);
$use_ssl  = filter_var(getenv('DB_SSL'), FILTER_VALIDATE_BOOLEAN) || (getenv('DB_SSL') === 'true');

$link = mysqli_init();
$flags = 0;

if ($use_ssl) {
    $ca = getenv('DB_SSL_CA') ?: '/etc/ssl/certs/ca-certificates.crt';
    if (file_exists($ca)) {
        mysqli_ssl_set($link, null, null, $ca, null, null);
    } else {
        mysqli_ssl_set($link, null, null, null, null, null);
    }
    $flags = MYSQLI_CLIENT_SSL;
}

try {
    mysqli_real_connect($link, $hostname, $username, $password, $db_name, $db_port, null, $flags);
    mysqli_set_charset($link, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('[busres] Database connection failed: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    die('Database connection failed. Please check database service status and configuration.');
}
