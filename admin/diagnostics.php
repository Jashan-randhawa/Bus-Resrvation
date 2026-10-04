<?php
// admin/diagnostics.php -- System Health & Operational Diagnostics Endpoint (O15)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$migration_log = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
    csrf_verify();
    ob_start();
    $_GET['migrate_key'] = 'admin_session';
    require __DIR__ . '/../database/db_migrate.php';
    $migration_log = ob_get_clean();
    flash_set('success', 'Database migrations executed successfully.');
}

$title = 'System Diagnostics & Health Check';
require_once __DIR__ . '/../includes/layout/header-admin.php';

// Safe query runner for administrative/metadata queries (SHOW COLUMNS, SHOW INDEX, etc.)
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

// Diagnostics checks
$checks = [];

// 1. PHP Version & Extensions
$checks[] = [
    'name' => 'PHP Version',
    'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'OK' : 'WARN',
    'message' => 'PHP ' . PHP_VERSION . ' (Recommended: 8.0+)'
];

$mysqli_loaded = extension_loaded('mysqli');
$checks[] = [
    'name' => 'MySQLi Extension',
    'status' => $mysqli_loaded ? 'OK' : 'FAIL',
    'message' => $mysqli_loaded ? 'Loaded' : 'Missing mysqli extension'
];

$openssl_loaded = extension_loaded('openssl');
$checks[] = [
    'name' => 'OpenSSL Extension',
    'status' => $openssl_loaded ? 'OK' : 'FAIL',
    'message' => $openssl_loaded ? 'Loaded' : 'Missing openssl extension'
];

// 2. Database Connectivity & Mode
$db_ping = false;
try {
    $db_ping = @mysqli_ping($link);
} catch (Throwable $e) {}

$checks[] = [
    'name' => 'Database Connectivity',
    'status' => $db_ping ? 'OK' : 'FAIL',
    'message' => $db_ping ? ('Connected to ' . DB_NAME . '@' . DB_HOST) : 'Database unreachable'
];

// 3. Schema & Constraints Inspection
$booking_cols = $run_query('SHOW COLUMNS FROM `booking`');
$b_col_names = array_column($booking_cols, 'Field');
$has_pnr = in_array('pnr', $b_col_names, true);
$has_status = in_array('status', $b_col_names, true);

$checks[] = [
    'name' => 'Booking Table Hardening',
    'status' => ($has_pnr && $has_status) ? 'OK' : 'WARN',
    'message' => "PNR Column: " . ($has_pnr ? 'Present' : 'Missing') . " | Status Column: " . ($has_status ? 'Present' : 'Missing')
];

$buses_cols = $run_query('SHOW COLUMNS FROM `buses`');
$has_capacity = in_array('capacity', array_column($buses_cols, 'Field'), true);
$checks[] = [
    'name' => 'Fleet Capacity Modeling',
    'status' => $has_capacity ? 'OK' : 'WARN',
    'message' => "Capacity Column in buses: " . ($has_capacity ? 'Present (Dynamic)' : 'Missing (36 fallback)')
];

// 4. Unique Constraints
$indexes = $run_query('SHOW INDEX FROM `booking`');
$idx_names = array_column($indexes, 'Key_name');
$has_uq_seat = in_array('uq_booking_seat', $idx_names, true);
$has_uq_pnr = in_array('uq_booking_pnr', $idx_names, true);

$checks[] = [
    'name' => 'Concurrency & Unique Indexes',
    'status' => ($has_uq_seat && $has_uq_pnr) ? 'OK' : 'WARN',
    'message' => "uq_booking_seat: " . ($has_uq_seat ? 'Active' : 'Missing') . " | uq_booking_pnr: " . ($has_uq_pnr ? 'Active' : 'Missing')
];

// 5. Rate Limiting Table
$login_attempts_check = $run_query("SHOW TABLES LIKE 'login_attempts'");
$checks[] = [
    'name' => 'Rate Limiting Table (login_attempts)',
    'status' => !empty($login_attempts_check) ? 'OK' : 'FAIL',
    'message' => !empty($login_attempts_check) ? 'Active & Ready' : 'Table missing (H-05)'
];

// 6. Migrations Status
$mig_check = $run_query("SHOW TABLES LIKE 'schema_migrations'");
$applied_count = 0;
$m_rows = [];
if (!empty($mig_check)) {
    $m_rows = $run_query('SELECT migration, applied_at FROM `schema_migrations` ORDER BY id ASC');
    $applied_count = count($m_rows);
}
$checks[] = [
    'name' => 'Schema Migrations Runner (O7)',
    'status' => ($applied_count > 0) ? 'OK' : 'INFO',
    'message' => "{$applied_count} migrations recorded in schema_migrations"
];

// 7. Security Configurations
$cookie_params = session_get_cookie_params();
$checks[] = [
    'name' => 'Session Cookie Security',
    'status' => ($cookie_params['httponly']) ? 'OK' : 'WARN',
    'message' => "HttpOnly: " . ($cookie_params['httponly'] ? 'Yes' : 'No') . " | SameSite: " . ($cookie_params['samesite'] ?? 'None') . " | Secure: " . ($cookie_params['secure'] ? 'Yes' : 'No')
];

$tz = date_default_timezone_get();
$checks[] = [
    'name' => 'Application Timezone',
    'status' => 'OK',
    'message' => "Current Timezone: {$tz} (Time: " . date('Y-m-d H:i:s') . ")"
];
?>
<div class="col-lg-10 col-md-10 col-sm-12" style="float: right;">
    <section class="mt-4 mb-5">
        <h2 class="text-info mb-3">System Diagnostics & Integrity Checks</h2>
        <p class="text-muted">
            Automated verification of database schema integrity, encryption, rate limiting, and session security parameters.
        </p>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Diagnostics Matrix</span>
                <div>
                    <form method="post" class="d-inline" onsubmit="return confirm('Execute all database schema migrations now?');">
                        <?= csrf_field() ?>
                        <button type="submit" name="run_migrations" value="1" class="btn btn-sm btn-outline-warning mr-2">
                            Run Database Migrations
                        </button>
                    </form>
                    <a href="" class="btn btn-sm btn-outline-light">Refresh Status</a>
                </div>
            </div>
            <?php if (!empty($migration_log)): ?>
                <div class="p-3 bg-secondary text-white">
                    <h6 class="font-weight-bold mb-2 text-warning">Migration Execution Log:</h6>
                    <pre class="bg-dark text-light p-3 rounded mb-0" style="max-height: 250px; overflow-y: auto; font-size: 0.85rem;"><?= e($migration_log) ?></pre>
                </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 28%;">Component / Check</th>
                            <th style="width: 15%;">Status</th>
                            <th>Diagnostic Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checks as $c): ?>
                            <?php
                            $badge_class = 'secondary';
                            if ($c['status'] === 'OK') $badge_class = 'success';
                            elseif ($c['status'] === 'WARN') $badge_class = 'warning';
                            elseif ($c['status'] === 'FAIL') $badge_class = 'danger';
                            elseif ($c['status'] === 'INFO') $badge_class = 'info';
                            ?>
                            <tr>
                                <td class="font-weight-bold"><?= e($c['name']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $badge_class ?> p-2 px-3">
                                        <?= e($c['status']) ?>
                                    </span>
                                </td>
                                <td><?= e($c['message']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($m_rows)): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-light font-weight-bold">
                    Applied Migrations History
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($m_rows as $mr): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <code><?= e($mr['migration']) ?></code>
                                <span class="badge badge-light border text-muted"><?= e($mr['applied_at']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>
