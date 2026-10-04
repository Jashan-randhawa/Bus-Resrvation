<?php
// admin/edit/edit-bus.php -- Edit Bus Details
require_once __DIR__ . '/../../includes/auth/admin-session.php';
require_once __DIR__ . '/../../includes/db_con.php';
require_once __DIR__ . '/../../includes/helpers.php';

$bus_pk = 'id';
$col_check = mysqli_query($link, "SHOW COLUMNS FROM `buses` LIKE 'sno'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
    $bus_pk = 'sno';
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/buses.php');
    exit;
}

$row = db_one($link, "SELECT * FROM buses WHERE `{$bus_pk}` = ?", 'i', [$id]);
if (!$row) {
    header('Location: ' . BASE_URL . '/admin/buses.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subbtn'])) {
    csrf_verify();
    $busno = trim((string)($_POST['edit'] ?? ''));
    $capacity = (int)($_POST['capacity'] ?? 36);
    if ($capacity < 10 || $capacity > 60) {
        $capacity = 36;
    }

    if ($busno === '') {
        $error = 'Bus number cannot be empty.';
    } else {
        $old_bus = (string)($row['bus_number'] ?? '');
        $cols = db_all($link, 'SHOW COLUMNS FROM buses');
        $has_cap = in_array('capacity', array_column($cols, 'Field'), true);

        if ($has_cap) {
            db_exec($link, "UPDATE buses SET bus_number = ?, capacity = ? WHERE `{$bus_pk}` = ?", 'sii', [$busno, $capacity, $id]);
        } else {
            db_exec($link, "UPDATE buses SET bus_number = ? WHERE `{$bus_pk}` = ?", 'si', [$busno, $id]);
        }

        if ($old_bus !== '' && $old_bus !== $busno) {
            db_exec($link, 'UPDATE route SET busno = ? WHERE busno = ?', 'ss', [$busno, $old_bus]);
            db_exec($link, 'UPDATE booking SET bus = ? WHERE bus = ?', 'ss', [$busno, $old_bus]);
        }

        flash_set('success', 'Bus updated successfully.');
        header('Location: ' . BASE_URL . '/admin/buses.php');
        exit;
    }
}

$title = 'Edit Bus';
require_once __DIR__ . '/../../includes/layout/header-admin.php';
?>
<div class="admin-content-wrap">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-white shadow-sm">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/buses.php">Buses</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Bus: <?= e($row['bus_number'] ?? '') ?></li>
        </ol>
    </nav>

    <div class="card shadow-sm col-lg-7 col-md-9 p-0 mx-auto mt-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Edit Bus Details</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <form action="" method="post">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="busno" class="font-weight-bold">Bus Number</label>
                    <input type="text" id="busno" name="edit" value="<?= e($row['bus_number'] ?? '') ?>" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="capacity" class="font-weight-bold">Total Capacity (Seats)</label>
                    <input type="number" id="capacity" name="capacity" value="<?= e((string)($row['capacity'] ?? 36)) ?>" min="10" max="60" class="form-control" required>
                    <small class="form-text text-muted">Configurable fleet capacity (default: 36 seats).</small>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <a href="<?= BASE_URL ?>/admin/buses.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="subbtn" class="btn btn-success">Update Bus</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout/footer-admin.php'; ?>