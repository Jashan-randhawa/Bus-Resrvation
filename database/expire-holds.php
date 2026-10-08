<?php
// database/expire-holds.php -- Background Hold Expiration Cron (Issue 31)
// CLI usage: php database/expire-holds.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Access denied: CLI execution only.\n";
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$holds_released = release_expired_holds($link);
$locks_purged = purge_stale_seat_locks($link);

echo "[" . date('Y-m-d H:i:s') . "] Expired holds cleanup: {$holds_released} hold(s) released, {$locks_purged} stale seat lock(s) purged.\n";
exit(0);
