<?php
// admin/customers.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

// Detect primary key column for customer table
$cust_pk = table_has_column($link, 'costumer', 'sno') ? 'sno' : 'id';

$alert = null;
$alert_type = 'info';

// Handle Add Customer (Phase A Item 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    csrf_verify();
    require_role('super_admin', 'operator');
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
            audit($link, 'CREATE', 'customer', (int)mysqli_insert_id($link), null, ['name' => $name, 'email' => $email, 'phone' => $phone]);
            $alert = 'Customer added successfully.';
            $alert_type = 'success';
        }
    }
}

$has_archived_col = table_has_column($link, 'costumer', 'archived_at');

// Handle Delete/Archive Customer (Phase B Item 4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    csrf_verify();
    require_role('super_admin');
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        $old_customer = db_one($link, "SELECT name, email, phone FROM costumer WHERE `{$cust_pk}` = ?", 'i', [$delete_id]);
        $active_bookings = db_one($link,
            "SELECT COUNT(*) AS c FROM booking WHERE id = ? AND (status IS NULL OR status IN ('Confirmed', 'Pending'))",
            'i', [$delete_id]
        );
        if ((int)($active_bookings['c'] ?? 0) > 0) {
            $alert = 'Cannot archive customer account because they have active ticket reservations.';
            $alert_type = 'danger';
        } else {
            if ($has_archived_col) {
                db_exec($link, "UPDATE costumer SET archived_at = NOW() WHERE `{$cust_pk}` = ?", 'i', [$delete_id]);
                audit($link, 'DELETE', 'customer', $delete_id, $old_customer ?: null, ['archived_at' => date('Y-m-d H:i:s')]);
                $alert = 'Customer archived successfully.';
            } else {
                db_exec($link, "DELETE FROM costumer WHERE `{$cust_pk}` = ?", 'i', [$delete_id]);
                audit($link, 'DELETE', 'customer', $delete_id, $old_customer ?: null, null);
                $alert = 'Customer deleted successfully.';
            }
            $alert_type = 'success';
        }
    }
}

// Handle Restore Customer (Phase B Item 4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_customer'])) {
    csrf_verify();
    require_role('super_admin');
    $restore_id = (int)($_POST['restore_id'] ?? 0);
    if ($restore_id > 0 && $has_archived_col) {
        $cust_row = db_one($link, "SELECT name, email FROM costumer WHERE `{$cust_pk}` = ?", 'i', [$restore_id]);
        if ($cust_row) {
            db_exec($link, "UPDATE costumer SET archived_at = NULL WHERE `{$cust_pk}` = ?", 'i', [$restore_id]);
            audit($link, 'RESTORE', 'customer', $restore_id, ['archived' => true], ['archived' => false]);
            $alert = "Customer '{$cust_row['name']}' restored successfully.";
            $alert_type = 'success';
        }
    }
}

// Tab Filter: active vs archived
$view_tab = trim((string)($_GET['tab'] ?? 'active'));
$where_archive = ($has_archived_col && $view_tab === 'archived') ? 'WHERE archived_at IS NOT NULL' : ($has_archived_col ? 'WHERE archived_at IS NULL' : '');

// 25-item Pagination (P-10)
$total_customers = (int)(db_one($link, "SELECT COUNT(*) AS c FROM costumer {$where_archive}")['c'] ?? 0);
$pagination = paginate($total_customers, 25);
$customers = db_all($link, "SELECT * FROM costumer {$where_archive} ORDER BY `{$cust_pk}` ASC LIMIT ? OFFSET ?", 'ii', [$pagination['per_page'], $pagination['offset']]);

$title = 'Customers';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Customer Accounts</h1>
        <p class="page-subtitle">Manage registered passenger profiles, credentials, contact records, and addresses.</p>
    </div>
    <?php if (can_write()): ?>
    <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addCustomerModal">
        + Register Customer
    </button>
    <?php endif; ?>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<?php if ($has_archived_col): ?>
<div class="mb-3">
    <div class="btn-group btn-group-sm" role="group">
        <a href="customers.php?tab=active" class="btn <?= $view_tab !== 'archived' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            Active Customers
        </a>
        <a href="customers.php?tab=archived" class="btn <?= $view_tab === 'archived' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            Archived Customers
        </a>
    </div>
</div>
<?php endif; ?>

<div class="data-table-wrapper">
    <div class="table-header">
        <h5 class="mb-0"><?= $view_tab === 'archived' ? 'Archived Customer Accounts' : 'Active Customer Directory' ?></h5>
        <span class="record-count"><?= $pagination['total_records'] ?> user(s)</span>
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
                                <div class="empty-title"><?= $view_tab === 'archived' ? 'No archived customers' : 'No customers registered' ?></div>
                                <div class="empty-text"><?= $view_tab === 'archived' ? 'Customer accounts you archive will appear here.' : "Click '+ Register Customer' to add a passenger account." ?></div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $row): ?>
                        <?php 
                        $cid = (int)($row[$cust_pk] ?? $row['id'] ?? $row['sno'] ?? 0);
                        $is_archived = !empty($row['archived_at']);
                        ?>
                        <tr class="<?= $is_archived ? 'text-muted bg-light' : '' ?>">
                            <td><span class="text-muted small">#<?= e($cid) ?></span></td>
                            <td class="font-weight-medium text-dark">
                                <?= e($row['name'] ?? '') ?>
                                <?php if ($is_archived): ?>
                                    <span class="badge badge-secondary ml-1">Archived</span>
                                <?php endif; ?>
                            </td>
                            <td><a href="mailto:<?= e($row['email'] ?? '') ?>" class="text-primary"><?= e($row['email'] ?? '') ?></a></td>
                            <td><span class="badge badge-light border text-muted">••••••••</span></td>
                            <td><?= e($row['phone'] ?? '') ?></td>
                            <td><small class="text-muted"><?= e($row['address'] ?? 'N/A') ?></small></td>
                            <td class="text-right">
                                <?php if ($is_archived && is_super_admin()): ?>
                                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('Restore this customer account?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="restore_id" value="<?= e($cid) ?>">
                                        <button type="submit" name="restore_customer" class="btn btn-outline-success btn-sm">Restore</button>
                                    </form>
                                <?php elseif (!$is_archived): ?>
                                    <?php if (can_write()): ?>
                                    <a href="<?= BASE_URL ?>/admin/edit/edit-customer.php?id=<?= e($cid) ?>" class="btn btn-outline-secondary btn-sm">Edit</a>
                                    <?php endif; ?>
                                    <?php if (is_super_admin()): ?>
                                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to archive this customer?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_id" value="<?= e($cid) ?>">
                                        <button type="submit" name="delete_customer" class="btn btn-outline-danger btn-sm ml-1">Archive</button>
                                    </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($pagination) ?>
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