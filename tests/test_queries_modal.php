<?php
// tests/test_queries_modal.php
// Validates Subject & Message cell clamping, Read More trigger, and Descriptive View Modal in admin/queries.php

$total = 0;
$passed = 0;

function assert_query_modal(string $description, bool $condition): void {
    global $total, $passed;
    $total++;
    if ($condition) {
        $passed++;
        echo " [PASS] {$description}\n";
    } else {
        echo " [FAIL] {$description}\n";
    }
}

echo "========================================================\n";
echo "   Support Inquiries Cell Clamping & Modal Test Suite   \n";
echo "========================================================\n\n";

$file_path = __DIR__ . '/../admin/queries.php';
assert_query_modal("admin/queries.php file exists", file_exists($file_path));

$content = file_get_contents($file_path);

// Suite 1: CSS Definition and Line Clamping
echo "\n[*] Suite 1: Definite Size & Line Clamping Styles\n";
assert_query_modal(
    ".query-content-cell sets max-width / width constraint",
    str_contains($content, '.query-content-cell') && str_contains($content, 'max-width: 320px')
);
assert_query_modal(
    ".query-message-preview defines line-clamp: 3 and max-height",
    str_contains($content, '.query-message-preview') &&
    str_contains($content, '-webkit-line-clamp: 3') &&
    str_contains($content, 'max-height: 4.5em')
);
assert_query_modal(
    ".query-message-preview preserves line breaks and prevents horizontal overflow",
    str_contains($content, 'white-space: pre-wrap') &&
    str_contains($content, 'word-break: break-word')
);
assert_query_modal(
    "Mobile table-stack displays Subject & Message cell as clean block",
    str_contains($content, '.table-stack td[data-label="Subject & Message"]') &&
    str_contains($content, 'display: block !important')
);

// Suite 2: Table Cell Elements & Triggers
echo "\n[*] Suite 2: Table Cell Markup & Triggers\n";
assert_query_modal(
    "Subject heading uses text-truncate with title tooltip",
    str_contains($content, 'class="font-weight-bold text-dark text-truncate query-subject-preview"')
);
assert_query_modal(
    "Contains conditional 'Read More &rarr;' button trigger for long messages",
    str_contains($content, 'Read More &rarr;') && str_contains($content, 'btn-view-query')
);
assert_query_modal(
    "Read More button targets #viewQueryModal via Bootstrap attributes",
    str_contains($content, 'data-toggle="modal"') && str_contains($content, 'data-target="#viewQueryModal"')
);
assert_query_modal(
    "Contains hidden raw textareas for safe, unescaped modal population",
    str_contains($content, 'class="d-none query-full-message"') &&
    str_contains($content, 'class="d-none query-full-reply"')
);
assert_query_modal(
    "Actions column provides dedicated 'View' button with btn-view-query class",
    str_contains($content, '<button type="button" class="btn btn-outline-secondary btn-sm mr-1 btn-view-query"')
);

// Suite 3: Descriptive Modal Dialog (#viewQueryModal)
echo "\n[*] Suite 3: Descriptive Modal Dialog Structure\n";
assert_query_modal(
    "Modal dialog #viewQueryModal exists with modal-lg sizing",
    str_contains($content, 'id="viewQueryModal"') && str_contains($content, 'modal-lg')
);
assert_query_modal(
    "Modal header displays dynamic ID #viewModalId and status badge #viewModalStatusBadge",
    str_contains($content, 'id="viewModalId"') && str_contains($content, 'id="viewModalStatusBadge"')
);
assert_query_modal(
    "Modal body contains passenger info card (Sender, Email, Date)",
    str_contains($content, 'id="viewModalSender"') &&
    str_contains($content, 'id="viewModalEmail"') &&
    str_contains($content, 'id="viewModalDate"')
);
assert_query_modal(
    "Modal body contains dedicated Subject container #viewModalSubject",
    str_contains($content, 'id="viewModalSubject"')
);
assert_query_modal(
    "Modal body contains scrollable inquiry description #viewModalMessage with query-desc-box",
    str_contains($content, 'id="viewModalMessage"') && str_contains($content, 'class="query-desc-box"')
);
assert_query_modal(
    "Modal includes conditional sent staff reply section #viewModalReplyContainer",
    str_contains($content, 'id="viewModalReplyContainer"') && str_contains($content, 'id="viewModalReplyBody"')
);
assert_query_modal(
    "Modal footer includes 1-click reply transition button #btnTransitionToReply",
    str_contains($content, 'id="btnTransitionToReply"')
);

// Suite 4: JavaScript Controller & Modal Transitions
echo "\n[*] Suite 4: Controller & Script Integration\n";
assert_query_modal(
    "jQuery event listener attached to .btn-view-query for dynamic population",
    str_contains($content, "jQuery(document).on('click', '.btn-view-query', function()")
);
assert_query_modal(
    "Modal population retrieves full text from raw hidden textareas safely",
    str_contains($content, '$row.find(\'.query-full-message\').val()')
);
assert_query_modal(
    "#btnTransitionToReply hides #viewQueryModal and smoothly shows #singleReplyModal",
    str_contains($content, "jQuery('#btnTransitionToReply').on('click'") &&
    str_contains($content, "jQuery('#viewQueryModal').modal('hide')") &&
    str_contains($content, "jQuery('#singleReplyModal').modal('show')")
);

echo "\n========================================================\n";
echo "   Test Results: {$passed} Passed, " . ($total - $passed) . " Failed\n";
echo "========================================================\n";

if ($passed !== $total) {
    exit(1);
}
exit(0);
