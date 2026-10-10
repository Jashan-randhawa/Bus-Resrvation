<?php
// admin/customers.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/admin-crud.php';

// Detect primary key column for customer table
$cust_pk = table_has_column($link, 'costumer', 'sno') ? 'sno' : 'id';

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
        $_SESSION['form_old'] = ['unm' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address];
        flash_set('danger', 'Please provide a valid name and email address.');
    } elseif (strlen($pwd) < 8) {
        $_SESSION['form_old'] = ['unm' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address];
        flash_set('danger', 'Password must be at least 8 characters.');
    } else {
        $existing = db_one($link, 'SELECT * FROM costumer WHERE email = ?', 's', [$email]);
        if ($existing) {
            $_SESSION['form_old'] = ['unm' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address];
            flash_set('danger', 'A customer with that email already exists.');
        } else {
            unset($_SESSION['form_old']);
            $hashed = password_hash($pwd, PASSWORD_DEFAULT);
            db_exec($link,
                "INSERT INTO costumer (name, email, pwd, phone, address) VALUES (?, ?, ?, ?, ?)",
                'sssss',
                [$name, $email, $hashed, $phone, $address]
            );
            audit($link, 'CREATE', 'customer', (int)mysqli_insert_id($link), null, ['name' => $name, 'email' => $email, 'phone' => $phone]);
            flash_set('success', 'Customer added successfully.');
        }
    }
    $redirect_url = BASE_URL . '/admin/customers.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
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
            flash_set('danger', 'Cannot archive customer account because they have active ticket reservations.');
        } else {
            if ($has_archived_col) {
                admin_archive_record($link, 'costumer', $cust_pk, $delete_id, 'customer', $old_customer ?: null);
                flash_set('success', 'Customer archived successfully.');
            } else {
                admin_archive_record($link, 'costumer', $cust_pk, $delete_id, 'customer', $old_customer ?: null);
                flash_set('success', 'Customer deleted successfully.');
            }
        }
    }
    $redirect_url = BASE_URL . '/admin/customers.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

// Handle Restore Customer (Phase B Item 4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_customer'])) {
    csrf_verify();
    require_role('super_admin');
    $restore_id = (int)($_POST['restore_id'] ?? 0);
    if ($restore_id > 0 && $has_archived_col) {
        $cust_row = db_one($link, "SELECT name, email FROM costumer WHERE `{$cust_pk}` = ?", 'i', [$restore_id]);
        if ($cust_row) {
            admin_restore_record($link, 'costumer', $cust_pk, $restore_id, 'customer');
            flash_set('success', "Customer '{$cust_row['name']}' restored successfully.");
        }
    }
    $redirect_url = BASE_URL . '/admin/customers.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

// Tab Filter: active vs archived
$view_tab = admin_get_archive_tab();
$search = trim((string)($_GET['q'] ?? ''));


$where_clauses = [];
$params = [];
$types = '';

if ($has_archived_col) {
    if ($view_tab === 'archived') {
        $where_clauses[] = 'archived_at IS NOT NULL';
    } else {
        $where_clauses[] = 'archived_at IS NULL';
    }
}

if ($search !== '') {
    $where_clauses[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR address LIKE ?)';
    $escaped_search = escape_like($search);
    $s_param = '%' . $escaped_search . '%';
    $params = array_merge($params, [$s_param, $s_param, $s_param, $s_param]);
    $types .= 'ssss';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// CSV Export with Role Protection & PII Masking (Issue 7)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $is_viewer = !can_write();
    $export_rows = !empty($params)
        ? db_all($link, "SELECT id, name, email, phone, address, archived_at FROM costumer {$where_sql} ORDER BY `{$cust_pk}` ASC", $types, $params)
        : db_all($link, "SELECT id, name, email, phone, address, archived_at FROM costumer {$where_sql} ORDER BY `{$cust_pk}` ASC");

    try {
        audit($link, 'EXPORT', 'customer', null, null, [
            'format' => 'csv',
            'count'  => count($export_rows),
            'masked' => $is_viewer,
            'tab'    => $view_tab,
            'search' => $search
        ]);
    } catch (Throwable $e) {}

    $headers = ['Customer ID', 'Full Name', 'Email Address', 'Phone Number', 'Address', 'Archived Timestamp'];
    $cleaned_export = [];
    foreach ($export_rows as $row) {
        $cleaned_export[] = [
            $row['id'],
            $row['name'],
            $is_viewer ? mask_email((string)($row['email'] ?? '')) : (string)($row['email'] ?? ''),
            $is_viewer ? mask_phone((string)($row['phone'] ?? '')) : (string)($row['phone'] ?? ''),
            $is_viewer ? '[Masked]' : ($row['address'] ?? ''),
            $row['archived_at'] ?? 'Active'
        ];
    }
    export_csv('customers-export-' . date('Ymd-His') . '.csv', $headers, $cleaned_export);
}

// 25-item Pagination (P-10)
$count_sql = "SELECT COUNT(*) AS c FROM costumer {$where_sql}";
$count_res = !empty($params) ? db_one($link, $count_sql, $types, $params) : db_one($link, $count_sql);
$total_customers = (int)($count_res['c'] ?? 0);
$pagination = paginate($total_customers, 25);

$query_sql = "SELECT * FROM costumer {$where_sql} ORDER BY `{$cust_pk}` ASC LIMIT ? OFFSET ?";
$query_params = array_merge($params, [$pagination['per_page'], $pagination['offset']]);
$query_types = $types . 'ii';
$customers = db_all($link, $query_sql, $query_types, $query_params);

$keep_params = array_filter([
    'tab' => $view_tab !== 'active' ? $view_tab : null,
    'q'   => $search !== '' ? $search : null,
], fn($v) => $v !== null);

$form_old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

$title = 'Customers';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Customer Accounts</h1>
        <p class="page-subtitle">Manage registered passenger profiles, credentials, contact records, and addresses.</p>
    </div>
    <div class="d-flex align-items-center">
        <a href="?<?= http_build_query(array_merge($keep_params, ['export' => 'csv'])) ?>" class="btn btn-outline-success btn-sm mr-2 shadow-sm font-weight-bold">
            📥 Export CSV
        </a>
        <?php if (can_write()): ?>
        <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addCustomerModal">
            + Register Customer
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Search Bar (Item 8) -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="customers.php" method="get" class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
            <?php if ($view_tab !== 'active'): ?>
                <input type="hidden" name="tab" value="<?= e($view_tab) ?>">
            <?php endif; ?>
            <div class="form-group mb-0 flex-grow-1">
                <input type="text" name="q" class="form-control form-control-sm w-100" placeholder="Search name, email, phone, city..." value="<?= e($search) ?>">
            </div>
            <div class="d-flex align-items-center gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-fill flex-sm-auto">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="customers.php<?= $view_tab !== 'active' ? '?tab=' . urlencode($view_tab) : '' ?>" class="btn btn-outline-secondary btn-sm flex-fill flex-sm-auto">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?= $has_archived_col ? admin_archive_tabs_html($view_tab, 'Active Customers', 'Archived Customers', ['q' => $search]) : '' ?>


<div class="data-table-wrapper">
    <div class="table-header">
        <h5 class="mb-0"><?= $view_tab === 'archived' ? 'Archived Customer Accounts' : 'Active Customer Directory' ?></h5>
        <span class="record-count"><?= $pagination['total_records'] ?> user(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-stack mb-0">
            <caption class="sr-only">Directory of registered customer accounts, contact details, and account status</caption>
            <thead class="thead-light">
                <tr>
                    <th scope="col" class="th-id-sm">#</th>
                    <th scope="col">Full Name</th>
                    <th scope="col">Email Address</th>
                    <th scope="col">Security</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Address</th>
                    <th scope="col" class="text-right">Actions</th>
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
                            <td data-label="ID"><span class="text-muted small">#<?= e($cid) ?></span></td>
                            <td data-label="Full Name" class="font-weight-medium text-dark">
                                <?= e($row['name'] ?? '') ?>
                                <?php if ($is_archived): ?>
                                    <span class="badge badge-secondary ml-1">Archived</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Email Address"><a href="mailto:<?= e($row['email'] ?? '') ?>" class="text-primary"><?= e($row['email'] ?? '') ?></a></td>
                            <td data-label="Security"><span class="badge badge-light border text-muted">••••••••</span></td>
                            <td data-label="Phone"><?= e($row['phone'] ?? '') ?></td>
                            <td data-label="Address"><small class="text-muted"><?= e($row['address'] ?? 'N/A') ?></small></td>
                            <td data-label="Actions" class="text-right">
                                <?= render_crud_action_buttons($cid, BASE_URL . "/admin/edit/edit-customer.php?id=" . $cid, $is_archived, 'delete_customer', 'restore_customer', 'customer account') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($pagination, $keep_params) ?>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" role="dialog" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold" id="addCustomerModalLabel">Register Passenger</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="unm" class="font-weight-bold small text-muted">Full Name</label>
                        <input type="text" id="unm" name="unm" class="form-control" value="<?= e($form_old['unm'] ?? '') ?>" placeholder="Jane Doe" required />
                    </div>
                    <div class="form-group">
                        <label for="email" class="font-weight-bold small text-muted">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= e($form_old['email'] ?? '') ?>" placeholder="jane@example.com" required />
                    </div>
                    <div class="form-row">
                        <div class="col-6 form-group">
                            <label for="pwd" class="font-weight-bold small text-muted">Initial Password</label>
                            <input type="password" id="pwd" name="pwd" class="form-control" placeholder="8+ chars" minlength="8" required />
                        </div>
                        <div class="col-6 form-group">
                            <label for="phone" class="font-weight-bold small text-muted">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($form_old['phone'] ?? '') ?>" placeholder="Phone" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address" class="font-weight-bold small text-muted">Address</label>
                        <textarea id="address" name="address" class="form-control" placeholder="Street, city..." rows="2"><?= e($form_old['address'] ?? '') ?></textarea>
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