<?php
// admin/edit/edit-customer.php -- Edit Customer Details
require_once __DIR__ . '/../../includes/auth/admin-session.php';
require_once __DIR__ . '/../../includes/db_con.php';
require_once __DIR__ . '/../../includes/helpers.php';

$cust_pk = table_has_column($link, 'costumer', 'sno') ? 'sno' : 'id';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/customers.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    csrf_verify();
    $name = trim((string)($_POST['unm'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $pwd = (string)($_POST['pwd'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid name and email address.';
    } else {
        $dup = db_one($link, "SELECT `{$cust_pk}` FROM costumer WHERE email = ? AND `{$cust_pk}` != ?", 'si', [$email, $id]);
        if ($dup) {
            $error = "Email address '{$email}' is already registered to another customer.";
        } else {
            if ($pwd !== '') {
                if (strlen($pwd) < 8) {
                    $error = 'New password must be at least 8 characters.';
                } else {
                    $hashed = password_hash($pwd, PASSWORD_DEFAULT);
                    db_exec($link,
                        "UPDATE costumer SET name = ?, email = ?, pwd = ?, phone = ?, address = ? WHERE `{$cust_pk}` = ?",
                        'sssssi',
                        [$name, $email, $hashed, $phone, $address, $id]
                    );
                    flash_set('success', 'Customer updated successfully.');
                    header('Location: ' . BASE_URL . '/admin/customers.php');
                    exit;
                }
            } else {
                db_exec($link,
                    "UPDATE costumer SET name = ?, email = ?, phone = ?, address = ? WHERE `{$cust_pk}` = ?",
                    'ssssi',
                    [$name, $email, $phone, $address, $id]
                );
                flash_set('success', 'Customer updated successfully.');
                header('Location: ' . BASE_URL . '/admin/customers.php');
                exit;
            }
        }
    }
}

$row = db_one($link, "SELECT * FROM costumer WHERE `{$cust_pk}` = ?", 'i', [$id]);
if (!$row) {
    header('Location: ' . BASE_URL . '/admin/customers.php');
    exit;
}

$title = 'Edit Customer';
require_once __DIR__ . '/../../includes/layout/header-admin.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/customers.php">Customers</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($row['name'] ?? 'Customer') ?></li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Edit Passenger Account</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="unm" class="font-weight-bold small text-muted">Customer Full Name</label>
                        <input type="text" id="unm" name="unm" class="form-control" value="<?= e($row['name'] ?? '') ?>" required />
                    </div>
                    <div class="form-group">
                        <label for="email" class="font-weight-bold small text-muted">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= e($row['email'] ?? '') ?>" required />
                    </div>
                    <div class="form-group">
                        <label for="pwd" class="font-weight-bold small text-muted">Update Password (optional)</label>
                        <input type="password" id="pwd" name="pwd" class="form-control" placeholder="Leave empty to keep current password" minlength="8" />
                    </div>
                    <div class="form-group">
                        <label for="phone" class="font-weight-bold small text-muted">Contact Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($row['phone'] ?? '') ?>" required />
                    </div>
                    <div class="form-group mb-4">
                        <label for="address" class="font-weight-bold small text-muted">Physical / Mailing Address</label>
                        <textarea id="address" name="address" class="form-control" rows="2"><?= e($row['address'] ?? '') ?></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/admin/customers.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4" name="edit">Save Account Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout/footer-admin.php'; ?>