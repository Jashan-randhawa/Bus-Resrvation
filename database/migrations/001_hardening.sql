-- database/migrations/001_hardening.sql
-- Select your database first. One change per ALTER (TiDB-friendly). Read the notes in C-03, H-04, M-03.

-- 1. Wider credential columns (hashes need 60+ characters)
ALTER TABLE admin MODIFY Password VARCHAR(255) NOT NULL;
ALTER TABLE costumer MODIFY pwd VARCHAR(255) NOT NULL;

-- 2. De-duplicate, then enforce unique identities
DELETE a1 FROM admin a1 JOIN admin a2 ON a1.Email_id = a2.Email_id AND a1.id > a2.id;
DELETE c1 FROM costumer c1 JOIN costumer c2 ON c1.email = c2.email AND c1.id > c2.id;
ALTER TABLE admin ADD UNIQUE KEY uq_admin_email (Email_id);
ALTER TABLE costumer ADD UNIQUE KEY uq_customer_email (email);
ALTER TABLE buses ADD UNIQUE KEY uq_bus_number (bus_number);

-- 3. Public PNR token (replaces the guessable booking.sno)
ALTER TABLE booking ADD COLUMN pnr CHAR(10) NULL;
UPDATE booking SET pnr = UPPER(SUBSTRING(MD5(CONCAT(sno, RAND(), NOW(6))), 1, 10)) WHERE pnr IS NULL;
ALTER TABLE booking MODIFY pnr CHAR(10) NOT NULL;
ALTER TABLE booking ADD UNIQUE KEY uq_booking_pnr (pnr);

-- 4. A seat can only be sold once per bus, date and departure (run the duplicate check from H-04 first)
ALTER TABLE booking ADD UNIQUE KEY uq_booking_seat (bus, `date`, `time`, seat);

-- 5. Indexes for the lookups the app performs
ALTER TABLE booking ADD KEY idx_booking_customer (id);
ALTER TABLE route ADD KEY idx_route_cities (city1, city2);
ALTER TABLE route ADD KEY idx_route_bus (busno);

-- 6. Foreign keys (run the orphan queries from M-03 first;
-- skip the last one if booking.id is not a customer id)
ALTER TABLE route ADD CONSTRAINT fk_route_bus FOREIGN KEY (busno) REFERENCES buses (bus_number);
ALTER TABLE booking ADD CONSTRAINT fk_booking_bus FOREIGN KEY (bus) REFERENCES buses (bus_number);
ALTER TABLE booking ADD CONSTRAINT fk_booking_customer FOREIGN KEY (id) REFERENCES costumer (id);

-- 7. Rate-limit storage (H-05)
CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  k CHAR(40) NOT NULL,
  ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempts (k, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
