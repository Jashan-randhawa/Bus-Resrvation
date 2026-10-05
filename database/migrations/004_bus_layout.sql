-- database/migrations/004_bus_layout.sql
-- Adds layout pattern column ('2+2', '2+1', '1+2', '1+1') to buses table (U-15)

ALTER TABLE `buses` ADD COLUMN IF NOT EXISTS `layout` VARCHAR(8) NOT NULL DEFAULT '2+2';
