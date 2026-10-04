<?php
// admin/add-admin.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

// Detect primary key column for admin table
$admin_pk = 'id';
$col_check = mysqli_query($link, "SHOW COLUMNS FROM `admin` LIKE 'sno'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
    $admin_pk = 'sno';
}

$alert = null;
$alert_type = 'info';

// Handle Add Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subbtn'])) {
    csrf_verify();
    $name = trim((string)($_POST['unm'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $pwd = (string)($_POST['pwd'] ?? '');
    $phone = trim((string)($_POST['phone'] ?? ''));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alert = 'Please provide a valid name and email address.';
        $alert_type = 'danger';
    } elseif (strlen($pwd) < 8) {
        $alert = 'Password must be at least 8 characters.';
        $alert_type = 'danger';
    } else {
        $existing = db_one($link, 'SELECT * FROM admin WHERE Email_id = ?', 's', [$email]);
        if ($existing) {
            $alert = 'An administrator with that email already exists.';
            $alert_type = 'danger';
        } else {
            $hashed = password_hash($pwd, PASSWORD_DEFAULT);
            db_exec($link,
                "INSERT INTO admin (name, Email_id, Password, phone) VALUES (?, ?, ?, ?)",
                'ssss',
                [$name, $email, $hashed, $phone]
            );
            $alert = 'New admin created successfully.';
            $alert_type = 'success';
        }
    }
}

$admins = db_all($link, "SELECT * FROM admin ORDER BY `{$admin_pk}` ASC");

$title = 'Administrators';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="admin-content-wrap">
    <h1 class="text-info">Administrator Management</h1>

    <?php if ($alert): ?>
        <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
            <?= e($alert) ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="card col-lg-5 col-md-6 col-sm-12 mt-4" style="background-color: #e0f7fa;">
            <div class="card-body">
                <h5 class="card-title text-info mb-3">Admin Registration</h5>
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="unm">Name : </label>
                        <input type="text" id="unm" class="form-control" name="unm" placeholder="Enter admin name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Id : </label>
                        <input type="email" id="email" class="form-control" name="email" placeholder="Enter admin email" required>
                    </div>
                    <div class="form-group">
                        <label for="pwd">Password : </label>
                        <input type="password" id="pwd" class="form-control" name="pwd" placeholder="Minimum 8 characters" minlength="8" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone : </label>
                        <input type="tel" id="phone" class="form-control" name="phone" placeholder="Enter phone number" required>
                    </div>
                    <div class="form-group">
                        <input type="submit" class="btn btn-info btn-block" name="subbtn" value="Create Admin">
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-7 col-md-6 col-sm-12 mt-4">
            <h5 class="text-secondary mb-3">Existing Admins</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="thead-dark">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Password</th>
                            <th>Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($admins)): ?>
                            <tr><td colspan="5" class="text-center text-muted">No admins found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($admins as $row): ?>
                                <?php $aid = (int)($row[$admin_pk] ?? $row['id'] ?? $row['sno'] ?? 0); ?>
                                <tr>
                                    <td><?= e($aid) ?></td>
                                    <td><?= e($row['name'] ?? '') ?></td>
                                    <td><?= e($row['Email_id'] ?? '') ?></td>
                                    <td><span class="text-muted">••••••••</span></td>
                                    <td><?= e($row['phone'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>