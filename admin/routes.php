<?php
// admin/routes.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

// Detect primary key column for route table
$route_pk = table_has_column($link, 'route', 'sno') ? 'sno' : 'id';

$alert = null;
$alert_type = 'info';

// Handle Add Route (O11, O12)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    csrf_verify();
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
        // O11: Check for route conflict (same bus assigned at same departure time)
        $conflict = db_one($link, 'SELECT * FROM route WHERE busno = ? AND `time` = ?', 'ss', [$bus, $time]);
        if ($conflict) {
            $alert = "Bus '{$bus}' is already scheduled to depart at {$time} ({$conflict['city1']} -> {$conflict['city2']}).";
            $alert_type = 'danger';
        } else {
            db_exec($link,
                "INSERT INTO route (city1, city2, busno, time, price) VALUES (?, ?, ?, ?, ?)",
                'ssssd',
                [$from, $to, $bus, $time, $price]
            );
            $alert = 'Route added successfully.';
            $alert_type = 'success';
        }
    }
}

// Handle Delete Route (O10: verify active bookings before deleting route)
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
                "SELECT COUNT(*) AS n FROM booking WHERE bus = ? AND `time` = ? AND `date` >= ? AND (status IS NULL OR status != 'Cancelled')",
                'sss', [$r_bus, $r_time, $today]
            );

            if ((int)($active_bookings['n'] ?? 0) > 0) {
                $alert = "Cannot delete route ({$route_row['city1']} -> {$route_row['city2']} at {$r_time}) because there are active upcoming bookings.";
                $alert_type = 'danger';
            } else {
                db_exec($link, "DELETE FROM route WHERE `{$route_pk}` = ?", 'i', [$delete_id]);
                $alert = 'Route deleted successfully.';
                $alert_type = 'success';
            }
        }
    }
}

$buses = db_all($link, 'SELECT bus_number FROM buses ORDER BY bus_number ASC');
$routes = db_all($link, "SELECT * FROM route ORDER BY `{$route_pk}` ASC");

$title = 'Routes';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Transit Route Schedules</h1>
        <p class="page-subtitle">Configure origins, destinations, bus allocations, departures, and ticket tariffs.</p>
    </div>
    <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addRouteModal">
        + Create Route Schedule
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
        <h5 class="mb-0">Active Schedules</h5>
        <span class="record-count"><?= count($routes) ?> route(s)</span>
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
                                <div class="empty-title">No routes configured</div>
                                <div class="empty-text">Click '+ Create Route Schedule' above to connect travel cities.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($routes as $row): ?>
                        <?php $rid = (int)($row[$route_pk] ?? $row['sno'] ?? $row['id'] ?? 0); ?>
                        <tr>
                            <td><span class="text-muted small">#<?= e($rid) ?></span></td>
                            <td class="font-weight-medium text-dark"><?= e($row['city1'] ?? '') ?></td>
                            <td class="font-weight-medium text-dark"><?= e($row['city2'] ?? '') ?></td>
                            <td><span class="badge badge-light border text-dark font-weight-bold">🚌 <?= e($row['busno'] ?? '') ?></span></td>
                            <td><?= e($row['time'] ?? '') ?></td>
                            <td class="font-weight-bold text-success h6 mb-0"><?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                            <td class="text-right">
                                <a href="<?= BASE_URL ?>/admin/edit/edit-route.php?id=<?= e($rid) ?>" class="btn btn-outline-secondary btn-sm">
                                    Edit
                                </a>
                                <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this route?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delete_id" value="<?= e($rid) ?>">
                                    <button type="submit" name="delete_route" class="btn btn-outline-danger btn-sm ml-1">
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