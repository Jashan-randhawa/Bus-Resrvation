<?php
// admin/edit/edit-route.php -- Edit Route Details
require_once __DIR__ . '/../../includes/auth/admin-session.php';
require_once __DIR__ . '/../../includes/db_con.php';
require_once __DIR__ . '/../../includes/helpers.php';

$route_pk = 'sno';
$col_check = mysqli_query($link, "SHOW COLUMNS FROM `route` LIKE 'sno'");
if (!$col_check || mysqli_num_rows($col_check) === 0) {
    $route_pk = 'id';
}

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
            db_exec($link,
                "UPDATE route SET busno = ?, city1 = ?, city2 = ?, time = ?, price = ? WHERE `{$route_pk}` = ?",
                'ssssdi',
                [$bus, $from, $to, $time, $price, $id]
            );
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
<div class="admin-content-wrap">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-white shadow-sm">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/routes.php">Routes</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Route: <?= e($row['city1'] ?? '') ?> &rarr; <?= e($row['city2'] ?? '') ?></li>
        </ol>
    </nav>

    <div class="card shadow-sm col-lg-7 col-md-9 p-0 mx-auto mt-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Edit Route Details</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <form action="" method="post">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="From" class="font-weight-bold">From City :</label>
                    <input type="text" id="From" name="From" class="form-control" value="<?= e($row['city1'] ?? '') ?>" placeholder="From city" required />
                </div>
                <div class="form-group">
                    <label for="To" class="font-weight-bold">To City :</label>
                    <input type="text" id="To" name="To" class="form-control" value="<?= e($row['city2'] ?? '') ?>" placeholder="To city" required />
                </div>
                <div class="form-group">
                    <label for="bus" class="font-weight-bold">Assigned Bus :</label>
                    <select name="bus" id="bus" class="form-control" required>
                        <option value="">Select Bus Number</option>
                        <?php foreach ($buses as $b): ?>
                            <option value="<?= e($b['bus_number']) ?>" <?= ($row['busno'] ?? '') === $b['bus_number'] ? 'selected' : '' ?>>
                                <?= e($b['bus_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="time" class="font-weight-bold">Departure Time :</label>
                    <input type="time" id="time" name="time" value="<?= e($row['time'] ?? '') ?>" class="form-control" required />
                </div>
                <div class="form-group">
                    <label for="price" class="font-weight-bold">Ticket Price :</label>
                    <input type="number" step="0.01" min="1" id="price" name="price" value="<?= e($row['price'] ?? '') ?>" class="form-control" required />
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <a href="<?= BASE_URL ?>/admin/routes.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="edit" class="btn btn-success">Update Route</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout/footer-admin.php'; ?>