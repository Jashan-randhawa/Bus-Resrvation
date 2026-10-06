-- ============================================================
-- Bus Reservation System — Database Schema (Hardened)
-- Run this on your selected database after deployment.
-- ============================================================

-- Admin table (wider password for bcrypt, unique email, role, active status, TOTP)
CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `Email_id` VARCHAR(100) NOT NULL,
  `Password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `role` ENUM('super_admin', 'operator', 'viewer') NOT NULL DEFAULT 'operator',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `password_changed_at` DATETIME NULL,
  `totp_secret` VARCHAR(64) NULL,
  `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY `uq_admin_email` (`Email_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customer table (wider password for bcrypt, unique email, soft-delete)
CREATE TABLE IF NOT EXISTS `costumer` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `pwd` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `address` TEXT DEFAULT NULL,
  `archived_at` DATETIME NULL,
  UNIQUE KEY `uq_customer_email` (`email`),
  KEY `idx_cust_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Buses table (unique bus number, configurable capacity, layout, soft-delete)
CREATE TABLE IF NOT EXISTS `buses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bus_number` VARCHAR(50) NOT NULL,
  `capacity` INT NOT NULL DEFAULT 36,
  `layout` VARCHAR(8) NOT NULL DEFAULT '2+2',
  `archived_at` DATETIME NULL,
  UNIQUE KEY `uq_bus_number` (`bus_number`),
  KEY `idx_buses_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Route table (soft-delete)
CREATE TABLE IF NOT EXISTS `route` (
  `sno` INT AUTO_INCREMENT PRIMARY KEY,
  `city1` VARCHAR(100) NOT NULL,
  `city2` VARCHAR(100) NOT NULL,
  `busno` VARCHAR(50) NOT NULL,
  `bus_id` INT NULL,
  `time` TIME NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `archived_at` DATETIME NULL,
  KEY `idx_route_cities` (`city1`, `city2`),
  KEY `idx_route_bus` (`busno`),
  KEY `idx_route_bus_id` (`bus_id`),
  KEY `idx_route_archived` (`archived_at`),
  CONSTRAINT `fk_route_bus` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Booking table (unique PNR and unique seat booking constraint)
CREATE TABLE IF NOT EXISTS `booking` (
  `sno` INT AUTO_INCREMENT PRIMARY KEY,
  `id` INT NOT NULL DEFAULT 0,
  `bus` VARCHAR(50) NOT NULL,
  `bus_id` INT NULL,
  `route_id` INT NULL,
  `name` VARCHAR(100) NOT NULL,
  `contact` VARCHAR(20) NOT NULL,
  `city1` VARCHAR(100) NOT NULL,
  `city2` VARCHAR(100) NOT NULL,
  `date` DATE NOT NULL,
  `time` TIME NOT NULL,
  `seat` INT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `pnr` CHAR(10) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Confirmed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `hold_expires_at` TIMESTAMP NULL DEFAULT NULL,
  `active_seat` INT GENERATED ALWAYS AS (IF(`status` IN ('Confirmed', 'Pending'), `seat`, NULL)) STORED,
  UNIQUE KEY `uq_booking_pnr` (`pnr`),
  UNIQUE KEY `uq_booking_active_seat` (`bus`, `date`, `time`, `active_seat`),
  KEY `idx_booking_customer` (`id`),
  KEY `idx_booking_bus_id` (`bus_id`),
  KEY `idx_booking_route_id` (`route_id`),
  KEY `idx_booking_hold` (`status`, `hold_expires_at`),
  CONSTRAINT `fk_book_bus` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dedicated Seat Lock Table (P-03)
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

-- Query / Feedback table (Item 10)
CREATE TABLE IF NOT EXISTS `query` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_name` VARCHAR(100) NOT NULL,
  `user_email` VARCHAR(100) NOT NULL,
  `user_subject` VARCHAR(200) DEFAULT NULL,
  `user_qry` TEXT NOT NULL,
  `status` ENUM('new', 'replied', 'closed') NOT NULL DEFAULT 'new',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `replied_at` DATETIME NULL,
  `reply_text` TEXT NULL,
  KEY `idx_query_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate limiting storage (H-05)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `k` CHAR(40) NOT NULL,
  `ts` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_attempts` (`k`, `ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration steps idempotency tracking (P-05)
CREATE TABLE IF NOT EXISTS `migration_steps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `migration` VARCHAR(100) NOT NULL,
  `step` VARCHAR(100) NOT NULL,
  `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_migration_step` (`migration`, `step`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comprehensive Audit Trail Logging (P-09)
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


