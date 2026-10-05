-- ============================================================
-- Bus Reservation System — Database Schema (Hardened)
-- Run this on your selected database after deployment.
-- ============================================================

-- Admin table (wider password for bcrypt, unique email)
CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `Email_id` VARCHAR(100) NOT NULL,
  `Password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  UNIQUE KEY `uq_admin_email` (`Email_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customer table (wider password for bcrypt, unique email)
CREATE TABLE IF NOT EXISTS `costumer` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `pwd` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `address` TEXT DEFAULT NULL,
  UNIQUE KEY `uq_customer_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Buses table (unique bus number and configurable capacity)
CREATE TABLE IF NOT EXISTS `buses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bus_number` VARCHAR(50) NOT NULL,
  `capacity` INT NOT NULL DEFAULT 36,
  UNIQUE KEY `uq_bus_number` (`bus_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Route table
CREATE TABLE IF NOT EXISTS `route` (
  `sno` INT AUTO_INCREMENT PRIMARY KEY,
  `city1` VARCHAR(100) NOT NULL,
  `city2` VARCHAR(100) NOT NULL,
  `busno` VARCHAR(50) NOT NULL,
  `time` TIME NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  KEY `idx_route_cities` (`city1`, `city2`),
  KEY `idx_route_bus` (`busno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Booking table (unique PNR and unique seat booking constraint)
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
  `status` VARCHAR(20) NOT NULL DEFAULT 'Confirmed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `hold_expires_at` TIMESTAMP NULL DEFAULT NULL,
  `active_seat` INT GENERATED ALWAYS AS (IF(`status` IN ('Confirmed', 'Pending'), `seat`, NULL)) STORED,
  UNIQUE KEY `uq_booking_pnr` (`pnr`),
  UNIQUE KEY `uq_booking_active_seat` (`bus`, `date`, `time`, `active_seat`),
  KEY `idx_booking_customer` (`id`),
  KEY `idx_booking_hold` (`status`, `hold_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Query / Feedback table
CREATE TABLE IF NOT EXISTS `query` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_name` VARCHAR(100) NOT NULL,
  `user_email` VARCHAR(100) NOT NULL,
  `user_subject` VARCHAR(200) DEFAULT NULL,
  `user_qry` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate limiting storage (H-05)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `k` CHAR(40) NOT NULL,
  `ts` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_attempts` (`k`, `ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
