<?php
// includes/admin-crud.php -- Common Admin CRUD Helpers & UI Components (Phase E Item 14)
// Consolidates common admin table patterns: archiving, restoring, tab switching, and action buttons.

require_once __DIR__ . '/helpers.php';

/**
 * Returns current view tab ('active' or 'archived')
 */
function admin_get_archive_tab(): string {
    $tab = trim((string)($_GET['tab'] ?? 'active'));
    return $tab === 'archived' ? 'archived' : 'active';
}

/**
 * Soft archives a record if archived_at column exists, otherwise hard deletes.
 * Audits the action in audit_log.
 */
function admin_archive_record(mysqli $link, string $table, string $pk, int $id, string $entity_type, ?array $before_state = null, string $archive_col = 'archived_at'): bool {
    $has_archived_col = table_has_column($link, $table, $archive_col);
    if ($has_archived_col) {
        $ok = db_exec($link, "UPDATE `{$table}` SET `{$archive_col}` = NOW() WHERE `{$pk}` = ?", 'i', [$id]);
        if ($ok) {
            audit($link, 'DELETE', $entity_type, $id, $before_state, [$archive_col => date('Y-m-d H:i:s')]);
            return true;
        }
    } else {
        $ok = db_exec($link, "DELETE FROM `{$table}` WHERE `{$pk}` = ?", 'i', [$id]);
        if ($ok) {
            audit($link, 'DELETE', $entity_type, $id, $before_state, null);
            return true;
        }
    }
    return false;
}

/**
 * Restores a soft-archived record by resetting archived_at to NULL.
 * Audits the action in audit_log.
 */
function admin_restore_record(mysqli $link, string $table, string $pk, int $id, string $entity_type, string $archive_col = 'archived_at'): bool {
    $has_archived_col = table_has_column($link, $table, $archive_col);
    if (!$has_archived_col) {
        return false;
    }
    $ok = db_exec($link, "UPDATE `{$table}` SET `{$archive_col}` = NULL WHERE `{$pk}` = ?", 'i', [$id]);
    if ($ok) {
        audit($link, 'RESTORE', $entity_type, $id, ['archived' => true], ['archived' => false]);
        return true;
    }
    return false;
}

/**
 * Renders HTML for Active vs Archived filter tabs
 */
function admin_archive_tabs_html(string $current_tab, string $active_label = 'Active Records', string $archived_label = 'Archived Records', array $extra_params = []): string {
    $active_url = '?' . http_build_query(array_merge($extra_params, ['tab' => 'active']));
    $archived_url = '?' . http_build_query(array_merge($extra_params, ['tab' => 'archived']));

    $active_cls = ($current_tab !== 'archived') ? 'btn-dark' : 'btn-outline-secondary';
    $archived_cls = ($current_tab === 'archived') ? 'btn-dark' : 'btn-outline-secondary';

    return '<div class="mb-3">' .
        '<div class="btn-group btn-group-sm" role="group">' .
        '<a href="' . e($active_url) . '" class="btn ' . $active_cls . '">' . e($active_label) . '</a>' .
        '<a href="' . e($archived_url) . '" class="btn ' . $archived_cls . '">' . e($archived_label) . '</a>' .
        '</div>' .
        '</div>';
}

/**
 * Renders standard CRUD action buttons (Edit, Archive/Delete, Restore)
 */
function render_crud_action_buttons(int $id, string $edit_url, bool $is_archived, string $delete_action_name, string $restore_action_name, string $item_label = 'record'): string {
    $html = '<div class="d-inline-flex align-items-center justify-content-end">';

    // Edit button (only if not archived and user has write access)
    if (!$is_archived && can_write()) {
        $html .= '<a href="' . e($edit_url) . '" class="btn btn-outline-secondary btn-sm mr-1">Edit</a>';
    }

    // Restore button (for super_admin on archived records)
    if ($is_archived && is_super_admin()) {
        $html .= '<form method="post" action="" style="display:inline;" onsubmit="return confirm(\'Restore this ' . e($item_label) . ' to active status?\');">' .
            csrf_field() .
            '<input type="hidden" name="restore_id" value="' . e($id) . '">' .
            '<button type="submit" name="' . e($restore_action_name) . '" class="btn btn-outline-success btn-sm">Restore</button>' .
            '</form>';
    }

    // Archive / Delete button (for super_admin on active records)
    if (!$is_archived && is_super_admin()) {
        $html .= '<form method="post" action="" style="display:inline;" onsubmit="return confirm(\'Archive this ' . e($item_label) . '? It can be restored later from the archive tab.\');">' .
            csrf_field() .
            '<input type="hidden" name="delete_id" value="' . e($id) . '">' .
            '<button type="submit" name="' . e($delete_action_name) . '" class="btn btn-outline-danger btn-sm">Archive</button>' .
            '</form>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * Renders a dismissible alert component
 */
function render_admin_alert(?string $alert, string $type = 'info'): string {
    if (!$alert) {
        return '';
    }
    return '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">' .
        e($alert) .
        '<button type="button" class="close" data-dismiss="alert">&times;</button>' .
        '</div>';
}
