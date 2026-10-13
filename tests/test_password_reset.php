<?php
// tests/test_password_reset.php -- Email reset-link flow (no database required).
// Usage: php tests/test_password_reset.php
declare(strict_types=1);

$total = 0;
$failed = 0;
function check(string $desc, bool $ok): void {
    global $total, $failed;
    $total++;
    echo ($ok ? ' [PASS] ' : ' [FAIL] ') . $desc . "\n";
    if (!$ok) { $failed++; }
}

echo "========================================================\n";
echo "   Password reset by e-mail link: test suite            \n";
echo "========================================================\n\n";

require_once __DIR__ . '/../includes/password-reset.php';

$root   = dirname(__DIR__);
$forgot = (string)file_get_contents($root . '/forgot-password.php');
$reset  = (string)file_get_contents($root . '/reset-password.php');
$lib    = (string)file_get_contents($root . '/includes/password-reset.php');

echo "[*] Suite 1: tokens\n";
$t = pr_new_token();
check('token is 64 hex chars', pr_is_token_shape($t));
check('two tokens differ', $t !== pr_new_token());
check('hash is sha256 of token', pr_hash_token($t) === hash('sha256', $t) && strlen(pr_hash_token($t)) === 64);
check('hash is not the token', pr_hash_token($t) !== $t);
check('rejects short / non-hex / SQL-ish tokens',
    !pr_is_token_shape('abc') && !pr_is_token_shape(str_repeat('g', 64)) && !pr_is_token_shape("' OR 1=1 --") && !pr_is_token_shape(''));

echo "\n[*] Suite 2: lifetime\n";
putenv('PASSWORD_RESET_TTL_MIN');
check('default TTL is 10 minutes', pr_ttl_minutes() === 10);
putenv('PASSWORD_RESET_TTL_MIN=1');
check('TTL is clamped up to 5', pr_ttl_minutes() === 5);
putenv('PASSWORD_RESET_TTL_MIN=999');
check('TTL is clamped down to 60', pr_ttl_minutes() === 60);
putenv('PASSWORD_RESET_TTL_MIN=15');
check('TTL honours env value', pr_ttl_minutes() === 15);
putenv('PASSWORD_RESET_TTL_MIN');

echo "\n[*] Suite 3: reset link URL\n";
putenv('APP_URL'); putenv('RENDER_EXTERNAL_URL'); putenv('APP_ENV');
$_SERVER['HTTP_HOST'] = 'evil.example';
check('no trusted base URL in production -> no link (Host header is ignored)', pr_build_reset_url($t) === '');
putenv('APP_URL=https://bus.example.com/');
check('APP_URL is used, trailing slash trimmed',
    pr_build_reset_url($t) === 'https://bus.example.com/reset-password.php?code=' . $t);
putenv('APP_URL');
putenv('RENDER_EXTERNAL_URL=https://bus-resrvation.onrender.com');
check('RENDER_EXTERNAL_URL is used as fallback', str_starts_with(pr_build_reset_url($t), 'https://bus-resrvation.onrender.com'));
putenv('RENDER_EXTERNAL_URL');
putenv('APP_ENV=development');
check('development falls back to request host', str_contains(pr_build_reset_url($t), 'evil.example/reset-password.php?code='));
putenv('APP_ENV');
unset($_SERVER['HTTP_HOST']);

echo "\n[*] Suite 4: e-mail templates\n";
$link = 'https://bus.example.com/reset-password.php?code=' . $t;
$html = pr_reset_email_html($link, 10, 'Asha <script>alert(1)</script>');
check('reset e-mail contains the link', str_contains($html, $link));
check('reset e-mail states expiry', str_contains($html, '10 minutes'));
check('customer name is HTML-escaped', !str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'));
check('notice e-mail built', str_contains(pr_notice_email_html('Asha'), 'password for your account was just changed'));

echo "\n[*] Suite 5: security properties (static)\n";
check('stored value is the hash (INSERT uses pr_hash_token)', str_contains($lib, 'pr_hash_token($token), $ttl]'));
check('plain token is never inserted', !preg_match('/\[\s*\$email\s*,\s*\$token\s*,/', $lib));
check('forgot page never renders a reset link',
    !str_contains($forgot, 'reset_link') && !str_contains($forgot, 'reset-password.php?') && !str_contains($forgot, 'random_bytes'));
check('forgot page sends response before e-mail work', str_contains($forgot, 'pr_after_response'));
check('forgot page verifies CSRF', str_contains($forgot, 'csrf_verify()'));
check('same response for unknown address (no enumeration branch in page)', !str_contains($forgot, 'No account'));
check('reset page is no-store + no-referrer',
    str_contains($reset, 'Cache-Control: no-store') && str_contains($reset, 'Referrer-Policy: no-referrer'));
check('reset page verifies CSRF', str_contains($reset, 'csrf_verify()'));
check('reset page uses ?code= link format', str_contains($reset, "\$_GET['code']"));
check('token deleted after successful reset (single use)', substr_count($lib, 'DELETE FROM password_resets WHERE email = ?') >= 3);
check('delivery failure deletes the stored token', str_contains($lib, '$drop();') && str_contains($lib, 'email_failed'));
check('archived customers cannot reset', substr_count($lib, 'archived_at IS NULL') >= 2);
check('current password reuse is blocked', str_contains($lib, 'password_verify($newPwd'));
check('password changed notice is sent', str_contains($lib, 'pr_notice_email_html'));
check('uses shared password policy', str_contains($lib, "validate_new_password(\$newPwd, 'user')"));
check('request throttle counts unknown addresses too',
    strpos($lib, 'throttle_hit($link, $key)') !== false && strpos($lib, 'costumer') > strpos($lib, 'throttle_hit($link, $key)'));
check('bad tokens are throttled per IP', str_contains($lib, "'pwreset:fail:'"));
check('Host header only trusted in development', str_contains($lib, "'development'"));

echo "\n[*] Suite 6: wiring\n";
foreach (['login.php', 'homepage.php'] as $f) {
    check("$f links to forgot-password.php", str_contains((string)file_get_contents("$root/$f"), 'forgot-password.php'));
}
check('migration 011 exists', is_file($root . '/database/migrations/011_password_reset_hashed_tokens.sql'));
check('.env.example documents APP_URL and TTL',
    str_contains((string)file_get_contents($root . '/.env.example'), 'APP_URL=') &&
    str_contains((string)file_get_contents($root . '/.env.example'), 'PASSWORD_RESET_TTL_MIN'));

echo "\n--------------------------------------------------------\n";
echo "Assertions: $total, failed: $failed\n";
exit($failed === 0 ? 0 : 1);
