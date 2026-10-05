<?php
// admin/queries.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

// Detect primary key column for query table
$query_pk = table_has_column($link, 'query', 'sno') ? 'sno' : 'id';

$alert = null;
$alert_type = 'info';

// Handle Delete Query
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

$title = 'Customer Queries';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Passenger Inquiries & Feedback</h1>
        <p class="page-subtitle">Review and manage contact submissions, support tickets, and passenger inquiries.</p>
    </div>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<div class="data-table-wrapper">
    <div class="table-header">
        <h5 class="mb-0">Support Inquiries</h5>
        <span class="record-count"><?= count($queries) ?> message(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th style="width: 70px;">#</th>
                    <th>Sender Name</th>
                    <th>Email Address</th>
                    <th>Subject</th>
                    <th>Message Content</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queries)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state py-5">
                                <div class="empty-icon">💬</div>
                                <div class="empty-title">Inbox is clear</div>
                                <div class="empty-text">No passenger inquiries or complaints currently pending.</div>
                            </div>
                        </td>
                    </tr>
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
                            <td><span class="text-muted small">#<?= e($qid) ?></span></td>
                            <td class="font-weight-medium text-dark"><?= e($name) ?></td>
                            <td><a href="mailto:<?= e($email) ?>" class="text-primary font-weight-medium"><?= e($email) ?></a></td>
                            <td class="font-weight-bold text-dark"><?= e($subject ?: '(No Subject)') ?></td>
                            <td style="max-width: 380px; white-space: pre-wrap;" class="text-muted small"><?= e($comment) ?></td>
                            <td class="text-right">
                                <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this query?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delete_id" value="<?= e($qid) ?>">
                                    <button type="submit" name="delete_query" class="btn btn-outline-danger btn-sm">
                                        Dismiss
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>