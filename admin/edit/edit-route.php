<?php
// admin/edit/edit-route.php -- Edit Route Details
require_once __DIR__ . '/../../includes/auth/admin-session.php';
require_once __DIR__ . '/../../includes/db_con.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_role('super_admin', 'operator');

$route_pk = table_has_column($link, 'route', 'sno') ? 'sno' : 'id';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/routes.php');
    exit;
}

$row = db_one($link, "SELECT * FROM route WHERE `{$route_pk}` = ?", 'i', [$id]);
if (!$row) {
    header('Location: ' . BASE_URL . '/admin/routes.php');
    exit;
}

$today = date('Y-m-d');
$r_bus = (string)$row['busno'];
$r_time = (string)$row['time'];
$has_archived_route = table_has_column($link, 'route', 'archived_at');
$has_bus_arch = table_has_column($link, 'buses', 'archived_at');

// Issue 14: Check for active upcoming bookings on this route
$future_bookings = db_one($link,
    "SELECT COUNT(*) AS n FROM booking 
     WHERE (route_id = ? OR (bus = ? AND `time` = ?)) 
       AND `date` >= ? 
       AND (status IS NULL OR status IN ('Confirmed', 'Pending'))",
    'isss', [$id, $r_bus, $r_time, $today]
);
$future_count = (int)($future_bookings['n'] ?? 0);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    csrf_verify();
    $from = trim((string)($_POST['From'] ?? ''));
    $to = trim((string)($_POST['To'] ?? ''));
    $bus = trim((string)($_POST['bus'] ?? ''));
    $time = trim((string)($_POST['time'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);

    $changing_schedule = (
        strcasecmp($from, (string)$row['city1']) !== 0 ||
        strcasecmp($to, (string)$row['city2']) !== 0 ||
        $bus !== (string)$row['busno'] ||
        $time !== (string)$row['time']
    );

    if ($from === '' || $to === '' || $bus === '' || $time === '' || $price <= 0) {
        $error = 'Please fill all route fields with valid values.';
    } elseif (strcasecmp($from, $to) === 0) {
        $error = 'Origin and destination cities cannot be the same.';
    } elseif ($changing_schedule && $future_count > 0) {
        // Issue 14: Block schedule modifications when upcoming active reservations exist
        $error = "Cannot modify route cities, assigned bus, or departure time because there are active upcoming bookings ({$future_count} reservation(s)) scheduled. Only ticket tariff may be updated.";
    } else {
        $conflict_where = $has_archived_route ? " AND archived_at IS NULL" : "";
        $conflict = db_one($link,
            "SELECT * FROM route WHERE busno = ? AND `time` = ? AND `{$route_pk}` != ?{$conflict_where}",
            'ssi', [$bus, $time, $id]
        );
        if ($conflict) {
            $error = "Bus '{$bus}' is already scheduled to depart at {$time} on another route ({$conflict['city1']} -> {$conflict['city2']}).";
        } else {
            // Issue 13: Bus assignment validation (must exist and not be archived)
            $bus_where = $has_bus_arch ? " AND archived_at IS NULL" : "";
            $bus_row = db_one($link, "SELECT id FROM buses WHERE bus_number = ?{$bus_where} LIMIT 1", 's', [$bus]);
            if (!$bus_row || empty($bus_row['id'])) {
                $error = "The selected bus '{$bus}' does not exist or is inactive/archived.";
            } else {
                $bus_id = (int)$bus_row['id'];
                $has_bus_id = table_has_column($link, 'route', 'bus_id');

                if ($has_bus_id) {
                    db_exec($link,
                        "UPDATE route SET busno = ?, city1 = ?, city2 = ?, time = ?, price = ?, bus_id = ? WHERE `{$route_pk}` = ?",
                        'ssssdii',
                        [$bus, $from, $to, $time, $price, $bus_id, $id]
                    );
                } else {
                    db_exec($link,
                        "UPDATE route SET busno = ?, city1 = ?, city2 = ?, time = ?, price = ? WHERE `{$route_pk}` = ?",
                        'ssssdi',
                        [$bus, $from, $to, $time, $price, $id]
                    );
                }
                audit($link, 'UPDATE', 'route', $id, ['busno' => $row['busno'], 'price' => $row['price']], ['busno' => $bus, 'city1' => $from, 'city2' => $to, 'time' => $time, 'price' => $price]);
                flash_set('success', 'Route updated successfully.');
                header('Location: ' . BASE_URL . '/admin/routes.php');
                exit;
            }
        }
    }
}

$buses = db_all($link, "SELECT bus_number FROM buses " . ($has_bus_arch ? "WHERE archived_at IS NULL" : "") . " ORDER BY bus_number ASC");

$title = 'Edit Route';
require_once __DIR__ . '/../../includes/layout/header-admin.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/routes.php">Routes</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($row['city1'] ?? '') ?> &rarr; <?= e($row['city2'] ?? '') ?></li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Configure Route Schedule</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($future_count > 0): ?>
                    <div class="alert alert-warning mb-4">
                        <strong>⚠️ Schedule Locked:</strong> There are <?= $future_count ?> active upcoming booking(s) scheduled on this route. Origin, destination, bus, and departure time are locked to protect existing passenger tickets. Only ticket tariff may be updated.
                    </div>
                <?php endif; ?>

                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="col-6 form-group">
                            <label for="From" class="font-weight-bold small text-muted">Origin City</label>
                            <input type="text" id="From" name="From" class="form-control" value="<?= e($row['city1'] ?? '') ?>" <?= $future_count > 0 ? 'readonly' : '' ?> required />
                        </div>
                        <div class="col-6 form-group">
                            <label for="To" class="font-weight-bold small text-muted">Destination City</label>
                            <input type="text" id="To" name="To" class="form-control" value="<?= e($row['city2'] ?? '') ?>" <?= $future_count > 0 ? 'readonly' : '' ?> required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bus" class="font-weight-bold small text-muted">Assigned Fleet Bus</label>
                        <?php if ($future_count > 0): ?>
                            <input type="hidden" name="bus" value="<?= e($row['busno'] ?? '') ?>" />
                            <input type="text" class="form-control" value="<?= e($row['busno'] ?? '') ?>" readonly disabled />
                        <?php else: ?>
                            <select name="bus" id="bus" class="form-control" required>
                                <option value="">Select Bus Number</option>
                                <?php foreach ($buses as $b): ?>
                                    <option value="<?= e($b['bus_number']) ?>" <?= ($row['busno'] ?? '') === $b['bus_number'] ? 'selected' : '' ?>>
                                        <?= e($b['bus_number']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="form-row">
                        <div class="col-6 form-group">
                            <label for="time" class="font-weight-bold small text-muted">Departure Time</label>
                            <input type="time" id="time" name="time" value="<?= e($row['time'] ?? '') ?>" class="form-control" <?= $future_count > 0 ? 'readonly' : '' ?> required />
                        </div>
                        <div class="col-6 form-group">
                            <label for="price" class="font-weight-bold small text-muted">Ticket Tariff (<?= CURRENCY ?>)</label>
                            <input type="number" step="0.01" min="1" id="price" name="price" value="<?= e($row['price'] ?? '') ?>" class="form-control" required />
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                        <a href="<?= BASE_URL ?>/admin/routes.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" name="edit" class="btn btn-primary px-4">Update Route</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout/footer-admin.php'; ?>