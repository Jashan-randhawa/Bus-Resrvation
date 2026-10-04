<?php
// admin/edit/edit-bus.php
require_once __DIR__ . '/../../includes/auth/admin-session.php';
require_once __DIR__ . '/../../includes/db_con.php';

// Detect primary key column for buses (supports both `id` and `sno` schemas)
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

$error = null;

// Handle Update before rendering HTML (avoids "headers already sent" on redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subbtn'])) {
    csrf_verify();
    $busno = trim((string)($_POST['edit'] ?? ''));
    if ($busno === '') {
        $error = 'Bus number cannot be empty.';
    } else {
        db_exec($link, "UPDATE buses SET bus_number = ? WHERE `{$bus_pk}` = ?", 'si', [$busno, $id]);
        header('Location: ' . BASE_URL . '/admin/buses.php');
        exit;
    }
}

$row = db_one($link, "SELECT * FROM buses WHERE `{$bus_pk}` = ?", 'i', [$id]);
if (!$row) {
    header('Location: ' . BASE_URL . '/admin/buses.php');
    exit;
}

require_once __DIR__ . '/../../includes/layout/header-edit.php';
?>
<section id="image">
    <div class="card-img-overlay">
        <div class="card col-lg-6 col-md-6 col-sm-6 col-xs-12 mt-5" style="margin-top: 6em; margin: auto;">
            <div class="card-body">
                <h5 class="card-title text-center text-success">Edit Bus Details</h5>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="busno">Bus Number</label>
                        <input type="text" id="busno" name="edit" value="<?= e($row['bus_number'] ?? '') ?>" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <input type="submit" name="subbtn" value="Update Bus" class="btn btn-success btn-block">
                    </div>
                    <div class="text-center">
                        <a href="<?= BASE_URL ?>/admin/buses.php" class="btn btn-secondary btn-sm">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>