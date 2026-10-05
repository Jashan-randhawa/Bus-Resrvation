<?php
// user/profile.php -- User Profile & Account Self-Service (U-17)
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$uid = (int)($_SESSION['uid'] ?? 0);
$user = db_one($link, 'SELECT * FROM costumer WHERE id = ?', 'i', [$uid]);

if (!$user) {
    flash_set('danger', 'User account not found.');
    header('Location: ' . BASE_URL . '/homepage.php');
    exit;
}

// 1. Handle Profile Information Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_verify();
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Full name must be between 2 and 100 characters.';
    }
    if (strlen($phone) < 10 || strlen($phone) > 15) {
        $errors[] = 'Please provide a valid contact number (10 to 15 digits).';
    }

    if (!empty($errors)) {
        flash_set('danger', implode(' ', $errors));
    } else {
        db_exec($link,
            'UPDATE costumer SET name = ?, phone = ?, address = ? WHERE id = ?',
            'sssi',
            [$name, $phone, $address, $uid]
        );

        // U-17: Refresh session state immediately so name and phone are never stale
        $_SESSION['name'] = $name;
        $_SESSION['phone'] = $phone;

        flash_set('success', 'Profile details updated successfully.');
    }
    header('Location: ' . BASE_URL . '/user/profile.php');
    exit;
}

// 2. Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrf_verify();
    $current_pwd = (string)($_POST['current_pwd'] ?? '');
    $new_pwd = (string)($_POST['new_pwd'] ?? '');
    $confirm_pwd = (string)($_POST['confirm_pwd'] ?? '');

    $pwd_errors = [];
    if (!password_verify($current_pwd, (string)$user['pwd'])) {
        $pwd_errors[] = 'Current password is incorrect.';
    }
    if (strlen($new_pwd) < 8 || !preg_match('/[A-Za-z]/', $new_pwd) || !preg_match('/\d/', $new_pwd)) {
        $pwd_errors[] = 'New password must be at least 8 characters long and contain both letters and digits.';
    }
    if ($new_pwd !== $confirm_pwd) {
        $pwd_errors[] = 'New password and confirmation password do not match.';
    }

    if (!empty($pwd_errors)) {
        flash_set('danger', implode(' ', $pwd_errors));
    } else {
        $hash = password_hash($new_pwd, PASSWORD_DEFAULT);
        db_exec($link, 'UPDATE costumer SET pwd = ? WHERE id = ?', 'si', [$hash, $uid]);
        flash_set('success', 'Your password has been changed successfully.');
    }
    header('Location: ' . BASE_URL . '/user/profile.php');
    exit;
}

$title = 'My Profile';
require_once __DIR__ . '/../includes/layout/header-user.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Account Profile &amp; Settings</h1>
        <p class="page-subtitle">Manage your personal information, contact phone number, and account security credentials.</p>
    </div>
</div>

<div class="row">
    <!-- Profile Info Form -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Personal Information</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="email" class="font-weight-bold small text-muted">Email Address (Read-only)</label>
                        <input type="email" id="email" class="form-control bg-light" value="<?= e($user['email'] ?? '') ?>" readonly>
                        <small class="text-muted">Email address is your primary account identifier and cannot be changed directly.</small>
                    </div>

                    <div class="form-group">
                        <label for="name" class="font-weight-bold small text-muted">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?= e($user['name'] ?? '') ?>" minlength="2" maxlength="100" required autocomplete="name">
                    </div>

                    <div class="form-group">
                        <label for="phone" class="font-weight-bold small text-muted">Contact Phone (10-15 digits)</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" minlength="10" maxlength="15" pattern="[0-9]{10,15}" inputmode="numeric" required autocomplete="tel">
                        <small class="text-muted">Important: Ticket PNR lookup relies on the last 4 digits of this phone number.</small>
                    </div>

                    <div class="form-group">
                        <label for="address" class="font-weight-bold small text-muted">Residential Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3" placeholder="Street, City, Postal Code"><?= e($user['address'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" name="update_profile" class="btn btn-primary px-4 font-weight-bold shadow-sm">
                            Save Profile Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Change Form -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">Change Password</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="current_pwd" class="font-weight-bold small text-muted">Current Password</label>
                        <input type="password" id="current_pwd" name="current_pwd" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label for="new_pwd" class="font-weight-bold small text-muted">New Password</label>
                        <input type="password" id="new_pwd" name="new_pwd" class="form-control" placeholder="••••••••" minlength="8" required autocomplete="new-password">
                        <small class="text-muted">Must contain at least 8 characters with letters and digits.</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_pwd" class="font-weight-bold small text-muted">Confirm New Password</label>
                        <input type="password" id="confirm_pwd" name="confirm_pwd" class="form-control" placeholder="••••••••" minlength="8" required autocomplete="new-password">
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" name="change_password" class="btn btn-warning px-4 font-weight-bold shadow-sm text-dark">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>
