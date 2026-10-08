-- database/migrations/009_admin_accounts_and_soft_delete.sql
-- Migration 009: Admin Account Controls and Soft Delete (Items 4, 5, 6)

-- 1. Admin account lifecycle fields (Item 5 & 6)
ALTER TABLE `admin`
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`,
  ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL AFTER `is_active`,
  ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME NULL AFTER `last_login_at`,
  ADD COLUMN IF NOT EXISTS `totp_secret` VARCHAR(255) NULL AFTER `password_changed_at`,
  ADD COLUMN IF NOT EXISTS `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `totp_secret`,
  ADD COLUMN IF NOT EXISTS `last_totp_step` INT NULL AFTER `totp_enabled`;

CREATE TABLE IF NOT EXISTS `admin_recovery_codes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_admin_recovery` (`admin_id`),
  CONSTRAINT `fk_admin_recovery_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Soft-delete columns for core entities (Item 4)
ALTER TABLE `buses`
  ADD COLUMN IF NOT EXISTS `archived_at` DATETIME NULL,
  ADD KEY IF NOT EXISTS `idx_buses_archived` (`archived_at`);

ALTER TABLE `route`
  ADD COLUMN IF NOT EXISTS `archived_at` DATETIME NULL,
  ADD KEY IF NOT EXISTS `idx_route_archived` (`archived_at`);

ALTER TABLE `costumer`
  ADD COLUMN IF NOT EXISTS `archived_at` DATETIME NULL,
  ADD KEY IF NOT EXISTS `idx_cust_archived` (`archived_at`);
