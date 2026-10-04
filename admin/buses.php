<?php
// admin/buses.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

// Detect primary key column for buses (supports both `id` and `sno` schemas)
$bus_pk = 'id';
$col_check = mysqli_query($link, "SHOW COLUMNS FROM `buses` LIKE 'sno'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
    $bus_pk = 'sno';
}

$alert = null;
$alert_type = 'info';

// Handle Add Bus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    csrf_verify();
    $busno = trim((string)($_POST['busno'] ?? ''));
    if ($busno === '') {
        $alert = 'Bus number is required.';
        $alert_type = 'danger';
    } else {
        $existing = db_one($link, 'SELECT * FROM buses WHERE bus_number = ?', 's', [$busno]);
        if ($existing) {
            $alert = 'A bus with that number already exists.';
            $alert_type = 'danger';
        } else {
            db_exec($link, 'INSERT INTO buses (bus_number) VALUES (?)', 's', [$busno]);
            $alert = 'Bus added successfully.';
            $alert_type = 'success';
        }
    }
}

// Handle Delete Bus (converted from insecure GET to POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bus'])) {
    csrf_verify();
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        db_exec($link, "DELETE FROM buses WHERE `{$bus_pk}` = ?", 'i', [$delete_id]);
        $alert = 'Bus deleted successfully.';
        $alert_type = 'success';
    }
}

$buses = db_all($link, 'SELECT * FROM buses ORDER BY ' . $bus_pk . ' ASC');

require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="col-lg-10 col-md-12 col-sm-12" style=" float: right ; ">
  <h1 class="text-info">Bus Status</h1>
  <br>

  <?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
      <?= e($alert) ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
  <?php endif; ?>

  <button class="btn btn-danger" data-toggle="modal" data-target="#addBusModal">Add Bus Details</button>

  <div class="modal fade" id="addBusModal">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <button type="button" class="close text-right pr-3 pt-2" data-dismiss="modal">
          <span>&times;</span>
        </button>
        <div class="modal-header">
          <h5 class="modal-title">Add Bus Number</h5>
        </div>
        <div class="modal-body">
          <form action="" method="post">
            <?= csrf_field() ?>
            <div class="form-group">
              <label for="busno">Bus Number :</label>
              <input type="text" id="busno" name="busno" class="form-control" placeholder="Bus Number" required />
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
    <h4 class="text-secondary mb-3">All Buses</h4>
    <div class="table-responsive">
      <table class="table table-bordered table-striped">
        <thead class="thead-dark">
          <tr>
            <th>#</th>
            <th>Bus Number</th>
            <th>Edit</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($buses)): ?>
            <tr><td colspan="4" class="text-center text-muted">No buses found.</td></tr>
          <?php else: ?>
            <?php foreach ($buses as $row): ?>
              <?php $bid = (int)($row[$bus_pk] ?? $row['id'] ?? $row['sno'] ?? 0); ?>
              <tr>
                <td><?= e($bid) ?></td>
                <td><?= e($row['bus_number'] ?? '') ?></td>
                <td>
                  <a href="<?= BASE_URL ?>/admin/edit/edit-bus.php?id=<?= e($bid) ?>" class="btn btn-warning btn-sm">Edit</a>
                </td>
                <td>
                  <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this bus?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_id" value="<?= e($bid) ?>">
                    <button type="submit" name="delete_bus" class="btn btn-danger btn-sm">Delete</button>
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