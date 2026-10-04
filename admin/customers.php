<?php
// admin/customers.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

// Detect primary key column for customer table
$cust_pk = 'id';
$col_check = mysqli_query($link, "SHOW COLUMNS FROM `costumer` LIKE 'sno'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
    $cust_pk = 'sno';
}

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

// Handle Delete Customer (converted from insecure GET to POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    csrf_verify();
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        // P-09: Prevent deleting customer with active bookings
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
<div class="admin-content-wrap">
  <h1 class="text-info">Customer Management</h1>
  <br>

  <?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
      <?= e($alert) ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
  <?php endif; ?>

  <button class="btn btn-danger" data-toggle="modal" data-target="#addCustomerModal">Add Customer Details</button>

  <div class="modal fade" id="addCustomerModal">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <button type="button" class="close text-right pr-3 pt-2" data-dismiss="modal">
          <span>&times;</span>
        </button>
        <div class="modal-header">
          <h5 class="modal-title">Customer Details</h5>
        </div>
        <div class="modal-body">
          <form action="" method="post">
            <?= csrf_field() ?>
            <div class="form-group">
              <label for="unm">Name :</label>
              <input type="text" id="unm" name="unm" class="form-control" placeholder="Enter full name" required />
            </div>
            <div class="form-group">
              <label for="email">Email Id :</label>
              <input type="email" id="email" name="email" class="form-control" placeholder="Enter email" required />
            </div>
            <div class="form-group">
              <label for="pwd">Password :</label>
              <input type="password" id="pwd" name="pwd" class="form-control" placeholder="Minimum 8 characters" minlength="8" required />
            </div>
            <div class="form-group">
              <label for="phone">Phone :</label>
              <input type="tel" id="phone" name="phone" class="form-control" placeholder="Enter phone number" required />
            </div>
            <div class="form-group">
              <label for="address">Address :</label>
              <textarea id="address" name="address" class="form-control" placeholder="Enter address" rows="2"></textarea>
            </div>
            <div class="form-group">
              <input type="submit" class="btn btn-success" name="add" value="Submit" />
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <section class="mt-4">
    <h4 class="text-secondary mb-3">All Customers</h4>
    <div class="table-responsive">
      <table class="table table-bordered table-striped">
        <thead class="thead-dark">
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Password</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Edit</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($customers)): ?>
            <tr><td colspan="8" class="text-center text-muted">No customers found.</td></tr>
          <?php else: ?>
            <?php foreach ($customers as $row): ?>
              <?php $cid = (int)($row[$cust_pk] ?? $row['id'] ?? $row['sno'] ?? 0); ?>
              <tr>
                <td><?= e($cid) ?></td>
                <td><?= e($row['name'] ?? '') ?></td>
                <td><?= e($row['email'] ?? '') ?></td>
                <td><span class="text-muted">••••••••</span></td>
                <td><?= e($row['phone'] ?? '') ?></td>
                <td><?= e($row['address'] ?? '') ?></td>
                <td>
                  <a href="<?= BASE_URL ?>/admin/edit/edit-customer.php?id=<?= e($cid) ?>" class="btn btn-warning btn-sm">Edit</a>
                </td>
                <td>
                  <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this customer?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_id" value="<?= e($cid) ?>">
                    <button type="submit" name="delete_customer" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>