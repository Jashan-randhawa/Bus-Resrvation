<?php
// admin/edit/edit-route.php
require_once __DIR__ . '/../../includes/auth/admin-session.php';
require_once __DIR__ . '/../../includes/db_con.php';

// Detect primary key column for route table
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

// Handle Update before rendering HTML (O11, O12)
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
        // Check for schedule conflict with another route
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
            header('Location: ' . BASE_URL . '/admin/routes.php');
            exit;
        }
    }
}

$buses = db_all($link, 'SELECT bus_number FROM buses ORDER BY bus_number ASC');

require_once __DIR__ . '/../../includes/layout/header-edit.php';
?>
<section id="image">
    <div class="card-img-overlay">
        <div class="card col-lg-6 col-md-6 col-sm-6 col-xs-12 mt-5" style="margin-top: 6em; margin: auto;">
            <div class="card-body">
                <h4 class="card-title text-center text-success">Edit Route Details</h4>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="From">From :</label>
                        <input type="text" id="From" name="From" class="form-control" value="<?= e($row['city1'] ?? '') ?>" placeholder="From city" required />
                    </div>
                    <div class="form-group">
                        <label for="To">To :</label>
                        <input type="text" id="To" name="To" class="form-control" value="<?= e($row['city2'] ?? '') ?>" placeholder="To city" required />
                    </div>
                    <div class="form-group">
                        <label for="bus">Bus no :</label>
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
                        <label for="time">Departure Time :</label>
                        <input type="time" id="time" name="time" value="<?= e($row['time'] ?? '') ?>" class="form-control" required />
                    </div>
                    <div class="form-group">
                        <label for="price">Ticket Price :</label>
                        <input type="number" step="0.01" min="1" id="price" name="price" value="<?= e($row['price'] ?? '') ?>" class="form-control" required />
                    </div>
                    <div class="form-group">
                        <input type="submit" class="btn btn-success btn-block" value="Update Route" name="edit" />
                    </div>
                    <div class="text-center">
                        <a href="<?= BASE_URL ?>/admin/routes.php" class="btn btn-secondary btn-sm">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>