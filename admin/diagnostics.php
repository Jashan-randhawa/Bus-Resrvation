<?php
// admin/diagnostics.php -- System Health & Operational Diagnostics Endpoint (O15 / P6)
header('Cache-Control: no-store, private');
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('super_admin');

// Audit diagnostics run (Phase A Item 3)
audit($link, 'DIAGNOSTICS_RUN', 'system', null, null, ['user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 100)]);

$migration_log = $_SESSION['migration_log'] ?? null;
unset($_SESSION['migration_log']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
    csrf_verify();
    require_once __DIR__ . '/../database/db_migrate.php';
    $res = run_migrations($link);
    audit($link, 'RUN_MIGRATIONS', 'system', null, null, ['ok' => $res['ok']]);
    if ($res['ok']) {
        flash_set('success', 'Database migrations executed successfully.');
    } else {
        flash_set('danger', 'One or more database migrations failed. Review log below.');
    }
    $_SESSION['migration_log'] = implode("\n", $res['log']);
    header('Location: ' . BASE_URL . '/admin/diagnostics.php');
    exit;
}

$run_query = function(string $sql) use ($link): array {
    try {
        $res = mysqli_query($link, $sql);
        if ($res instanceof mysqli_result) {
            $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
            mysqli_free_result($res);
            return $rows;
        }
        return [];
    } catch (Throwable $e) {
        error_log('[busres] diagnostics query error: ' . $e->getMessage());
        return [];
    }
};

function run_check(string $name, callable $fn): array {
    try {
        return ['name' => $name] + $fn();
    } catch (Throwable $t) {
        error_log('[busres] diag ' . $name . ': ' . $t->getMessage());
        return ['name' => $name, 'status' => 'FAIL', 'message' => $t->getMessage()];
    }
}

$checks = [];

// 1. PHP Version & Extensions
$checks[] = run_check('PHP Version', function() {
    return [
        'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'OK' : 'WARN',
        'message' => 'PHP ' . PHP_VERSION . ' (Recommended: 8.0+)'
    ];
});

$checks[] = run_check('MySQLi Extension', function() {
    $ok = extension_loaded('mysqli');
    return [
        'status' => $ok ? 'OK' : 'FAIL',
        'message' => $ok ? 'Loaded & Active' : 'Missing mysqli extension'
    ];
});

$checks[] = run_check('OpenSSL Extension', function() {
    $ok = extension_loaded('openssl');
    return [
        'status' => $ok ? 'OK' : 'FAIL',
        'message' => $ok ? 'Loaded & Active' : 'Missing openssl extension'
    ];
});

// 2. Database Connectivity & Latency
$checks[] = run_check('Database Round-Trip & Latency', function() use ($link) {
    $start = microtime(true);
    $res = mysqli_query($link, 'SELECT VERSION() AS v');
    $duration_ms = round((microtime(true) - $start) * 1000, 2);
    $ver_row = ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : null;
    $version = $ver_row['v'] ?? 'unknown';

    $ssl_cipher = '';
    try {
        $c_res = mysqli_query($link, "SHOW STATUS LIKE 'Ssl_cipher'");
        if ($c_res instanceof mysqli_result && $c_row = mysqli_fetch_assoc($c_res)) {
            $ssl_cipher = (string)($c_row['Value'] ?? '');
        }
    } catch (Throwable $e) {}

    $ssl_desc = $ssl_cipher !== '' ? "SSL: {$ssl_cipher}" : 'SSL: Plain';
    $conn_status = ($link instanceof mysqli && !mysqli_connect_errno()) ? 'Connected: yes' : 'Connected: no';

    return [
        'status' => 'OK',
        'message' => "{$conn_status} | Latency: {$duration_ms}ms | {$ssl_desc} | v{$version}"
    ];
});

// 3. Core Tables Audit
$checks[] = run_check('Core Schema Tables Presence', function() use ($run_query) {
    $required = ['admin', 'costumer', 'buses', 'route', 'booking', 'query', 'login_attempts', 'schema_migrations'];
    $raw_tables = $run_query('SHOW TABLES');
    $tables = array_map(function($r) {
        return (string)array_values($r)[0];
    }, $raw_tables);

    $missing = array_diff($required, $tables);
    if (empty($missing)) {
        return ['status' => 'OK', 'message' => 'All 8 core application tables are present.'];
    }
    return ['status' => 'FAIL', 'message' => 'Missing database tables: ' . implode(', ', $missing)];
});

// 4. Booking Table Hardening
$checks[] = run_check('Booking Table Schema Hardening', function() use ($run_query) {
    $booking_cols = $run_query('SHOW COLUMNS FROM `booking`');
    $b_col_names = array_column($booking_cols, 'Field');
    $has_pnr = in_array('pnr', $b_col_names, true);
    $has_status = in_array('status', $b_col_names, true);
    $has_hold = in_array('hold_expires_at', $b_col_names, true);
    $all_ok = $has_pnr && $has_status && $has_hold;
    return [
        'status' => $all_ok ? 'OK' : 'WARN',
        'message' => "PNR: " . ($has_pnr ? 'Present' : 'Missing') . " | Status: " . ($has_status ? 'Present' : 'Missing') . " | Hold Expiry: " . ($has_hold ? 'Present' : 'Missing')
    ];
});

// 5. Fleet Capacity Modeling
$checks[] = run_check('Fleet Dynamic Capacity Modeling', function() use ($run_query) {
    $buses_cols = $run_query('SHOW COLUMNS FROM `buses`');
    $has_capacity = in_array('capacity', array_column($buses_cols, 'Field'), true);
    return [
        'status' => $has_capacity ? 'OK' : 'WARN',
        'message' => "Capacity Column: " . ($has_capacity ? 'Active (Dynamic fleet)' : 'Missing (36 fallback)')
    ];
});

// 6. Concurrency & Unique Indexes
$checks[] = run_check('Booking Concurrency Constraints', function() use ($link) {
    $c = booking_concurrency_status($link);
    return ['status' => $c['status'], 'message' => $c['message']];
});

// 7. Master Data Unique Indexes
$checks[] = run_check('Master Data Uniqueness Constraints', function() use ($run_query) {
    $admin_idx = array_column($run_query('SHOW INDEX FROM `admin`'), 'Key_name');
    $cust_idx = array_column($run_query('SHOW INDEX FROM `costumer`'), 'Key_name');
    $buses_idx = array_column($run_query('SHOW INDEX FROM `buses`'), 'Key_name');

    $has_ua = in_array('uq_admin_email', $admin_idx, true);
    $has_uc = in_array('uq_customer_email', $cust_idx, true);
    $has_ub = in_array('uq_bus_number', $buses_idx, true);

    if ($has_ua && $has_uc && $has_ub) {
        return ['status' => 'OK', 'message' => 'Email and vehicle uniqueness constraints active.'];
    }
    return [
        'status' => 'WARN',
        'message' => 'Pending unique indexes: ' . (!$has_ua ? 'uq_admin_email ' : '') . (!$has_uc ? 'uq_customer_email ' : '') . (!$has_ub ? 'uq_bus_number' : '')
    ];
});

// 8. Password Hashing Audit
$checks[] = run_check('Password Cryptography Audit', function() use ($run_query) {
    $legacy_admins = 0;
    $admin_rows = $run_query('SELECT Password FROM `admin`');
    foreach ($admin_rows as $a) {
        if (password_get_info($a['Password'])['algo'] === null) {
            $legacy_admins++;
        }
    }
    $legacy_users = 0;
    $user_rows = $run_query('SELECT pwd FROM `costumer`');
    foreach ($user_rows as $u) {
        if (password_get_info($u['pwd'])['algo'] === null) {
            $legacy_users++;
        }
    }
    if ($legacy_admins === 0 && $legacy_users === 0) {
        return ['status' => 'OK', 'message' => 'All admin and customer credentials hashed with strong algorithms.'];
    }
    return [
        'status' => 'WARN',
        'message' => "Detected unhashed legacy passwords: {$legacy_admins} admin(s), {$legacy_users} customer(s)."
    ];
});

// 9. Rate Limiting Table
$checks[] = run_check('Rate Limiting Registry', function() use ($run_query) {
    $login_attempts_check = $run_query("SHOW TABLES LIKE 'login_attempts'");
    return [
        'status' => !empty($login_attempts_check) ? 'OK' : 'FAIL',
        'message' => !empty($login_attempts_check) ? 'Active & Ready' : 'Table missing (H-05)'
    ];
});

// 10. Migrations Status
$m_rows = [];
$checks[] = run_check('Schema Migrations Status', function() use ($run_query, &$m_rows) {
    $mig_check = $run_query("SHOW TABLES LIKE 'schema_migrations'");
    $applied_count = 0;
    if (!empty($mig_check)) {
        $m_rows = $run_query('SELECT migration, applied_at FROM `schema_migrations` ORDER BY id ASC');
        $applied_count = count($m_rows);
    }
    $expected_count = 4;
    return [
        'status' => ($applied_count >= $expected_count) ? 'OK' : 'INFO',
        'message' => "{$applied_count} of {$expected_count} migrations recorded in schema_migrations"
    ];
});

// 11. Audit Logging Trail (Phase A Item 3)
$checks[] = run_check('Audit Logging System', function() use ($run_query) {
    $audit_check = $run_query("SHOW TABLES LIKE 'audit_log'");
    if (empty($audit_check)) {
        return ['status' => 'FAIL', 'message' => 'Table audit_log missing'];
    }
    $cnt = $run_query("SELECT COUNT(*) AS c FROM audit_log");
    $total_logs = (int)($cnt[0]['c'] ?? 0);
    return [
        'status' => 'OK',
        'message' => "Audit table active ({$total_logs} recorded events)"
    ];
});

$title = 'System Diagnostics & Health Check';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">System Diagnostics</h1>
        <p class="page-subtitle">Automated verification of database schema integrity, encryption, rate limits, and security controls.</p>
    </div>
    <div class="d-flex align-items-center">
        <form method="post" class="d-inline mr-2" onsubmit="return confirm('Execute all database schema migrations now?');">
            <?= csrf_field() ?>
            <button type="submit" name="run_migrations" value="1" class="btn btn-outline-warning btn-sm">
                Run Migrations
            </button>
        </form>
        <a href="" class="btn btn-outline-secondary btn-sm">Refresh</a>
    </div>
</div>

<?php if (!empty($migration_log)): ?>
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning text-dark font-weight-bold">
            Migration Execution Log
        </div>
        <div class="card-body p-0">
            <pre class="bg-dark text-light p-3 rounded mb-0" style="max-height: 250px; overflow-y: auto; font-size: 0.85rem;"><?= e($migration_log) ?></pre>
        </div>
    </div>
<?php endif; ?>

<div class="data-table-wrapper mb-4">
    <div class="table-header">
        <h5 class="mb-0">Integrity Matrix</h5>
        <span class="record-count"><?= count($checks) ?> checks performed</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th style="width: 30%;">Component / Integrity Target</th>
                    <th style="width: 15%;">Status</th>
                    <th>Diagnostic Telemetry</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checks as $c): ?>
                    <?php
                    $badge_class = 'secondary';
                    if ($c['status'] === 'OK') $badge_class = 'success';
                    elseif ($c['status'] === 'WARN') $badge_class = 'warning text-white';
                    elseif ($c['status'] === 'FAIL') $badge_class = 'danger';
                    elseif ($c['status'] === 'INFO') $badge_class = 'info';
                    ?>
                    <tr>
                        <td class="font-weight-medium text-dark"><?= e($c['name']) ?></td>
                        <td>
                            <span class="badge badge-<?= $badge_class ?> px-2 py-1">
                                <?= e($c['status']) ?>
                            </span>
                        </td>
                        <td><small class="text-muted"><?= e($c['message']) ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($m_rows)): ?>
    <div class="data-table-wrapper">
        <div class="table-header">
            <h5 class="mb-0">Applied Migrations History</h5>
            <span class="record-count"><?= count($m_rows) ?> applied</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Migration Script</th>
                        <th class="text-right">Execution Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($m_rows as $mr): ?>
                        <tr>
                            <td><code><?= e($mr['migration']) ?></code></td>
                            <td class="text-right text-muted small"><?= e($mr['applied_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>
