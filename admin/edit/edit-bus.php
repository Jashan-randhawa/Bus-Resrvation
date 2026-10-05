<?php
// admin/edit/edit-bus.php -- Edit Bus Details
require_once __DIR__ . '/../../includes/auth/admin-session.php';
require_once __DIR__ . '/../../includes/db_con.php';
require_once __DIR__ . '/../../includes/helpers.php';

$bus_pk = table_has_column($link, 'buses', 'sno') ? 'sno' : 'id';

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
        $dup = db_one($link, "SELECT `{$bus_pk}` FROM buses WHERE bus_number = ? AND `{$bus_pk}` != ?", 'si', [$busno, $id]);
        if ($dup) {
            $error = "Bus number '{$busno}' is already registered to another vehicle.";
        } else {
            $max_seat_row = db_one($link, "SELECT COALESCE(MAX(seat), 0) AS max_s FROM booking WHERE bus = ? AND (status IS NULL OR status IN ('Confirmed', 'Pending'))", 's', [$old_bus]);
            $max_booked_seat = (int)($max_seat_row['max_s'] ?? 0);
            if ($capacity < $max_booked_seat) {
                $error = "Cannot reduce capacity to {$capacity} seats because seat #{$max_booked_seat} is currently reserved.";
            } else {
                $has_cap = table_has_column($link, 'buses', 'capacity');

                mysqli_begin_transaction($link);
                try {
                    if ($has_cap) {
                        db_exec($link, "UPDATE buses SET bus_number = ?, capacity = ? WHERE `{$bus_pk}` = ?", 'sii', [$busno, $capacity, $id]);
                    } else {
                        db_exec($link, "UPDATE buses SET bus_number = ? WHERE `{$bus_pk}` = ?", 'si', [$busno, $id]);
                    }

                    if ($old_bus !== '' && $old_bus !== $busno) {
                        db_exec($link, 'UPDATE route SET busno = ? WHERE busno = ?', 'ss', [$busno, $old_bus]);
                        db_exec($link, 'UPDATE booking SET bus = ? WHERE bus = ?', 'ss', [$busno, $old_bus]);
                    }

                    mysqli_commit($link);
                    flash_set('success', 'Bus updated successfully.');
                    header('Location: ' . BASE_URL . '/admin/buses.php');
                    exit;
                } catch (Throwable $e) {
                    mysqli_rollback($link);
                    $error = ($link->errno === 1062) ? 'Bus number already in use.' : ('Update failed: ' . $e->getMessage());
                }
            }
        }
    }
}

$title = 'Edit Bus';
require_once __DIR__ . '/../../includes/layout/header-admin.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/buses.php">Buses</a></li>
        <li class="breadcrumb-item active" aria-current="page">Bus <?= e($row['bus_number'] ?? '') ?></li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Configure Vehicle Fleet</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="busno" class="font-weight-bold small text-muted">Bus Number / License</label>
                        <input type="text" id="busno" name="edit" value="<?= e($row['bus_number'] ?? '') ?>" class="form-control" required>
                    </div>
                    <div class="form-group mb-4">
                        <label for="capacity" class="font-weight-bold small text-muted">Total Seating Capacity</label>
                        <input type="number" id="capacity" name="capacity" value="<?= e((string)($row['capacity'] ?? 36)) ?>" min="10" max="60" class="form-control" required>
                        <small class="form-text text-muted">Configurable vehicle passenger limits.</small>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/admin/buses.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" name="subbtn" class="btn btn-primary px-4">Update Bus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout/footer-admin.php'; ?>