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

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    csrf_verify();
    $from = trim((string)($_POST['From'] ?? ''));
    $to = trim((string)($_POST['To'] ?? ''));
    $bus = trim((string)($_POST['bus'] ?? ''));
    $time = trim((string)($_POST['time'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);

    if ($from === '' || $to === '' || $bus === '' || $time === '' || $price <= 0) {
        $error = 'Please fill all route fields with valid values.';
    } elseif (strcasecmp($from, $to) === 0) {
        $error = 'Origin and destination cities cannot be the same.';
    } else {
        $conflict = db_one($link,
            "SELECT * FROM route WHERE busno = ? AND `time` = ? AND `{$route_pk}` != ?",
            'ssi', [$bus, $time, $id]
        );
        if ($conflict) {
            $error = "Bus '{$bus}' is already scheduled to depart at {$time} on another route ({$conflict['city1']} -> {$conflict['city2']}).";
        } else {
            $has_bus_id = table_has_column($link, 'route', 'bus_id');
            $bus_row = db_one($link, 'SELECT id FROM buses WHERE bus_number = ? LIMIT 1', 's', [$bus]);
            $bus_id = $bus_row ? (int)$bus_row['id'] : null;

            if ($has_bus_id && $bus_id !== null) {
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

$buses = db_all($link, 'SELECT bus_number FROM buses ORDER BY bus_number ASC');

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

                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="col-6 form-group">
                            <label for="From" class="font-weight-bold small text-muted">Origin City</label>
                            <input type="text" id="From" name="From" class="form-control" value="<?= e($row['city1'] ?? '') ?>" required />
                        </div>
                        <div class="col-6 form-group">
                            <label for="To" class="font-weight-bold small text-muted">Destination City</label>
                            <input type="text" id="To" name="To" class="form-control" value="<?= e($row['city2'] ?? '') ?>" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bus" class="font-weight-bold small text-muted">Assigned Fleet Bus</label>
                        <select name="bus" id="bus" class="form-control" required>
                            <option value="">Select Bus Number</option>
                            <?php foreach ($buses as $b): ?>
                                <option value="<?= e($b['bus_number']) ?>" <?= ($row['busno'] ?? '') === $b['bus_number'] ? 'selected' : '' ?>>
                                    <?= e($b['bus_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="col-6 form-group">
                            <label for="time" class="font-weight-bold small text-muted">Departure Time</label>
                            <input type="time" id="time" name="time" value="<?= e($row['time'] ?? '') ?>" class="form-control" required />
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