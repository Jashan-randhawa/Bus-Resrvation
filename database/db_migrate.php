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

/**
 * Executes all pending database schema migrations idempotently.
 * Returns structured result ['ok' => bool, 'log' => string[]].
 */
function run_migrations(mysqli $link): array {
    $log = [];
    $all_ok = true;

    $log[] = "=== Bus Reservation Database Migration Runner ===";
    $log[] = "Database: " . (defined('DB_NAME') ? DB_NAME : 'unknown') . "@" . (defined('DB_HOST') ? DB_HOST : 'unknown');

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

        if (!has_index($link, 'booking', 'uq_booking_seat')) {
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
            $m5_ok = try_sql($link, "ALTER TABLE `booking` ADD UNIQUE KEY `uq_booking_active_seat` (`bus`, `date`, `time`, `active_seat`)", $log) && $m5_ok;
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
