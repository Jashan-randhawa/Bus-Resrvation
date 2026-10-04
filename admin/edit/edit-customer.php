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
<div class="admin-content-wrap">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-white shadow-sm">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/customers.php">Customers</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Customer: <?= e($row['name'] ?? '') ?></li>
        </ol>
    </nav>

    <div class="card shadow-sm col-lg-7 col-md-9 p-0 mx-auto mt-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Edit Customer Details</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <form action="" method="post">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="unm" class="font-weight-bold">Name :</label>
                    <input type="text" id="unm" name="unm" class="form-control" value="<?= e($row['name'] ?? '') ?>" placeholder="Enter name" required />
                </div>
                <div class="form-group">
                    <label for="email" class="font-weight-bold">Email Id :</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= e($row['email'] ?? '') ?>" placeholder="Enter email" required />
                </div>
                <div class="form-group">
                    <label for="pwd" class="font-weight-bold">New Password (leave blank to keep current) :</label>
                    <input type="password" id="pwd" name="pwd" class="form-control" placeholder="Leave blank to keep unchanged" minlength="8" />
                </div>
                <div class="form-group">
                    <label for="phone" class="font-weight-bold">Phone Number :</label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($row['phone'] ?? '') ?>" placeholder="Enter number" required />
                </div>
                <div class="form-group">
                    <label for="address" class="font-weight-bold">Address :</label>
                    <textarea id="address" name="address" class="form-control" placeholder="Enter address" rows="2"><?= e($row['address'] ?? '') ?></textarea>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <a href="<?= BASE_URL ?>/admin/customers.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-success" name="edit">Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout/footer-admin.php'; ?>