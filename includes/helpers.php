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
        $return_url = $_SERVER['HTTP_REFERER'] ?? (defined('BASE_URL') ? BASE_URL . '/homepage.php' : '/');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Session Expired</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"><style>body{background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;font-family:system-ui,-apple-system,sans-serif;}.box{background:#fff;border-radius:12px;padding:32px;max-width:480px;width:90%;box-shadow:0 10px 25px -5px rgba(0,0,0,0.1);text-align:center;border:1px solid #e2e8f0;}</style></head><body><div class="box"><div style="font-size:48px;margin-bottom:16px;">⏱️</div><h4 class="font-weight-bold text-dark mb-2">Session Timed Out</h4><p class="text-muted mb-4">Your security token expired or became invalid. Please return to the previous page and refresh to continue safely.</p><a href="' . e($return_url) . '" class="btn btn-primary btn-block font-weight-bold py-2 shadow-sm">&larr; Return to Previous Page</a></div></body></html>';
        exit;
    }
}

/* ---------- Flash Messages (D-03) ---------- */
function flash_set(string $type, string $msg): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Date & Time Formatting (U-18) ---------- */
function fmt_date(?string $date, string $format = 'D, j M Y'): string {
    if ($date === null || $date === '') {
        return '';
    }
    $ts = strtotime($date);
    return $ts !== false ? date($format, $ts) : $date;
}

function fmt_time(?string $time, string $format = 'g:i A'): string {
    if ($time === null || $time === '') {
        return '';
    }
    $ts = strtotime($time);
    return $ts !== false ? date($format, $ts) : $time;
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

function ensure_login_attempts_table(mysqli $link): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        mysqli_query($link, "
            CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `k` CHAR(40) NOT NULL,
                `ts` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_attempts` (`k`, `ts`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $checked = true;
    } catch (mysqli_sql_exception $e) {
        error_log('[busres] ensure_login_attempts_table: ' . $e->getMessage());
    }
}

function ensure_password_resets_table(mysqli $link): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        mysqli_query($link, "
            CREATE TABLE IF NOT EXISTS `password_resets` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `email` VARCHAR(100) NOT NULL,
                `token` CHAR(64) NOT NULL,
                `expires_at` TIMESTAMP NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_token` (`token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $checked = true;
    } catch (Throwable $e) {
        error_log('[busres] ensure_password_resets_table: ' . $e->getMessage());
    }
}

function throttle_hit(mysqli $link, string $key): void {
    ensure_login_attempts_table($link);
    try {
        db_exec($link, 'INSERT INTO login_attempts (k) VALUES (?)', 's', [sha1($key)]);
        // O9: Clean up expired rows older than 1 day so table stays small
        if (random_int(1, 20) === 1) {
            db_exec($link, 'DELETE FROM login_attempts WHERE ts < (NOW() - INTERVAL 1 DAY)');
        }
    } catch (mysqli_sql_exception $e) {
        // Fallback gracefully without breaking login if table creation pending
        error_log('[busres] throttle_hit exception: ' . $e->getMessage());
    }
}

function throttle_clear(mysqli $link, string $key): void {
    ensure_login_attempts_table($link);
    try {
        db_exec($link, 'DELETE FROM login_attempts WHERE k = ?', 's', [sha1($key)]);
    } catch (mysqli_sql_exception $e) {
        error_log('[busres] throttle_clear exception: ' . $e->getMessage());
    }
}

function throttle_blocked(mysqli $link, string $key, int $max = 5, int $window = 900): bool {
    ensure_login_attempts_table($link);
    try {
        $r = db_one($link,
            'SELECT COUNT(*) AS n FROM login_attempts WHERE k = ? AND ts > (NOW() - INTERVAL ? SECOND)',
            'si', [sha1($key), $window]);
        return (int)($r['n'] ?? 0) >= $max;
    } catch (mysqli_sql_exception $e) {
        error_log('[busres] throttle_blocked exception: ' . $e->getMessage());
        return false;
    }
}

/**
 * Cached table column inspection to avoid redundant SHOW COLUMNS queries per request (P-10).
 */
function table_columns(mysqli $link, string $table): array {
    static $cache = [];
    if (!isset($cache[$table])) {
        $cols = [];
        try {
            $res = mysqli_query($link, "SHOW COLUMNS FROM `{$table}`");
            if ($res instanceof mysqli_result) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $cols[] = $r['Field'];
                }
                mysqli_free_result($res);
            }
        } catch (Throwable $e) {
            error_log('[busres] table_columns error for ' . $table . ': ' . $e->getMessage());
        }
        $cache[$table] = $cols;
    }
    return $cache[$table];
}

function table_has_column(mysqli $link, string $table, string $column): bool {
    return in_array($column, table_columns($link, $table), true);
}

/**
 * Automatically releases seats held in 'Pending' status whose hold window has expired (O13).
 */
function release_expired_holds(mysqli $link): void {
    if (table_has_column($link, 'booking', 'status') && table_has_column($link, 'booking', 'hold_expires_at')) {
        db_exec($link,
            "UPDATE booking SET status = 'Expired' WHERE status = 'Pending' AND hold_expires_at IS NOT NULL AND hold_expires_at < NOW()"
        );
    }
}

/**
 * Returns a map of booked seat numbers [seat_no => true] for a specific bus, date, and departure time.
 * Ignores cancelled and expired bookings. Automatically sweeps expired holds.
 */
function get_booked_seats(mysqli $link, string $bus, string $date, string $time): array {
    $booked = [];
    if ($bus === '' || $date === '' || $time === '') {
        return $booked;
    }

    // Sweep expired holds first (O13)
    release_expired_holds($link);

    $has_status = table_has_column($link, 'booking', 'status');

    if ($has_status) {
        $rows = db_all($link,
            "SELECT seat FROM booking WHERE bus = ? AND `date` = ? AND `time` = ? AND (status IS NULL OR status IN ('Confirmed', 'Pending'))",
            'sss', [$bus, $date, $time]
        );
    } else {
        $rows = db_all($link,
            'SELECT seat FROM booking WHERE bus = ? AND `date` = ? AND `time` = ?',
            'sss', [$bus, $date, $time]
        );
    }

    foreach ($rows as $r) {
        $booked[(int)$r['seat']] = true;
    }
    return $booked;
}

/**
 * Get total seat capacity for a bus number (O8).
 * Defaults to 36 if column is not yet present or value is invalid.
 */
function get_bus_capacity(mysqli $link, string $bus_number): int {
    if ($bus_number === '') {
        return 36;
    }
    if (table_has_column($link, 'buses', 'capacity')) {
        $row = db_one($link, 'SELECT capacity FROM buses WHERE bus_number = ? LIMIT 1', 's', [$bus_number]);
        $cap = (int)($row['capacity'] ?? 0);
        if ($cap > 0) {
            return $cap;
        }
    }
    return 36;
}

/**
 * Canonical booking helper (O6).
 * Validates travel date, seat number, and inserts atomically, catching duplicate seat reservations.
 *
 * @return array ['ok' => bool, 'pnr' => string, 'error' => string]
 */
/**
 * Validates travel date and optional departure time against scheduling rules (U-03).
 *
 * @param string $date Travel date in YYYY-MM-DD format
 * @param string|null $time Departure time (e.g., HH:MM or HH:MM:SS)
 * @return array ['ok' => bool, 'error' => string]
 */
function validate_travel_datetime(string $date, ?string $time = null): array {
    if ($date === '') {
        return ['ok' => false, 'error' => 'Please provide a travel date.'];
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        return ['ok' => false, 'error' => 'Please provide a valid travel date (YYYY-MM-DD).'];
    }

    $today = date('Y-m-d');
    $max_date = date('Y-m-d', strtotime('+90 days'));
    $now_time = date('H:i:s');

    if ($date < $today) {
        return ['ok' => false, 'error' => 'Travel date cannot be in the past.'];
    }
    if ($date > $max_date) {
        return ['ok' => false, 'error' => 'Bookings can only be made up to 90 days in advance.'];
    }
    if ($time !== null && $time !== '') {
        $t_parsed = date('H:i:s', strtotime($time));
        if ($date === $today && $t_parsed < $now_time) {
            return ['ok' => false, 'error' => 'This bus has already departed for today.'];
        }
    }

    return ['ok' => true, 'error' => ''];
}

function create_booking(mysqli $link, array $data): array {
    $bus = trim((string)($data['bus'] ?? ''));
    $from = trim((string)($data['city1'] ?? ''));
    $to = trim((string)($data['city2'] ?? ''));
    $date = trim((string)($data['date'] ?? ''));
    $time = trim((string)($data['time'] ?? ''));
    $seat = (int)($data['seat'] ?? 0);
    $price = (float)($data['price'] ?? 0);
    $name = trim((string)($data['name'] ?? ''));
    $contact = trim((string)($data['contact'] ?? ''));
    $cust_id = (int)($data['id'] ?? 0);

    if ($bus === '' || $from === '' || $to === '') {
        return ['ok' => false, 'pnr' => '', 'error' => 'Incomplete route details.'];
    }

    // Validate travel date and departure time (U-03)
    $dt_val = validate_travel_datetime($date, $time);
    if (!$dt_val['ok']) {
        return ['ok' => false, 'pnr' => '', 'error' => $dt_val['error']];
    }

    $capacity = get_bus_capacity($link, $bus);

    if ($seat < 1 || $seat > $capacity) {
        return ['ok' => false, 'pnr' => '', 'error' => "Please select a valid seat number between 1 and {$capacity}."];
    }

    // Passenger name and phone validation (U-07)
    if ($name === '' || $contact === '') {
        return ['ok' => false, 'pnr' => '', 'error' => 'Passenger name and contact number are required.'];
    }
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        return ['ok' => false, 'pnr' => '', 'error' => 'Passenger name must be between 2 and 100 characters.'];
    }
    $phone_digits = preg_replace('/\D+/', '', $contact);
    if (strlen($phone_digits) < 10 || strlen($phone_digits) > 15) {
        return ['ok' => false, 'pnr' => '', 'error' => 'Please enter a valid phone number (10 to 15 digits).'];
    }
    $contact = $phone_digits;

    $has_pnr = table_has_column($link, 'booking', 'pnr');
    $has_status = table_has_column($link, 'booking', 'status');
    $has_hold = table_has_column($link, 'booking', 'hold_expires_at');

    $booking_status = trim((string)($data['status'] ?? 'Confirmed'));
    if (!in_array($booking_status, ['Confirmed', 'Pending'], true)) {
        $booking_status = 'Confirmed';
    }

    // Release any stale holds before checking availability (O13)
    release_expired_holds($link);

    // If status column exists, verify seat is not currently active
    if ($has_status) {
        $active_seat = db_one($link,
            "SELECT sno FROM booking WHERE bus = ? AND `date` = ? AND `time` = ? AND seat = ? AND status IN ('Confirmed', 'Pending') LIMIT 1",
            'sssi', [$bus, $date, $time, $seat]
        );
        if ($active_seat) {
            return ['ok' => false, 'pnr' => '', 'error' => "Seat #{$seat} on bus {$bus} for date {$date} ({$time}) is already booked."];
        }
    }

    mysqli_begin_transaction($link);
    try {
        $pnr = strtoupper(bin2hex(random_bytes(5)));

        if ($has_pnr && $has_status && $has_hold) {
            if ($booking_status === 'Pending') {
                $sql = "INSERT INTO booking (id, bus, name, contact, city1, city2, `date`, `time`, seat, price, pnr, status, hold_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))";
            } else {
                $sql = "INSERT INTO booking (id, bus, name, contact, city1, city2, `date`, `time`, seat, price, pnr, status, hold_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)";
            }
            db_exec($link, $sql, 'isssssssidss', [$cust_id, $bus, $name, $contact, $from, $to, $date, $time, $seat, $price, $pnr, $booking_status]);
        } elseif ($has_pnr && $has_status) {
            $sql = "INSERT INTO booking (id, bus, name, contact, city1, city2, `date`, `time`, seat, price, pnr, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            db_exec($link, $sql, 'isssssssidss', [$cust_id, $bus, $name, $contact, $from, $to, $date, $time, $seat, $price, $pnr, $booking_status]);
        } elseif ($has_pnr) {
            $sql = 'INSERT INTO booking (id, bus, name, contact, city1, city2, `date`, `time`, seat, price, pnr) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            db_exec($link, $sql, 'isssssssids', [$cust_id, $bus, $name, $contact, $from, $to, $date, $time, $seat, $price, $pnr]);
        } else {
            $sql = 'INSERT INTO booking (id, bus, name, contact, city1, city2, `date`, `time`, seat, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            db_exec($link, $sql, 'isssssssid', [$cust_id, $bus, $name, $contact, $from, $to, $date, $time, $seat, $price]);
        }

        mysqli_commit($link);
        return ['ok' => true, 'pnr' => $pnr, 'status' => $booking_status, 'error' => ''];
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($link);
        if ((int)$e->getCode() === 1062) {
            // U-05: Double submit / rapid duplicate submission recovery
            if ($cust_id > 0) {
                $existing = db_one($link,
                    "SELECT pnr FROM booking WHERE id = ? AND bus = ? AND `date` = ? AND `time` = ? AND seat = ? AND status IN ('Confirmed', 'Pending') LIMIT 1",
                    'isssi', [$cust_id, $bus, $date, $time, $seat]
                );
                if ($existing && !empty($existing['pnr'])) {
                    return ['ok' => true, 'pnr' => $existing['pnr'], 'status' => 'Confirmed', 'error' => '', 'duplicate' => true];
                }
            }
            return ['ok' => false, 'pnr' => '', 'error' => "Seat #{$seat} on bus {$bus} for date {$date} ({$time}) was just reserved by another passenger. Please pick another seat."];
        }
        // U-06: Log internal database error without exposing raw database exceptions to users
        error_log("create_booking error: " . $e->getMessage());
        return ['ok' => false, 'pnr' => '', 'error' => 'We could not complete the booking. Please try again.'];
    }
}
