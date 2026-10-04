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
 * Automatically releases seats held in 'Pending' status whose hold window has expired (O13).
 */
function release_expired_holds(mysqli $link): void {
    $cols = db_all($link, 'SHOW COLUMNS FROM booking');
    $col_names = array_column($cols, 'Field');
    if (in_array('status', $col_names, true) && in_array('hold_expires_at', $col_names, true)) {
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

    $cols = db_all($link, 'SHOW COLUMNS FROM booking');
    $has_status = in_array('status', array_column($cols, 'Field'), true);

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
    $cols = db_all($link, 'SHOW COLUMNS FROM buses');
    $has_cap = in_array('capacity', array_column($cols, 'Field'), true);
    if ($has_cap) {
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

    $today = date('Y-m-d');
    $max_date = date('Y-m-d', strtotime('+90 days'));
    $now_time = date('H:i:s');

    if ($bus === '' || $from === '' || $to === '') {
        return ['ok' => false, 'pnr' => '', 'error' => 'Incomplete route details.'];
    }
    if ($date < $today) {
        return ['ok' => false, 'pnr' => '', 'error' => 'Travel date cannot be in the past.'];
    }
    if ($date > $max_date) {
        return ['ok' => false, 'pnr' => '', 'error' => 'Bookings can only be made up to 90 days in advance.'];
    }
    if ($date === $today && $time !== '' && $time < $now_time) {
        return ['ok' => false, 'pnr' => '', 'error' => 'This bus has already departed for today.'];
    }

    $capacity = get_bus_capacity($link, $bus);

    if ($seat < 1 || $seat > $capacity) {
        return ['ok' => false, 'pnr' => '', 'error' => "Please select a valid seat number between 1 and {$capacity}."];
    }
    if ($name === '' || $contact === '') {
        return ['ok' => false, 'pnr' => '', 'error' => 'Passenger name and contact number are required.'];
    }

    $cols = db_all($link, 'SHOW COLUMNS FROM booking');
    $col_names = array_column($cols, 'Field');
    $has_pnr = in_array('pnr', $col_names, true);
    $has_status = in_array('status', $col_names, true);
    $has_hold = in_array('hold_expires_at', $col_names, true);

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
            $hold_exp = ($booking_status === 'Pending') ? date('Y-m-d H:i:s', strtotime('+10 minutes')) : null;
            $sql = "INSERT INTO booking (id, bus, name, contact, city1, city2, `date`, `time`, seat, price, pnr, status, hold_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            db_exec($link, $sql, 'isssssssidsss', [$cust_id, $bus, $name, $contact, $from, $to, $date, $time, $seat, $price, $pnr, $booking_status, $hold_exp]);
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
            return ['ok' => false, 'pnr' => '', 'error' => "Seat #{$seat} on bus {$bus} for date {$date} ({$time}) was just reserved by another passenger. Please pick another seat."];
        }
        return ['ok' => false, 'pnr' => '', 'error' => 'Booking could not be completed: ' . $e->getMessage()];
    }
}
