<?php
// admin/edit/edit-booking.php
require_once __DIR__ . '/../../includes/auth/admin-session.php';
require_once __DIR__ . '/../../includes/db_con.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/bookings.php');
    exit;
}

$error = null;

// Handle Update before rendering HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    csrf_verify();
    $name = trim((string)($_POST['unm'] ?? ''));
    $from = trim((string)($_POST['from'] ?? ''));
    $to = trim((string)($_POST['to'] ?? ''));
    $num = trim((string)($_POST['num'] ?? ''));
    $amount = (float)($_POST['amount'] ?? 0);

    if ($name === '' || $from === '' || $to === '' || $num === '' || $amount <= 0) {
        $error = 'Please fill all fields with valid information.';
    } else {
        db_exec($link,
            "UPDATE booking SET name = ?, city1 = ?, city2 = ?, contact = ?, price = ? WHERE sno = ?",
            'ssssdi',
            [$name, $from, $to, $num, $amount, $id]
        );
        header('Location: ' . BASE_URL . '/admin/bookings.php');
        exit;
    }
}

$row = db_one($link, "SELECT * FROM booking WHERE sno = ?", 'i', [$id]);
if (!$row) {
    header('Location: ' . BASE_URL . '/admin/bookings.php');
    exit;
}

require_once __DIR__ . '/../../includes/layout/header-edit.php';
?>
<section id="image">
    <div class="card-img-overlay">
        <div class="card col-lg-6 col-md-6 col-sm-6 col-xs-12 mt-5" style="margin-top: 6em; margin: auto;">
            <div class="card-body">
                <h4 class="card-title text-center text-success">Edit Booking Details</h4>
                <p class="text-center text-muted">
                    PNR: <strong><?= e($row['pnr'] ?? $row['sno']) ?></strong> | Bus: <strong><?= e($row['bus'] ?? '') ?></strong> | Seat: <strong>#<?= e($row['seat'] ?? '') ?></strong>
                </p>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="unm">Customer Name :</label>
                        <input type="text" id="unm" name="unm" class="form-control" value="<?= e($row['name'] ?? '') ?>" placeholder="Enter Customer Name" required />
                    </div>
                    <div class="form-group">
                        <label for="num">Contact Number:</label>
                        <input type="tel" id="num" name="num" class="form-control" value="<?= e($row['contact'] ?? '') ?>" placeholder="Enter Contact Number" required />
                    </div>
                    <div class="form-group">
                        <label for="from">From :</label>
                        <input type="text" id="from" name="from" value="<?= e($row['city1'] ?? '') ?>" class="form-control" placeholder="From City" required />
                    </div>
                    <div class="form-group">
                        <label for="to">To :</label>
                        <input type="text" id="to" name="to" value="<?= e($row['city2'] ?? '') ?>" class="form-control" placeholder="To City" required />
                    </div>
                    <div class="form-group">
                        <label for="amount">Total Amount ($):</label>
                        <input type="number" step="0.01" min="1" name="amount" value="<?= e($row['price'] ?? '') ?>" class="form-control" id="amount" placeholder="Enter Amount" required>
                    </div>
                    <div class="form-group">
                        <input type="submit" class="btn btn-success btn-block" name="edit" value="Update Booking" />
                    </div>
                    <div class="text-center">
                        <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-secondary btn-sm">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>