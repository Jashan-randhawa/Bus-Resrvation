-- database/migrations/008_query_inbox_enhancements.sql
-- Migration 008: Customer Queries Status, Replies & Timestamps (Item 10)

ALTER TABLE `query`
  ADD COLUMN IF NOT EXISTS `status` ENUM('new', 'replied', 'closed') NOT NULL DEFAULT 'new',
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS `replied_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `reply_text` TEXT NULL,
  ADD KEY IF NOT EXISTS `idx_query_status` (`status`);
