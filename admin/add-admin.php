<?php
// admin/add-admin.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

require_role('super_admin');

// Detect primary key column for admin table
$admin_pk = table_has_column($link, 'admin', 'sno') ? 'sno' : 'id';
$has_role_col = table_has_column($link, 'admin', 'role');

$has_active_col = table_has_column($link, 'admin', 'is_active');
$current_admin_id = (int)($_SESSION['admin_id'] ?? 0);

// Helper to count active super admins
function count_active_super_admins(mysqli $link): int {
    $has_active = table_has_column($link, 'admin', 'is_active');
    $sql = $has_active 
        ? "SELECT COUNT(*) AS c FROM `admin` WHERE `role` = 'super_admin' AND `is_active` = 1"
        : "SELECT COUNT(*) AS c FROM `admin` WHERE `role` = 'super_admin'";
    return (int)(db_one($link, $sql)['c'] ?? 0);
}

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

    $pwd_errors = validate_new_password($pwd, 'admin');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alert = 'Please provide a valid name and email address.';
        $alert_type = 'danger';
    } elseif (!empty($pwd_errors)) {
        $alert = implode(' ', $pwd_errors);
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
            $new_id = (int)mysqli_insert_id($link);
            audit($link, 'CREATE', 'admin', $new_id, null, ['name' => $name, 'email' => $email, 'role' => $role]);
            $alert = "New administrator created successfully with '{$role}' privileges.";
            $alert_type = 'success';
        }
    }
}

// Handle Role Change (Item 5 & Issue 21: Self-demotion guard)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    csrf_verify();
    $target_id = (int)($_POST['target_id'] ?? 0);
    $new_role = trim((string)($_POST['new_role'] ?? ''));

    if ($target_id === $current_admin_id) {
        $alert = 'You cannot change your own role.';
        $alert_type = 'danger';
    } elseif (!in_array($new_role, ['super_admin', 'operator', 'viewer'], true)) {
        $alert = 'Invalid role specified.';
        $alert_type = 'danger';
    } elseif ($target_id <= 0) {
        $alert = 'Invalid administrator ID.';
        $alert_type = 'danger';
    } else {
        $target_row = db_one($link, "SELECT * FROM `admin` WHERE `{$admin_pk}` = ?", 'i', [$target_id]);
        if (!$target_row) {
            $alert = 'Administrator not found.';
            $alert_type = 'danger';
        } else {
            $old_role = (string)($target_row['role'] ?? 'operator');
            if ($old_role === 'super_admin' && $new_role !== 'super_admin') {
                if (count_active_super_admins($link) <= 1) {
                    $alert = 'Cannot demote the last remaining active Super Admin.';
                    $alert_type = 'danger';
                }
            }

            if (!$alert) {
                db_exec($link, "UPDATE `admin` SET `role` = ? WHERE `{$admin_pk}` = ?", 'si', [$new_role, $target_id]);
                audit($link, 'ROLE_CHANGE', 'admin', $target_id, ['role' => $old_role], ['role' => $new_role]);
                $alert = "Role updated successfully to '{$new_role}'.";
                $alert_type = 'success';
            }
        }
    }
}

// Handle Toggle Active/Deactivate (Item 5)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    csrf_verify();
    $target_id = (int)($_POST['target_id'] ?? 0);
    if ($target_id === $current_admin_id) {
        $alert = 'You cannot deactivate your own account.';
        $alert_type = 'danger';
    } else {
        $target_row = db_one($link, "SELECT * FROM `admin` WHERE `{$admin_pk}` = ?", 'i', [$target_id]);
        if (!$target_row) {
            $alert = 'Administrator not found.';
            $alert_type = 'danger';
        } else {
            $curr_active = (int)($target_row['is_active'] ?? 1);
            $new_active = $curr_active === 1 ? 0 : 1;

            if ($curr_active === 1 && ($target_row['role'] ?? '') === 'super_admin' && count_active_super_admins($link) <= 1) {
                $alert = 'Cannot deactivate the last remaining active Super Admin.';
                $alert_type = 'danger';
            } else {
                db_exec($link, "UPDATE `admin` SET `is_active` = ? WHERE `{$admin_pk}` = ?", 'ii', [$new_active, $target_id]);
                audit($link, 'UPDATE', 'admin', $target_id, ['is_active' => $curr_active], ['is_active' => $new_active]);
                $alert = ($new_active === 1) ? 'Administrator account activated.' : 'Administrator account deactivated.';
                $alert_type = 'success';
            }
        }
    }
}

// Handle Force Password Reset (Issues 1, 24, 29)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_pwd'])) {
    csrf_verify();
    $target_id = (int)($_POST['target_id'] ?? 0);
    $new_pwd = (string)($_POST['new_pwd'] ?? '');
    $confirm_pwd = (string)($_POST['confirm_pwd'] ?? $new_pwd);

    $pwd_errors = validate_new_password($new_pwd, 'admin');

    if ($target_id <= 0) {
        $alert = 'Invalid administrator ID.';
        $alert_type = 'danger';
    } elseif ($new_pwd !== $confirm_pwd) {
        $alert = 'New password and confirmation do not match.';
        $alert_type = 'danger';
    } elseif (!empty($pwd_errors)) {
        $alert = implode(' ', $pwd_errors);
        $alert_type = 'danger';
    } else {
        $target_row = db_one($link, "SELECT * FROM `admin` WHERE `{$admin_pk}` = ?", 'i', [$target_id]);
        if (!$target_row) {
            $alert = 'Administrator not found.';
            $alert_type = 'danger';
        } else {
            $hashed = password_hash($new_pwd, PASSWORD_DEFAULT);
            db_exec($link, "UPDATE `admin` SET `Password` = ?, `password_changed_at` = NOW() WHERE `{$admin_pk}` = ?", 'si', [$hashed, $target_id]);
            audit($link, 'UPDATE', 'admin', $target_id, null, ['action' => 'password_reset_by_admin']);
            $alert = 'Password has been reset successfully.';
            $alert_type = 'success';
        }
    }
}

// Handle Delete Admin (Item 5)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    csrf_verify();
    $target_id = (int)($_POST['target_id'] ?? 0);

    if ($target_id === $current_admin_id) {
        $alert = 'You cannot delete your own account.';
        $alert_type = 'danger';
    } else {
        $target_row = db_one($link, "SELECT * FROM `admin` WHERE `{$admin_pk}` = ?", 'i', [$target_id]);
        if (!$target_row) {
            $alert = 'Administrator not found.';
            $alert_type = 'danger';
        } elseif (($target_row['role'] ?? '') === 'super_admin' && count_active_super_admins($link) <= 1) {
            $alert = 'Cannot delete the last remaining active Super Admin.';
            $alert_type = 'danger';
        } else {
            db_exec($link, "DELETE FROM `admin` WHERE `{$admin_pk}` = ?", 'i', [$target_id]);
            audit($link, 'DELETE', 'admin', $target_id, ['email' => $target_row['Email_id'] ?? ''], null);
            $alert = 'Administrator deleted successfully.';
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
                            <th style="width: 50px;">#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Sign-In</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($admins)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No admins found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($admins as $row): ?>
                                <?php
                                $aid = (int)($row[$admin_pk] ?? $row['id'] ?? $row['sno'] ?? 0);
                                $arole = (string)($row['role'] ?? 'super_admin');
                                $is_active = (int)($row['is_active'] ?? 1) === 1;
                                $is_self = ($aid === $current_admin_id);
                                $role_badge = match($arole) {
                                    'super_admin' => 'badge-danger',
                                    'operator' => 'badge-primary',
                                    'viewer' => 'badge-secondary',
                                    default => 'badge-info',
                                };
                                ?>
                                <tr class="<?= !$is_active ? 'text-muted bg-light' : '' ?>">
                                    <td><span class="text-muted small">#<?= e($aid) ?></span></td>
                                    <td>
                                        <strong class="text-dark"><?= e($row['name'] ?? '') ?></strong>
                                        <?php if ($is_self): ?>
                                            <span class="badge badge-info ml-1">You</span>
                                        <?php endif; ?>
                                        <div class="small text-muted"><?= e($row['phone'] ?? '') ?></div>
                                    </td>
                                    <td><a href="mailto:<?= e($row['Email_id'] ?? '') ?>"><?= e($row['Email_id'] ?? '') ?></a></td>
                                    <td>
                                        <span class="badge <?= $role_badge ?> mb-1"><?= e(ucfirst(str_replace('_', ' ', $arole))) ?></span>
                                        <form method="post" action="" class="form-inline mt-1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="target_id" value="<?= e($aid) ?>">
                                            <select name="new_role" class="form-control form-control-sm mr-1" style="font-size: 0.75rem; height: 26px; padding: 2px 5px;" onchange="if(confirm('Change role to ' + this.value + '?')) this.form.submit(); else this.value='<?= $arole ?>';">
                                                <option value="super_admin" <?= $arole === 'super_admin' ? 'selected' : '' ?>>super_admin</option>
                                                <option value="operator" <?= $arole === 'operator' ? 'selected' : '' ?>>operator</option>
                                                <option value="viewer" <?= $arole === 'viewer' ? 'selected' : '' ?>>viewer</option>
                                            </select>
                                            <input type="hidden" name="update_role" value="1">
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ($is_active): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Deactivated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= !empty($row['last_login_at']) ? e(date('d M Y, H:i', strtotime($row['last_login_at']))) : 'Never' ?>
                                        </small>
                                    </td>
                                    <td class="text-right">
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!$is_self): ?>
                                                <form method="post" action="" class="d-inline" onsubmit="return confirm('<?= $is_active ? 'Deactivate this admin account?' : 'Reactivate this admin account?' ?>');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="target_id" value="<?= e($aid) ?>">
                                                    <button type="submit" name="toggle_active" class="btn btn-sm <?= $is_active ? 'btn-outline-warning' : 'btn-outline-success' ?> mr-1">
                                                        <?= $is_active ? 'Deactivate' : 'Activate' ?>
                                                    </button>
                                                </form>
                                                <form method="post" action="" class="d-inline" onsubmit="return confirm('Permanently remove this administrator?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="target_id" value="<?= e($aid) ?>">
                                                    <button type="submit" name="delete_admin" class="btn btn-sm btn-outline-danger mr-1">
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary btn-reset-modal" data-toggle="modal" data-target="#resetPwdModal" data-id="<?= e($aid) ?>" data-name="<?= e($row['name'] ?? 'Administrator') ?>">
                                                Reset Pwd
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Secure Password Reset Modal (Issue 29) -->
<div class="modal fade" id="resetPwdModal" tabindex="-1" role="dialog" aria-labelledby="resetPwdModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold" id="resetPwdModalLabel">Reset Administrator Password</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="target_id" id="modal_target_id" value="">
                <input type="hidden" name="reset_pwd" value="1">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Set a new password for <strong id="modal_target_name" class="text-dark">administrator</strong>. 
                        Password must be at least 12 characters and max 72 bytes.
                    </p>
                    <div class="form-group">
                        <label for="modal_new_pwd" class="small font-weight-bold text-muted">New Master Password</label>
                        <input type="password" id="modal_new_pwd" name="new_pwd" class="form-control" minlength="12" maxlength="72" required autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="modal_confirm_pwd" class="small font-weight-bold text-muted">Confirm New Password</label>
                        <input type="password" id="modal_confirm_pwd" name="confirm_pwd" class="form-control" minlength="12" maxlength="72" required autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger font-weight-bold">Apply Password Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('.btn-reset-modal').on('click', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        $('#modal_target_id').val(id);
        $('#modal_target_name').text(name);
        $('#modal_new_pwd').val('');
        $('#modal_confirm_pwd').val('');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>