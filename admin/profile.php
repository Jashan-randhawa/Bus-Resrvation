<?php
// admin/profile.php -- Administrator Profile & Security Settings (Item 6)
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_row = db_one($link, "SELECT * FROM `admin` WHERE id = ? LIMIT 1", 'i', [$admin_id]);

if (!$admin_row) {
    logout_all();
    header('Location: ' . BASE_URL . '/homepage.php?login=admin');
    exit;
}

$alert = null;
$alert_type = 'info';

// Handle Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrf_verify();
    $current_pwd = (string)($_POST['current_password'] ?? '');
    $new_pwd = (string)($_POST['new_password'] ?? '');
    $confirm_pwd = (string)($_POST['confirm_password'] ?? '');

    if ($current_pwd === '' || $new_pwd === '' || $confirm_pwd === '') {
        $alert = 'Please fill all password fields.';
        $alert_type = 'danger';
    } elseif ($new_pwd !== $confirm_pwd) {
        $alert = 'New password and confirmation password do not match.';
        $alert_type = 'danger';
    } elseif (strlen($new_pwd) < 12) {
        $alert = 'New password must be at least 12 characters.';
        $alert_type = 'danger';
    } elseif (!password_verify($current_pwd, $admin_row['Password'])) {
        $alert = 'Current password is incorrect.';
        $alert_type = 'danger';
    } else {
        $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
        db_exec($link, "UPDATE `admin` SET `Password` = ?, `password_changed_at` = NOW() WHERE id = ?", 'si', [$new_hash, $admin_id]);
        session_regenerate_id(true);
        audit($link, 'UPDATE', 'admin', $admin_id, null, ['action' => 'password_change']);
        $alert = 'Your password has been changed successfully.';
        $alert_type = 'success';
        // Refresh admin row
        $admin_row = db_one($link, "SELECT * FROM `admin` WHERE id = ? LIMIT 1", 'i', [$admin_id]);
    }
}

// Handle Update Profile Information (name, phone)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_verify();
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    if ($name === '' || $phone === '') {
        $alert = 'Name and phone cannot be empty.';
        $alert_type = 'danger';
    } else {
        $old_profile = ['name' => $admin_row['name'], 'phone' => $admin_row['phone']];
        db_exec($link, "UPDATE `admin` SET `name` = ?, `phone` = ? WHERE id = ?", 'ssi', [$name, $phone, $admin_id]);
        $_SESSION['name'] = $name;
        $_SESSION['phone'] = $phone;
        audit($link, 'UPDATE', 'admin', $admin_id, $old_profile, ['name' => $name, 'phone' => $phone]);
        $alert = 'Profile updated successfully.';
        $alert_type = 'success';
        $admin_row = db_one($link, "SELECT * FROM `admin` WHERE id = ? LIMIT 1", 'i', [$admin_id]);
    }
}

$title = 'Admin Profile & Security';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Profile & Security Credentials</h1>
        <p class="page-subtitle">Manage administrative profile details, credential rotation, and session authentication.</p>
    </div>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Info Card -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Administrator Profile</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="small font-weight-bold text-muted">Email Address (Read-only)</label>
                        <input type="email" class="form-control" value="<?= e($admin_row['Email_id'] ?? '') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold text-muted">Administrative Role</label>
                        <div>
                            <span class="badge badge-primary px-3 py-2 text-uppercase"><?= e(str_replace('_', ' ', $admin_row['role'] ?? 'operator')) ?></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="name" class="small font-weight-bold text-muted">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?= e($admin_row['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone" class="small font-weight-bold text-muted">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($admin_row['phone'] ?? '') ?>" required>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary px-4">
                        Save Profile Details
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Rotation Card -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Rotate Master Password</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="current_password" class="small font-weight-bold text-muted">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password" class="small font-weight-bold text-muted">New Password (Min 12 chars)</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" minlength="12" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="small font-weight-bold text-muted">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="12" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-dark px-4 font-weight-bold">
                        Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>
