<?php
// admin/customers.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

// Detect primary key column for customer table
$cust_pk = table_has_column($link, 'costumer', 'sno') ? 'sno' : 'id';

$alert = null;
$alert_type = 'info';

// Handle Add Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    csrf_verify();
    $name = trim((string)($_POST['unm'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $pwd = (string)($_POST['pwd'] ?? '');
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alert = 'Please provide a valid name and email address.';
        $alert_type = 'danger';
    } elseif (strlen($pwd) < 8) {
        $alert = 'Password must be at least 8 characters.';
        $alert_type = 'danger';
    } else {
        $existing = db_one($link, 'SELECT * FROM costumer WHERE email = ?', 's', [$email]);
        if ($existing) {
            $alert = 'A customer with that email already exists.';
            $alert_type = 'danger';
        } else {
            $hashed = password_hash($pwd, PASSWORD_DEFAULT);
            db_exec($link,
                "INSERT INTO costumer (name, email, pwd, phone, address) VALUES (?, ?, ?, ?, ?)",
                'sssss',
                [$name, $email, $hashed, $phone, $address]
            );
            $alert = 'Customer added successfully.';
            $alert_type = 'success';
        }
    }
}

// Handle Delete Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    csrf_verify();
    require_role('super_admin');
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        $active_bookings = db_one($link,
            "SELECT COUNT(*) AS c FROM booking WHERE id = ? AND (status IS NULL OR status IN ('Confirmed', 'Pending'))",
            'i', [$delete_id]
        );
        if ((int)($active_bookings['c'] ?? 0) > 0) {
            $alert = 'Cannot delete customer account because they have active ticket reservations.';
            $alert_type = 'danger';
        } else {
            db_exec($link, "DELETE FROM costumer WHERE `{$cust_pk}` = ?", 'i', [$delete_id]);
            $alert = 'Customer deleted successfully.';
            $alert_type = 'success';
        }
    }
}

$customers = db_all($link, "SELECT * FROM costumer ORDER BY `{$cust_pk}` ASC");

$title = 'Customers';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Customer Accounts</h1>
        <p class="page-subtitle">Manage registered passenger profiles, credentials, contact records, and addresses.</p>
    </div>
    <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addCustomerModal">
        + Register Customer
    </button>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<div class="data-table-wrapper">
    <div class="table-header">
        <h5 class="mb-0">Customer Directory</h5>
        <span class="record-count"><?= count($customers) ?> user(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th style="width: 70px;">#</th>
                    <th>Full Name</th>
                    <th>Email Address</th>
                    <th>Security</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state py-5">
                                <div class="empty-icon">👥</div>
                                <div class="empty-title">No customers registered</div>
                                <div class="empty-text">Click '+ Register Customer' to add a passenger account.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $row): ?>
                        <?php $cid = (int)($row[$cust_pk] ?? $row['id'] ?? $row['sno'] ?? 0); ?>
                        <tr>
                            <td><span class="text-muted small">#<?= e($cid) ?></span></td>
                            <td class="font-weight-medium text-dark"><?= e($row['name'] ?? '') ?></td>
                            <td><a href="mailto:<?= e($row['email'] ?? '') ?>" class="text-primary"><?= e($row['email'] ?? '') ?></a></td>
                            <td><span class="badge badge-light border text-muted">••••••••</span></td>
                            <td><?= e($row['phone'] ?? '') ?></td>
                            <td><small class="text-muted"><?= e($row['address'] ?? 'N/A') ?></small></td>
                            <td class="text-right">
                                <a href="<?= BASE_URL ?>/admin/edit/edit-customer.php?id=<?= e($cid) ?>" class="btn btn-outline-secondary btn-sm">Edit</a>
                                <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this customer?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delete_id" value="<?= e($cid) ?>">
                                    <button type="submit" name="delete_customer" class="btn btn-outline-danger btn-sm ml-1">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold">Register Passenger</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="unm" class="font-weight-bold small text-muted">Full Name</label>
                        <input type="text" id="unm" name="unm" class="form-control" placeholder="Jane Doe" required />
                    </div>
                    <div class="form-group">
                        <label for="email" class="font-weight-bold small text-muted">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="jane@example.com" required />
                    </div>
                    <div class="form-row">
                        <div class="col-6 form-group">
                            <label for="pwd" class="font-weight-bold small text-muted">Initial Password</label>
                            <input type="password" id="pwd" name="pwd" class="form-control" placeholder="8+ chars" minlength="8" required />
                        </div>
                        <div class="col-6 form-group">
                            <label for="phone" class="font-weight-bold small text-muted">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="Phone" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address" class="font-weight-bold small text-muted">Address</label>
                        <textarea id="address" name="address" class="form-control" placeholder="Street, city..." rows="2"></textarea>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" class="btn btn-outline-secondary mr-2" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add">Create Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>