<?php
// admin/buses.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/admin-crud.php';

// Detect primary key column for buses (supports both `id` and `sno` schemas)
$bus_pk = table_has_column($link, 'buses', 'sno') ? 'sno' : 'id';

// Handle Add Bus (O8, O12, Phase A Item 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    csrf_verify();
    require_role('super_admin', 'operator');
    $busno = trim((string)($_POST['busno'] ?? ''));
    $capacity_input = $_POST['capacity'] ?? null;
    $capacity = ($capacity_input !== null && $capacity_input !== '') ? (int)$capacity_input : 36;

    $layout = trim((string)($_POST['layout'] ?? '2+2'));
    if (!in_array($layout, ['2+2', '2+1', '1+2', '1+1'], true)) {
        $layout = '2+2';
    }

    if ($busno === '' || mb_strlen($busno) < 2 || mb_strlen($busno) > 50) {
        $_SESSION['form_old'] = ['busno' => $busno, 'capacity' => $capacity, 'layout' => $layout];
        flash_set('danger', 'Bus number must be between 2 and 50 characters.');
    } elseif ($capacity < 10 || $capacity > 60) {
        $_SESSION['form_old'] = ['busno' => $busno, 'capacity' => $capacity, 'layout' => $layout];
        flash_set('danger', 'Bus capacity must be between 10 and 60 seats.');
    } else {
        $existing = db_one($link, 'SELECT * FROM buses WHERE bus_number = ?', 's', [$busno]);
        if ($existing) {
            $_SESSION['form_old'] = ['busno' => $busno, 'capacity' => $capacity, 'layout' => $layout];
            flash_set('danger', 'A bus with that number already exists.');
        } else {
            unset($_SESSION['form_old']);
            $has_cap = table_has_column($link, 'buses', 'capacity');
            $has_layout = table_has_column($link, 'buses', 'layout');
            if ($has_cap && $has_layout) {
                db_exec($link, 'INSERT INTO buses (bus_number, capacity, layout) VALUES (?, ?, ?)', 'sis', [$busno, $capacity, $layout]);
            } elseif ($has_cap) {
                db_exec($link, 'INSERT INTO buses (bus_number, capacity) VALUES (?, ?)', 'si', [$busno, $capacity]);
            } else {
                db_exec($link, 'INSERT INTO buses (bus_number) VALUES (?)', 's', [$busno]);
            }
            audit($link, 'CREATE', 'bus', (int)mysqli_insert_id($link), null, ['bus_number' => $busno, 'capacity' => $capacity, 'layout' => $layout]);
            flash_set('success', 'Bus added successfully.');
        }
    }
    $redirect_url = BASE_URL . '/admin/buses.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

$has_archived_col = table_has_column($link, 'buses', 'archived_at');

// Handle Delete/Archive Bus (Item 4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bus'])) {
    csrf_verify();
    require_role('super_admin');
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        $bus_row = db_one($link, "SELECT bus_number FROM buses WHERE `{$bus_pk}` = ?", 'i', [$delete_id]);
        if (!$bus_row) {
            flash_set('danger', 'Bus not found.');
        } else {
            $b_num = (string)$bus_row['bus_number'];
            $active_routes = db_one($link, 'SELECT COUNT(*) AS n FROM route WHERE (busno = ? OR bus_id = ?) AND (archived_at IS NULL)', 'si', [$b_num, $delete_id]);
            $active_bookings = db_one($link, "SELECT COUNT(*) AS n FROM booking WHERE (bus = ? OR bus_id = ?) AND (status IS NULL OR status NOT IN ('Cancelled', 'Expired'))", 'si', [$b_num, $delete_id]);

            if ((int)($active_routes['n'] ?? 0) > 0) {
                flash_set('danger', "Cannot archive bus '{$b_num}' because it is assigned to active routes. Remove or reassign those routes first.");
            } elseif ((int)($active_bookings['n'] ?? 0) > 0) {
                flash_set('danger', "Cannot archive bus '{$b_num}' because it has active passenger bookings.");
            } else {
                if ($has_archived_col) {
                    admin_archive_record($link, 'buses', $bus_pk, $delete_id, 'bus', ['bus_number' => $b_num]);
                    flash_set('success', "Bus '{$b_num}' archived successfully.");
                } else {
                    admin_archive_record($link, 'buses', $bus_pk, $delete_id, 'bus', ['bus_number' => $b_num]);
                    flash_set('success', 'Bus deleted successfully.');
                }
            }
        }
    }
    $redirect_url = BASE_URL . '/admin/buses.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

// Handle Restore Bus (Item 4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_bus'])) {
    csrf_verify();
    require_role('super_admin');
    $restore_id = (int)($_POST['restore_id'] ?? 0);
    if ($restore_id > 0 && $has_archived_col) {
        $bus_row = db_one($link, "SELECT bus_number FROM buses WHERE `{$bus_pk}` = ?", 'i', [$restore_id]);
        if ($bus_row) {
            admin_restore_record($link, 'buses', $bus_pk, $restore_id, 'bus');
            flash_set('success', "Bus '{$bus_row['bus_number']}' restored to active fleet.");
        }
    }
    $redirect_url = BASE_URL . '/admin/buses.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

// Tab Filter: active vs archived
$view_tab = admin_get_archive_tab();
$where_archive = ($has_archived_col && $view_tab === 'archived') ? 'WHERE archived_at IS NOT NULL' : ($has_archived_col ? 'WHERE archived_at IS NULL' : '');

// 25-item Pagination (P-10)
$total_buses = (int)(db_one($link, "SELECT COUNT(*) AS c FROM buses {$where_archive}")['c'] ?? 0);
$pagination = paginate($total_buses, 25);
$buses = db_all($link, "SELECT * FROM buses {$where_archive} ORDER BY {$bus_pk} ASC LIMIT ? OFFSET ?", 'ii', [$pagination['per_page'], $pagination['offset']]);

$form_old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

$title = 'Buses';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Fleet Management</h1>
        <p class="page-subtitle">Add, inspect, configure seating capacities, and maintain transit vehicles.</p>
    </div>
    <?php if (can_write()): ?>
    <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addBusModal">
        + Register New Bus
    </button>
    <?php endif; ?>
</div>

<?= $has_archived_col ? admin_archive_tabs_html($view_tab, 'Active Fleet', 'Archived Buses') : '' ?>


<div class="data-table-wrapper">
    <div class="table-header">
        <h5 class="mb-0"><?= $view_tab === 'archived' ? 'Archived Fleet Vehicles' : 'Active Fleet Vehicles' ?></h5>
        <span class="record-count"><?= $pagination['total_records'] ?> bus(es)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th style="width: 80px;">#</th>
                    <th>Bus Identifier / License</th>
                    <th>Seating Capacity</th>
                    <th>Seating Layout</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($buses)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state py-5">
                                <div class="empty-icon">🚌</div>
                                <div class="empty-title"><?= $view_tab === 'archived' ? 'No archived buses' : 'No buses in fleet' ?></div>
                                <div class="empty-text"><?= $view_tab === 'archived' ? 'Vehicles you archive will appear here.' : "Click '+ Register New Bus' above to add your first transit vehicle." ?></div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($buses as $row): ?>
                        <?php
                        $bid = (int)($row[$bus_pk] ?? $row['id'] ?? $row['sno'] ?? 0);
                        $cap = (int)($row['capacity'] ?? 36);
                        $lyt = (string)($row['layout'] ?? '2+2');
                        $is_archived = !empty($row['archived_at']);
                        ?>
                        <tr class="<?= $is_archived ? 'text-muted bg-light' : '' ?>">
                            <td><span class="text-muted small">#<?= e($bid) ?></span></td>
                            <td>
                                <strong class="text-dark" style="font-size: 0.95rem;"><?= e($row['bus_number'] ?? '') ?></strong>
                                <?php if ($is_archived): ?>
                                    <span class="badge badge-secondary ml-1">Archived</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-light border text-dark font-weight-bold px-2 py-1">
                                    🪑 <?= $cap ?> Passenger Seats
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-info px-2 py-1 font-weight-bold">
                                    <?= e($lyt) ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <?= render_crud_action_buttons($bid, BASE_URL . "/admin/edit/edit-bus.php?id=" . $bid, $is_archived, 'delete_bus', 'restore_bus', 'bus') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($pagination) ?>
</div>

<!-- Add Bus Modal -->
<div class="modal fade" id="addBusModal" tabindex="-1" role="dialog" aria-labelledby="addBusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold" id="addBusModalLabel">Register New Bus</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="busno" class="font-weight-bold small text-muted">Bus Number / License Plate</label>
                        <input type="text" id="busno" name="busno" class="form-control" value="<?= e($form_old['busno'] ?? '') ?>" placeholder="e.g. DL-01-AB-1234" required />
                    </div>
                    <div class="form-group">
                        <label for="capacity" class="font-weight-bold small text-muted">Total Seating Capacity</label>
                        <input type="number" id="capacity" name="capacity" class="form-control" value="<?= e((string)($form_old['capacity'] ?? 36)) ?>" min="10" max="60" required />
                        <small class="form-text text-muted">Standard coaches seat between 10 and 60 passengers.</small>
                    </div>
                    <div class="form-group mb-4">
                        <label for="layout" class="font-weight-bold small text-muted">Seating Layout Pattern</label>
                        <select id="layout" name="layout" class="form-control" required>
                            <option value="2+2" <?= ($form_old['layout'] ?? '2+2') === '2+2' ? 'selected' : '' ?>>2+2 (Standard Coach -- 2 Left, 2 Right)</option>
                            <option value="2+1" <?= ($form_old['layout'] ?? '') === '2+1' ? 'selected' : '' ?>>2+1 (Executive Coach -- 2 Left, 1 Right)</option>
                            <option value="1+2" <?= ($form_old['layout'] ?? '') === '1+2' ? 'selected' : '' ?>>1+2 (Executive Coach -- 1 Left, 2 Right)</option>
                            <option value="1+1" <?= ($form_old['layout'] ?? '') === '1+1' ? 'selected' : '' ?>>1+1 (VIP / Luxury Sleeper)</option>
                        </select>
                        <small class="form-text text-muted">Defines seat column distribution around central aisle.</small>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-secondary mr-2" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add">Register Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>