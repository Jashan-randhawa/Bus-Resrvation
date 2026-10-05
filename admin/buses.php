<?php
// admin/buses.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

// Detect primary key column for buses (supports both `id` and `sno` schemas)
$bus_pk = table_has_column($link, 'buses', 'sno') ? 'sno' : 'id';

$alert = null;
$alert_type = 'info';

// Handle Add Bus (O8, O12)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    csrf_verify();
    $busno = trim((string)($_POST['busno'] ?? ''));
    $capacity = (int)($_POST['capacity'] ?? 36);
    if ($capacity < 10 || $capacity > 60) {
        $capacity = 36;
    }

    $layout = trim((string)($_POST['layout'] ?? '2+2'));
    if (!in_array($layout, ['2+2', '2+1', '1+2', '1+1'], true)) {
        $layout = '2+2';
    }

    if ($busno === '') {
        $alert = 'Bus number is required.';
        $alert_type = 'danger';
    } else {
        $existing = db_one($link, 'SELECT * FROM buses WHERE bus_number = ?', 's', [$busno]);
        if ($existing) {
            $alert = 'A bus with that number already exists.';
            $alert_type = 'danger';
        } else {
            $has_cap = table_has_column($link, 'buses', 'capacity');
            $has_layout = table_has_column($link, 'buses', 'layout');
            if ($has_cap && $has_layout) {
                db_exec($link, 'INSERT INTO buses (bus_number, capacity, layout) VALUES (?, ?, ?)', 'sis', [$busno, $capacity, $layout]);
            } elseif ($has_cap) {
                db_exec($link, 'INSERT INTO buses (bus_number, capacity) VALUES (?, ?)', 'si', [$busno, $capacity]);
            } else {
                db_exec($link, 'INSERT INTO buses (bus_number) VALUES (?)', 's', [$busno]);
            }
            $alert = 'Bus added successfully.';
            $alert_type = 'success';
        }
    }
}

// Handle Delete Bus (O10: referential check before deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bus'])) {
    csrf_verify();
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        $bus_row = db_one($link, "SELECT bus_number FROM buses WHERE `{$bus_pk}` = ?", 'i', [$delete_id]);
        if (!$bus_row) {
            $alert = 'Bus not found.';
            $alert_type = 'danger';
        } else {
            $b_num = (string)$bus_row['bus_number'];
            $active_routes = db_one($link, 'SELECT COUNT(*) AS n FROM route WHERE busno = ?', 's', [$b_num]);
            $active_bookings = db_one($link, "SELECT COUNT(*) AS n FROM booking WHERE bus = ? AND (status IS NULL OR status != 'Cancelled')", 's', [$b_num]);

            if ((int)($active_routes['n'] ?? 0) > 0) {
                $alert = "Cannot delete bus '{$b_num}' because it is assigned to existing routes. Remove or reassign those routes first.";
                $alert_type = 'danger';
            } elseif ((int)($active_bookings['n'] ?? 0) > 0) {
                $alert = "Cannot delete bus '{$b_num}' because it has active passenger bookings.";
                $alert_type = 'danger';
            } else {
                db_exec($link, "DELETE FROM buses WHERE `{$bus_pk}` = ?", 'i', [$delete_id]);
                $alert = 'Bus deleted successfully.';
                $alert_type = 'success';
            }
        }
    }
}

$buses = db_all($link, 'SELECT * FROM buses ORDER BY ' . $bus_pk . ' ASC');

$title = 'Buses';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Fleet Management</h1>
        <p class="page-subtitle">Add, inspect, configure seating capacities, and maintain transit vehicles.</p>
    </div>
    <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addBusModal">
        + Register New Bus
    </button>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<div class="data-table-wrapper">
    <div class="table-header">
        <h5 class="mb-0">All Fleet Vehicles</h5>
        <span class="record-count"><?= count($buses) ?> bus(es)</span>
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
                                <div class="empty-title">No buses in fleet</div>
                                <div class="empty-text">Click '+ Register New Bus' above to add your first transit vehicle.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($buses as $row): ?>
                        <?php
                        $bid = (int)($row[$bus_pk] ?? $row['id'] ?? $row['sno'] ?? 0);
                        $cap = (int)($row['capacity'] ?? 36);
                        $lyt = (string)($row['layout'] ?? '2+2');
                        ?>
                        <tr>
                            <td><span class="text-muted small">#<?= e($bid) ?></span></td>
                            <td>
                                <strong class="text-dark" style="font-size: 0.95rem;"><?= e($row['bus_number'] ?? '') ?></strong>
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
                                <a href="<?= BASE_URL ?>/admin/edit/edit-bus.php?id=<?= e($bid) ?>" class="btn btn-outline-secondary btn-sm">
                                    Edit
                                </a>
                                <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this bus?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delete_id" value="<?= e($bid) ?>">
                                    <button type="submit" name="delete_bus" class="btn btn-outline-danger btn-sm ml-1">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Bus Modal -->
<div class="modal fade" id="addBusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold">Register New Bus</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="busno" class="font-weight-bold small text-muted">Bus Number / License Plate</label>
                        <input type="text" id="busno" name="busno" class="form-control" placeholder="e.g. DL-01-AB-1234" required />
                    </div>
                    <div class="form-group">
                        <label for="capacity" class="font-weight-bold small text-muted">Total Seating Capacity</label>
                        <input type="number" id="capacity" name="capacity" class="form-control" value="36" min="10" max="60" required />
                        <small class="form-text text-muted">Standard coaches seat between 20 and 52 passengers.</small>
                    </div>
                    <div class="form-group mb-4">
                        <label for="layout" class="font-weight-bold small text-muted">Seating Layout Pattern</label>
                        <select id="layout" name="layout" class="form-control" required>
                            <option value="2+2" selected>2+2 (Standard Coach -- 2 Left, 2 Right)</option>
                            <option value="2+1">2+1 (Executive Coach -- 2 Left, 1 Right)</option>
                            <option value="1+2">1+2 (Executive Coach -- 1 Left, 2 Right)</option>
                            <option value="1+1">1+1 (VIP / Luxury Sleeper)</option>
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