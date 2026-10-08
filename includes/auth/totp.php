<?php
// includes/auth/totp.php -- RFC 6238 TOTP Two-Factor Authentication & Recovery Codes (Issue 2)
declare(strict_types=1);

/**
 * Base32 character set per RFC 4648.
 */
const TOTP_BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/**
 * Generates an RFC 6238 Base32 secret string.
 */
function totp_generate_secret(int $length = 16): string {
    $chars = TOTP_BASE32_CHARS;
    $secret = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, $max)];
    }
    return $secret;
}

/**
 * Decodes a Base32 string into binary bytes.
 */
function totp_base32_decode(string $b32): string {
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32) ?? '');
    $binary = '';
    for ($i = 0; $i < strlen($b32); $i++) {
        $pos = strpos(TOTP_BASE32_CHARS, $b32[$i]);
        if ($pos === false) continue;
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    $len = strlen($binary);
    for ($i = 0; $i + 8 <= $len; $i += 8) {
        $bytes .= chr((int)bindec(substr($binary, $i, 8)));
    }
    return $bytes;
}

/**
 * Computes the 6-digit TOTP code for a specific 30-second time slice.
 */
function totp_calc_code(string $secret, int $timeSlice): string {
    $secretKey = totp_base32_decode($secret);
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hmac = hash_hmac('sha1', $time, $secretKey, true);
    $offset = ord(substr($hmac, -1)) & 0x0F;
    $hashpart = substr($hmac, $offset, 4);
    $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;
    $modulo = 1000000;
    return str_pad((string)($value % $modulo), 6, '0', STR_PAD_LEFT);
}

/**
 * Verifies a 6-digit TOTP code against a secret within a ±$discrepancy window.
 * Returns true if valid and outputs the matched time slice.
 */
function totp_verify_code(string $secret, string $code, int $discrepancy = 1, ?int &$matchedSlice = null): bool {
    $code = trim($code);
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        return false;
    }
    $currentTimeSlice = (int)floor(time() / 30);
    for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
        $slice = $currentTimeSlice + $i;
        if (hash_equals(totp_calc_code($secret, $slice), $code)) {
            $matchedSlice = $slice;
            return true;
        }
    }
    return false;
}

/**
 * Constructs an otpauth:// URI for authenticator applications.
 */
function totp_get_otpauth_uri(string $secret, string $accountEmail, string $issuer = 'Bus Reservation'): string {
    $label = rawurlencode($issuer) . ':' . rawurlencode($accountEmail);
    return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}

/**
 * Derives a 256-bit encryption key from APP_SECRET or fallback environment key.
 */
function totp_get_encryption_key(): string {
    $secret = getenv('APP_SECRET') ?: ($_ENV['APP_SECRET'] ?? '');
    if (!$secret) {
        $secret = 'busres-totp-system-secret-v2-fallback-key';
    }
    return hash('sha256', (string)$secret, true);
}

/**
 * Encrypts totp_secret at rest using AES-256-GCM.
 */
function totp_encrypt_secret(string $secret): string {
    $key = totp_get_encryption_key();
    $iv = openssl_random_pseudo_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    return base64_encode((string)json_encode([
        'iv'  => base64_encode($iv),
        'tag' => base64_encode($tag),
        'ct'  => base64_encode((string)$ciphertext)
    ]));
}

/**
 * Decrypts totp_secret from AES-256-GCM payload.
 */
function totp_decrypt_secret(string $payload): ?string {
    if (empty($payload)) {
        return null;
    }
    $decoded = json_decode((string)base64_decode($payload), true);
    if (!is_array($decoded) || !isset($decoded['iv'], $decoded['tag'], $decoded['ct'])) {
        // Plaintext Base32 fallback if already plain in legacy row
        return preg_match('/^[A-Z2-7]{16,64}$/i', $payload) ? strtoupper($payload) : null;
    }
    $key = totp_get_encryption_key();
    $iv = base64_decode((string)$decoded['iv']);
    $tag = base64_decode((string)$decoded['tag']);
    $ciphertext = base64_decode((string)$decoded['ct']);
    $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return ($plain !== false) ? (string)$plain : null;
}

/**
 * Generates 10 single-use recovery codes.
 */
function totp_generate_recovery_codes(int $count = 10): array {
    $codes = [];
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($i = 0; $i < $count; $i++) {
        $part1 = '';
        $part2 = '';
        for ($j = 0; $j < 4; $j++) $part1 .= $chars[random_int(0, strlen($chars) - 1)];
        for ($j = 0; $j < 4; $j++) $part2 .= $chars[random_int(0, strlen($chars) - 1)];
        $codes[] = $part1 . '-' . $part2;
    }
    return $codes;
}

/**
 * Ensures admin_recovery_codes table exists in database.
 */
function ensure_admin_recovery_table(mysqli $link): void {
    static $checked = false;
    if ($checked) return;
    try {
        mysqli_query($link, "
            CREATE TABLE IF NOT EXISTS `admin_recovery_codes` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `admin_id` INT NOT NULL,
              `code_hash` VARCHAR(255) NOT NULL,
              `used_at` DATETIME NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY `idx_admin_recovery` (`admin_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $checked = true;
    } catch (Throwable $e) {
        error_log('[busres] ensure_admin_recovery_table error: ' . $e->getMessage());
    }
}

/**
 * Hashes and saves recovery codes for an administrator.
 */
function store_recovery_codes(mysqli $link, int $admin_id, array $plain_codes): void {
    ensure_admin_recovery_table($link);
    // Delete any existing unused recovery codes for this admin
    db_exec($link, "DELETE FROM admin_recovery_codes WHERE admin_id = ?", 'i', [$admin_id]);
    foreach ($plain_codes as $code) {
        $clean = strtoupper(trim(str_replace('-', '', (string)$code)));
        $hash = password_hash($clean, PASSWORD_DEFAULT);
        db_exec($link, "INSERT INTO admin_recovery_codes (admin_id, code_hash) VALUES (?, ?)", 'is', [$admin_id, $hash]);
    }
}

/**
 * Validates and consumes a recovery code.
 */
function verify_and_consume_recovery_code(mysqli $link, int $admin_id, string $code): bool {
    ensure_admin_recovery_table($link);
    $clean = strtoupper(trim(str_replace('-', '', $code)));
    if (strlen($clean) !== 8) {
        return false;
    }
    $rows = db_all($link, "SELECT id, code_hash FROM admin_recovery_codes WHERE admin_id = ? AND used_at IS NULL", 'i', [$admin_id]);
    foreach ($rows as $r) {
        if (password_verify($clean, $r['code_hash'])) {
            db_exec($link, "UPDATE admin_recovery_codes SET used_at = NOW() WHERE id = ?", 'i', [(int)$r['id']]);
            return true;
        }
    }
    return false;
}
