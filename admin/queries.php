<?php
// admin/queries.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

// Detect primary key column for query table
$query_pk = 'id';
$col_check = mysqli_query($link, "SHOW COLUMNS FROM `query` LIKE 'sno'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
    $query_pk = 'sno';
}

$alert = null;
$alert_type = 'info';

// Handle Delete Query (converted to POST with CSRF and fixed target table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_query'])) {
    csrf_verify();
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        db_exec($link, "DELETE FROM `query` WHERE `{$query_pk}` = ?", 'i', [$delete_id]);
        $alert = 'Query deleted successfully.';
        $alert_type = 'success';
    }
}

$queries = db_all($link, "SELECT * FROM `query` ORDER BY `{$query_pk}` DESC");

require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="col-lg-10 col-md-12 col-sm-12" style=" float: right ; ">
  <h1 class="text-info">Customer Queries & Feedback</h1>
  <br>

  <?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
      <?= e($alert) ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
  <?php endif; ?>

  <section class="mt-2">
    <div class="table-responsive">
      <table class="table table-bordered table-striped">
        <thead class="thead-dark">
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email Id</th>
            <th>Subject</th>
            <th>Comment / Query</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($queries)): ?>
            <tr><td colspan="6" class="text-center text-muted">No customer queries found.</td></tr>
          <?php else: ?>
            <?php foreach ($queries as $row): ?>
              <?php
              $qid = (int)($row[$query_pk] ?? $row['id'] ?? $row['sno'] ?? 0);
              $name = $row['user_name'] ?? $row['name'] ?? '';
              $email = $row['user_email'] ?? $row['email'] ?? '';
              $subject = $row['user_subject'] ?? $row['subject'] ?? '';
              $comment = $row['user_qry'] ?? $row['query'] ?? '';
              ?>
              <tr>
                <td><?= e($qid) ?></td>
                <td><?= e($name) ?></td>
                <td><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></td>
                <td><?= e($subject) ?></td>
                <td style="white-space: pre-wrap;"><?= e($comment) ?></td>
                <td>
                  <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this query?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_id" value="<?= e($qid) ?>">
                    <button type="submit" name="delete_query" class="btn btn-danger btn-sm">Delete</button>
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