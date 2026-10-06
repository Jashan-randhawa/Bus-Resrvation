<?php
// admin/add-admin.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

require_role('super_admin');

// Detect primary key column for admin table
$admin_pk = table_has_column($link, 'admin', 'sno') ? 'sno' : 'id';
$has_role_col = table_has_column($link, 'admin', 'role');

$alert = null;
$alert_type = 'info';

// Handle Add Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subbtn'])) {
    csrf_verify();
    $name = trim((string)($_POST['unm'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $pwd = (string)($_POST['pwd'] ?? '');
    $phone = trim((string)($_POST['phone'] ?? ''));
    $role = trim((string)($_POST['role'] ?? 'operator'));
    if (!in_array($role, ['super_admin', 'operator', 'viewer'], true)) {
        $role = 'operator';
    }

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alert = 'Please provide a valid name and email address.';
        $alert_type = 'danger';
    } elseif (strlen($pwd) < 12) {
        $alert = 'Password must be at least 12 characters.';
        $alert_type = 'danger';
    } else {
        $existing = db_one($link, 'SELECT * FROM admin WHERE Email_id = ?', 's', [$email]);
        if ($existing) {
            $alert = 'An administrator with that email already exists.';
            $alert_type = 'danger';
        } else {
            $hashed = password_hash($pwd, PASSWORD_DEFAULT);
            if ($has_role_col) {
                db_exec($link,
                    "INSERT INTO admin (name, Email_id, Password, phone, role) VALUES (?, ?, ?, ?, ?)",
                    'sssss',
                    [$name, $email, $hashed, $phone, $role]
                );
            } else {
                db_exec($link,
                    "INSERT INTO admin (name, Email_id, Password, phone) VALUES (?, ?, ?, ?)",
                    'ssss',
                    [$name, $email, $hashed, $phone]
                );
            }
            audit($link, 'CREATE', 'admin', (int)mysqli_insert_id($link), null, ['name' => $name, 'email' => $email, 'role' => $role]);
            $alert = "New administrator created successfully with '{$role}' privileges.";
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
        <p class="page-subtitle">Configure system administrator accounts with role-based access control (super_admin, operator, viewer).</p>
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
                        <label for="pwd" class="font-weight-bold small text-muted">Master Password (Min 12 chars)</label>
                        <input type="password" id="pwd" class="form-control" name="pwd" placeholder="Min 12 characters" minlength="12" required>
                    </div>
                    <div class="form-group">
                        <label for="phone" class="font-weight-bold small text-muted">Telephone / Contact</label>
                        <input type="tel" id="phone" class="form-control" name="phone" placeholder="Contact number" required>
                    </div>
                    <div class="form-group mb-4">
                        <label for="role" class="font-weight-bold small text-muted">Assigned Administrative Role</label>
                        <select id="role" name="role" class="form-control">
                            <option value="operator" selected>Operator (Fleet operations, bookings & dispatch)</option>
                            <option value="viewer">Viewer (Read-only observation access)</option>
                            <option value="super_admin">Super Admin (Full administrative & diagnostics control)</option>
                        </select>
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
                            <th style="width: 60px;">#</th>
                            <th>Name</th>
                            <th>Email Address</th>
                            <th>Role</th>
                            <th>Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($admins)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No admins found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($admins as $row): ?>
                                <?php
                                $aid = (int)($row[$admin_pk] ?? $row['id'] ?? $row['sno'] ?? 0);
                                $arole = (string)($row['role'] ?? 'super_admin');
                                $role_badge = match($arole) {
                                    'super_admin' => 'badge-danger',
                                    'operator' => 'badge-primary',
                                    'viewer' => 'badge-secondary',
                                    default => 'badge-info',
                                };
                                ?>
                                <tr>
                                    <td><span class="text-muted small">#<?= e($aid) ?></span></td>
                                    <td class="font-weight-medium text-dark"><?= e($row['name'] ?? '') ?></td>
                                    <td><a href="mailto:<?= e($row['Email_id'] ?? '') ?>" class="text-primary"><?= e($row['Email_id'] ?? '') ?></a></td>
                                    <td><span class="badge <?= $role_badge ?>"><?= e(ucfirst(str_replace('_', ' ', $arole))) ?></span></td>
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