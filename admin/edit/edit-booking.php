<?php
// admin/edit/edit-booking.php -- Edit Booking Details
require_once __DIR__ . '/../../includes/auth/admin-session.php';
require_once __DIR__ . '/../../includes/db_con.php';
require_once __DIR__ . '/../../includes/helpers.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/bookings.php');
    exit;
}

$error = null;

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
        flash_set('success', 'Booking updated successfully.');
        header('Location: ' . BASE_URL . '/admin/bookings.php');
        exit;
    }
}

$row = db_one($link, "SELECT * FROM booking WHERE sno = ?", 'i', [$id]);
if (!$row) {
    header('Location: ' . BASE_URL . '/admin/bookings.php');
    exit;
}

$title = 'Edit Booking';
require_once __DIR__ . '/../../includes/layout/header-admin.php';
?>
<div class="admin-content-wrap">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-white shadow-sm">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/bookings.php">Bookings</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Booking: PNR <?= e($row['pnr'] ?? (string)$row['sno']) ?></li>
        </ol>
    </nav>

    <div class="card shadow-sm col-lg-7 col-md-9 p-0 mx-auto mt-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Edit Booking Details</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                PNR: <strong class="text-primary"><?= e($row['pnr'] ?? (string)$row['sno']) ?></strong> | 
                Bus: <strong><?= e($row['bus'] ?? '') ?></strong> | 
                Seat: <strong>#<?= e((string)$row['seat']) ?></strong> |
                Date: <strong><?= e($row['date'] ?? '') ?></strong>
            </p>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <form action="" method="post">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="unm" class="font-weight-bold">Customer Name :</label>
                    <input type="text" id="unm" name="unm" class="form-control" value="<?= e($row['name'] ?? '') ?>" placeholder="Enter Customer Name" required />
                </div>
                <div class="form-group">
                    <label for="num" class="font-weight-bold">Contact Number :</label>
                    <input type="tel" id="num" name="num" class="form-control" value="<?= e($row['contact'] ?? '') ?>" placeholder="Enter Contact Number" required />
                </div>
                <div class="form-group">
                    <label for="from" class="font-weight-bold">From City :</label>
                    <input type="text" id="from" name="from" value="<?= e($row['city1'] ?? '') ?>" class="form-control" placeholder="From City" required />
                </div>
                <div class="form-group">
                    <label for="to" class="font-weight-bold">To City :</label>
                    <input type="text" id="to" name="to" value="<?= e($row['city2'] ?? '') ?>" class="form-control" placeholder="To City" required />
                </div>
                <div class="form-group">
                    <label for="amount" class="font-weight-bold">Total Amount :</label>
                    <input type="number" step="0.01" min="1" name="amount" value="<?= e($row['price'] ?? '') ?>" class="form-control" id="amount" placeholder="Enter Amount" required>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-success" name="edit">Update Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout/footer-admin.php'; ?>