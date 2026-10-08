<?php
// database/db_migrate.php -- Robust Database Schema & Migration Runner (O7 / D-05 / D-06)
// CLI usage: php database/db_migrate.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

// Prevent direct unauthenticated web execution (D-06)
if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'db_migrate.php') {
    http_response_code(403);
    echo "Access denied: CLI or authenticated admin panel execution only.\n";
    exit;
}

if (!function_exists('try_sql')) {
    function try_sql(mysqli $link, string $sql, array &$log): bool {
        try {
            mysqli_query($link, $sql);
            return true;
        } catch (mysqli_sql_exception $e) {
            $log[] = "FAILED: {$sql} -> " . $e->getMessage();
            return false;
        }
    }
}

if (!function_exists('migration_applied')) {
    function migration_applied(mysqli $link, string $name): bool {
        $row = db_one($link, "SELECT id FROM schema_migrations WHERE migration = ?", 's', [$name]);
        return !empty($row);
    }
}

if (!function_exists('record_migration')) {
    function record_migration(mysqli $link, string $name): void {
        db_exec($link, "INSERT INTO schema_migrations (migration) VALUES (?)", 's', [$name]);
    }
}

if (!function_exists('has_column')) {
    function has_column(mysqli $link, string $table, string $column): bool {
        try {
            $res = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
            return $res && mysqli_num_rows($res) > 0;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }
}

if (!function_exists('has_index')) {
    function has_index(mysqli $link, string $table, string $index_name): bool {
        try {
            $res = mysqli_query($link, "SHOW INDEX FROM `$table` WHERE Key_name = '$index_name'");
            return $res && mysqli_num_rows($res) > 0;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }
}

if (!function_exists('step_applied')) {
    function step_applied(mysqli $link, string $migration, string $step): bool {
        try {
            $row = db_one($link, "SELECT id FROM migration_steps WHERE migration = ? AND step = ?", 'ss', [$migration, $step]);
            return !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('run_step')) {
    function run_step(mysqli $link, string $migration, string $step, string $sql, array &$log): bool {
        if (step_applied($link, $migration, $step)) {
            return true;
        }
        $ok = try_sql($link, $sql, $log);
        if ($ok) {
            try {
                db_exec($link, "INSERT INTO migration_steps (migration, step) VALUES (?, ?)", 'ss', [$migration, $step]);
            } catch (Throwable $e) {
                // Ignore if already recorded
            }
        }
        return $ok;
    }
}

/**
 * Executes all pending database schema migrations idempotently (P-05).
 * Uses named advisory lock to prevent concurrent execution across containers.
 * Returns structured result ['ok' => bool, 'log' => string[]].
 */
function run_migrations(mysqli $link): array {
    $log = [];
    $all_ok = true;

    $log[] = "=== Bus Reservation Database Migration Runner ===";
    $log[] = "Database: " . (defined('DB_NAME') ? DB_NAME : 'unknown') . "@" . (defined('DB_HOST') ? DB_HOST : 'unknown');

    // Advisory Locking (P-05: prevent multi-instance race conditions)
    $lock_acquired = false;
    try {
        $lock_res = mysqli_query($link, "SELECT GET_LOCK('busres_migrate', 30) AS locked");
        if ($lock_res) {
            $row = mysqli_fetch_assoc($lock_res);
            $lock_acquired = ((int)($row['locked'] ?? 0) === 1);
            mysqli_free_result($lock_res);
        }
    } catch (Throwable $e) {
        $log[] = "[i] Advisory locking check bypassed: " . $e->getMessage();
    }

    if (!$lock_acquired) {
        $log[] = "[!] Could not acquire migration lock 'busres_migrate' within 30s. Another migration may be running.";
        return ['ok' => false, 'log' => $log];
    }

    try {
        // 1. Create migrations tracking table if not exists
        $sm_created = try_sql($link, "
            CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL,
                `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ", $log);

        if (!$sm_created) {
            $log[] = "[!] Failed to initialize schema_migrations tracking table.";
            return ['ok' => false, 'log' => $log];
        }

        // Create migration_steps tracking table for statement-level idempotency
        try_sql($link, "
            CREATE TABLE IF NOT EXISTS `migration_steps` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(100) NOT NULL,
                `step` VARCHAR(100) NOT NULL,
                `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_migration_step` (`migration`, `step`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ", $log);

    // 2. Ensure base tables exist (idempotent init)
    $log[] = "[*] Verifying base schema tables...";

    try_sql($link, "
        CREATE TABLE IF NOT EXISTS `admin` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `Email_id` VARCHAR(100) NOT NULL,
            `Password` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(20) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ", $log);

    try_sql($link, "
        CREATE TABLE IF NOT EXISTS `costumer` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `pwd` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `address` TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ", $log);

    try_sql($link, "
        CREATE TABLE IF NOT EXISTS `buses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `bus_number` VARCHAR(50) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ", $log);

    try_sql($link, "
        CREATE TABLE IF NOT EXISTS `route` (
            `sno` INT AUTO_INCREMENT PRIMARY KEY,
            `city1` VARCHAR(100) NOT NULL,
            `city2` VARCHAR(100) NOT NULL,
            `busno` VARCHAR(50) NOT NULL,
            `time` TIME NOT NULL,
            `price` DECIMAL(10,2) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ", $log);

    try_sql($link, "
        CREATE TABLE IF NOT EXISTS `booking` (
            `sno` INT AUTO_INCREMENT PRIMARY KEY,
            `id` INT NOT NULL DEFAULT 0,
            `bus` VARCHAR(50) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `contact` VARCHAR(20) NOT NULL,
            `city1` VARCHAR(100) NOT NULL,
            `city2` VARCHAR(100) NOT NULL,
            `date` DATE NOT NULL,
            `time` TIME NOT NULL,
            `seat` INT NOT NULL,
            `price` DECIMAL(10,2) NOT NULL,
            `pnr` CHAR(10) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'Confirmed'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ", $log);

    try_sql($link, "
        CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `k` CHAR(40) NOT NULL,
            `ts` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_attempts` (`k`, `ts`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ", $log);

    try_sql($link, "
        CREATE TABLE IF NOT EXISTS `query` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_name` VARCHAR(100) NOT NULL,
            `user_email` VARCHAR(100) NOT NULL,
            `user_subject` VARCHAR(200) DEFAULT NULL,
            `user_qry` TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ", $log);

    $log[] = "  -> Base tables verified.";

    // 3. Migration 001: 001_hardening_and_schema_updates
    $m1 = '001_hardening_and_schema_updates';
    if (!migration_applied($link, $m1)) {
        $log[] = "[*] Running migration: {$m1}...";
        $m1_ok = true;

        $m1_ok = try_sql($link, "ALTER TABLE `admin` MODIFY `Password` VARCHAR(255) NOT NULL", $log) && $m1_ok;
        $m1_ok = try_sql($link, "ALTER TABLE `costumer` MODIFY `pwd` VARCHAR(255) NOT NULL", $log) && $m1_ok;

        if (!has_index($link, 'admin', 'uq_admin_email')) {
            try_sql($link, "DELETE a1 FROM admin a1 JOIN admin a2 ON a1.Email_id = a2.Email_id AND a1.id > a2.id", $log);
            $m1_ok = try_sql($link, "ALTER TABLE `admin` ADD UNIQUE KEY `uq_admin_email` (`Email_id`)", $log) && $m1_ok;
        }

        if (!has_column($link, 'admin', 'role')) {
            $m1_ok = try_sql($link, "ALTER TABLE `admin` ADD COLUMN `role` ENUM('super_admin', 'operator', 'viewer') NOT NULL DEFAULT 'operator'", $log) && $m1_ok;
        }

        if (!has_index($link, 'costumer', 'uq_customer_email')) {
            try_sql($link, "DELETE c1 FROM costumer c1 JOIN costumer c2 ON c1.email = c2.email AND c1.id > c2.id", $log);
            $m1_ok = try_sql($link, "ALTER TABLE `costumer` ADD UNIQUE KEY `uq_customer_email` (`email`)", $log) && $m1_ok;
        }

        if (!has_index($link, 'buses', 'uq_bus_number')) {
            try_sql($link, "DELETE b1 FROM buses b1 JOIN buses b2 ON b1.bus_number = b2.bus_number AND b1.id > b2.id", $log);
            $m1_ok = try_sql($link, "ALTER TABLE `buses` ADD UNIQUE KEY `uq_bus_number` (`bus_number`)", $log) && $m1_ok;
        }

        if (!has_column($link, 'booking', 'pnr')) {
            $m1_ok = try_sql($link, "ALTER TABLE `booking` ADD COLUMN `pnr` CHAR(10) NULL", $log) && $m1_ok;
            try_sql($link, "UPDATE `booking` SET `pnr` = UPPER(SUBSTRING(MD5(CONCAT(sno, RAND(), NOW(6))), 1, 10)) WHERE `pnr` IS NULL", $log);
            $m1_ok = try_sql($link, "ALTER TABLE `booking` MODIFY `pnr` CHAR(10) NOT NULL", $log) && $m1_ok;
        }
        if (!has_index($link, 'booking', 'uq_booking_pnr')) {
            $m1_ok = try_sql($link, "ALTER TABLE `booking` ADD UNIQUE KEY `uq_booking_pnr` (`pnr`)", $log) && $m1_ok;
        }

        if (!has_column($link, 'booking', 'status')) {
            $m1_ok = try_sql($link, "ALTER TABLE `booking` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'Confirmed'", $log) && $m1_ok;
        }

        $m5_applied = migration_applied($link, '005_rebookable_active_seats');
        if (!$m5_applied && !has_index($link, 'booking', 'uq_booking_seat')) {
            $m1_ok = try_sql($link, "ALTER TABLE `booking` ADD UNIQUE KEY `uq_booking_seat` (`bus`, `date`, `time`, `seat`)", $log) && $m1_ok;
        }

        if (!has_index($link, 'booking', 'idx_booking_customer')) {
            $m1_ok = try_sql($link, "ALTER TABLE `booking` ADD KEY `idx_booking_customer` (`id`)", $log) && $m1_ok;
        }
        if (!has_index($link, 'route', 'idx_route_cities')) {
            $m1_ok = try_sql($link, "ALTER TABLE `route` ADD KEY `idx_route_cities` (`city1`, `city2`)", $log) && $m1_ok;
        }
        if (!has_index($link, 'route', 'idx_route_bus')) {
            $m1_ok = try_sql($link, "ALTER TABLE `route` ADD KEY `idx_route_bus` (`busno`)", $log) && $m1_ok;
        }

        if ($m1_ok) {
            record_migration($link, $m1);
            $log[] = "  -> Completed {$m1}.";
        } else {
            $log[] = "  [!] Migration {$m1} had errors; not marked as applied.";
            $all_ok = false;
        }
    } else {
        $log[] = "[i] Migration {$m1} already applied.";
    }

    // 4. Migration 002: 002_rehash_legacy_passwords
    $m2 = '002_rehash_legacy_passwords';
    if (!migration_applied($link, $m2)) {
        $log[] = "[*] Running migration: {$m2}...";
        $m2_ok = true;
        $rehashed_admins = 0;

        try {
            $res = mysqli_query($link, "SELECT id, Password FROM `admin`");
            if ($res) {
                $upd = mysqli_prepare($link, "UPDATE `admin` SET Password = ? WHERE id = ?");
                while ($row = mysqli_fetch_assoc($res)) {
                    if (password_get_info($row['Password'])['algo'] === null) {
                        $hash = password_hash($row['Password'], PASSWORD_DEFAULT);
                        $id = (int)$row['id'];
                        mysqli_stmt_bind_param($upd, 'si', $hash, $id);
                        mysqli_stmt_execute($upd);
                        $rehashed_admins++;
                    }
                }
                mysqli_stmt_close($upd);
            }
        } catch (Throwable $e) {
            $log[] = "FAILED rehashing admins: " . $e->getMessage();
            $m2_ok = false;
        }

        $rehashed_users = 0;
        try {
            $res = mysqli_query($link, "SELECT id, pwd FROM `costumer`");
            if ($res) {
                $upd = mysqli_prepare($link, "UPDATE `costumer` SET pwd = ? WHERE id = ?");
                while ($row = mysqli_fetch_assoc($res)) {
                    if (password_get_info($row['pwd'])['algo'] === null) {
                        $hash = password_hash($row['pwd'], PASSWORD_DEFAULT);
                        $id = (int)$row['id'];
                        mysqli_stmt_bind_param($upd, 'si', $hash, $id);
                        mysqli_stmt_execute($upd);
                        $rehashed_users++;
                    }
                }
                mysqli_stmt_close($upd);
            }
        } catch (Throwable $e) {
            $log[] = "FAILED rehashing customers: " . $e->getMessage();
            $m2_ok = false;
        }

        if ($m2_ok) {
            $log[] = "  -> Rehashed {$rehashed_admins} admin passwords and {$rehashed_users} customer passwords.";
            record_migration($link, $m2);
            $log[] = "  -> Completed {$m2}.";
        } else {
            $log[] = "  [!] Migration {$m2} had errors; not marked as applied.";
            $all_ok = false;
        }
    } else {
        $log[] = "[i] Migration {$m2} already applied.";
    }

    // 5. Migration 003: 003_bus_capacity
    $m3 = '003_bus_capacity';
    if (!migration_applied($link, $m3)) {
        $log[] = "[*] Running migration: {$m3}...";
        $m3_ok = true;
        if (!has_column($link, 'buses', 'capacity')) {
            $m3_ok = try_sql($link, "ALTER TABLE `buses` ADD COLUMN `capacity` INT NOT NULL DEFAULT 36", $log);
            if ($m3_ok) {
                $log[] = "  -> Added capacity column to buses table (default 36 seats).";
            }
        }
        if ($m3_ok) {
            record_migration($link, $m3);
            $log[] = "  -> Completed {$m3}.";
        } else {
            $log[] = "  [!] Migration {$m3} had errors; not marked as applied.";
            $all_ok = false;
        }
    } else {
        $log[] = "[i] Migration {$m3} already applied.";
    }

    // 6. Migration 004: 004_seat_hold_and_payment_states
    $m4 = '004_seat_hold_and_payment_states';
    if (!migration_applied($link, $m4)) {
        $log[] = "[*] Running migration: {$m4}...";
        $m4_ok = true;
        if (!has_column($link, 'booking', 'created_at')) {
            $m4_ok = try_sql($link, "ALTER TABLE `booking` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP", $log) && $m4_ok;
        }
        if (!has_column($link, 'booking', 'hold_expires_at')) {
            $m4_ok = try_sql($link, "ALTER TABLE `booking` ADD COLUMN `hold_expires_at` TIMESTAMP NULL DEFAULT NULL", $log) && $m4_ok;
        }
        if (!has_index($link, 'booking', 'idx_booking_hold')) {
            $m4_ok = try_sql($link, "ALTER TABLE `booking` ADD KEY `idx_booking_hold` (`status`, `hold_expires_at`)", $log) && $m4_ok;
        }
        if ($m4_ok) {
            record_migration($link, $m4);
            $log[] = "  -> Completed {$m4}.";
        } else {
            $log[] = "  [!] Migration {$m4} had errors; not marked as applied.";
            $all_ok = false;
        }
    } else {
        $log[] = "[i] Migration {$m4} already applied.";
    }

    // 7. Migration 005: 005_rebookable_active_seats (U-01)
    $m5 = '005_rebookable_active_seats';
    if (!migration_applied($link, $m5)) {
        $log[] = "[*] Running migration: {$m5}...";
        $m5_ok = true;
        if (!has_column($link, 'booking', 'active_seat')) {
            $m5_ok = try_sql($link, "ALTER TABLE `booking` ADD COLUMN `active_seat` INT GENERATED ALWAYS AS (IF(`status` IN ('Confirmed', 'Pending'), `seat`, NULL)) STORED", $log) && $m5_ok;
        }
        if (has_index($link, 'booking', 'uq_booking_seat')) {
            $m5_ok = try_sql($link, "ALTER TABLE `booking` DROP INDEX `uq_booking_seat`", $log) && $m5_ok;
        }
        if (!has_index($link, 'booking', 'uq_booking_active_seat')) {
            $dup_groups = db_all($link, "
                SELECT bus, `date`, `time`, seat, COUNT(*) AS cnt, GROUP_CONCAT(pnr SEPARATOR ', ') AS pnrs
                FROM `booking`
                WHERE (`status` IS NULL OR `status` IN ('Confirmed', 'Pending'))
                GROUP BY bus, `date`, `time`, seat
                HAVING cnt > 1
            ");
            if (!empty($dup_groups)) {
                $m5_ok = false;
                $log[] = "  [!] " . count($dup_groups) . " conflicting active seat group(s) found. uq_booking_active_seat was NOT created.";
                foreach ($dup_groups as $g) {
                    $log[] = "      bus={$g['bus']} date={$g['date']} time={$g['time']} seat={$g['seat']} rows={$g['cnt']} pnrs={$g['pnrs']}";
                }
                $log[] = "  [!] Resolve these bookings manually (cancel the duplicate), then re-run migrations.";
            } else {
                $m5_ok = try_sql($link, "ALTER TABLE `booking` ADD UNIQUE KEY `uq_booking_active_seat` (`bus`, `date`, `time`, `active_seat`)", $log) && $m5_ok;
            }
        }
        if ($m5_ok) {
            record_migration($link, $m5);
            $log[] = "  -> Completed {$m5}.";
        } else {
            $log[] = "  [!] Migration {$m5} had errors; not marked as applied.";
            $all_ok = false;
        }
    } else {
        $log[] = "[i] Migration {$m5} already applied.";
    }

    // 8. Migration 006: 006_bus_layout (U-15)
    $m6 = '006_bus_layout';
    if (!migration_applied($link, $m6)) {
        $log[] = "[*] Running migration: {$m6}...";
        $m6_ok = true;
        if (!has_column($link, 'buses', 'layout')) {
            $m6_ok = try_sql($link, "ALTER TABLE `buses` ADD COLUMN `layout` VARCHAR(8) NOT NULL DEFAULT '2+2'", $log);
            if ($m6_ok) {
                $log[] = "  -> Added layout column to buses table (default '2+2').";
            }
        }
        if ($m6_ok) {
            record_migration($link, $m6);
            $log[] = "  -> Completed {$m6}.";
        } else {
            $log[] = "  [!] Migration {$m6} had errors; not marked as applied.";
            $all_ok = false;
        }
    } else {
        $log[] = "[i] Migration {$m6} already applied.";
    }

    // 9. Migration 007: 007_referential_integrity_and_seat_locks (P-03, P-04)
    $m7 = '007_referential_integrity_and_seat_locks';
    if (!migration_applied($link, $m7)) {
        $log[] = "[*] Running migration: {$m7}...";
        $m7_ok = true;

        if (!has_index($link, 'buses', 'uq_bus_number')) {
            $m7_ok = try_sql($link, "ALTER TABLE `buses` ADD UNIQUE KEY `uq_bus_number` (`bus_number`)", $log) && $m7_ok;
        }

        if (!has_column($link, 'route', 'bus_id')) {
            $m7_ok = try_sql($link, "ALTER TABLE `route` ADD COLUMN `bus_id` INT NULL", $log) && $m7_ok;
            try_sql($link, "UPDATE `route` r JOIN `buses` b ON b.bus_number = r.busno SET r.bus_id = b.id", $log);
            if (!has_index($link, 'route', 'idx_route_bus_id')) {
                try_sql($link, "ALTER TABLE `route` ADD KEY `idx_route_bus_id` (`bus_id`)", $log);
            }
        }

        if (!has_column($link, 'booking', 'bus_id')) {
            $m7_ok = try_sql($link, "ALTER TABLE `booking` ADD COLUMN `bus_id` INT NULL", $log) && $m7_ok;
            try_sql($link, "UPDATE `booking` k JOIN `buses` b ON b.bus_number = k.bus SET k.bus_id = b.id", $log);
            if (!has_index($link, 'booking', 'idx_booking_bus_id')) {
                try_sql($link, "ALTER TABLE `booking` ADD KEY `idx_booking_bus_id` (`bus_id`)", $log);
            }
        }
        if (!has_column($link, 'booking', 'route_id')) {
            $m7_ok = try_sql($link, "ALTER TABLE `booking` ADD COLUMN `route_id` INT NULL", $log) && $m7_ok;
            try_sql($link, "UPDATE `booking` k JOIN `route` r ON r.city1 = k.city1 AND r.city2 = k.city2 AND r.busno = k.bus AND r.time = k.time SET k.route_id = r.sno", $log);
            if (!has_index($link, 'booking', 'idx_booking_route_id')) {
                try_sql($link, "ALTER TABLE `booking` ADD KEY `idx_booking_route_id` (`route_id`)", $log);
            }
        }

        $m7_ok = try_sql($link, "
            CREATE TABLE IF NOT EXISTS `seat_lock` (
              `bus_id` INT NOT NULL,
              `travel_date` DATE NOT NULL,
              `seat_no` SMALLINT NOT NULL,
              `booking_id` INT NOT NULL,
              `held_until` DATETIME NULL,
              PRIMARY KEY (`bus_id`, `travel_date`, `seat_no`),
              KEY `idx_seat_lock_booking` (`booking_id`),
              CONSTRAINT `fk_seat_lock_booking` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`sno`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ", $log) && $m7_ok;

        $lock_dups = db_all($link, "
            SELECT k.bus_id, k.`date`, k.seat, COUNT(*) AS cnt, GROUP_CONCAT(k.pnr SEPARATOR ', ') AS pnrs
            FROM `booking` k
            WHERE k.bus_id IS NOT NULL AND (k.`status` IS NULL OR k.`status` IN ('Confirmed', 'Pending'))
            GROUP BY k.bus_id, k.`date`, k.seat
            HAVING cnt > 1
        ");
        if (!empty($lock_dups)) {
            $m7_ok = false;
            $log[] = "  [!] " . count($lock_dups) . " conflicting seat lock group(s) found. seat_lock was NOT backfilled.";
            foreach ($lock_dups as $ld) {
                $log[] = "      bus_id={$ld['bus_id']} date={$ld['date']} seat={$ld['seat']} rows={$ld['cnt']} pnrs={$ld['pnrs']}";
            }
            $log[] = "  [!] Resolve conflicting bookings before applying seat_lock backfill.";
        } else {
            try_sql($link, "
                INSERT IGNORE INTO `seat_lock` (bus_id, travel_date, seat_no, booking_id, held_until)
                SELECT k.bus_id, k.date, k.seat, k.sno, k.hold_expires_at
                FROM `booking` k
                WHERE k.bus_id IS NOT NULL AND (k.status IS NULL OR k.status IN ('Confirmed', 'Pending'))
            ", $log);
        }

        if ($m7_ok) {
            record_migration($link, $m7);
            $log[] = "  -> Completed {$m7}.";
        } else {
            $log[] = "  [!] Migration {$m7} had errors; not marked as applied.";
            $all_ok = false;
        }
    } else {
        $log[] = "[i] Migration {$m7} already applied.";
    }

    // 10. Migration 008: 008_audit_logging (P-09)
    $m8 = '008_audit_logging';
    if (!migration_applied($link, $m8)) {
        $log[] = "[*] Running migration: {$m8}...";
        $m8_ok = try_sql($link, "
            CREATE TABLE IF NOT EXISTS `audit_log` (
              `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
              `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `admin_id` INT NULL,
              `action` VARCHAR(50) NOT NULL,
              `entity_type` VARCHAR(50) NOT NULL,
              `entity_id` INT NULL,
              `old_value` JSON NULL,
              `new_value` JSON NULL,
              `ip_address` VARCHAR(45) NOT NULL,
              `user_agent` VARCHAR(255) NULL,
              KEY `idx_audit_admin` (`admin_id`),
              KEY `idx_audit_action` (`action`),
              KEY `idx_audit_entity` (`entity_type`, `entity_id`),
              KEY `idx_audit_ts` (`timestamp`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ", $log);

        if ($m8_ok) {
            record_migration($link, $m8);
            $log[] = "  -> Completed {$m8}.";
        } else {
            $log[] = "  [!] Migration {$m8} had errors; not marked as applied.";
            $all_ok = false;
        }
    } else {
        $log[] = "[i] Migration {$m8} already applied.";
    }

    // 11. Migration 009: 009_admin_accounts_and_soft_delete (Items 4, 5, 6)
    $m9 = '009_admin_accounts_and_soft_delete';
    if (!migration_applied($link, $m9)) {
        $log[] = "[*] Running migration: {$m9}...";
        $m9_ok = true;

        if (!has_column($link, 'admin', 'is_active')) {
            $m9_ok = $m9_ok && try_sql($link, "ALTER TABLE `admin` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`", $log);
        }
        if (!has_column($link, 'admin', 'last_login_at')) {
            $m9_ok = $m9_ok && try_sql($link, "ALTER TABLE `admin` ADD COLUMN `last_login_at` DATETIME NULL AFTER `is_active`", $log);
        }
        if (!has_column($link, 'admin', 'password_changed_at')) {
            $m9_ok = $m9_ok && try_sql($link, "ALTER TABLE `admin` ADD COLUMN `password_changed_at` DATETIME NULL AFTER `last_login_at`", $log);
        }
        if (!has_column($link, 'admin', 'totp_secret')) {
            $m9_ok = $m9_ok && try_sql($link, "ALTER TABLE `admin` ADD COLUMN `totp_secret` VARCHAR(255) NULL AFTER `password_changed_at`", $log);
        } else {
            try_sql($link, "ALTER TABLE `admin` MODIFY COLUMN `totp_secret` VARCHAR(255) NULL", $log);
        }
        if (!has_column($link, 'admin', 'totp_enabled')) {
            $m9_ok = $m9_ok && try_sql($link, "ALTER TABLE `admin` ADD COLUMN `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `totp_secret`", $log);
        }
        if (!has_column($link, 'admin', 'last_totp_step')) {
            $m9_ok = $m9_ok && try_sql($link, "ALTER TABLE `admin` ADD COLUMN `last_totp_step` INT NULL AFTER `totp_enabled`", $log);
        }
        $m9_ok = $m9_ok && try_sql($link, "
            CREATE TABLE IF NOT EXISTS `admin_recovery_codes` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `admin_id` INT NOT NULL,
              `code_hash` VARCHAR(255) NOT NULL,
              `used_at` DATETIME NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY `idx_admin_recovery` (`admin_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ", $log);

        if (!has_column($link, 'buses', 'archived_at')) {
            $m9_ok = $m9_ok && try_sql($link, "ALTER TABLE `buses` ADD COLUMN `archived_at` DATETIME NULL", $log);
            $m9_ok = $m9_ok && try_sql($link, "ALTER TABLE `buses` ADD KEY `idx_buses_archived` (`archived_at`)", $log);
        }
        if (!has_column($link, 'route', 'archived_at')) {
            $m9_ok = $m9_ok && try_sql($link, "ALTER TABLE `route` ADD COLUMN `archived_at` DATETIME NULL", $log);
            $m9_ok = $m9_ok && try_sql($link, "ALTER TABLE `route` ADD KEY `idx_route_archived` (`archived_at`)", $log);
        }
        if (!has_column($link, 'costumer', 'archived_at')) {
            $m9_ok = $m9_ok && try_sql($link, "ALTER TABLE `costumer` ADD COLUMN `archived_at` DATETIME NULL", $log);
            $m9_ok = $m9_ok && try_sql($link, "ALTER TABLE `costumer` ADD KEY `idx_cust_archived` (`archived_at`)", $log);
        }

        if ($m9_ok) {
            record_migration($link, $m9);
            $log[] = "  -> Completed {$m9}.";
        } else {
            $log[] = "  [!] Migration {$m9} had errors; not marked as applied.";
            $all_ok = false;
        }
    } else {
        $log[] = "[i] Migration {$m9} already applied.";
    }

    // 12. Migration 010: 010_query_inbox_enhancements (Item 10)
    $m10 = '010_query_inbox_enhancements';
    if (!migration_applied($link, $m10)) {
        $log[] = "[*] Running migration: {$m10}...";
        $m10_ok = true;

        if (!has_column($link, 'query', 'status')) {
            $m10_ok = $m10_ok && try_sql($link, "ALTER TABLE `query` ADD COLUMN `status` ENUM('new', 'replied', 'closed') NOT NULL DEFAULT 'new'", $log);
            $m10_ok = $m10_ok && try_sql($link, "ALTER TABLE `query` ADD KEY `idx_query_status` (`status`)", $log);
        }
        if (!has_column($link, 'query', 'created_at')) {
            $m10_ok = $m10_ok && try_sql($link, "ALTER TABLE `query` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP", $log);
        }
        if (!has_column($link, 'query', 'replied_at')) {
            $m10_ok = $m10_ok && try_sql($link, "ALTER TABLE `query` ADD COLUMN `replied_at` DATETIME NULL", $log);
        }
        if (!has_column($link, 'query', 'reply_text')) {
            $m10_ok = $m10_ok && try_sql($link, "ALTER TABLE `query` ADD COLUMN `reply_text` TEXT NULL", $log);
        }

        if ($m10_ok) {
            record_migration($link, $m10);
            $log[] = "  -> Completed {$m10}.";
        } else {
            $log[] = "  [!] Migration {$m10} had errors; not marked as applied.";
            $all_ok = false;
        }
    } else {
        $log[] = "[i] Migration {$m10} already applied.";
    }
    } finally {
        if ($lock_acquired) {
            try {
                mysqli_query($link, "SELECT RELEASE_LOCK('busres_migrate')");
            } catch (Throwable $e) {
                // Ignore release errors
            }
        }
    }

    if ($all_ok) {
        $log[] = "\n[✓] All database migrations are up to date!";
    } else {
        $log[] = "\n[!] One or more database migrations failed. Review log above.";
    }

    return ['ok' => $all_ok, 'log' => $log];
}

// CLI entry point only (D-06)
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? $argv[0] ?? '') === __FILE__) {
    $result = run_migrations($link);
    echo implode("\n", $result['log']) . "\n";
    exit($result['ok'] ? 0 : 1);
}
