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
