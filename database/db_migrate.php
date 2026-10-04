<?php
// database/db_migrate.php -- Idempotent Database Schema & Migration Runner (O7)
// Usage: php database/db_migrate.php [--force]

if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    session_start();
}
if (PHP_SAPI !== 'cli' && !isset($_GET['migrate_key']) && empty($_SESSION['admin'])) {
    http_response_code(403);
    echo "CLI or authorized web invocation only.\n";
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_con.php';

echo "=== Bus Reservation Database Migration Runner ===\n";
echo "Database: " . DB_NAME . "@" . DB_HOST . "\n\n";

// 1. Create migrations tracking table if not exists
mysqli_query($link, "
    CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(255) NOT NULL,
        `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

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

// 2. Ensure base tables exist (idempotent init)
echo "[*] Verifying base schema tables...\n";

// Table: admin
mysqli_query($link, "
    CREATE TABLE IF NOT EXISTS `admin` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `Email_id` VARCHAR(100) NOT NULL,
        `Password` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(20) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Table: costumer
mysqli_query($link, "
    CREATE TABLE IF NOT EXISTS `costumer` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL,
        `pwd` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(20) NOT NULL,
        `address` TEXT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Table: buses
mysqli_query($link, "
    CREATE TABLE IF NOT EXISTS `buses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `bus_number` VARCHAR(50) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Table: route
mysqli_query($link, "
    CREATE TABLE IF NOT EXISTS `route` (
        `sno` INT AUTO_INCREMENT PRIMARY KEY,
        `city1` VARCHAR(100) NOT NULL,
        `city2` VARCHAR(100) NOT NULL,
        `busno` VARCHAR(50) NOT NULL,
        `time` TIME NOT NULL,
        `price` DECIMAL(10,2) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Table: booking
mysqli_query($link, "
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
");

// Table: login_attempts
mysqli_query($link, "
    CREATE TABLE IF NOT EXISTS `login_attempts` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `k` CHAR(40) NOT NULL,
        `ts` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_attempts` (`k`, `ts`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Table: query
mysqli_query($link, "
    CREATE TABLE IF NOT EXISTS `query` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_name` VARCHAR(100) NOT NULL,
        `user_email` VARCHAR(100) NOT NULL,
        `user_subject` VARCHAR(200) DEFAULT NULL,
        `user_qry` TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo "  -> Base tables verified.\n";

// Helper for column existence check
if (!function_exists('has_column')) {
    function has_column(mysqli $link, string $table, string $column): bool {
        $res = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

// Helper for index existence check
if (!function_exists('has_index')) {
    function has_index(mysqli $link, string $table, string $index_name): bool {
        $res = mysqli_query($link, "SHOW INDEX FROM `$table` WHERE Key_name = '$index_name'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

// 3. Run Step Migrations
$m1 = '001_hardening_and_schema_updates';
if (!migration_applied($link, $m1)) {
    echo "[*] Running migration: {$m1}...\n";

    // Column widenings for password hashes
    mysqli_query($link, "ALTER TABLE `admin` MODIFY `Password` VARCHAR(255) NOT NULL");
    mysqli_query($link, "ALTER TABLE `costumer` MODIFY `pwd` VARCHAR(255) NOT NULL");

    // Add unique index on admin email if not present
    if (!has_index($link, 'admin', 'uq_admin_email')) {
        mysqli_query($link, "DELETE a1 FROM admin a1 JOIN admin a2 ON a1.Email_id = a2.Email_id AND a1.id > a2.id");
        @mysqli_query($link, "ALTER TABLE `admin` ADD UNIQUE KEY `uq_admin_email` (`Email_id`)");
    }

    // Add unique index on customer email if not present
    if (!has_index($link, 'costumer', 'uq_customer_email')) {
        mysqli_query($link, "DELETE c1 FROM costumer c1 JOIN costumer c2 ON c1.email = c2.email AND c1.id > c2.id");
        @mysqli_query($link, "ALTER TABLE `costumer` ADD UNIQUE KEY `uq_customer_email` (`email`)");
    }

    // Add unique index on bus_number if not present
    if (!has_index($link, 'buses', 'uq_bus_number')) {
        mysqli_query($link, "DELETE b1 FROM buses b1 JOIN buses b2 ON b1.bus_number = b2.bus_number AND b1.id > b2.id");
        @mysqli_query($link, "ALTER TABLE `buses` ADD UNIQUE KEY `uq_bus_number` (`bus_number`)");
    }

    // Add PNR column to booking if missing
    if (!has_column($link, 'booking', 'pnr')) {
        mysqli_query($link, "ALTER TABLE `booking` ADD COLUMN `pnr` CHAR(10) NULL");
        mysqli_query($link, "UPDATE `booking` SET `pnr` = UPPER(SUBSTRING(MD5(CONCAT(sno, RAND(), NOW(6))), 1, 10)) WHERE `pnr` IS NULL");
        mysqli_query($link, "ALTER TABLE `booking` MODIFY `pnr` CHAR(10) NOT NULL");
    }
    if (!has_index($link, 'booking', 'uq_booking_pnr')) {
        @mysqli_query($link, "ALTER TABLE `booking` ADD UNIQUE KEY `uq_booking_pnr` (`pnr`)");
    }

    // Add Status column to booking if missing
    if (!has_column($link, 'booking', 'status')) {
        mysqli_query($link, "ALTER TABLE `booking` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'Confirmed'");
    }

    // Add unique seat constraint
    if (!has_index($link, 'booking', 'uq_booking_seat')) {
        @mysqli_query($link, "ALTER TABLE `booking` ADD UNIQUE KEY `uq_booking_seat` (`bus`, `date`, `time`, `seat`)");
    }

    // Add lookup indexes
    if (!has_index($link, 'booking', 'idx_booking_customer')) {
        @mysqli_query($link, "ALTER TABLE `booking` ADD KEY `idx_booking_customer` (`id`)");
    }
    if (!has_index($link, 'route', 'idx_route_cities')) {
        @mysqli_query($link, "ALTER TABLE `route` ADD KEY `idx_route_cities` (`city1`, `city2`)");
    }
    if (!has_index($link, 'route', 'idx_route_bus')) {
        @mysqli_query($link, "ALTER TABLE `route` ADD KEY `idx_route_bus` (`busno`)");
    }

    record_migration($link, $m1);
    echo "  -> Completed {$m1}.\n";
} else {
    echo "[i] Migration {$m1} already applied.\n";
}

// 4. Password hashing verification (002_hash_passwords)
$m2 = '002_rehash_legacy_passwords';
if (!migration_applied($link, $m2)) {
    echo "[*] Running migration: {$m2}...\n";
    $rehashed_admins = 0;
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
    }

    $rehashed_users = 0;
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
    }

    echo "  -> Rehashed {$rehashed_admins} admin passwords and {$rehashed_users} customer passwords.\n";
    record_migration($link, $m2);
    echo "  -> Completed {$m2}.\n";
} else {
    echo "[i] Migration {$m2} already applied.\n";
}

// 5. Fleet capacity modeling (O8: 003_bus_capacity)
$m3 = '003_bus_capacity';
if (!migration_applied($link, $m3)) {
    echo "[*] Running migration: {$m3}...\n";
    if (!has_column($link, 'buses', 'capacity')) {
        mysqli_query($link, "ALTER TABLE `buses` ADD COLUMN `capacity` INT NOT NULL DEFAULT 36");
        echo "  -> Added capacity column to buses table (default 36 seats).\n";
    }
    record_migration($link, $m3);
    echo "  -> Completed {$m3}.\n";
} else {
    echo "[i] Migration {$m3} already applied.\n";
}

// 6. Seat hold expiration & payment states (O13: 004_seat_hold_and_payment_states)
$m4 = '004_seat_hold_and_payment_states';
if (!migration_applied($link, $m4)) {
    echo "[*] Running migration: {$m4}...\n";
    if (!has_column($link, 'booking', 'created_at')) {
        mysqli_query($link, "ALTER TABLE `booking` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
    if (!has_column($link, 'booking', 'hold_expires_at')) {
        mysqli_query($link, "ALTER TABLE `booking` ADD COLUMN `hold_expires_at` TIMESTAMP NULL DEFAULT NULL");
    }
    if (!has_index($link, 'booking', 'idx_booking_hold')) {
        @mysqli_query($link, "ALTER TABLE `booking` ADD KEY `idx_booking_hold` (`status`, `hold_expires_at`)");
    }
    record_migration($link, $m4);
    echo "  -> Completed {$m4}.\n";
} else {
    echo "[i] Migration {$m4} already applied.\n";
}

echo "\n[✓] All database migrations are up to date!\n";
