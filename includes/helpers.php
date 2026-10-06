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
 * Automatically releases seats held in 'Pending' status whose hold window has expired (O13, P-03).
 */
function release_expired_holds(mysqli $link): void {
    if (table_has_column($link, 'booking', 'status') && table_has_column($link, 'booking', 'hold_expires_at')) {
        db_exec($link,
            "UPDATE booking SET status = 'Expired' WHERE status = 'Pending' AND hold_expires_at IS NOT NULL AND hold_expires_at < NOW()"
        );
    }
    try {
        db_exec($link, "DELETE FROM seat_lock WHERE held_until IS NOT NULL AND held_until < NOW()");
    } catch (Throwable $e) {
        // Table seat_lock may not exist yet in legacy environments
    }
}

/**
 * Returns a map of booked seat numbers [seat_no => true] for a specific bus, date, and departure time.
 * Checks dedicated seat_lock table and active booking rows. Automatically sweeps expired holds.
 */
function get_booked_seats(mysqli $link, string $bus, string $date, string $time): array {
    $booked = [];
    if ($bus === '' || $date === '' || $time === '') {
        return $booked;
    }

    // Sweep expired holds first (O13, P-03)
    release_expired_holds($link);

    // 1. Check seat_lock table (P-03)
    try {
        $bus_row = db_one($link, 'SELECT id FROM buses WHERE bus_number = ? LIMIT 1', 's', [$bus]);
        if ($bus_row && !empty($bus_row['id'])) {
            $bus_id = (int)$bus_row['id'];
            $lock_rows = db_all($link,
                "SELECT seat_no FROM seat_lock WHERE bus_id = ? AND travel_date = ?",
                'is', [$bus_id, $date]
            );
            foreach ($lock_rows as $lr) {
                $booked[(int)$lr['seat_no']] = true;
            }
        }
    } catch (Throwable $e) {
        // Fallback gracefully if seat_lock is not yet populated
    }

    // 2. Check booking table for active bookings
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
 * Get seat layout pattern for a bus number (U-15).
 * Defaults to '2+2' if column is not yet present or pattern is invalid.
 */
function get_bus_layout(mysqli $link, string $bus_number): string {
    if ($bus_number === '') {
        return '2+2';
    }
    if (table_has_column($link, 'buses', 'layout')) {
        $row = db_one($link, 'SELECT layout FROM buses WHERE bus_number = ? LIMIT 1', 's', [$bus_number]);
        $val = trim((string)($row['layout'] ?? ''));
        if (preg_match('/^[12]\+[12]$/', $val)) {
            return $val;
        }
    }
    return '2+2';
}

/**
 * Build matrix of seat rows based on capacity and pattern (U-15).
 *
 * @param int $capacity Total bus capacity
 * @param string $pattern Column pattern ('2+2', '2+1', '1+2', '1+1')
 * @return array ['left' => int, 'right' => int, 'rows' => array]
 */
function build_seat_layout(int $capacity, string $pattern = '2+2'): array {
    if (!preg_match('/^([12])\+([12])$/', $pattern, $m)) {
        $m = [0, 2, 2];
    }
    $left = (int)$m[1];
    $right = (int)$m[2];
    $per = $left + $right;
    $rows = [];
    $n = 1;

    while ($n <= $capacity) {
        $row = [];
        for ($c = 0; $c < $per; $c++) {
            if ($n > $capacity) {
                $row[] = null; // placeholder for missing seat in short row
                continue;
            }
            $row[] = [
                'no' => $n++,
                'col' => $c,
                'type' => ($c === 0 || $c === $per - 1) ? 'Window' : 'Aisle'
            ];
        }
        $rows[] = $row;
    }
    return ['left' => $left, 'right' => $right, 'rows' => $rows];
}

/**
 * Render HTML seat grid based on capacity and taken seats (P-08).
 *
 * @param int $capacity Total bus seat capacity
 * @param array $taken Array of booked seat numbers [seat_no => true] or [seat_no, ...]
 * @param int $perRow Number of seats per row (default 4)
 * @return string HTML grid markup
 */
function render_seat_grid(int $capacity, array $taken, int $perRow = 4): string {
    $capacity = max(1, $capacity);
    $perRow = max(1, $perRow);
    $takenMap = [];
    foreach ($taken as $k => $v) {
        if ($v === true) {
            $takenMap[(int)$k] = true;
        } elseif (is_numeric($v)) {
            $takenMap[(int)$v] = true;
        }
    }

    $html = '<div class="seat-grid" style="display: grid; grid-template-columns: repeat(' . $perRow . ', 1fr); gap: 10px;">';
    for ($i = 1; $i <= $capacity; $i++) {
        $isBooked = isset($takenMap[$i]);
        $btnClass = $isBooked ? 'btn-danger' : 'btn-outline-secondary';
        $statusLabel = $isBooked ? 'Booked' : 'Available';
        $html .= '<button type="button" class="btn seat-btn ' . $btnClass . '" disabled title="Seat ' . $i . ' (' . $statusLabel . ')">';
        $html .= $i;
        $html .= '</button>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Determines whether a departure timestamp is strictly in the future based on the configured timezone (P-07).
 *
 * @param string $date Travel date in YYYY-MM-DD format
 * @param string $time Departure time (e.g. HH:MM or HH:MM:SS)
 * @return bool True if departure is strictly in the future, false otherwise
 */
function departure_in_future(string $date, string $time = '00:00:00'): bool {
    try {
        $tz = new DateTimeZone(defined('APP_TZ') ? APP_TZ : 'Asia/Kolkata');
        $time_clean = trim($time);
        if ($time_clean === '') {
            $time_clean = '00:00:00';
        } elseif (strlen($time_clean) === 5) {
            $time_clean .= ':00';
        }
        $dep = DateTime::createFromFormat('Y-m-d H:i:s', trim($date) . ' ' . $time_clean, $tz);
        if (!$dep) {
            $dep = new DateTime(trim($date) . ' ' . $time_clean, $tz);
        }
        $now = new DateTime('now', $tz);
        return $dep > $now;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Validates travel date and optional departure time against scheduling rules (U-03, P-07).
 *
 * @param string $date Travel date in YYYY-MM-DD format
 * @param string|null $time Departure time (e.g., HH:MM or HH:MM:SS)
 * @return array ['ok' => bool, 'error' => string]
 */
function validate_travel_datetime(string $date, ?string $time = null): array {
    if ($date === '') {
        return ['ok' => false, 'error' => 'Please provide a travel date.'];
    }
    $tz = new DateTimeZone(defined('APP_TZ') ? APP_TZ : 'Asia/Kolkata');
    $d = DateTime::createFromFormat('Y-m-d', $date, $tz);
    if (!$d || $d->format('Y-m-d') !== $date) {
        return ['ok' => false, 'error' => 'Please provide a valid travel date (YYYY-MM-DD).'];
    }

    $today = (new DateTime('today', $tz))->format('Y-m-d');
    $max_date = (new DateTime('today', $tz))->modify('+90 days')->format('Y-m-d');

    if ($date < $today) {
        return ['ok' => false, 'error' => 'Travel date cannot be in the past.'];
    }
    if ($date > $max_date) {
        return ['ok' => false, 'error' => 'Bookings can only be made up to 90 days in advance.'];
    }
    if ($time !== null && $time !== '') {
        $time_clean = trim($time);
        if (strlen($time_clean) === 5) {
            $time_clean .= ':00';
        }
        $dep_dt = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time_clean, $tz);
        $now_dt = new DateTime('now', $tz);
        $cutoff_min = defined('APP_BOOKING_CUTOFF_MIN') ? (int)APP_BOOKING_CUTOFF_MIN : 30;
        $cutoff_dt = $dep_dt ? (clone $dep_dt)->modify("-{$cutoff_min} minutes") : null;

        if ($dep_dt && $dep_dt <= $now_dt) {
            return ['ok' => false, 'error' => 'This bus has already departed for today.'];
        }
        if ($cutoff_dt && $now_dt > $cutoff_dt) {
            return ['ok' => false, 'error' => "Booking has closed for this departure (reservations close {$cutoff_min} minutes prior to departure)."];
        }
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * Canonical booking helper (O6, P-03, P-04).
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

    if ($bus === '' || $from === '' || $to === '') {
        return ['ok' => false, 'pnr' => '', 'error' => 'Incomplete route details.'];
    }

    // Validate travel date and departure time (U-03, P-07)
    $dt_val = validate_travel_datetime($date, $time);
    if (!$dt_val['ok']) {
        return ['ok' => false, 'pnr' => '', 'error' => $dt_val['error']];
    }

    // Lookup bus and capacity (P-04, P-08)
    $bus_row = db_one($link, 'SELECT id, capacity FROM buses WHERE bus_number = ? LIMIT 1', 's', [$bus]);
    $bus_id = $bus_row ? (int)$bus_row['id'] : null;
    $capacity = $bus_row && (int)$bus_row['capacity'] > 0 ? (int)$bus_row['capacity'] : get_bus_capacity($link, $bus);

    if ($seat < 1 || $seat > $capacity) {
        return ['ok' => false, 'pnr' => '', 'error' => "Please select a valid seat number between 1 and {$capacity}."];
    }

    // Lookup route_id (P-04)
    $route_row = db_one($link, 'SELECT sno FROM route WHERE city1 = ? AND city2 = ? AND busno = ? AND time = ? LIMIT 1', 'ssss', [$from, $to, $bus, $time]);
    $route_id = $route_row ? (int)$route_row['sno'] : null;

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
    $has_bus_id = table_has_column($link, 'booking', 'bus_id');
    $has_route_id = table_has_column($link, 'booking', 'route_id');

    $booking_status = trim((string)($data['status'] ?? 'Confirmed'));
    if (!in_array($booking_status, ['Confirmed', 'Pending'], true)) {
        $booking_status = 'Confirmed';
    }

    // Release any stale holds before checking availability (O13, P-03)
    release_expired_holds($link);

    // Pre-check seat_lock if bus_id is known (P-03)
    if ($bus_id !== null) {
        try {
            $locked = db_one($link,
                "SELECT booking_id FROM seat_lock WHERE bus_id = ? AND travel_date = ? AND seat_no = ? LIMIT 1",
                'isi', [$bus_id, $date, $seat]
            );
            if ($locked) {
                return ['ok' => false, 'pnr' => '', 'error' => "Seat #{$seat} on bus {$bus} for date {$date} ({$time}) is already booked."];
            }
        } catch (Throwable $e) {}
    }

    // Pre-check booking table if status column exists
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

        $cols = ['id', 'bus', 'name', 'contact', 'city1', 'city2', '`date`', '`time`', 'seat', 'price'];
        $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?'];
        $types = 'isssssssid';
        $vals = [$cust_id, $bus, $name, $contact, $from, $to, $date, $time, $seat, $price];

        if ($has_pnr) {
            $cols[] = 'pnr';
            $placeholders[] = '?';
            $types .= 's';
            $vals[] = $pnr;
        }
        if ($has_status) {
            $cols[] = 'status';
            $placeholders[] = '?';
            $types .= 's';
            $vals[] = $booking_status;
        }
        if ($has_hold) {
            $cols[] = 'hold_expires_at';
            if ($booking_status === 'Pending') {
                $placeholders[] = 'DATE_ADD(NOW(), INTERVAL 10 MINUTE)';
            } else {
                $placeholders[] = 'NULL';
            }
        }
        if ($has_bus_id && $bus_id !== null) {
            $cols[] = 'bus_id';
            $placeholders[] = '?';
            $types .= 'i';
            $vals[] = $bus_id;
        }
        if ($has_route_id && $route_id !== null) {
            $cols[] = 'route_id';
            $placeholders[] = '?';
            $types .= 'i';
            $vals[] = $route_id;
        }

        $sql = "INSERT INTO booking (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        db_exec($link, $sql, $types, $vals);
        $new_booking_id = (int)mysqli_insert_id($link);

        // Insert atomic seat lock (P-03)
        if ($bus_id !== null && $new_booking_id > 0) {
            try {
                if ($booking_status === 'Pending') {
                    db_exec($link,
                        "INSERT INTO seat_lock (bus_id, travel_date, seat_no, booking_id, held_until) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))",
                        'isis', [$bus_id, $date, $seat, $new_booking_id]
                    );
                } else {
                    db_exec($link,
                        "INSERT INTO seat_lock (bus_id, travel_date, seat_no, booking_id, held_until) VALUES (?, ?, ?, ?, NULL)",
                        'isis', [$bus_id, $date, $seat, $new_booking_id]
                    );
                }
            } catch (mysqli_sql_exception $se) {
                if ((int)$se->getCode() === 1062) {
                    throw $se; // Triggers outer catch to rollback and report clean race condition error
                }
                error_log("[busres] seat_lock insert skipped/error: " . $se->getMessage());
            }
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

/**
 * Returns badge CSS class and readable label for booking statuses (U-09).
 *
 * @param string $status Database status value
 * @param bool $is_past Whether the departure timestamp is in the past
 * @return array ['class' => string, 'label' => string]
 */
function get_booking_status_badge(string $status, bool $is_past = false): array {
    $raw = trim($status);
    if ($raw === 'Cancelled') {
        return ['class' => 'badge-danger', 'label' => 'Cancelled'];
    }
    if ($raw === 'Expired') {
        return ['class' => 'badge-secondary', 'label' => 'Expired'];
    }
    if ($is_past) {
        return ['class' => 'badge-secondary', 'label' => 'Completed'];
    }
    if ($raw === 'Pending') {
        return ['class' => 'badge-warning text-dark', 'label' => 'Pending Hold'];
    }
    return ['class' => 'badge-success', 'label' => 'Confirmed'];
}

/**
 * Validates and normalizes internal redirect return paths (Phase 1.1).
 * Prevents open-redirect attacks via backslashes (/\evil.com), protocol-relative
 * URLs (//evil.com), encoded slashes, control chars, external schemes/hosts.
 *
 * @param string|null $next
 * @return string|null Safe relative internal URL or null if invalid
 */
function safe_next_url(?string $next): ?string {
    if ($next === null) {
        return null;
    }
    $raw = trim($next);
    if ($raw === '') {
        return null;
    }

    // Reject control characters, newlines, carriage returns, tabs, null bytes
    if (preg_match('/[\x00-\x1F\x7F]/', $raw)) {
        return null;
    }

    // Reject backslashes in raw or url-decoded form (prevents /\evil.com, /%5Cevil.com)
    if (str_contains($raw, '\\') || str_contains(urldecode($raw), '\\')) {
        return null;
    }

    // Must start with a single slash and not double slash
    if (!str_starts_with($raw, '/') || str_starts_with($raw, '//')) {
        return null;
    }

    // Reject scheme-relative or protocol specifications
    if (str_contains($raw, '://') || str_contains(urldecode($raw), '://')) {
        return null;
    }

    $parsed = parse_url($raw);
    if ($parsed === false) {
        return null;
    }

    // Disallow scheme, host, user, pass in return path
    if (isset($parsed['scheme']) || isset($parsed['host']) || isset($parsed['user']) || isset($parsed['pass'])) {
        return null;
    }

    $path = $parsed['path'] ?? '';
    if (!str_starts_with($path, '/')) {
        return null;
    }

    return $raw;
}

/**
 * Normalizes and validates personal contact fields across registration, profile, and booking (Phase 1.4).
 *
 * @param string $name Full name
 * @param string $phone Phone number string
 * @param string $address Optional address
 * @return array ['ok' => bool, 'name' => string, 'phone' => string, 'address' => string, 'errors' => string[]]
 */
function validate_person_fields(string $name, string $phone, string $address = ''): array {
    $clean_name = trim($name);
    $clean_name = preg_replace('/[\x00-\x1F\x7F]/', '', $clean_name);

    // Normalize phone: strip all non-digits
    $clean_phone = preg_replace('/\D+/', '', $phone);

    $clean_address = trim($address);
    $clean_address = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean_address);

    $errors = [];
    if (mb_strlen($clean_name) < 2 || mb_strlen($clean_name) > 100) {
        $errors[] = 'Full name must be between 2 and 100 characters.';
    }
    if (strlen($clean_phone) < 10 || strlen($clean_phone) > 15) {
        $errors[] = 'Please provide a valid contact phone number (10 to 15 digits).';
    }
    if (mb_strlen($clean_address) > 255) {
        $errors[] = 'Address cannot exceed 255 characters.';
    }

    return [
        'ok'      => empty($errors),
        'name'    => $clean_name,
        'phone'   => $clean_phone,
        'address' => $clean_address,
        'errors'  => $errors
    ];
}

/**
 * Sends application email notification (Phases 3.1, 4.2).
 * Gracefully handles environments without configured SMTP.
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $html_body HTML message body
 * @return bool True if sent or queued, false on failure
 */
function send_app_mail(string $to, string $subject, string $html_body): bool {
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Header injection prevention
    $subject = str_replace(["\r", "\n"], '', trim($subject));

    $mail_enabled = (getenv('MAIL_ENABLED') === '1' || strtolower((string)getenv('MAIL_ENABLED')) === 'true');
    $from_email = getenv('MAIL_FROM') ?: 'no-reply@busreservation.local';
    $from_name = getenv('MAIL_FROM_NAME') ?: 'Bus Reservation System';

    if (!$mail_enabled) {
        error_log(sprintf('[busres mail notice] Mail to <%s> suppressed (MAIL_ENABLED is false). Subject: %s', $to, $subject));
        return true;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . sprintf('"%s" <%s>', addcslashes($from_name, '"'), $from_email),
        'Reply-To: ' . $from_email,
        'X-Mailer: BusReservation-PHP/' . phpversion()
    ];

    return @mail($to, $subject, $html_body, implode("\r\n", $headers));
}

/**
 * Resolves pagination parameters for listing pages (P-10).
 *
 * @param int $total_records Total number of records in table/dataset
 * @param int $per_page Records to display per page (default 25)
 * @param string $page_param Name of the GET query parameter (default 'page')
 * @return array ['page' => int, 'per_page' => int, 'total_pages' => int, 'offset' => int, 'total_records' => int]
 */
function paginate(int $total_records, int $per_page = 25, string $page_param = 'page'): array {
    $per_page = max(1, $per_page);
    $total_pages = max(1, (int)ceil($total_records / $per_page));
    $current_page = max(1, min($total_pages, (int)($_GET[$page_param] ?? 1)));
    $offset = ($current_page - 1) * $per_page;

    return [
        'page'          => $current_page,
        'per_page'      => $per_page,
        'total_pages'   => $total_pages,
        'offset'        => $offset,
        'total_records' => $total_records
    ];
}

/**
 * Renders Bootstrap 4 pagination links bar (P-10).
 *
 * @param array $pagination Array returned from paginate()
 * @param array $keep_params Extra query parameters to preserve in links
 * @return string HTML pagination markup
 */
function render_pagination(array $pagination, array $keep_params = []): string {
    if (($pagination['total_pages'] ?? 1) <= 1) {
        return '';
    }

    $page = (int)$pagination['page'];
    $total_pages = (int)$pagination['total_pages'];

    $build_url = function(int $p) use ($keep_params): string {
        $params = array_merge($keep_params, ['page' => $p]);
        return '?' . http_build_query($params);
    };

    $html = '<nav aria-label="Page navigation" class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-0">';

    // Previous button
    if ($page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e($build_url($page - 1)) . '">&laquo; Prev</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&laquo; Prev</span></li>';
    }

    // Numbered links
    $start = max(1, $page - 2);
    $end = min($total_pages, $page + 2);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e($build_url(1)) . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i === $page) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . e($build_url($i)) . '">' . $i . '</a></li>';
        }
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . e($build_url($total_pages)) . '">' . $total_pages . '</a></li>';
    }

    // Next button
    if ($page < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e($build_url($page + 1)) . '">Next &raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next &raquo;</span></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Records an entry into the audit trail (P-09).
 * Captures admin actor, IP, timestamp, action type, entity, and state changes.
 * Fails silently so audit logging errors never disrupt primary operations.
 *
 * @param mysqli $link Database connection
 * @param string $action Action performed (e.g. 'CREATE', 'UPDATE', 'DELETE', 'CANCEL')
 * @param string $entity_type Entity affected (e.g. 'bus', 'route', 'booking', 'customer', 'admin')
 * @param int|null $entity_id ID of the affected record
 * @param mixed $old_value Previous state (string, array, or null)
 * @param mixed $new_value New state (string, array, or null)
 */
function audit(mysqli $link, string $action, string $entity_type, ?int $entity_id = null, $old_value = null, $new_value = null): void {
    try {
        $allowed_actions = [
            'CREATE', 'UPDATE', 'DELETE', 'CANCEL', 'LOGIN', 'LOGIN_FAILED',
            'ROLE_CHANGE', 'EXPORT', 'RESTORE', 'DIAGNOSTICS_RUN', 'RUN_MIGRATIONS'
        ];
        $act = strtoupper(trim($action));
        if (!in_array($act, $allowed_actions, true)) {
            $act = 'OTHER';
        }

        $ent = strtolower(trim($entity_type));
        $admin_id = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
        if ($admin_id === null && isset($_SESSION['id']) && in_array(($_SESSION['role'] ?? ''), ['admin', 'super_admin', 'operator', 'viewer'], true)) {
            $admin_id = (int)$_SESSION['id'];
        }
        $ip = client_ip();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $old_json = ($old_value !== null) ? json_encode($old_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $new_json = ($new_value !== null) ? json_encode($new_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        db_exec($link,
            "INSERT INTO audit_log (admin_id, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            'ississss',
            [$admin_id, $act, $ent, $entity_id, $old_json, $new_json, $ip, $ua]
        );
    } catch (Throwable $e) {
        error_log("[busres audit error] Failed to record audit log: " . $e->getMessage());
    }
}



