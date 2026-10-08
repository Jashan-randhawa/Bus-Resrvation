<?php
// tests/test_phase4.php -- Verification Suite for Phase 4: Hygiene & Hardening Remediation
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
echo "   Phase 4: Hygiene & Hardening Test Suite              \n";
echo "========================================================\n\n";

require_once __DIR__ . '/../includes/helpers.php';

// -------------------------------------------------------------
// Suite 4.1: Diagnostics Audit Gating & Accurate Migration Count (Issue 19)
// -------------------------------------------------------------
echo "[*] Suite 4.1: Diagnostics Audit Gating & Migration Count (Issue 19)\n";
$diag_content = file_get_contents(__DIR__ . '/../admin/diagnostics.php');

assert_check("diagnostics.php sets expected migrations count to 10",
    str_contains($diag_content, '$expected_count = 10;')
);
assert_check("diagnostics.php logs DIAGNOSTICS_RUN only on explicit execution",
    str_contains($diag_content, '$is_explicit_run') &&
    str_contains($diag_content, "audit(\$link, 'DIAGNOSTICS_RUN'")
);
assert_check("diagnostics.php includes explicit Run Diagnostics trigger",
    str_contains($diag_content, '?run=1') &&
    str_contains($diag_content, 'Run Diagnostics')
);

// -------------------------------------------------------------
// Suite 4.2: Output Encoding & No Double-Escaping (Issue 25)
// -------------------------------------------------------------
echo "\n[*] Suite 4.2: Output Encoding & No Double-Escaping (Issue 25)\n";
$queries_content = file_get_contents(__DIR__ . '/../admin/queries.php');

assert_check("queries.php does not double-escape status in alert string before rendering",
    !str_contains($queries_content, "\$alert = \"Status updated to '\" . e(\$new_st)") &&
    str_contains($queries_content, "\$alert = \"Status updated to '{\$new_st}'.\";")
);

// -------------------------------------------------------------
// Suite 4.3: SQL LIKE Wildcard Escaping (Issue 27)
// -------------------------------------------------------------
echo "\n[*] Suite 4.3: SQL LIKE Wildcard Escaping (Issue 27)\n";

assert_check("helpers.php defines escape_like() function", function_exists('escape_like'));
assert_check("escape_like escapes percent and underscore characters",
    escape_like('100%_test') === '100\\%\\_test'
);

$bookings_content = file_get_contents(__DIR__ . '/../admin/bookings.php');
$customers_content = file_get_contents(__DIR__ . '/../admin/customers.php');

assert_check("bookings.php uses escape_like() for search query parameter",
    str_contains($bookings_content, 'escape_like($search)')
);
assert_check("customers.php uses escape_like() for search query parameter",
    str_contains($customers_content, 'escape_like($search)')
);

// -------------------------------------------------------------
// Suite 4.4: Enforced CSP & Subresource Integrity (Issue 30)
// -------------------------------------------------------------
echo "\n[*] Suite 4.4: Enforced CSP & Subresource Integrity (Issue 30)\n";
$apache_sec = file_get_contents(__DIR__ . '/../docker/apache-security.conf');
$header_admin = file_get_contents(__DIR__ . '/../includes/layout/header-admin.php');
$footer_admin = file_get_contents(__DIR__ . '/../includes/layout/footer-admin.php');

assert_check("apache-security.conf enforces Content-Security-Policy (not Report-Only)",
    str_contains($apache_sec, 'Header always set Content-Security-Policy \\') &&
    !str_contains($apache_sec, 'Header always set Content-Security-Policy-Report-Only')
);
assert_check("apache-security.conf CSP defines connect-src and frame-ancestors directives",
    str_contains($apache_sec, "connect-src 'self'") &&
    str_contains($apache_sec, "frame-ancestors 'self'")
);
assert_check("header-admin.php contains SRI hash on AOS stylesheet",
    str_contains($header_admin, 'https://unpkg.com/aos@2.3.1/dist/aos.css') &&
    str_contains($header_admin, 'integrity="sha384-') &&
    str_contains($header_admin, 'crossorigin="anonymous"')
);
assert_check("footer-admin.php contains SRI hash on AOS script",
    str_contains($footer_admin, 'https://unpkg.com/aos@2.3.1/dist/aos.js') &&
    str_contains($footer_admin, 'integrity="sha384-') &&
    str_contains($footer_admin, 'crossorigin="anonymous"')
);

// -------------------------------------------------------------
// Suite 4.5: Read-Only GET Cleanliness & CLI Hold Expiration (Issue 31)
// -------------------------------------------------------------
echo "\n[*] Suite 4.5: Read-Only GET Cleanliness & CLI Expiration (Issue 31)\n";
$seats_content = file_get_contents(__DIR__ . '/../admin/seats.php');

assert_check("seats.php does NOT invoke release_expired_holds write operation on GET",
    !str_contains($seats_content, 'release_expired_holds($link);')
);
assert_check("database/expire-holds.php exists with CLI guard",
    file_exists(__DIR__ . '/../database/expire-holds.php')
);

$expire_holds_content = file_get_contents(__DIR__ . '/../database/expire-holds.php');
assert_check("database/expire-holds.php enforces CLI SAPI check",
    str_contains($expire_holds_content, "PHP_SAPI !== 'cli'")
);
assert_check("database/expire-holds.php invokes release_expired_holds and purge_stale_seat_locks",
    str_contains($expire_holds_content, 'release_expired_holds($link)') &&
    str_contains($expire_holds_content, 'purge_stale_seat_locks($link)')
);

// -------------------------------------------------------------
// Suite 4.6: Manifest Date Validation (Issue 32)
// -------------------------------------------------------------
echo "\n[*] Suite 4.6: Manifest Date Input Validation (Issue 32)\n";
$manifest_content = file_get_contents(__DIR__ . '/../admin/manifest.php');

assert_check("manifest.php validates travel date format strictly with DateTime::createFromFormat",
    str_contains($manifest_content, "DateTime::createFromFormat('Y-m-d', \$raw_date)")
);

// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo "\n========================================================\n";
echo "   Phase 4 Test Results: " . ($total_assertions - $failed_assertions) . " Passed, {$failed_assertions} Failed\n";
echo "========================================================\n";

if ($failed_assertions > 0) {
    exit(1);
}
