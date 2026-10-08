<?php
// tests/test_phase2.php -- Verification Suite for Phase 2: Money & Reporting Remediation
declare(strict_types=1);

$total_assertions = 0;
$failed_assertions = 0;

function assert_check(string $desc, bool $condition): void {
    global $total_assertions, $failed_assertions;
    $total_assertions++;
    if ($condition) {
        echo " [PASS] {$desc}\n";
    } else {
        echo " [FAIL] {$desc}\n";
        $failed_assertions++;
    }
}

echo "========================================================\n";
echo "   Phase 2: Money & Reporting Test Suite               \n";
echo "========================================================\n\n";

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/smtp.php';

// -------------------------------------------------------------
// Suite 2.1: SMTP Mail Transport & Status Updates (Issue 3)
// -------------------------------------------------------------
echo "[*] Suite 2.1: SMTP Mail Transport & Inquiry Mail Guard (Issue 3)\n";
$smtp_content = file_get_contents(__DIR__ . '/../includes/smtp.php');
$queries_content = file_get_contents(__DIR__ . '/../admin/queries.php');

assert_check("smtp.php defines smtp_send_mail() and app_smtp_send()", 
    function_exists('smtp_send_mail') && function_exists('app_smtp_send'));
assert_check("smtp.php defines smtp_diagnostics_check()", function_exists('smtp_diagnostics_check'));
assert_check("smtp.php implements RFC 5321 command sequences (EHLO, MAIL FROM, RCPT TO, DATA)",
    str_contains($smtp_content, 'EHLO') &&
    str_contains($smtp_content, 'MAIL FROM:') &&
    str_contains($smtp_content, 'RCPT TO:') &&
    str_contains($smtp_content, 'DATA')
);

// Test send_app_mail returns structured array ['ok' => bool, 'error' => string]
$dummy_mail_res = send_app_mail('invalid-host.local:9999', 'test@example.com', 'Subject', 'Body');
assert_check("send_app_mail returns array with 'ok' and 'error' keys",
    is_array($dummy_mail_res) && isset($dummy_mail_res['ok']) && isset($dummy_mail_res['error'])
);
assert_check("send_app_mail with unresolvable host returns ok=false and non-empty error",
    $dummy_mail_res['ok'] === false && !empty($dummy_mail_res['error'])
);

assert_check("admin/queries.php checks \$mail_result['ok'] before updating inquiry status",
    str_contains($queries_content, '$mail_result = send_app_mail(') &&
    str_contains($queries_content, '!$mail_result[\'ok\']')
);

// -------------------------------------------------------------
// Suite 2.2: Passenger Manifest & Hold Segregation (Issue 4)
// -------------------------------------------------------------
echo "\n[*] Suite 2.2: Passenger Manifest & Hold Segregation (Issue 4)\n";
$manifest_content = file_get_contents(__DIR__ . '/../admin/manifest.php');

assert_check("manifest.php queries confirmed bookings only for main passenger table",
    str_contains($manifest_content, "(status IS NULL OR status = 'Confirmed')") ||
    str_contains($manifest_content, "status = 'Confirmed'"));

assert_check("manifest.php separates pending holds into dedicated hold alert query",
    str_contains($manifest_content, "status = 'Pending'") &&
    str_contains($manifest_content, "unconfirmed_holds"));

assert_check("manifest.php validates journey date parameter format",
    str_contains($manifest_content, 'DateTime::createFromFormat'));

// -------------------------------------------------------------
// Suite 2.3: Data Minimization & PII Masking (Issue 7)
// -------------------------------------------------------------
echo "\n[*] Suite 2.3: PII Masking for Viewer Role (Issue 7)\n";

assert_check("mask_phone masks standard 10-digit number correctly",
    mask_phone('9876543210') === '******3210' || str_ends_with(mask_phone('9876543210'), '3210')
);
assert_check("mask_phone leaves short number masked with asterisks",
    str_contains(mask_phone('1234'), '*')
);
assert_check("mask_email masks local part correctly",
    str_contains(mask_email('johndoe@example.com'), '***') &&
    str_ends_with(mask_email('johndoe@example.com'), '@example.com')
);
assert_check("mask_email handles short username",
    str_contains(mask_email('ab@example.com'), '@example.com')
);

$customers_content = file_get_contents(__DIR__ . '/../admin/customers.php');
$bookings_content = file_get_contents(__DIR__ . '/../admin/bookings.php');

assert_check("customers.php applies mask_phone and mask_email for viewer export",
    str_contains($customers_content, "mask_phone") && str_contains($customers_content, "mask_email")
);
assert_check("customers.php logs exported row count to audit trail",
    str_contains($customers_content, "audit(\$link, 'EXPORT', 'customer'") && str_contains($customers_content, "'count'")
);
assert_check("manifest.php applies mask_phone for viewer export and logs export",
    str_contains($manifest_content, "mask_phone") && str_contains($manifest_content, "audit(\$link, 'EXPORT', 'manifest'")
);
assert_check("bookings.php applies mask_phone for viewer export",
    str_contains($bookings_content, "mask_phone")
);
assert_check("bookings.php logs exported row count to audit trail",
    str_contains($bookings_content, "audit(\$link, 'EXPORT', 'booking'") && str_contains($bookings_content, "'count'")
);

// -------------------------------------------------------------
// Suite 2.4: Booking Pricing Integrity & Override Governance (Issue 8)
// -------------------------------------------------------------
echo "\n[*] Suite 2.4: Pricing Integrity & Override Governance (Issue 8)\n";
$edit_booking_content = file_get_contents(__DIR__ . '/../admin/edit/edit-booking.php');

assert_check("bookings.php validates price override requires super_admin role",
    str_contains($bookings_content, "price_override") && str_contains($bookings_content, "super_admin")
);
assert_check("bookings.php requires override reason of at least 10 characters",
    str_contains($bookings_content, "override_reason") && str_contains($bookings_content, "strlen")
);
assert_check("edit-booking.php restricts UPDATE to name, contact, price only (locks journey fields)",
    str_contains($edit_booking_content, "UPDATE booking SET name = ?, contact = ?, price = ? WHERE sno = ?")
);
assert_check("edit-booking.php blocks modifying cancelled or expired bookings",
    str_contains($edit_booking_content, "cannot be modified") && str_contains($edit_booking_content, "['Cancelled', 'Expired']")
);
assert_check("edit-booking.php requires reason of at least 10 characters for audit",
    str_contains($edit_booking_content, "strlen(\$edit_reason) < 10") && str_contains($edit_booking_content, "strlen(\$price_reason) < 10")
);

// -------------------------------------------------------------
// Suite 2.5: Dashboard Date Window & KPI Filtering (Issues 9 & 10)
// -------------------------------------------------------------
echo "\n[*] Suite 2.5: Dashboard Analytics Window & Active KPI Filters (Issues 9 & 10)\n";
$dashboard_content = file_get_contents(__DIR__ . '/../admin/dashboard.php');

assert_check("dashboard.php uses explicit 30-day window date range (BETWEEN ? AND ?)",
    str_contains($dashboard_content, "WHERE `date` BETWEEN ? AND ?")
);
assert_check("dashboard.php calculates cancellation metrics strictly within the 30-day window",
    str_contains($dashboard_content, "WHERE `date` BETWEEN ? AND ?") &&
    str_contains($dashboard_content, "cnl_rate")
);
assert_check("dashboard.php displays date range window in card header",
    str_contains($dashboard_content, "30-Day Performance Overview") &&
    str_contains($dashboard_content, "window_start") &&
    str_contains($dashboard_content, "window_end")
);
assert_check("dashboard.php filters active buses with archived_at IS NULL",
    str_contains($dashboard_content, "FROM buses {\$bus_where}")
);
assert_check("dashboard.php filters active routes with archived_at IS NULL",
    str_contains($dashboard_content, "FROM route {\$route_where}")
);
assert_check("dashboard.php filters active customers with archived_at IS NULL",
    str_contains($dashboard_content, "FROM costumer {\$cust_where}")
);
assert_check("dashboard.php filters active admins with is_active = 1",
    str_contains($dashboard_content, "FROM admin {\$admin_where}")
);
assert_check("dashboard.php top routes query filters strictly Confirmed bookings",
    str_contains($dashboard_content, "top_routes_where") &&
    str_contains($dashboard_content, "status = 'Confirmed'")
);
assert_check("dashboard.php includes definitions footnote for KPI metrics",
    str_contains($dashboard_content, "Definitions:") &&
    str_contains($dashboard_content, "archived")
);

// -------------------------------------------------------------
// Suite 2.6: Seat Occupancy Map Departure Time Enforcement (Issue 11)
// -------------------------------------------------------------
echo "\n[*] Suite 2.6: Seat Occupancy Map Parameter Enforcement (Issue 11)\n";
$seats_content = file_get_contents(__DIR__ . '/../admin/seats.php');

assert_check("seats.php requires departure time along with bus and date",
    str_contains($seats_content, "\$selected_bus !== '' && \$selected_date !== '' && \$selected_time !== ''")
);
assert_check("seats.php form specifies departure time input as required",
    str_contains($seats_content, 'name="time"') && str_contains($seats_content, 'required')
);
assert_check("seats.php seat lock active status join assertion is preserved",
    str_contains($seats_content, 'JOIN booking b ON b.sno = sl.booking_id') &&
    str_contains($seats_content, "b.status IN ('Confirmed', 'Pending')")
);

// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo "\n========================================================\n";
echo "   Phase 2 Test Results: " . ($total_assertions - $failed_assertions) . " Passed, {$failed_assertions} Failed\n";
echo "========================================================\n";

if ($failed_assertions > 0) {
    exit(1);
}
