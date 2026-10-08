-- database/migrations/004_seat_hold_and_payment_states.sql
-- Migration 004: Seat hold expiration and booking timestamps

ALTER TABLE `booking` ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `booking` ADD COLUMN IF NOT EXISTS `hold_expires_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `booking` ADD KEY IF NOT EXISTS `idx_booking_hold` (`status`, `hold_expires_at`);
