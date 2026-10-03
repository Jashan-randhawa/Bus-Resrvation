<?php
require_once __DIR__ . '/config.php';

$hostname = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$db_name  = getenv('DB_NAME') ?: 'majorproject';
$db_port  = getenv('DB_PORT') ?: 3306;
$use_ssl  = getenv('DB_SSL') === 'true';

$link = mysqli_init();

if ($use_ssl) {
    mysqli_ssl_set($link, NULL, NULL, NULL, NULL, NULL);
    mysqli_real_connect($link, $hostname, $username, $password, $db_name, (int)$db_port, NULL, MYSQLI_CLIENT_SSL);
} else {
    mysqli_real_connect($link, $hostname, $username, $password, $db_name, (int)$db_port);
}

if (!$link) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
