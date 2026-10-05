-- database/migrations/003_active_seat_unique_key.sql
-- U-01: Rebookable cancelled and expired seats via active_seat stored generated column
-- Replaces total uniqueness on (bus, date, time, seat) with conditional uniqueness on active bookings only.

-- 1. Add stored generated column active_seat
-- Evaluates to seat for 'Confirmed' and 'Pending' reservations; NULL for 'Cancelled' and 'Expired'
ALTER TABLE `booking` ADD COLUMN `active_seat` INT GENERATED ALWAYS AS (IF(`status` IN ('Confirmed', 'Pending'), `seat`, NULL)) STORED;

-- 2. Drop legacy unique constraint that prevented rebooking cancelled or expired seats
ALTER TABLE `booking` DROP INDEX `uq_booking_seat`;

-- 3. Add new unique constraint scoped to active seats only (MySQL allows multiple NULLs in a unique index)
ALTER TABLE `booking` ADD UNIQUE KEY `uq_booking_active_seat` (`bus`, `date`, `time`, `active_seat`);
