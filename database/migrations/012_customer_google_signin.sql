-- database/migrations/012_customer_google_signin.sql
-- Migration 012: Customer "Continue with Google" OAuth 2.0 / OpenID Connect.
--
-- Adds `google_id` to `costumer` table with a unique constraint so customers can
-- sign in or register via Google. Explicitly restricted to customers (not admin).

ALTER TABLE `costumer` ADD COLUMN `google_id` VARCHAR(255) NULL AFTER `pwd`;
ALTER TABLE `costumer` ADD UNIQUE KEY `uq_customer_google_id` (`google_id`);
