<?php
// admin/routes.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

// Detect primary key column for route table
$route_pk = 'sno';
$col_check = mysqli_query($link, "SHOW COLUMNS FROM `route` LIKE 'sno'");
if (!$col_check || mysqli_num_rows($col_check) === 0) {
    $route_pk = 'id';
}

$alert = null;
$alert_type = 'info';

// Handle Add Route (O11, O12)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    csrf_verify();
    $from = trim((string)($_POST['From'] ?? ''));
    $to = trim((string)($_POST['To'] ?? ''));
    $bus = trim((string)($_POST['bus'] ?? ''));
    $time = trim((string)($_POST['time'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);

    if ($from === '' || $to === '' || $bus === '' || $time === '' || $price <= 0) {
        $alert = 'Please fill all route fields with valid values.';
        $alert_type = 'danger';
    } elseif (strcasecmp($from, $to) === 0) {
        $alert = 'Origin and destination cities cannot be the same.';
        $alert_type = 'danger';
    } else {
        // O11: Check for route conflict (same bus assigned at same departure time)
        $conflict = db_one($link, 'SELECT * FROM route WHERE busno = ? AND `time` = ?', 'ss', [$bus, $time]);
        if ($conflict) {
            $alert = "Bus '{$bus}' is already scheduled to depart at {$time} ({$conflict['city1']} -> {$conflict['city2']}).";
            $alert_type = 'danger';
        } else {
            db_exec($link,
                "INSERT INTO route (city1, city2, busno, time, price) VALUES (?, ?, ?, ?, ?)",
                'ssssd',
                [$from, $to, $bus, $time, $price]
            );
            $alert = 'Route added successfully.';
            $alert_type = 'success';
        }
    }
}

// Handle Delete Route (O10: verify active bookings before deleting route)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_route'])) {
    csrf_verify();
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        $route_row = db_one($link, "SELECT * FROM route WHERE `{$route_pk}` = ?", 'i', [$delete_id]);
        if (!$route_row) {
            $alert = 'Route not found.';
            $alert_type = 'danger';
        } else {
            $r_bus = (string)$route_row['busno'];
            $r_time = (string)$route_row['time'];
            $today = date('Y-m-d');
            // Check active bookings for this route's bus and departure time on/after today
            $active_bookings = db_one($link,
                "SELECT COUNT(*) AS n FROM booking WHERE bus = ? AND `time` = ? AND `date` >= ? AND (status IS NULL OR status != 'Cancelled')",
                'sss', [$r_bus, $r_time, $today]
            );

            if ((int)($active_bookings['n'] ?? 0) > 0) {
                $alert = "Cannot delete route ({$route_row['city1']} -> {$route_row['city2']} at {$r_time}) because there are active upcoming bookings.";
                $alert_type = 'danger';
            } else {
                db_exec($link, "DELETE FROM route WHERE `{$route_pk}` = ?", 'i', [$delete_id]);
                $alert = 'Route deleted successfully.';
                $alert_type = 'success';
            }
        }
    }
}

$buses = db_all($link, 'SELECT bus_number FROM buses ORDER BY bus_number ASC');
$routes = db_all($link, "SELECT * FROM route ORDER BY `{$route_pk}` ASC");

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

  <button class="btn btn-danger" data-toggle="modal" data-target="#addRouteModal">Add Route Details</button>

  <div class="modal fade" id="addRouteModal">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <button type="button" class="close text-right pr-3 pt-2" data-dismiss="modal">
          <span>&times;</span>
        </button>
        <div class="modal-header">
          <h5 class="modal-title">Add Bus Route Details</h5>
        </div>
        <div class="modal-body">
          <form action="" method="post">
            <?= csrf_field() ?>
            <div class="form-group">
              <label for="From">From :</label>
              <input type="text" id="From" name="From" class="form-control" placeholder="From city" required />
            </div>
            <div class="form-group">
              <label for="To">To :</label>
              <input type="text" id="To" name="To" class="form-control" placeholder="To city" required />
            </div>
            <div class="form-group">
              <label for="bus">Bus no :</label>
              <select name="bus" id="bus" class="form-control" required>
                <option value="">Select Bus Number</option>
                <?php foreach ($buses as $b): ?>
                  <option value="<?= e($b['bus_number']) ?>"><?= e($b['bus_number']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="time">Departure Time :</label>
              <input type="time" id="time" name="time" class="form-control" required />
            </div>
            <div class="form-group">
              <label for="price">Ticket Price :</label>
              <input type="number" step="0.01" min="1" id="price" name="price" class="form-control" placeholder="Ticket Price" required />
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
    <h4 class="text-secondary mb-3">All Routes</h4>
    <div class="table-responsive">
      <table class="table table-bordered table-striped">
        <thead class="thead-dark">
          <tr>
            <th>#</th>
            <th>From</th>
            <th>To</th>
            <th>Bus Number</th>
            <th>Time</th>
            <th>Price</th>
            <th>Edit</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($routes)): ?>
            <tr><td colspan="8" class="text-center text-muted">No routes found.</td></tr>
          <?php else: ?>
            <?php foreach ($routes as $row): ?>
              <?php $rid = (int)($row[$route_pk] ?? $row['sno'] ?? $row['id'] ?? 0); ?>
              <tr>
                <td><?= e($rid) ?></td>
                <td><?= e($row['city1'] ?? '') ?></td>
                <td><?= e($row['city2'] ?? '') ?></td>
                <td><?= e($row['busno'] ?? '') ?></td>
                <td><?= e($row['time'] ?? '') ?></td>
                <td>$<?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                <td>
                  <a href="<?= BASE_URL ?>/admin/edit/edit-route.php?id=<?= e($rid) ?>" class="btn btn-warning btn-sm">Edit</a>
                </td>
                <td>
                  <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this route?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_id" value="<?= e($rid) ?>">
                    <button type="submit" name="delete_route" class="btn btn-danger btn-sm">Delete</button>
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