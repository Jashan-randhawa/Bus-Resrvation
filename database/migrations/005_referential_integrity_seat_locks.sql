-- database/migrations/005_referential_integrity_seat_locks.sql
-- Referential integrity (bus_id, route_id) and dedicated seat_lock table (P-03, P-04)

ALTER TABLE `route` ADD COLUMN IF NOT EXISTS `bus_id` INT NULL;
UPDATE `route` r JOIN `buses` b ON b.bus_number = r.busno SET r.bus_id = b.id WHERE r.bus_id IS NULL;

ALTER TABLE `booking` ADD COLUMN IF NOT EXISTS `bus_id` INT NULL;
ALTER TABLE `booking` ADD COLUMN IF NOT EXISTS `route_id` INT NULL;
UPDATE `booking` k JOIN `buses` b ON b.bus_number = k.bus SET k.bus_id = b.id WHERE k.bus_id IS NULL;

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

INSERT IGNORE INTO `seat_lock` (bus_id, travel_date, seat_no, booking_id, held_until)
SELECT k.bus_id, k.date, k.seat, k.sno, k.hold_expires_at
FROM `booking` k
WHERE k.bus_id IS NOT NULL AND (k.status IS NULL OR k.status IN ('Confirmed', 'Pending'));
