<?php
// admin/add-admin.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

// Detect primary key column for admin table
$admin_pk = table_has_column($link, 'admin', 'sno') ? 'sno' : 'id';

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
            $alert = 'New administrator created successfully.';
            $alert_type = 'success';
        }
    }
}

$admins = db_all($link, "SELECT * FROM admin ORDER BY `{$admin_pk}` ASC");

$title = 'Administrators';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Administrator Management</h1>
        <p class="page-subtitle">Configure system administrator accounts with privileged platform control access.</p>
    </div>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Creation Card -->
    <div class="col-lg-5 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Register Admin</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="unm" class="font-weight-bold small text-muted">Admin Full Name</label>
                        <input type="text" id="unm" class="form-control" name="unm" placeholder="Admin Name" required>
                    </div>
                    <div class="form-group">
                        <label for="email" class="font-weight-bold small text-muted">Corporate Email Address</label>
                        <input type="email" id="email" class="form-control" name="email" placeholder="admin@domain.com" required>
                    </div>
                    <div class="form-group">
                        <label for="pwd" class="font-weight-bold small text-muted">Master Password</label>
                        <input type="password" id="pwd" class="form-control" name="pwd" placeholder="Min 8 characters" minlength="8" required>
                    </div>
                    <div class="form-group mb-4">
                        <label for="phone" class="font-weight-bold small text-muted">Telephone / Contact</label>
                        <input type="tel" id="phone" class="form-control" name="phone" placeholder="Contact number" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm" name="subbtn">
                        Create Administrator
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Active Admins Directory -->
    <div class="col-lg-7 mb-4">
        <div class="data-table-wrapper h-100">
            <div class="table-header">
                <h5 class="mb-0">Existing Administrators</h5>
                <span class="record-count"><?= count($admins) ?> admin(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 70px;">#</th>
                            <th>Name</th>
                            <th>Email Address</th>
                            <th>Security</th>
                            <th>Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($admins)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No admins found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($admins as $row): ?>
                                <?php $aid = (int)($row[$admin_pk] ?? $row['id'] ?? $row['sno'] ?? 0); ?>
                                <tr>
                                    <td><span class="text-muted small">#<?= e($aid) ?></span></td>
                                    <td class="font-weight-medium text-dark"><?= e($row['name'] ?? '') ?></td>
                                    <td><a href="mailto:<?= e($row['Email_id'] ?? '') ?>" class="text-primary"><?= e($row['Email_id'] ?? '') ?></a></td>
                                    <td><span class="badge badge-light border text-muted">••••••••</span></td>
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