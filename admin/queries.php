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
        $alert = "Status updated to '{$new_st}'.";
        flash_set('success', $alert);
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

<style>
.query-content-cell {
    max-width: 320px;
    width: 320px;
    vertical-align: top;
}
.query-subject-preview {
    max-width: 100%;
    margin-bottom: 0.25rem;
    font-size: 0.95rem;
}
.query-message-preview {
    max-height: 4.5em;
    overflow: hidden;
    position: relative;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    line-clamp: 3;
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
    line-height: 1.5;
}
.btn-view-query {
    font-size: 0.825rem;
    cursor: pointer;
    text-decoration: none;
}
.btn-view-query:hover {
    text-decoration: underline;
}
.query-desc-box {
    max-height: 340px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-word;
    overflow-wrap: anywhere;
    font-size: 0.925rem;
    line-height: 1.6;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 14px 16px;
}
html[data-theme="dark"] .query-desc-box,
html.dark-mode .query-desc-box {
    background-color: rgba(15, 23, 42, 0.75) !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}
html[data-theme="dark"] #viewQueryModal .modal-content,
html.dark-mode #viewQueryModal .modal-content {
    background-color: #1e293b;
    color: #f8fafc;
}
html[data-theme="dark"] #viewQueryModal .card.bg-light,
html.dark-mode #viewQueryModal .card.bg-light,
html[data-theme="dark"] #viewQueryModal .modal-footer.bg-light,
html.dark-mode #viewQueryModal .modal-footer.bg-light {
    background-color: #0f172a !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] #viewQueryModal .border,
html.dark-mode #viewQueryModal .border {
    border-color: #334155 !important;
}
html[data-theme="dark"] #viewQueryModal #viewModalSubject,
html.dark-mode #viewQueryModal #viewModalSubject {
    background-color: #0f172a !important;
    color: #f8fafc !important;
    border-color: #334155 !important;
}
@media (max-width: 767.98px) {
    .table-stack td[data-label="Subject & Message"] {
        display: block !important;
        text-align: left !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    .table-stack td[data-label="Subject & Message"]::before {
        display: block !important;
        margin-bottom: 0.35rem;
    }
}
</style>

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
        <table class="table table-hover table-stack queries-table mb-0">
            <caption class="sr-only">List of customer support messages and tickets</caption>
            <thead class="thead-light">
                <tr>
                    <th scope="col" class="th-id-xs">#</th>
                    <th scope="col">Status</th>
                    <th scope="col">Sender</th>
                    <th scope="col">Subject & Message</th>
                    <th scope="col">Date Received</th>
                    <th scope="col" class="text-right">Action</th>
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
                        $is_long = mb_strlen($comment) > 80 || substr_count($comment, "\n") >= 1 || mb_strlen($subject) > 35;
                        ?>
                        <tr class="query-row query-row-<?= e($qst) ?>" data-status="<?= e($qst) ?>">
                            <td data-label="ID"><span class="text-muted small">#<?= e($qid) ?></span></td>
                            <td data-label="Status">
                                <span class="badge <?= $status_badge ?>"><?= ucfirst(e($qst)) ?></span>
                            </td>
                            <td data-label="Sender">
                                <strong class="text-dark"><?= e($name) ?></strong>
                                <div><a href="mailto:<?= e($email) ?>" class="text-primary small"><?= e($email) ?></a></div>
                            </td>
                            <td data-label="Subject & Message" class="query-content-cell">
                                <div class="font-weight-bold text-dark text-truncate query-subject-preview" title="<?= e($subject ?: '(No Subject)') ?>">
                                    <?= e($subject ?: '(No Subject)') ?>
                                </div>
                                <div class="text-muted small query-message-preview"><?= e($comment) ?></div>
                                <?php if ($is_long): ?>
                                    <button type="button" class="btn btn-link btn-sm p-0 font-weight-bold text-primary mt-1 btn-view-query"
                                            data-toggle="modal" data-target="#viewQueryModal"
                                            data-id="<?= e($qid) ?>"
                                            data-status="<?= e($qst) ?>"
                                            data-status-badge="<?= e($status_badge) ?>"
                                            data-name="<?= e($name) ?>"
                                            data-email="<?= e($email) ?>"
                                            data-date="<?= !empty($row['created_at']) ? e(date('d M Y, H:i', strtotime($row['created_at']))) : 'N/A' ?>"
                                            data-subject="<?= e($subject ?: '(No Subject)') ?>">
                                        Read More &rarr;
                                    </button>
                                <?php endif; ?>
                                <?php if (!empty($row['reply_text'])): ?>
                                    <div class="alert alert-light border mt-2 p-1 px-2 small mb-0 text-truncate" title="<?= e($row['reply_text']) ?>">
                                        <strong class="text-success">Sent Reply:</strong> <?= e(mb_strimwidth($row['reply_text'], 0, 50, '...')) ?>
                                    </div>
                                <?php endif; ?>
                                <textarea class="d-none query-full-message"><?= e($comment) ?></textarea>
                                <textarea class="d-none query-full-reply"><?= e($row['reply_text'] ?? '') ?></textarea>
                            </td>
                            <td data-label="Date Received">
                                <small class="text-muted">
                                    <?= !empty($row['created_at']) ? e(date('d M Y, H:i', strtotime($row['created_at']))) : 'N/A' ?>
                                </small>
                            </td>
                            <td data-label="Actions" class="text-right">
                                <button type="button" class="btn btn-outline-secondary btn-sm mr-1 btn-view-query"
                                        data-toggle="modal" data-target="#viewQueryModal"
                                        data-id="<?= e($qid) ?>"
                                        data-status="<?= e($qst) ?>"
                                        data-status-badge="<?= e($status_badge) ?>"
                                        data-name="<?= e($name) ?>"
                                        data-email="<?= e($email) ?>"
                                        data-date="<?= !empty($row['created_at']) ? e(date('d M Y, H:i', strtotime($row['created_at']))) : 'N/A' ?>"
                                        data-subject="<?= e($subject ?: '(No Subject)') ?>"
                                        title="View full inquiry details">
                                    👁️ View
                                </button>
                                <?php if (can_write()): ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm mr-1 btn-open-reply"
                                            data-toggle="modal" data-target="#singleReplyModal"
                                            data-id="<?= e($qid) ?>"
                                            data-name="<?= e($name) ?>"
                                            data-email="<?= e($email) ?>"
                                            data-subject="<?= e($subject ?: 'Inquiry #' . $qid) ?>">
                                        ✉️ Reply
                                    </button>
                                <?php endif; ?>
                                <?php if (is_super_admin()): ?>
                                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this query?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_id" value="<?= e($qid) ?>">
                                        <button type="submit" name="delete_query" class="btn btn-outline-danger btn-sm">
                                            🗑️ Dismiss
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($pagination, $filter_status !== 'all' ? ['status' => $filter_status] : []) ?>
</div>

<!-- Descriptive Query View Modal -->
<div class="modal fade text-left" id="viewQueryModal" tabindex="-1" role="dialog" aria-labelledby="viewQueryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <div class="d-flex align-items-center">
                    <h5 class="modal-title font-weight-bold mb-0" id="viewQueryModalLabel">
                        Inquiry Details <span class="text-muted font-weight-normal" id="viewModalId"></span>
                    </h5>
                    <span class="badge ml-3" id="viewModalStatusBadge"></span>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <!-- Passenger Info Card -->
                <div class="card bg-light border-0 mb-3">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-sm-4 mb-2 mb-sm-0">
                                <small class="text-muted text-uppercase font-weight-bold d-block">Passenger</small>
                                <span class="font-weight-bold text-dark" id="viewModalSender"></span>
                            </div>
                            <div class="col-sm-5 mb-2 mb-sm-0">
                                <small class="text-muted text-uppercase font-weight-bold d-block">Email Address</small>
                                <a href="#" id="viewModalEmailLink" class="text-primary font-weight-bold">
                                    <span id="viewModalEmail"></span>
                                </a>
                            </div>
                            <div class="col-sm-3">
                                <small class="text-muted text-uppercase font-weight-bold d-block">Date Received</small>
                                <span class="text-dark" id="viewModalDate"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subject Field -->
                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-uppercase text-muted">Subject</label>
                    <div class="h6 font-weight-bold text-dark mb-0 p-2 px-3 bg-white rounded border" id="viewModalSubject"></div>
                </div>

                <!-- Message Description Field -->
                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-uppercase text-muted">Inquiry Description</label>
                    <div class="query-desc-box" id="viewModalMessage"></div>
                </div>

                <!-- Sent Reply Section -->
                <div id="viewModalReplyContainer" style="display: none;">
                    <div class="alert alert-success border mb-0">
                        <div class="font-weight-bold mb-1">
                            Sent Staff Reply:
                        </div>
                        <div id="viewModalReplyBody" style="white-space: pre-wrap; word-break: break-word;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                <?php if (can_write()): ?>
                    <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="btnTransitionToReply">
                        Reply to Passenger
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Single Reusable Reply Modal (A10) -->
<div class="modal fade text-left" id="singleReplyModal" tabindex="-1" role="dialog" aria-labelledby="singleReplyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title font-weight-bold" id="singleReplyModalLabel">Reply to Passenger</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="post" id="replyQueryForm">
                <?= csrf_field() ?>
                <input type="hidden" name="query_id" id="replyQueryId" value="">
                <div class="modal-body p-4">
                    <div class="form-group">
                        <label for="replyModalEmail" class="small font-weight-bold text-muted">Recipient Email</label>
                        <input type="text" id="replyModalEmail" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="replyModalSubject" class="small font-weight-bold text-muted">Inquiry Subject</label>
                        <input type="text" id="replyModalSubject" class="form-control" readonly>
                    </div>
                    <div class="form-group mb-0">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="reply_message" class="small font-weight-bold text-muted mb-0">Your Response</label>
                            <span class="small text-muted" id="replyCharCounter">0 / 2000 chars</span>
                        </div>
                        <textarea name="reply_message" id="reply_message" class="form-control" rows="5" maxlength="2000" placeholder="Type your response to the passenger here..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_reply" class="btn btn-primary btn-sm font-weight-bold">Send Email Response</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery) {
        // Descriptive Query View Modal handler
        jQuery(document).on('click', '.btn-view-query', function() {
            var $btn = jQuery(this);
            var id = $btn.data('id');
            var name = $btn.data('name') || '';
            var email = $btn.data('email') || '';
            var date = $btn.data('date') || 'N/A';
            var status = $btn.data('status') || 'new';
            var statusBadge = $btn.data('status-badge') || 'badge-danger';
            var subject = $btn.data('subject') || '(No Subject)';

            var $row = $btn.closest('tr');
            var fullMessage = $row.find('.query-full-message').val();
            if (typeof fullMessage === 'undefined' || fullMessage === null) {
                fullMessage = $btn.data('message') || '';
            }
            var fullReply = $row.find('.query-full-reply').val();
            if (typeof fullReply === 'undefined' || fullReply === null) {
                fullReply = $btn.data('reply') || '';
            }

            jQuery('#viewModalId').text('#' + id);
            jQuery('#viewModalStatusBadge')
                .attr('class', 'badge ml-3 ' + statusBadge)
                .text(status.charAt(0).toUpperCase() + status.slice(1));
            jQuery('#viewModalSender').text(name);
            jQuery('#viewModalEmail').text(email);
            jQuery('#viewModalEmailLink').attr('href', 'mailto:' + encodeURIComponent(email));
            jQuery('#viewModalDate').text(date);
            jQuery('#viewModalSubject').text(subject);
            jQuery('#viewModalMessage').text(fullMessage);

            if (fullReply && fullReply.trim() !== '') {
                jQuery('#viewModalReplyBody').text(fullReply);
                jQuery('#viewModalReplyContainer').show();
            } else {
                jQuery('#viewModalReplyContainer').hide();
            }

            jQuery('#btnTransitionToReply')
                .data('id', id)
                .data('name', name)
                .data('email', email)
                .data('subject', subject);
        });

        // 1-Click Transition from View Modal to Reply Modal
        jQuery('#btnTransitionToReply').on('click', function() {
            var $btn = jQuery(this);
            var id = $btn.data('id');
            var name = $btn.data('name');
            var email = $btn.data('email');
            var subject = $btn.data('subject');

            jQuery('#viewQueryModal').modal('hide');
            jQuery('#viewQueryModal').one('hidden.bs.modal', function() {
                jQuery('#replyQueryId').val(id);
                jQuery('#singleReplyModalLabel').text('Reply to ' + name);
                jQuery('#replyModalEmail').val(email);
                jQuery('#replyModalSubject').val(subject);
                jQuery('#reply_message').val('');
                jQuery('#replyCharCounter').text('0 / 2000 chars');
                jQuery('#singleReplyModal').modal('show');
            });
        });

        // Existing Reply Modal open handler
        jQuery('.btn-open-reply').on('click', function() {
            var $btn = jQuery(this);
            var id = $btn.data('id');
            var name = $btn.data('name');
            var email = $btn.data('email');
            var subject = $btn.data('subject');

            jQuery('#replyQueryId').val(id);
            jQuery('#singleReplyModalLabel').text('Reply to ' + name);
            jQuery('#replyModalEmail').val(email);
            jQuery('#replyModalSubject').val(subject);
            jQuery('#reply_message').val('');
            jQuery('#replyCharCounter').text('0 / 2000 chars');
        });

        var replyText = document.getElementById('reply_message');
        var counter = document.getElementById('replyCharCounter');
        if (replyText && counter) {
            replyText.addEventListener('input', function() {
                var len = this.value.length;
                counter.textContent = len + ' / 2000 chars';
            });
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>