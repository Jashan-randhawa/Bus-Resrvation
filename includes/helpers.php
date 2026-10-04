<?php
// includes/helpers.php
declare(strict_types=1);

/** HTML-escape for output. Use on EVERY value printed into a page. */
function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals((string)($_SESSION['csrf'] ?? ''), $sent)) {
        http_response_code(419);
        exit('Session expired. Please go back, refresh the page and try again.');
    }
}

/* ---------- Database helpers (prepared statements) ---------- */
function db_one(mysqli $link, string $sql, string $types = '', array $params = []): ?array {
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        throw new mysqli_sql_exception(mysqli_error($link));
    }
    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function db_all(mysqli $link, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        throw new mysqli_sql_exception(mysqli_error($link));
    }
    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

function db_exec(mysqli $link, string $sql, string $types = '', array $params = []): int {
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        throw new mysqli_sql_exception(mysqli_error($link));
    }
    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

/* ---------- Rate limiting (needs table login_attempts, see 001_hardening.sql) ---------- */
function client_ip(): string {
    // When behind a reverse proxy (e.g. Render), the client can spoof the leftmost X-Forwarded-For value.
    // The trusted proxy appends the real client IP at the right. We take the rightmost address (O1).
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff !== '') {
        $ips = array_map('trim', explode(',', $xff));
        $hops = (int)(getenv('TRUSTED_PROXY_HOPS') ?: 1);
        $idx = max(0, count($ips) - $hops);
        $candidate = $ips[$idx] ?? '';
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function throttle_hit(mysqli $link, string $key): void {
    db_exec($link, 'INSERT INTO login_attempts (k) VALUES (?)', 's', [sha1($key)]);
    // O9: Clean up expired rows older than 1 day so table stays small
    if (random_int(1, 20) === 1) {
        db_exec($link, 'DELETE FROM login_attempts WHERE ts < (NOW() - INTERVAL 1 DAY)');
    }
}

function throttle_clear(mysqli $link, string $key): void {
    db_exec($link, 'DELETE FROM login_attempts WHERE k = ?', 's', [sha1($key)]);
}

function throttle_blocked(mysqli $link, string $key, int $max = 5, int $window = 900): bool {
    $r = db_one($link,
        'SELECT COUNT(*) AS n FROM login_attempts WHERE k = ? AND ts > (NOW() - INTERVAL ? SECOND)',
        'si', [sha1($key), $window]);
    return (int)($r['n'] ?? 0) >= $max;
}
