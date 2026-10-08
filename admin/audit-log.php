<?php
// admin/audit-log.php -- Comprehensive Administrative Audit Trail (P-09)
header('Cache-Control: no-store, private');
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('super_admin');

$filter_action = trim((string)($_GET['action'] ?? ''));
$filter_entity = trim((string)($_GET['entity'] ?? ''));
$filter_from_date = trim((string)($_GET['from_date'] ?? ''));
$filter_to_date = trim((string)($_GET['to_date'] ?? ''));

$alert = null;
$alert_type = 'info';

$where_clauses = [];
$params = [];
$types = '';

$available_actions = ['CREATE', 'UPDATE', 'DELETE', 'CANCEL', 'LOGIN', 'LOGIN_FAILED', 'ROLE_CHANGE', 'EXPORT', 'RESTORE', 'DIAGNOSTICS_RUN', 'RUN_MIGRATIONS', 'OTHER'];
if ($filter_action !== '' && in_array(strtoupper($filter_action), $available_actions, true)) {
    $where_clauses[] = 'a.action = ?';
    $params[] = strtoupper($filter_action);
    $types .= 's';
}

$available_entities = ['bus', 'route', 'booking', 'customer', 'admin', 'query', 'system'];
if ($filter_entity !== '' && in_array(strtolower($filter_entity), $available_entities, true)) {
    $where_clauses[] = 'a.entity_type = ?';
    $params[] = strtolower($filter_entity);
    $types .= 's';
}

if ($filter_from_date !== '') {
    $where_clauses[] = 'DATE(a.timestamp) >= ?';
    $params[] = $filter_from_date;
    $types .= 's';
}

if ($filter_to_date !== '') {
    $where_clauses[] = 'DATE(a.timestamp) <= ?';
    $params[] = $filter_to_date;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$keep_filter = array_filter([
    'action'    => $filter_action !== '' ? $filter_action : null,
    'entity'    => $filter_entity !== '' ? $filter_entity : null,
    'from_date' => $filter_from_date !== '' ? $filter_from_date : null,
    'to_date'   => $filter_to_date !== '' ? $filter_to_date : null,
], fn($v) => $v !== null);

// CSV Export (Item 7)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_sql = "
        SELECT a.id, a.timestamp, adm.name AS admin_name, a.action, a.entity_type, a.entity_id, a.old_value, a.new_value, a.ip_address
        FROM audit_log a
        LEFT JOIN `admin` adm ON a.admin_id = adm.id
        {$where_sql}
        ORDER BY a.id DESC
    ";
    $export_rows = !empty($params) ? db_all($link, $export_sql, $types, $params) : db_all($link, $export_sql);
    $headers = ['Log ID', 'Timestamp', 'Admin Name', 'Action', 'Entity Type', 'Entity ID', 'Old Value (JSON)', 'New Value (JSON)', 'IP Address'];
    export_csv('audit-log-export-' . date('Ymd-His') . '.csv', $headers, $export_rows);
}

$count_sql = "SELECT COUNT(*) AS c FROM audit_log a {$where_sql}";
$count_res = !empty($params) ? db_one($link, $count_sql, $types, $params) : db_one($link, $count_sql);
$total_records = (int)($count_res['c'] ?? 0);

$pagination = paginate($total_records, 25);

$query_sql = "
    SELECT a.*, adm.name AS admin_name, adm.Email_id AS admin_email
    FROM audit_log a
    LEFT JOIN `admin` adm ON a.admin_id = adm.id
    {$where_sql}
    ORDER BY a.id DESC
    LIMIT ? OFFSET ?
";
$query_params = array_merge($params, [$pagination['per_page'], $pagination['offset']]);
$query_types = $types . 'ii';

$logs = db_all($link, $query_sql, $query_types, $query_params);

$title = 'Audit Log';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Administrative Audit Log</h1>
        <p class="page-subtitle">Append-only compliance audit trail tracking administrative mutations, cancellations, and entity modifications.</p>
    </div>
    <div class="d-flex align-items-center">
        <a href="?<?= http_build_query(array_merge($keep_filter, ['export' => 'csv'])) ?>" class="btn btn-outline-success btn-sm shadow-sm font-weight-bold">
            📥 Export CSV
        </a>
    </div>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="audit-log.php" method="get" class="form-row align-items-end">
            <div class="col-md-3 mb-2 mb-md-0">
                <label for="action" class="small text-muted font-weight-bold mb-1">Action:</label>
                <select name="action" id="action" class="form-control form-control-sm">
                    <option value="">All Actions</option>
                    <?php foreach ($available_actions as $act): ?>
                        <option value="<?= $act ?>" <?= $filter_action === $act ? 'selected' : '' ?>><?= $act ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-2 mb-md-0">
                <label for="entity" class="small text-muted font-weight-bold mb-1">Entity:</label>
                <select name="entity" id="entity" class="form-control form-control-sm">
                    <option value="">All Entities</option>
                    <?php foreach ($available_entities as $ent): ?>
                        <option value="<?= $ent ?>" <?= $filter_entity === $ent ? 'selected' : '' ?>><?= ucfirst($ent) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 mb-2 mb-md-0">
                <label for="from_date" class="small text-muted font-weight-bold mb-1">From Date:</label>
                <input type="date" name="from_date" id="from_date" class="form-control form-control-sm" value="<?= e($filter_from_date) ?>">
            </div>
            <div class="col-md-2 mb-2 mb-md-0">
                <label for="to_date" class="small text-muted font-weight-bold mb-1">To Date:</label>
                <input type="date" name="to_date" id="to_date" class="form-control form-control-sm" value="<?= e($filter_to_date) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <button type="submit" class="btn btn-primary btn-sm px-3 mr-2">Filter</button>
                <?php if (!empty($keep_filter)): ?>
                    <a href="audit-log.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="data-table-wrapper">
    <div class="table-header">
        <h5 class="mb-0">Logged Events</h5>
        <span class="record-count"><?= $pagination['total_records'] ?> event(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Timestamp</th>
                    <th>Administrator</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th style="width: 40%;">State Mutation & Diff</th>
                    <th>Origin IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state py-5">
                                <div class="empty-icon">🛡️</div>
                                <div class="empty-title">No audit records found</div>
                                <div class="empty-text">Administrative modifications and events will be recorded here automatically.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $row): ?>
                        <?php
                        $act = strtoupper((string)$row['action']);
                        $badge_class = 'secondary';
                        if ($act === 'CREATE') $badge_class = 'success';
                        elseif ($act === 'UPDATE' || $act === 'ROLE_CHANGE') $badge_class = 'primary';
                        elseif ($act === 'DELETE') $badge_class = 'danger';
                        elseif ($act === 'CANCEL') $badge_class = 'warning text-dark';
                        elseif ($act === 'RESTORE') $badge_class = 'info';

                        $old_arr = !empty($row['old_value']) ? json_decode($row['old_value'], true) : null;
                        $new_arr = !empty($row['new_value']) ? json_decode($row['new_value'], true) : null;
                        ?>
                        <tr>
                            <td><span class="text-muted small">#<?= e((string)$row['id']) ?></span></td>
                            <td>
                                <small class="text-muted font-weight-medium">
                                    <?= e(date('d M Y, H:i:s', strtotime($row['timestamp']))) ?>
                                </small>
                            </td>
                            <td>
                                <?php if (!empty($row['admin_name'])): ?>
                                    <span class="font-weight-medium text-dark"><?= e($row['admin_name']) ?></span>
                                    <br><small class="text-muted"><?= e($row['admin_email']) ?></small>
                                <?php elseif (!empty($row['admin_id'])): ?>
                                    <span class="text-muted">Admin #<?= e((string)$row['admin_id']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-light border">System / Automated</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= $badge_class ?> px-2 py-1"><?= e($act) ?></span></td>
                            <td>
                                <span class="badge badge-light border font-weight-bold">
                                    <?= e(ucfirst($row['entity_type'])) ?><?= !empty($row['entity_id']) ? ' #' . e((string)$row['entity_id']) : '' ?>
                                </span>
                            </td>
                            <td style="font-size: 0.82rem;">
                                <?php if ($old_arr && $new_arr): ?>
                                    <!-- Two-column visual diff (Item 9) -->
                                    <div class="row no-gutters border rounded p-1 bg-light">
                                        <div class="col-6 pr-1 border-right">
                                            <div class="text-danger font-weight-bold mb-1" style="font-size: 0.75rem;">BEFORE:</div>
                                            <?php foreach ($old_arr as $k => $v): ?>
                                                <?php $changed = isset($new_arr[$k]) && $new_arr[$k] !== $v; ?>
                                                <div class="<?= $changed ? 'bg-danger text-white px-1 rounded' : '' ?>">
                                                    <strong><?= e($k) ?>:</strong> <?= e(is_scalar($v) ? (string)$v : json_encode($v)) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="col-6 pl-1">
                                            <div class="text-success font-weight-bold mb-1" style="font-size: 0.75rem;">AFTER:</div>
                                            <?php foreach ($new_arr as $k => $v): ?>
                                                <?php $changed = isset($old_arr[$k]) && $old_arr[$k] !== $v; ?>
                                                <div class="<?= $changed ? 'bg-success text-white px-1 rounded' : '' ?>">
                                                    <strong><?= e($k) ?>:</strong> <?= e(is_scalar($v) ? (string)$v : json_encode($v)) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php elseif ($new_arr): ?>
                                    <div class="bg-light p-2 rounded border">
                                        <div class="text-success font-weight-bold mb-1" style="font-size: 0.75rem;">CREATED / APPLIED:</div>
                                        <?php foreach ($new_arr as $k => $v): ?>
                                            <div><strong><?= e($k) ?>:</strong> <?= e(is_scalar($v) ? (string)$v : json_encode($v)) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($old_arr): ?>
                                    <div class="bg-light p-2 rounded border">
                                        <div class="text-danger font-weight-bold mb-1" style="font-size: 0.75rem;">DELETED / REMOVED:</div>
                                        <?php foreach ($old_arr as $k => $v): ?>
                                            <div><strong><?= e($k) ?>:</strong> <?= e(is_scalar($v) ? (string)$v : json_encode($v)) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code><?= e($row['ip_address']) ?></code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($pagination, $keep_filter) ?>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>
