-- database/migrations/013_google_pwd_set.sql
-- Migration 013: Explicit credential state flag for customer Google accounts.
--
-- Adds `pwd_set` (TINYINT(1), default 1) to `costumer`. Accounts created or linked
-- through Google have pwd_set = 0 until a password is set via change-password or reset-password.

ALTER TABLE `costumer` ADD COLUMN `pwd_set` TINYINT(1) NOT NULL DEFAULT 1 AFTER `google_id`;

UPDATE `costumer` SET `pwd_set` = 0 WHERE `google_id` IS NOT NULL;
