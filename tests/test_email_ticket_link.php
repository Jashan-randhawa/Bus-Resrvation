<?php
// tests/test_email_ticket_link.php -- Boarding Pass Email Button & Signature Access Tests
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI execution only.\n";
    exit(1);
}

echo "========================================================\n";
echo "   Boarding Pass Email Link & Verification Suite       \n";
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

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

// 1. get_app_url() resolution tests
echo "[*] Suite 1: get_app_url() base URL resolution\n";

putenv('APP_URL=https://bus-resrvation.onrender.com');
assert_test("get_app_url() honors APP_URL environment variable", get_app_url() === 'https://bus-resrvation.onrender.com');

putenv('APP_URL=https://bus-resrvation.onrender.com/');
assert_test("get_app_url() trims trailing slash from APP_URL", get_app_url() === 'https://bus-resrvation.onrender.com');

putenv('APP_URL');
putenv('RENDER_EXTERNAL_URL=https://bus-service.onrender.com');
assert_test("get_app_url() falls back to RENDER_EXTERNAL_URL", get_app_url() === 'https://bus-service.onrender.com' . (defined('BASE_URL') ? BASE_URL : ''));

putenv('RENDER_EXTERNAL_URL');
putenv('RENDER_EXTERNAL_HOSTNAME=my-bus.onrender.com');
assert_test("get_app_url() falls back to RENDER_EXTERNAL_HOSTNAME with https", get_app_url() === 'https://my-bus.onrender.com' . (defined('BASE_URL') ? BASE_URL : ''));

putenv('RENDER_EXTERNAL_HOSTNAME');
$_SERVER['HTTP_HOST'] = 'localhost:8000';
$_SERVER['HTTPS'] = 'off';
assert_test("get_app_url() falls back to HTTP_HOST when no env vars set", get_app_url() === 'http://localhost:8000' . (defined('BASE_URL') ? BASE_URL : ''));

$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
assert_test("get_app_url() detects forwarded https protocol", str_starts_with(get_app_url(), 'https://'));
unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

// 2. Ticket signature generation
echo "\n[*] Suite 2: Ticket cryptographic signature verification\n";
$mock_booking = [
    'pnr'  => '9B3A57EF10',
    'date' => '2026-10-25',
    'time' => '14:30:00',
    'seat' => '12',
];

$sig = get_ticket_signature($mock_booking);
assert_test("get_ticket_signature() returns non-empty 12-char hex string", strlen($sig) === 12 && ctype_xdigit($sig));

$mock_booking2 = $mock_booking;
$mock_booking2['seat'] = '14';
$sig2 = get_ticket_signature($mock_booking2);
assert_test("get_ticket_signature() varies with seat change", $sig !== $sig2);

assert_test("hash_equals validates matching signature", hash_equals($sig, get_ticket_signature($mock_booking)));
assert_test("hash_equals rejects forged signature", !hash_equals($sig, '000000000000'));

// 3. Static checks on booking.php and ticket.php
echo "\n[*] Suite 3: Static checks in booking.php & ticket.php\n";
$booking_src = file_get_contents(__DIR__ . '/../user/booking.php');
$ticket_src = file_get_contents(__DIR__ . '/../user/ticket.php');
$header_src = file_get_contents(__DIR__ . '/../includes/layout/header-user.php');

assert_test("booking.php uses get_app_url() to build absolute ticket URL",
    str_contains($booking_src, 'get_app_url() . \'/user/ticket.php?pnr=\''));

assert_test("booking.php attaches cryptographic signature (&sig=) to email link",
    str_contains($booking_src, '&sig=\' . urlencode($ticket_sig)'));

assert_test("booking.php contains fallback copyable text link in email",
    str_contains($booking_src, 'If the button above does not open'));

assert_test("ticket.php uses session-bootstrap instead of user-session",
    str_contains($ticket_src, 'includes/auth/session-bootstrap.php') &&
    !str_contains($ticket_src, 'includes/auth/user-session.php'));

assert_test("ticket.php checks for &sig= parameter",
    str_contains($ticket_src, "\$sig = trim((string)(\$_GET['sig'] ?? ''));"));

assert_test("ticket.php validates HMAC signature via hash_equals",
    str_contains($ticket_src, 'hash_equals($expected_sig, $sig)'));

assert_test("ticket.php authorizes matching signature without active login",
    str_contains($ticket_src, '$sig_valid = ($sig !== \'\' && hash_equals($expected_sig, $sig));'));

assert_test("header-user.php safely defaults profile name when logged out",
    str_contains($header_src, '$_SESSION[\'name\'] ?? ($booking[\'name\'] ?? \'Passenger\')'));

assert_test("header-user.php wraps idleSessionModal in logged in check",
    str_contains($header_src, '<?php if (!empty($_SESSION[\'uid\'])): ?>') &&
    str_contains($header_src, 'id="idleSessionModal"'));

echo "\n========================================================\n";
echo "   Email Ticket Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
