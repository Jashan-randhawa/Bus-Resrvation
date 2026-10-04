<?php
// admin/edit/edit-customer.php
require_once __DIR__ . '/../../includes/auth/admin-session.php';
require_once __DIR__ . '/../../includes/db_con.php';

// Detect primary key column for customer table
$cust_pk = 'id';
$col_check = mysqli_query($link, "SHOW COLUMNS FROM `costumer` LIKE 'sno'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
    $cust_pk = 'sno';
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/customers.php');
    exit;
}

$error = null;

// Handle Update before rendering HTML
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
                header('Location: ' . BASE_URL . '/admin/customers.php');
                exit;
            }
        } else {
            // Keep existing password
            db_exec($link,
                "UPDATE costumer SET name = ?, email = ?, phone = ?, address = ? WHERE `{$cust_pk}` = ?",
                'ssssi',
                [$name, $email, $phone, $address, $id]
            );
            header('Location: ' . BASE_URL . '/admin/customers.php');
            exit;
        }
    }
}

$row = db_one($link, "SELECT * FROM costumer WHERE `{$cust_pk}` = ?", 'i', [$id]);
if (!$row) {
    header('Location: ' . BASE_URL . '/admin/customers.php');
    exit;
}

require_once __DIR__ . '/../../includes/layout/header-edit.php';
?>
<section id="image">
    <div class="card-img-overlay">
        <div class="card col-lg-6 col-md-6 col-sm-6 col-xs-12 mt-5" style="margin-top: 6em; margin: auto;">
            <div class="card-body">
                <h4 class="card-title text-center text-success">Edit Customer Details</h4>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="unm">Name :</label>
                        <input type="text" id="unm" name="unm" class="form-control" value="<?= e($row['name'] ?? '') ?>" placeholder="Enter name" required />
                    </div>
                    <div class="form-group">
                        <label for="email">Email Id :</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= e($row['email'] ?? '') ?>" placeholder="Enter email" required />
                    </div>
                    <div class="form-group">
                        <label for="pwd">New Password (leave blank to keep current):</label>
                        <input type="password" id="pwd" name="pwd" class="form-control" placeholder="Leave blank to keep unchanged" minlength="8" />
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone :</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($row['phone'] ?? '') ?>" placeholder="Enter number" required />
                    </div>
                    <div class="form-group">
                        <label for="address">Address :</label>
                        <textarea id="address" name="address" class="form-control" placeholder="Enter address" rows="2"><?= e($row['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <input type="submit" class="btn btn-success btn-block" name="edit" value="Update Customer" />
                    </div>
                    <div class="text-center">
                        <a href="<?= BASE_URL ?>/admin/customers.php" class="btn btn-secondary btn-sm">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>