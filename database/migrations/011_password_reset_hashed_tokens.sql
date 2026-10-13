-- database/migrations/011_password_reset_hashed_tokens.sql
-- Migration 011: Email reset-link flow with hashed tokens.
--
-- The `token` column now stores the SHA-256 hash of the emailed token instead of the
-- token itself, so a database leak cannot be turned into working reset links.
-- Rows written by earlier versions held plain tokens and will simply stop matching;
-- they are removed here (customers just request a new link).

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(100) NOT NULL,
  `token` CHAR(64) NOT NULL COMMENT 'SHA-256 hash of the emailed token, never the token itself',
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_token` (`token`),
  INDEX `idx_pr_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `password_resets` ADD INDEX IF NOT EXISTS `idx_pr_email` (`email`);

DELETE FROM `password_resets`;
