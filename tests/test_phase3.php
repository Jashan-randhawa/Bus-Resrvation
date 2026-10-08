<?php
// tests/test_phase3.php -- Verification Suite for Phase 3: Data Integrity Remediation
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
echo "   Phase 3: Data Integrity Test Suite                  \n";
echo "========================================================\n\n";

require_once __DIR__ . '/../includes/helpers.php';

// -------------------------------------------------------------
// Suite 3.1: Route Conflicts & Bus Assignment (Issues 12 & 13)
// -------------------------------------------------------------
echo "[*] Suite 3.1: Route Schedule Conflict & Bus Assignment (Issues 12 & 13)\n";
$routes_content = file_get_contents(__DIR__ . '/../admin/routes.php');
$edit_route_content = file_get_contents(__DIR__ . '/../admin/edit/edit-route.php');

assert_check("routes.php checks route conflict with archived_at IS NULL filter",
    str_contains($routes_content, "archived_at IS NULL") &&
    str_contains($routes_content, "SELECT * FROM route WHERE busno = ? AND `time` = ?")
);
assert_check("routes.php validates assigned bus exists and is not archived",
    str_contains($routes_content, "SELECT id FROM buses WHERE bus_number = ?") &&
    str_contains($routes_content, "archived_at IS NULL") &&
    str_contains($routes_content, "does not exist or is inactive/archived")
);
assert_check("edit-route.php checks conflict with archived_at IS NULL filter",
    str_contains($edit_route_content, "archived_at IS NULL") &&
    str_contains($edit_route_content, "SELECT * FROM route WHERE busno = ? AND `time` = ?")
);
assert_check("edit-route.php validates assigned bus exists and is not archived",
    str_contains($edit_route_content, "SELECT id FROM buses WHERE bus_number = ?") &&
    str_contains($edit_route_content, "archived_at IS NULL") &&
    str_contains($edit_route_content, "does not exist or is inactive/archived")
);

// -------------------------------------------------------------
// Suite 3.2: Route Edit Guard for Future Bookings (Issue 14)
// -------------------------------------------------------------
echo "\n[*] Suite 3.2: Route Edit Guard for Active Reservations (Issue 14)\n";

assert_check("edit-route.php queries future active bookings on route schedule",
    str_contains($edit_route_content, "SELECT COUNT(*) AS n FROM booking") &&
    str_contains($edit_route_content, "`date` >= ?") &&
    str_contains($edit_route_content, "status IN ('Confirmed', 'Pending')")
);
assert_check("edit-route.php blocks changes to cities, bus, or time if future bookings exist",
    str_contains($edit_route_content, "changing_schedule") &&
    str_contains($edit_route_content, "future_count > 0") &&
    str_contains($edit_route_content, "Cannot modify route cities, assigned bus, or departure time")
);
assert_check("edit-route.php displays schedule locked warning banner when future bookings exist",
    str_contains($edit_route_content, "Schedule Locked:") &&
    str_contains($edit_route_content, "future_count")
);
assert_check("edit-route.php sets readonly/hidden controls for locked route parameters",
    str_contains($edit_route_content, "future_count > 0 ? 'readonly' : ''") &&
    str_contains($edit_route_content, 'type="hidden" name="bus"')
);

// -------------------------------------------------------------
// Suite 3.3: Bus Capacity & Number Validation (Issues 22 & 23)
// -------------------------------------------------------------
echo "\n[*] Suite 3.3: Fleet Bus Capacity & Input Validation (Issues 22 & 23)\n";
$buses_content = file_get_contents(__DIR__ . '/../admin/buses.php');
$edit_bus_content = file_get_contents(__DIR__ . '/../admin/edit/edit-bus.php');

assert_check("buses.php rejects capacity < 10 or > 60 with an error alert",
    str_contains($buses_content, "capacity < 10 || \$capacity > 60") &&
    str_contains($buses_content, "Bus capacity must be between 10 and 60 seats.")
);
assert_check("buses.php validates bus number length (2 to 50 chars)",
    str_contains($buses_content, "mb_strlen(\$busno) < 2 || mb_strlen(\$busno) > 50")
);
assert_check("edit-bus.php rejects capacity < 10 or > 60 with an error alert",
    str_contains($edit_bus_content, "capacity < 10 || \$capacity > 60") &&
    str_contains($edit_bus_content, "Bus capacity must be between 10 and 60 seats.")
);
assert_check("edit-bus.php validates bus number length (2 to 50 chars)",
    str_contains($edit_bus_content, "mb_strlen(\$busno) < 2 || mb_strlen(\$busno) > 50")
);

// -------------------------------------------------------------
// Suite 3.4: Customer Linkage on Bookings (Issue 26)
// -------------------------------------------------------------
echo "\n[*] Suite 3.4: Customer Account Linkage (Issue 26)\n";
$helpers_content = file_get_contents(__DIR__ . '/../includes/helpers.php');
$init_sql = file_get_contents(__DIR__ . '/../database/init.sql');

assert_check("helpers.php create_booking resolves customer id from customer email",
    str_contains($helpers_content, "SELECT id FROM costumer WHERE email = ?") &&
    str_contains($helpers_content, "cust_email")
);
assert_check("helpers.php create_booking handles customer_id column insertion",
    str_contains($helpers_content, "table_has_column(\$link, 'booking', 'customer_id')") &&
    str_contains($helpers_content, "\$cols[] = 'customer_id'")
);
assert_check("database/init.sql defines customer_id column and foreign key constraint",
    str_contains($init_sql, "`customer_id` INT NULL") &&
    str_contains($init_sql, "fk_booking_cust")
);

// -------------------------------------------------------------
// Suite 3.5: Migration Gating & Schema Reconciliation (Issues 17 & 18)
// -------------------------------------------------------------
echo "\n[*] Suite 3.5: Migration Execution Gating & Schema Reconciliation (Issues 17 & 18)\n";
$entrypoint_content = file_get_contents(__DIR__ . '/../docker/entrypoint.sh');
$migrate_content = file_get_contents(__DIR__ . '/../database/db_migrate.php');

assert_check("docker/entrypoint.sh gates startup migration execution on MIGRATE_ON_START=1",
    str_contains($entrypoint_content, 'MIGRATE_ON_START') &&
    str_contains($entrypoint_content, 'db_migrate.php')
);
assert_check("database/db_migrate.php migration 001 does NOT contain inline DELETE statements",
    !str_contains($migrate_content, 'DELETE a1 FROM admin') &&
    !str_contains($migrate_content, 'DELETE c1 FROM costumer') &&
    !str_contains($migrate_content, 'DELETE b1 FROM buses')
);
assert_check("database/db_migrate.php checks for duplicate admin emails safely",
    str_contains($migrate_content, "SELECT Email_id, COUNT(*) AS cnt FROM admin GROUP BY Email_id HAVING cnt > 1")
);
assert_check("database/db_migrate.php checks for duplicate customer emails safely",
    str_contains($migrate_content, "SELECT email, COUNT(*) AS cnt FROM costumer GROUP BY email HAVING cnt > 1")
);
assert_check("database/db_migrate.php checks for duplicate bus numbers safely",
    str_contains($migrate_content, "SELECT bus_number, COUNT(*) AS cnt FROM buses GROUP BY bus_number HAVING cnt > 1")
);

$expected_migrations = [
    '001_hardening.sql',
    '002_hash_passwords.php',
    '003_bus_capacity.sql',
    '004_seat_hold_and_payment_states.sql',
    '005_referential_integrity_seat_locks.sql',
    '006_audit_logging.sql',
    '007_admin_accounts_and_soft_delete.sql',
    '008_query_inbox_enhancements.sql',
    '009_admin_accounts_and_soft_delete.sql',
    '010_query_inbox_enhancements.sql'
];

foreach ($expected_migrations as $mig_file) {
    assert_check("database/migrations/{$mig_file} exists", file_exists(__DIR__ . "/../database/migrations/{$mig_file}"));
}

// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo "\n========================================================\n";
echo "   Phase 3 Test Results: " . ($total_assertions - $failed_assertions) . " Passed, {$failed_assertions} Failed\n";
echo "========================================================\n";

if ($failed_assertions > 0) {
    exit(1);
}
