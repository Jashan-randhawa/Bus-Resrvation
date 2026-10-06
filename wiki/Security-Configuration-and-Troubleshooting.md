# 🛡️ Security, Configuration & Troubleshooting

This document explains security hardening measures, multi-role RBAC, concurrency guarantees, regression test verification, and diagnostic troubleshooting runbooks.

---

## 1. Security Architecture & Hardening

### 1.1 Zero Hardcoded Secrets (P1)
All production credentials are read dynamically via environment variables (`includes/db_con.php`):
- `DB_HOST`: Hostname (default: `localhost`)
- `DB_USER`: Username (default: `root`)
- `DB_PASS`: Password (default: `""`)
- `DB_NAME`: Database name (default: `majorproject`)
- `DB_PORT`: Port (default: `3306`, or `4000` on TiDB Cloud)
- `DB_SSL`: Enforce TLS encryption (default: `false`, set `true` in production)

### 1.2 Multi-Role RBAC & Session Isolation (P2, P6)
- **Role Enforcement:** Administrative privileges are scoped into `super_admin`, `operator`, and `viewer`.
- **Session Keys:** `admin_id` is segregated from customer session keys (`uid`), preventing token impersonation across contexts.
- **Session Expiration:** Idle sessions expire after 1,800 seconds (30 minutes) and require re-authentication.
- **Diagnostic Protection:** `admin/diagnostics.php` is restricted to `super_admin` only, strips sensitive credentials from output, and sends `Cache-Control: no-store, private`.

### 1.3 Atomic Seat Allocation & Concurrency Guarantees (P3)
- Real-time seat reservations utilize a dedicated `seat_lock` table with `PRIMARY KEY (bus_id, travel_date, seat_no)`.
- Inserting into `booking` and `seat_lock` occurs within a single ACID transaction (`mysqli_begin_transaction`).
- Double-booking collisions throw `errno 1062` which cleanly rolls back the transaction and informs the user immediately without leaking raw SQL errors.

### 1.4 Cryptographic Passwords & Rehashing (O2)
- Passwords use bcrypt (`PASSWORD_DEFAULT`).
- Stored bcrypt hashes are protected: submittal of matching bcrypt strings does not overwrite existing password hashes (`password_get_info()['algo'] === null`).
- Admin accounts require a minimum password length of 12 characters.

### 1.5 Pre-Auth Brute-Force Rate Limiting (O3, O9)
- Login attempts are throttled before password verification:
  - Account key (`login:user:email` / `login:admin:email`): max 5 failures per 15 minutes.
  - IP key (`login:ip:ip`): max 20 failures per 15 minutes.
- Throttled requests receive an HTTP 429 Too Many Requests response.
- Expired throttle records older than 24 hours are automatically pruned.

### 1.6 IP Spoofing Prevention (O1)
- `client_ip()` verifies `REMOTE_ADDR` as the trusted foundation.
- If behind a load balancer or reverse proxy, `TRUSTED_PROXY_HOPS` extracts the rightmost trusted hop rather than blindly accepting spoofed leftmost client headers.

### 1.7 PNR Enumeration & Timing Attack Defense (O1)
- Replaced sequential integer IDs with random 10-hex uppercase tokens (`random_bytes(5)`).
- Public PNR status checks require the last 4 digits of the phone number.
- Throttled per IP (10 / 10 min) and per target PNR token (5 / 15 min).
- Constant-time and uniform error messages prevent oracle enumeration.

### 1.8 Comprehensive Audit Trail (P9)
- All administrative mutations (bus create/delete, route schedule create/delete, booking create/cancel, customer modifications) write immutable log entries to `audit_log`.
- Super administrators can inspect actor, timestamp, IP, action, and JSON state diffs in `admin/audit-log.php`.

---

## 2. Automated Regression Test Suite (`tests/run_tests.php`) (O14)

Run the test suite from the CLI to ensure all security and concurrency constraints hold:

```bash
php tests/run_tests.php
```

### Test Coverage Matrix:
1. **IP Extraction & Anti-Spoofing:** Tests rightmost hop parsing and fallback on corrupted headers.
2. **Throttling & Clearing:** Tests block triggers at threshold and recovery after window expiration.
3. **Password Hash Verification:** Verifies bcrypt detection and plaintext isolation.
4. **Fleet Capacity Modeling:** Validates custom bus capacity (10–60) and fallback behavior.
5. **Double-Booking Race Condition Defense:** Simulates concurrent reservations for the same seat on the same trip to ensure the duplicate key constraint `uq_booking_seat` and `seat_lock` block double allocation.
6. **Soft Cancellation & Seat Recovery:** Verifies that cancelling a booking preserves the audit row while freeing the seat for subsequent passengers.
7. **Seat Hold Expiration & Liberation:** Verifies that `Pending` holds block other users, and auto-expire after timeout, liberating the seat.

---

## 3. Operational Troubleshooting Runbook

### Issue 1: "Connections using insecure transport are prohibited"
- **Cause:** TiDB Cloud mandates TLS transport.
- **Fix:** Set `DB_SSL=true` in your Render environment variables.

### Issue 2: "Seat was just reserved by another customer"
- **Cause:** Two passengers attempted to purchase the same seat simultaneously.
- **Fix:** Expected behavior. The database unique index `seat_lock` safely prevented double-booking. The second customer is prompted to pick another seat.

### Issue 3: "Travel date cannot be in the past / This bus has already departed for today"
- **Cause:** Customer selected a past date or a departure time earlier than the current server clock.
- **Fix:** Verify application timezone in `includes/config.php` (`APP_TZ`, defaults to `Asia/Kolkata`).

### Issue 4: "Cannot delete bus / route: active bookings exist"
- **Cause:** Administrator attempted to delete a vehicle or schedule with upcoming customer bookings.
- **Fix:** Reassign the passengers or wait until the scheduled travel date has passed.
