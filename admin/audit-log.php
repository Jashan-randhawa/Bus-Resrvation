<?php
// admin/audit-log.php -- Comprehensive Administrative Audit Trail (P-09)
header('Cache-Control: no-store, private');
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('super_admin');

$filter_action = trim((string)($_GET['action'] ?? ''));
$filter_entity = trim((string)($_GET['entity'] ?? ''));

$where_clauses = [];
$params = [];
$types = '';

if ($filter_action !== '' && in_array(strtoupper($filter_action), ['CREATE', 'UPDATE', 'DELETE', 'CANCEL'], true)) {
    $where_clauses[] = 'a.action = ?';
    $params[] = strtoupper($filter_action);
    $types .= 's';
}

if ($filter_entity !== '' && in_array(strtolower($filter_entity), ['bus', 'route', 'booking', 'customer', 'admin'], true)) {
    $where_clauses[] = 'a.entity_type = ?';
    $params[] = strtolower($filter_entity);
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

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
        <p class="page-subtitle">Immutable compliance record tracking administrative mutations, cancellations, and entity modifications.</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="audit-log.php" method="get" class="form-inline">
            <div class="form-group mr-3 mb-2 mb-sm-0">
                <label for="action" class="small text-muted font-weight-bold mr-2">Action:</label>
                <select name="action" id="action" class="form-control form-control-sm">
                    <option value="">All Actions</option>
                    <?php foreach (['CREATE', 'UPDATE', 'DELETE', 'CANCEL'] as $act): ?>
                        <option value="<?= $act ?>" <?= $filter_action === $act ? 'selected' : '' ?>><?= $act ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mr-3 mb-2 mb-sm-0">
                <label for="entity" class="small text-muted font-weight-bold mr-2">Entity:</label>
                <select name="entity" id="entity" class="form-control form-control-sm">
                    <option value="">All Entities</option>
                    <?php foreach (['bus', 'route', 'booking', 'customer', 'admin'] as $ent): ?>
                        <option value="<?= $ent ?>" <?= $filter_entity === $ent ? 'selected' : '' ?>><?= ucfirst($ent) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm mr-2">Filter</button>
            <?php if ($filter_action !== '' || $filter_entity !== ''): ?>
                <a href="audit-log.php" class="btn btn-outline-secondary btn-sm">Clear</a>
            <?php endif; ?>
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
                    <th style="width: 70px;">#</th>
                    <th>Timestamp</th>
                    <th>Administrator</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Change Details</th>
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
                        elseif ($act === 'UPDATE') $badge_class = 'primary';
                        elseif ($act === 'DELETE') $badge_class = 'danger';
                        elseif ($act === 'CANCEL') $badge_class = 'warning text-dark';

                        $details = [];
                        if (!empty($row['new_value'])) {
                            $decoded = json_decode($row['new_value'], true);
                            if (is_array($decoded)) {
                                foreach ($decoded as $k => $v) {
                                    $details[] = "<strong>" . e($k) . ":</strong> " . e(is_scalar($v) ? (string)$v : json_encode($v));
                                }
                            }
                        } elseif (!empty($row['old_value'])) {
                            $decoded = json_decode($row['old_value'], true);
                            if (is_array($decoded)) {
                                foreach ($decoded as $k => $v) {
                                    $details[] = "<span class='text-muted'><strong>" . e($k) . ":</strong> " . e(is_scalar($v) ? (string)$v : json_encode($v)) . "</span>";
                                }
                            }
                        }
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
                            <td style="max-width: 320px; font-size: 0.85rem;">
                                <?= !empty($details) ? implode(' | ', $details) : '<span class="text-muted">-</span>' ?>
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
    <?php
    $keep_filter = [];
    if ($filter_action !== '') $keep_filter['action'] = $filter_action;
    if ($filter_entity !== '') $keep_filter['entity'] = $filter_entity;
    ?>
    <?= render_pagination($pagination, $keep_filter) ?>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>
