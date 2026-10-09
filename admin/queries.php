<?php
// admin/queries.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

// Detect primary key column for query table
$query_pk = table_has_column($link, 'query', 'sno') ? 'sno' : 'id';
$has_status = table_has_column($link, 'query', 'status');

// Handle Reply to Query (Item 10)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    csrf_verify();
    require_role('super_admin', 'operator');
    $reply_id = (int)($_POST['query_id'] ?? 0);
    $reply_text = trim((string)($_POST['reply_message'] ?? ''));

    if ($reply_id > 0 && $reply_text !== '') {
        $q_row = db_one($link, "SELECT * FROM `query` WHERE `{$query_pk}` = ?", 'i', [$reply_id]);
        if ($q_row) {
            $to_email = $q_row['user_email'] ?? $q_row['email'] ?? '';
            $to_name = $q_row['user_name'] ?? $q_row['name'] ?? 'Passenger';
            $subject = 'Re: ' . ($q_row['user_subject'] ?? $q_row['subject'] ?? 'Your Inquiry');

            $mail_body = "
                <p>Dear " . e($to_name) . ",</p>
                <p>" . nl2br(e($reply_text)) . "</p>
                <hr>
                <p><small style='color: #666;'>Original message:<br>" . nl2br(e($q_row['user_qry'] ?? $q_row['query'] ?? '')) . "</small></p>
            ";

            $mail_result = send_app_mail($to_email, $subject, $mail_body);

            if (!$mail_result['ok']) {
                flash_set('danger', 'Reply not sent: ' . $mail_result['error']);
                try {
                    audit($link, 'UPDATE', 'query', $reply_id, null, ['reply_sent' => false, 'error' => $mail_result['error']]);
                } catch (Throwable $e) {}
            } else {
                if ($has_status) {
                    db_exec($link, "UPDATE `query` SET `status` = 'replied', `replied_at` = NOW(), `reply_text` = ? WHERE `{$query_pk}` = ?", 'si', [$reply_text, $reply_id]);
                }
                try {
                    audit($link, 'UPDATE', 'query', $reply_id, ['status' => $q_row['status'] ?? 'new'], ['status' => 'replied', 'reply_sent' => true]);
                } catch (Throwable $e) {}
                flash_set('success', 'Reply sent to ' . $to_email . ' successfully.');
            }
        } else {
            flash_set('danger', 'Query record not found.');
        }
    } else {
        flash_set('danger', 'Please provide a valid query and reply message.');
    }
    $redirect_url = BASE_URL . '/admin/queries.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

// Handle Mark Closed / Open (Item 10)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    csrf_verify();
    require_role('super_admin', 'operator');
    $qid = (int)($_POST['query_id'] ?? 0);
    $new_st = trim((string)($_POST['new_status'] ?? ''));
    if ($qid > 0 && in_array($new_st, ['new', 'replied', 'closed'], true) && $has_status) {
        db_exec($link, "UPDATE `query` SET `status` = ? WHERE `{$query_pk}` = ?", 'si', [$new_st, $qid]);
        audit($link, 'UPDATE', 'query', $qid, null, ['status' => $new_st]);
        flash_set('success', "Status updated to '{$new_st}'.");
    } else {
        flash_set('danger', 'Failed to update query status.');
    }
    $redirect_url = BASE_URL . '/admin/queries.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

// Handle Delete Query (Phase A Items 1 & 3)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_query'])) {
    csrf_verify();
    require_role('super_admin');
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        $old_query = db_one($link, "SELECT * FROM `query` WHERE `{$query_pk}` = ?", 'i', [$delete_id]);
        db_exec($link, "DELETE FROM `query` WHERE `{$query_pk}` = ?", 'i', [$delete_id]);
        audit($link, 'DELETE', 'query', $delete_id, $old_query ?: null, null);
        flash_set('success', 'Query deleted successfully.');
    } else {
        flash_set('danger', 'Invalid query ID for deletion.');
    }
    $redirect_url = BASE_URL . '/admin/queries.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

// Status filtering (Item 10)
$filter_status = trim((string)($_GET['status'] ?? 'all'));
$where_status = '';
$status_param = [];
$status_types = '';
if ($has_status && in_array($filter_status, ['new', 'replied', 'closed'], true)) {
    $where_status = 'WHERE `status` = ?';
    $status_param = [$filter_status];
    $status_types = 's';
}

// 25-item Pagination (P-10)
$count_sql = "SELECT COUNT(*) AS c FROM `query` {$where_status}";
$total_queries = (int)(!empty($status_param) ? db_one($link, $count_sql, $status_types, $status_param)['c'] : db_one($link, $count_sql)['c'] ?? 0);
$pagination = paginate($total_queries, 25);

$query_sql = "SELECT * FROM `query` {$where_status} ORDER BY `{$query_pk}` DESC LIMIT ? OFFSET ?";
$query_params = array_merge($status_param, [$pagination['per_page'], $pagination['offset']]);
$query_types = $status_types . 'ii';
$queries = db_all($link, $query_sql, $query_types, $query_params);

$title = 'Customer Queries';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Passenger Inquiries & Feedback</h1>
        <p class="page-subtitle">Review contact submissions, reply to customer tickets via email, and maintain customer satisfaction.</p>
    </div>
</div>


<!-- Status Filter Tabs (Item 10) -->
<?php if ($has_status): ?>
<div class="mb-3">
    <div class="btn-group btn-group-sm" role="group">
        <a href="queries.php" class="btn <?= $filter_status === 'all' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            All Tickets
        </a>
        <a href="queries.php?status=new" class="btn <?= $filter_status === 'new' ? 'btn-danger' : 'btn-outline-danger' ?>">
            New / Unread
        </a>
        <a href="queries.php?status=replied" class="btn <?= $filter_status === 'replied' ? 'btn-success' : 'btn-outline-success' ?>">
            Replied
        </a>
        <a href="queries.php?status=closed" class="btn <?= $filter_status === 'closed' ? 'btn-secondary' : 'btn-outline-secondary' ?>">
            Closed
        </a>
    </div>
</div>
<?php endif; ?>

<div class="data-table-wrapper">
    <div class="table-header">
        <h5 class="mb-0">Support Inquiries</h5>
        <span class="record-count"><?= $pagination['total_records'] ?> message(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Status</th>
                    <th>Sender</th>
                    <th>Subject & Message</th>
                    <th>Date Received</th>
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
                        $qst = $row['status'] ?? 'new';
                        $status_badge = match($qst) {
                            'replied' => 'badge-success',
                            'closed'  => 'badge-secondary',
                            default   => 'badge-danger',
                        };
                        ?>
                        <tr>
                            <td><span class="text-muted small">#<?= e($qid) ?></span></td>
                            <td>
                                <span class="badge <?= $status_badge ?>"><?= ucfirst(e($qst)) ?></span>
                            </td>
                            <td>
                                <strong class="text-dark"><?= e($name) ?></strong>
                                <div><a href="mailto:<?= e($email) ?>" class="text-primary small"><?= e($email) ?></a></div>
                            </td>
                            <td style="max-width: 320px;">
                                <div class="font-weight-bold text-dark"><?= e($subject ?: '(No Subject)') ?></div>
                                <div class="text-muted small" style="white-space: pre-wrap;"><?= e($comment) ?></div>
                                <?php if (!empty($row['reply_text'])): ?>
                                    <div class="alert alert-light border mt-2 p-2 small mb-0">
                                        <strong class="text-success">Sent Reply:</strong><br>
                                        <?= nl2br(e($row['reply_text'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?= !empty($row['created_at']) ? e(date('d M Y, H:i', strtotime($row['created_at']))) : 'N/A' ?>
                                </small>
                            </td>
                            <td class="text-right">
                                <?php if (can_write()): ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm mr-1" data-toggle="modal" data-target="#replyModal<?= $qid ?>">
                                        Reply
                                    </button>
                                <?php endif; ?>
                                <?php if (is_super_admin()): ?>
                                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this query?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_id" value="<?= e($qid) ?>">
                                        <button type="submit" name="delete_query" class="btn btn-outline-danger btn-sm">
                                            Dismiss
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Reply Modal -->
                                <div class="modal fade text-left" id="replyModal<?= $qid ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-dark text-white">
                                                <h5 class="modal-title font-weight-bold">Reply to <?= e($name) ?></h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                            </div>
                                            <form action="" method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="query_id" value="<?= e($qid) ?>">
                                                <div class="modal-body p-4">
                                                    <div class="form-group">
                                                        <label class="small font-weight-bold text-muted">Recipient Email</label>
                                                        <input type="text" class="form-control" value="<?= e($email) ?>" disabled>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="small font-weight-bold text-muted">Inquiry Subject</label>
                                                        <input type="text" class="form-control" value="<?= e($subject ?: 'Inquiry #' . $qid) ?>" disabled>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="reply_message<?= $qid ?>" class="small font-weight-bold text-muted">Your Response</label>
                                                        <textarea name="reply_message" id="reply_message<?= $qid ?>" class="form-control" rows="5" placeholder="Type your response to the passenger here..." required></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="send_reply" class="btn btn-primary btn-sm">Send Email Response</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($pagination, $filter_status !== 'all' ? ['status' => $filter_status] : []) ?>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>