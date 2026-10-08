-- database/migrations/003_bus_capacity.sql
-- Migration 003: Configurable bus capacity (Issue 3 / O8)

ALTER TABLE `buses` ADD COLUMN IF NOT EXISTS `capacity` INT NOT NULL DEFAULT 36;
