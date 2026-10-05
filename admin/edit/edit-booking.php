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
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/bookings.php">Bookings</a></li>
        <li class="breadcrumb-item active" aria-current="page">PNR <?= e($row['pnr'] ?? (string)$row['sno']) ?></li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 font-weight-bold">Modify Booking Record</h5>
                <code>PNR: <?= e($row['pnr'] ?? (string)$row['sno']) ?></code>
            </div>
            <div class="card-body p-4">
                <div class="p-3 bg-light rounded border mb-4">
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted text-uppercase">Bus Number</small>
                            <div class="font-weight-bold text-dark"><?= e($row['bus'] ?? '') ?></div>
                        </div>
                        <div class="col-4">
                            <small class="text-muted text-uppercase">Seat</small>
                            <div class="font-weight-bold text-primary">#<?= e((string)$row['seat']) ?></div>
                        </div>
                        <div class="col-4">
                            <small class="text-muted text-uppercase">Travel Date</small>
                            <div class="font-weight-bold text-dark"><?= e($row['date'] ?? '') ?></div>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="unm" class="font-weight-bold small text-muted">Passenger Full Name</label>
                        <input type="text" id="unm" name="unm" class="form-control" value="<?= e($row['name'] ?? '') ?>" required />
                    </div>
                    <div class="form-group">
                        <label for="num" class="font-weight-bold small text-muted">Contact Phone Number</label>
                        <input type="tel" id="num" name="num" class="form-control" value="<?= e($row['contact'] ?? '') ?>" required />
                    </div>
                    <div class="form-row">
                        <div class="col-6 form-group">
                            <label for="from" class="font-weight-bold small text-muted">Departure City</label>
                            <input type="text" id="from" name="from" value="<?= e($row['city1'] ?? '') ?>" class="form-control" required />
                        </div>
                        <div class="col-6 form-group">
                            <label for="to" class="font-weight-bold small text-muted">Destination City</label>
                            <input type="text" id="to" name="to" value="<?= e($row['city2'] ?? '') ?>" class="form-control" required />
                        </div>
                    </div>
                    <div class="form-group mb-4">
                        <label for="amount" class="font-weight-bold small text-muted">Total Tariff (<?= CURRENCY ?>)</label>
                        <input type="number" step="0.01" min="1" name="amount" value="<?= e($row['price'] ?? '') ?>" class="form-control" id="amount" required>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4" name="edit">Save Modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout/footer-admin.php'; ?>