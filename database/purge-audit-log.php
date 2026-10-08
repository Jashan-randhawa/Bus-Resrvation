<?php
// database/purge-audit-log.php -- CLI-only Retention Archival & Purge Script (Issue 16)
// Usage: php database/purge-audit-log.php [retention_days: default 365]

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("CLI execution only.\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$days = isset($argv[1]) && is_numeric($argv[1]) ? max(30, (int)$argv[1]) : 365;
$cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

echo "[*] Audit Log Retention Purge\n";
echo "    Purge cutoff: {$cutoff} (older than {$days} days)\n";

$countRow = db_one($link, "SELECT COUNT(*) AS c FROM audit_log WHERE `timestamp` < ?", 's', [$cutoff]);
$totalToPurge = (int)($countRow['c'] ?? 0);

if ($totalToPurge === 0) {
    echo "[i] No audit log records found older than {$cutoff}. Nothing to purge.\n";
    exit(0);
}

echo "[*] Found {$totalToPurge} record(s) eligible for archival and deletion.\n";

$archiveDir = __DIR__ . '/../logs/audit_archive';
if (!is_dir($archiveDir)) {
    mkdir($archiveDir, 0750, true);
}

$archiveFile = $archiveDir . '/audit_archive_' . date('Ymd_His') . '.jsonl';
$fp = fopen($archiveFile, 'w');
if (!$fp) {
    fwrite(STDERR, "[!] Failed to open archive file for writing: {$archiveFile}\n");
    exit(1);
}

$rows = db_all($link, "SELECT * FROM audit_log WHERE `timestamp` < ? ORDER BY id ASC", 's', [$cutoff]);
foreach ($rows as $r) {
    fwrite($fp, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
}
fclose($fp);

echo "[✓] Exported {$totalToPurge} records to archive: " . basename($archiveFile) . "\n";

// Disable append-only trigger temporarily if session allows, or execute delete
$deleted = db_exec($link, "DELETE FROM audit_log WHERE `timestamp` < ?", 's', [$cutoff]);

try {
    audit($link, 'RETENTION_PURGE', 'audit_log', null, null, [
        'count'        => $totalToPurge,
        'archive_file' => basename($archiveFile),
        'cutoff'       => $cutoff,
        'days'         => $days
    ]);
} catch (Throwable $e) {
    echo "[!] Warning: failed to log RETENTION_PURGE audit row: " . $e->getMessage() . "\n";
}

echo "[✓] Retention purge completed successfully. {$totalToPurge} historic row(s) purged.\n";
exit(0);
