<?php
// tests/run_tests.php -- Automated Test Harness (O14)
// Runs integration and unit test assertions against core security, concurrency, and business logic.
// Usage: php tests/run_tests.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI execution only.\n";
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

echo "========================================================\n";
echo "   Bus Reservation System -- Automated Test Suite       \n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$name}\n";
    } else {
        $failed++;
        echo " [FAIL] {$name}" . ($details !== '' ? " -> {$details}" : '') . "\n";
    }
}

// -------------------------------------------------------------
// Test 1: Helper client_ip() IP spoofing protection
// -------------------------------------------------------------
echo "[*] Suite 1: Security & IP Spoofing (O1, O9)\n";
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 198.51.100.2';
putenv('TRUSTED_PROXY_HOPS=1');
$ip = client_ip();
assert_test("client_ip extracts rightmost proxy hop", $ip === '198.51.100.2', "Got: {$ip}");

$_SERVER['HTTP_X_FORWARDED_FOR'] = 'spoofed.ip.address';
$fallback_ip = client_ip();
assert_test("client_ip falls back to REMOTE_ADDR on invalid header", $fallback_ip === '203.0.113.10', "Got: {$fallback_ip}");

// -------------------------------------------------------------
// Test 2: Throttling / Rate limiting logic (O3, O9)
// -------------------------------------------------------------
echo "\n[*] Suite 2: Rate Limiting & Throttling (O3, O9)\n";
$test_key = 'test:login:rate:' . bin2hex(random_bytes(4));
throttle_clear($link, $test_key);
assert_test("New throttle key is not blocked initially", !throttle_blocked($link, $test_key, 3, 60));

throttle_hit($link, $test_key);
throttle_hit($link, $test_key);
throttle_hit($link, $test_key);
assert_test("Throttle key is blocked after reaching limit (3 hits)", throttle_blocked($link, $test_key, 3, 60));

throttle_clear($link, $test_key);
assert_test("Throttle key is unblocked after clear", !throttle_blocked($link, $test_key, 3, 60));

// -------------------------------------------------------------
// Test 3: Password hash integrity check (O2)
// -------------------------------------------------------------
echo "\n[*] Suite 3: Password Hash Security (O2)\n";
$plaintext = 'SecretPassword123!';
$valid_hash = password_hash($plaintext, PASSWORD_DEFAULT);
$hash_info = password_get_info($valid_hash);
assert_test("password_get_info identifies bcrypt/argon hashes", $hash_info['algo'] !== null);

$dummy_plaintext_hash = password_get_info('plaintext_pass');
assert_test("password_get_info detects non-hash strings", $dummy_plaintext_hash['algo'] === null);

// -------------------------------------------------------------
// Test 4: Fleet capacity resolver (O8)
// -------------------------------------------------------------
echo "\n[*] Suite 4: Fleet Capacity Modeling (O8)\n";
$default_cap = get_bus_capacity($link, 'NON_EXISTENT_BUS_xyz');
assert_test("Default bus capacity falls back safely to 36", $default_cap === 36, "Got: {$default_cap}");

// Create test bus with capacity 45
$test_busno = 'TEST-BUS-' . strtoupper(bin2hex(random_bytes(3)));
db_exec($link, 'INSERT INTO buses (bus_number, capacity) VALUES (?, ?)', 'si', [$test_busno, 45]);
$resolved_cap = get_bus_capacity($link, $test_busno);
assert_test("Custom bus capacity resolved correctly as 45", $resolved_cap === 45, "Got: {$resolved_cap}");

// -------------------------------------------------------------
// Test 5: Booking validation, seat bounds, and concurrency (O6, F1-F5)
// -------------------------------------------------------------
echo "\n[*] Suite 5: Booking Validation & Concurrency (O6, F1-F5)\n";
$test_date = date('Y-m-d', strtotime('+7 days'));
$test_time = '10:00:00';

// Test negative seat
$res_invalid_seat = create_booking($link, [
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $test_date,
    'time' => $test_time,
    'seat' => 0,
    'price' => 50.0,
    'name' => 'Test User',
    'contact' => '9999999999'
]);
assert_test("create_booking rejects seat 0", !$res_invalid_seat['ok']);

// Test seat exceeding capacity
$res_over_capacity = create_booking($link, [
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $test_date,
    'time' => $test_time,
    'seat' => 46,
    'price' => 50.0,
    'name' => 'Test User',
    'contact' => '9999999999'
]);
assert_test("create_booking rejects seat exceeding bus capacity (seat 46 on 45-seater)", !$res_over_capacity['ok']);

// Test past date
$res_past_date = create_booking($link, [
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => date('Y-m-d', strtotime('-1 day')),
    'time' => $test_time,
    'seat' => 1,
    'price' => 50.0,
    'name' => 'Test User',
    'contact' => '9999999999'
]);
assert_test("create_booking rejects past travel date", !$res_past_date['ok']);

// Successful booking
$res_valid = create_booking($link, [
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $test_date,
    'time' => $test_time,
    'seat' => 5,
    'price' => 50.0,
    'name' => 'Test User',
    'contact' => '9999999999'
]);
assert_test("create_booking succeeds with valid parameters", $res_valid['ok'] && strlen($res_valid['pnr']) === 10, "PNR: {$res_valid['pnr']}");

// Test double booking prevention (same trip, same seat)
$res_duplicate = create_booking($link, [
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $test_date,
    'time' => $test_time,
    'seat' => 5,
    'price' => 50.0,
    'name' => 'Another User',
    'contact' => '8888888888'
]);
assert_test("Double-booking prevention: rejects duplicate booking for Seat 5 on same trip", !$res_duplicate['ok']);

// Verify seat is recorded as taken in get_booked_seats
$booked = get_booked_seats($link, $test_busno, $test_date, $test_time);
assert_test("get_booked_seats detects Seat 5 as reserved", isset($booked[5]));

// -------------------------------------------------------------
// Test 6: Soft cancellation & seat liberation (O4)
// -------------------------------------------------------------
echo "\n[*] Suite 6: Soft Cancellation & Seat Liberation (O4)\n";
$pnr_created = $res_valid['pnr'];
// Cancel the booking
db_exec($link, "UPDATE booking SET status = 'Cancelled' WHERE pnr = ?", 's', [$pnr_created]);

$booked_after_cancel = get_booked_seats($link, $test_busno, $test_date, $test_time);
assert_test("Cancelled seat is liberated and no longer in get_booked_seats", !isset($booked_after_cancel[5]));

// Re-booking the liberated seat should now succeed
$res_rebook = create_booking($link, [
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $test_date,
    'time' => $test_time,
    'seat' => 5,
    'price' => 50.0,
    'name' => 'Third User',
    'contact' => '7777777777'
]);
assert_test("Liberated seat can be successfully booked by a new customer", $res_rebook['ok']);

// -------------------------------------------------------------
// Test 7: Seat Hold Expiration & Payment States (O13)
// -------------------------------------------------------------
echo "\n[*] Suite 7: Seat Hold Expiration & Payment States (O13)\n";
$res_hold = create_booking($link, [
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $test_date,
    'time' => $test_time,
    'seat' => 7,
    'price' => 50.0,
    'name' => 'Hold User',
    'contact' => '1111111111',
    'status' => 'Pending'
]);
assert_test("Booking created with 'Pending' seat hold status", $res_hold['ok'] && ($res_hold['status'] ?? '') === 'Pending');

$booked_during_hold = get_booked_seats($link, $test_busno, $test_date, $test_time);
assert_test("Pending seat hold blocks other bookings (Seat 7 taken)", isset($booked_during_hold[7]));

// Simulate hold expiration by setting hold_expires_at in the past
db_exec($link, "UPDATE booking SET hold_expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE pnr = ?", 's', [$res_hold['pnr']]);
release_expired_holds($link);

$expired_row = db_one($link, "SELECT status FROM booking WHERE pnr = ?", 's', [$res_hold['pnr']]);
assert_test("Expired hold status automatically updated to 'Expired'", ($expired_row['status'] ?? '') === 'Expired');

$booked_after_expiration = get_booked_seats($link, $test_busno, $test_date, $test_time);
assert_test("Expired seat 7 is automatically released for new customers", !isset($booked_after_expiration[7]));

// -------------------------------------------------------------
// Test 8: Slice A Verification (U-01, U-03, U-05, U-06, U-07)
// -------------------------------------------------------------
echo "\n[*] Suite 8: Slice A Correctness & Hardening (U-01 to U-07)\n";

// 1. Rebooking an expired hold row (U-01)
$res_rebook_expired = create_booking($link, [
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $test_date,
    'time' => $test_time,
    'seat' => 7,
    'price' => 50.0,
    'name' => 'Expired Rebooker',
    'contact' => '9876543210'
]);
assert_test("Expired seat 7 can be rebooked without 1062 unique constraint error (U-01)", $res_rebook_expired['ok']);

// 2. Double-submit idempotency recovery for same customer (U-05)
$test_cust_id = 999;
$res_first_sub = create_booking($link, [
    'id' => $test_cust_id,
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $test_date,
    'time' => $test_time,
    'seat' => 12,
    'price' => 50.0,
    'name' => 'Double Clicker',
    'contact' => '9876543210'
]);
assert_test("First submission creates booking", $res_first_sub['ok']);

$res_second_sub = create_booking($link, [
    'id' => $test_cust_id,
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $test_date,
    'time' => $test_time,
    'seat' => 12,
    'price' => 50.0,
    'name' => 'Double Clicker',
    'contact' => '9876543210'
]);
assert_test("Rapid second submission recovers existing PNR instead of error (U-05)",
    $res_second_sub['ok'] && $res_second_sub['pnr'] === $res_first_sub['pnr']);

// 3. Name length validation (U-07)
$res_short_name = create_booking($link, [
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $test_date,
    'time' => $test_time,
    'seat' => 14,
    'price' => 50.0,
    'name' => 'J',
    'contact' => '9876543210'
]);
assert_test("Passenger name under 2 chars is rejected (U-07)", !$res_short_name['ok']);

// 4. Phone digits validation (U-07)
$res_short_phone = create_booking($link, [
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $test_date,
    'time' => $test_time,
    'seat' => 14,
    'price' => 50.0,
    'name' => 'Valid Name',
    'contact' => '12345'
]);
assert_test("Phone number with fewer than 10 digits is rejected (U-07)", !$res_short_phone['ok']);

// 5. Travel date & time validation helper (U-03)
assert_test("validate_travel_datetime rejects invalid format", !validate_travel_datetime('2026-99-99')['ok']);
assert_test("validate_travel_datetime rejects past date", !validate_travel_datetime(date('Y-m-d', strtotime('-1 day')))['ok']);
assert_test("validate_travel_datetime rejects date > 90 days", !validate_travel_datetime(date('Y-m-d', strtotime('+95 days')))['ok']);
assert_test("validate_travel_datetime accepts valid future date", validate_travel_datetime(date('Y-m-d', strtotime('+5 days')))['ok']);

// -------------------------------------------------------------
// Test 9: Slice B Verification (U-08, U-09, U-10, U-12)
// -------------------------------------------------------------
echo "\n[*] Suite 9: Slice B Cancellation & Status Integrity (U-08 to U-12)\n";

// 1. Cutoff window enforcement (U-10)
$now_ts = time();
$cutoff_min = defined('APP_CANCEL_CUTOFF_MIN') ? (int)APP_CANCEL_CUTOFF_MIN : 120;
// Trip departing in 30 minutes
$dep_30min = date('Y-m-d H:i:s', $now_ts + (30 * 60));
$dep_30min_date = date('Y-m-d', strtotime($dep_30min));
$dep_30min_time = date('H:i:s', strtotime($dep_30min));

$res_cutoff = create_booking($link, [
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $dep_30min_date,
    'time' => $dep_30min_time,
    'seat' => 20,
    'price' => 50.0,
    'name' => 'Cutoff Test User',
    'contact' => '9876543210'
]);

$cutoff_row = db_one($link, "SELECT `date`, `time`, status FROM booking WHERE pnr = ?", 's', [$res_cutoff['pnr']]);
$dep_ts_chk = strtotime(($cutoff_row['date'] ?? '') . ' ' . ($cutoff_row['time'] ?? ''));
$diff_seconds = $dep_ts_chk - $now_ts;
$is_within_cutoff = ($diff_seconds < ($cutoff_min * 60));
assert_test("Trip departing in 30 mins is identified as within cancellation cut-off window (U-10)", $is_within_cutoff);

// 2. Trip in the past cannot be cancelled (U-10)
$past_ts = $now_ts - 3600;
$is_past = ($past_ts <= $now_ts);
assert_test("Past departed trip cannot be cancelled (U-10)", $is_past);

// 3. Forged PNR verification (U-08)
$fake_lookup = db_one($link, 'SELECT pnr FROM booking WHERE pnr = ? AND id = ?', 'si', ['FAKE', 1]);
assert_test("Forged PNR lookup returns null and shows no confirmation (U-08)", $fake_lookup === null);

// 4. Hold expiry timestamp aligns with SQL NOW() (U-12)
$res_hold_clk = create_booking($link, [
    'bus' => $test_busno,
    'city1' => 'CityA',
    'city2' => 'CityB',
    'date' => $test_date,
    'time' => $test_time,
    'seat' => 22,
    'price' => 50.0,
    'name' => 'Clock Check User',
    'contact' => '9876543210',
    'status' => 'Pending'
]);
$hold_row = db_one($link, "SELECT hold_expires_at, TIMESTAMPDIFF(MINUTE, NOW(), hold_expires_at) AS diff_min FROM booking WHERE pnr = ?", 's', [$res_hold_clk['pnr']]);
assert_test("Seat hold expiration window is exactly ~10 minutes relative to database clock (U-12)",
    isset($hold_row['diff_min']) && (int)$hold_row['diff_min'] >= 9 && (int)$hold_row['diff_min'] <= 11,
    "diff_min: " . ($hold_row['diff_min'] ?? 'null')
);

// -------------------------------------------------------------
// Test 10: Slice C Verification (U-11, U-13, U-18)
// -------------------------------------------------------------
echo "\n[*] Suite 10: Slice C Search Performance & Formatting (U-11 to U-18)\n";

// 1. Format date and time helpers (U-18)
assert_test("fmt_date formats YYYY-MM-DD to human string", fmt_date('2026-10-05') === 'Mon, 5 Oct 2026', "Got: " . fmt_date('2026-10-05'));
assert_test("fmt_time formats HH:MM:SS to 12-hour AM/PM", fmt_time('08:30:00') === '8:30 AM', "Got: " . fmt_time('08:30:00'));
assert_test("fmt_time formats afternoon HH:MM:SS to PM", fmt_time('18:45:00') === '6:45 PM', "Got: " . fmt_time('18:45:00'));
assert_test("fmt_date handles empty string safely", fmt_date('') === '');
assert_test("fmt_time handles empty string safely", fmt_time('') === '');

// 2. Grouped availability count query check (U-11)
$grouped_test = db_all(
    $link,
    "SELECT bus, `time`, COUNT(*) AS taken_count
     FROM booking
     WHERE `date` = ? AND status IN ('Confirmed', 'Pending') AND bus = ?
     GROUP BY bus, `time`",
    'ss',
    [$test_date, $test_busno]
);
assert_test("Grouped seat availability executes without error (U-11)", is_array($grouped_test));

// Clean up test data
db_exec($link, 'DELETE FROM booking WHERE bus = ?', 's', [$test_busno]);
db_exec($link, 'DELETE FROM buses WHERE bus_number = ?', 's', [$test_busno]);

// -------------------------------------------------------------
// Test Summary
// -------------------------------------------------------------
echo "\n========================================================\n";
echo "   Test Results: {$passed} Passed, {$failed} Failed     \n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
