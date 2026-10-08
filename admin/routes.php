<?php
// admin/routes.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/admin-crud.php';

// Detect primary key column for route table
$route_pk = table_has_column($link, 'route', 'sno') ? 'sno' : 'id';

$alert = null;
$alert_type = 'info';

// Handle Add Route (O11, O12, Phase A Item 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    csrf_verify();
    require_role('super_admin', 'operator');
    $from = trim((string)($_POST['From'] ?? ''));
    $to = trim((string)($_POST['To'] ?? ''));
    $bus = trim((string)($_POST['bus'] ?? ''));
    $time = trim((string)($_POST['time'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);

    if ($from === '' || $to === '' || $bus === '' || $time === '' || $price <= 0) {
        $alert = 'Please fill all route fields with valid values.';
        $alert_type = 'danger';
    } elseif (strcasecmp($from, $to) === 0) {
        $alert = 'Origin and destination cities cannot be the same.';
        $alert_type = 'danger';
    } else {
        $has_archived_col = table_has_column($link, 'route', 'archived_at');
        $conflict_where = $has_archived_col ? " AND archived_at IS NULL" : "";
        // O11 & Issue 12: Check for route conflict on active schedules only
        $conflict = db_one($link, "SELECT * FROM route WHERE busno = ? AND `time` = ?{$conflict_where}", 'ss', [$bus, $time]);
        if ($conflict) {
            $alert = "Bus '{$bus}' is already scheduled to depart at {$time} ({$conflict['city1']} -> {$conflict['city2']}).";
            $alert_type = 'danger';
        } else {
            // Issue 13: Bus assignment validation (must exist and not be archived)
            $has_bus_arch = table_has_column($link, 'buses', 'archived_at');
            $bus_where = $has_bus_arch ? " AND archived_at IS NULL" : "";
            $bus_row = db_one($link, "SELECT id FROM buses WHERE bus_number = ?{$bus_where} LIMIT 1", 's', [$bus]);
            if (!$bus_row || empty($bus_row['id'])) {
                $alert = "The selected bus '{$bus}' does not exist or is inactive/archived.";
                $alert_type = 'danger';
            } else {
                $bus_id = (int)$bus_row['id'];
                $has_bus_id = table_has_column($link, 'route', 'bus_id');

                if ($has_bus_id) {
                    db_exec($link,
                        "INSERT INTO route (city1, city2, busno, time, price, bus_id) VALUES (?, ?, ?, ?, ?, ?)",
                        'ssssdi',
                        [$from, $to, $bus, $time, $price, $bus_id]
                    );
                } else {
                    db_exec($link,
                        "INSERT INTO route (city1, city2, busno, time, price) VALUES (?, ?, ?, ?, ?)",
                        'ssssd',
                        [$from, $to, $bus, $time, $price]
                    );
                }
                audit($link, 'CREATE', 'route', (int)mysqli_insert_id($link), null, ['city1' => $from, 'city2' => $to, 'busno' => $bus, 'time' => $time, 'price' => $price]);
                $alert = 'Route added successfully.';
                $alert_type = 'success';
            }
        }
    }
}

$has_archived_col = table_has_column($link, 'route', 'archived_at');

// Handle Delete/Archive Route (O10, Phase B Item 4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_route'])) {
    csrf_verify();
    require_role('super_admin');
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        $route_row = db_one($link, "SELECT * FROM route WHERE `{$route_pk}` = ?", 'i', [$delete_id]);
        if (!$route_row) {
            $alert = 'Route not found.';
            $alert_type = 'danger';
        } else {
            $r_bus = (string)$route_row['busno'];
            $r_time = (string)$route_row['time'];
            $today = date('Y-m-d');
            $active_bookings = db_one($link,
                "SELECT COUNT(*) AS n FROM booking WHERE (route_id = ? OR (bus = ? AND `time` = ?)) AND `date` >= ? AND (status IS NULL OR status NOT IN ('Cancelled', 'Expired'))",
                'isss', [$delete_id, $r_bus, $r_time, $today]
            );

            if ((int)($active_bookings['n'] ?? 0) > 0) {
                $alert = "Cannot archive route ({$route_row['city1']} -> {$route_row['city2']} at {$r_time}) because there are active upcoming bookings.";
                $alert_type = 'danger';
            } else {
                if ($has_archived_col) {
                    admin_archive_record($link, 'route', $route_pk, $delete_id, 'route', ['city1' => $route_row['city1'], 'city2' => $route_row['city2'], 'busno' => $r_bus, 'time' => $r_time]);
                    // soft-delete audit: UPDATE route SET archived_at = NOW()
                    $alert = 'Route archived successfully.';
                } else {
                    admin_archive_record($link, 'route', $route_pk, $delete_id, 'route', ['city1' => $route_row['city1'], 'city2' => $route_row['city2'], 'busno' => $r_bus, 'time' => $r_time]);
                    $alert = 'Route deleted successfully.';
                }
                $alert_type = 'success';
            }
        }
    }
}

// Handle Restore Route (Phase B Item 4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_route'])) {
    csrf_verify();
    require_role('super_admin');
    $restore_id = (int)($_POST['restore_id'] ?? 0);
    if ($restore_id > 0 && $has_archived_col) {
        $route_row = db_one($link, "SELECT * FROM route WHERE `{$route_pk}` = ?", 'i', [$restore_id]);
        if ($route_row) {
            admin_restore_record($link, 'route', $route_pk, $restore_id, 'route');
            // soft-restore audit: UPDATE route SET archived_at = NULL
            $alert = "Route schedule ({$route_row['city1']} -> {$route_row['city2']}) restored successfully.";
            $alert_type = 'success';
        }
    }
}

// Tab Filter: active vs archived
$view_tab = admin_get_archive_tab();
$where_archive = ($has_archived_col && $view_tab === 'archived') ? 'WHERE archived_at IS NOT NULL' : ($has_archived_col ? 'WHERE archived_at IS NULL' : '');

$buses = db_all($link, "SELECT bus_number FROM buses " . (table_has_column($link, 'buses', 'archived_at') ? "WHERE archived_at IS NULL" : "") . " ORDER BY bus_number ASC");
// 25-item Pagination (P-10)
$total_routes = (int)(db_one($link, "SELECT COUNT(*) AS c FROM route {$where_archive}")['c'] ?? 0);
$pagination = paginate($total_routes, 25);
$routes = db_all($link, "SELECT * FROM route {$where_archive} ORDER BY `{$route_pk}` ASC LIMIT ? OFFSET ?", 'ii', [$pagination['per_page'], $pagination['offset']]);

$title = 'Routes';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Transit Route Schedules</h1>
        <p class="page-subtitle">Configure origins, destinations, bus allocations, departures, and ticket tariffs.</p>
    </div>
    <?php if (can_write()): ?>
    <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addRouteModal">
        + Create Route Schedule
    </button>
    <?php endif; ?>
</div>

<?= render_admin_alert($alert, $alert_type) ?>

<?= $has_archived_col ? admin_archive_tabs_html($view_tab, 'Active Routes', 'Archived Schedules') : '' ?>


<div class="data-table-wrapper">
    <div class="table-header">
        <h5 class="mb-0"><?= $view_tab === 'archived' ? 'Archived Schedules' : 'Active Schedules' ?></h5>
        <span class="record-count"><?= $pagination['total_records'] ?> route(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th style="width: 80px;">#</th>
                    <th>Origin City</th>
                    <th>Destination City</th>
                    <th>Bus Assigned</th>
                    <th>Departure Time</th>
                    <th>Ticket Tariff</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($routes)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state py-5">
                                <div class="empty-icon">🗺️</div>
                                <div class="empty-title"><?= $view_tab === 'archived' ? 'No archived schedules' : 'No routes configured' ?></div>
                                <div class="empty-text"><?= $view_tab === 'archived' ? 'Schedules you archive will appear here.' : "Click '+ Create Route Schedule' above to connect travel cities." ?></div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($routes as $row): ?>
                        <?php 
                        $rid = (int)($row[$route_pk] ?? $row['sno'] ?? $row['id'] ?? 0);
                        $is_archived = !empty($row['archived_at']);
                        ?>
                        <tr class="<?= $is_archived ? 'text-muted bg-light' : '' ?>">
                            <td><span class="text-muted small">#<?= e($rid) ?></span></td>
                            <td class="font-weight-medium text-dark">
                                <?= e($row['city1'] ?? '') ?>
                                <?php if ($is_archived): ?>
                                    <span class="badge badge-secondary ml-1">Archived</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-weight-medium text-dark"><?= e($row['city2'] ?? '') ?></td>
                            <td><span class="badge badge-light border text-dark font-weight-bold">🚌 <?= e($row['busno'] ?? '') ?></span></td>
                            <td><?= e($row['time'] ?? '') ?></td>
                            <td class="font-weight-bold text-success h6 mb-0"><?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                            <td class="text-right">
                                <?= render_crud_action_buttons($rid, BASE_URL . "/admin/edit/edit-route.php?id=" . $rid, $is_archived, 'delete_route', 'restore_route', 'route schedule') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($pagination) ?>
</div>

<!-- Add Route Modal -->
<div class="modal fade" id="addRouteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold">Configure New Route</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="col-6 form-group">
                            <label for="From" class="font-weight-bold small text-muted">From City</label>
                            <input type="text" id="From" name="From" class="form-control" placeholder="Origin" required />
                        </div>
                        <div class="col-6 form-group">
                            <label for="To" class="font-weight-bold small text-muted">To City</label>
                            <input type="text" id="To" name="To" class="form-control" placeholder="Destination" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bus" class="font-weight-bold small text-muted">Assigned Fleet Bus</label>
                        <select name="bus" id="bus" class="form-control" required>
                            <option value="">Select Fleet Bus</option>
                            <?php foreach ($buses as $b): ?>
                                <option value="<?= e($b['bus_number']) ?>"><?= e($b['bus_number']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="col-6 form-group">
                            <label for="time" class="font-weight-bold small text-muted">Departure Time</label>
                            <input type="time" id="time" name="time" class="form-control" required />
                        </div>
                        <div class="col-6 form-group">
                            <label for="price" class="font-weight-bold small text-muted">Ticket Tariff (<?= CURRENCY ?>)</label>
                            <input type="number" step="0.01" min="1" id="price" name="price" class="form-control" placeholder="0.00" required />
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" class="btn btn-outline-secondary mr-2" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add">Save Route</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>