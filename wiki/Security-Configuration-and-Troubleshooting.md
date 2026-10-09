# 🛡️ Security, Configuration & Troubleshooting

This document explains security hardening measures, multi-role RBAC, concurrency guarantees, regression test verification, and diagnostic troubleshooting runbooks.

---

## 1. Security Architecture & Hardening

### 1.1 Zero Hardcoded Secrets
All production credentials and API keys are injected dynamically via environment variables (`includes/db_con.php`, `includes/config.php`):
- `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_SSL`
- `ADMIN_MFA_ENFORCE`
- `TICKET_HMAC_SECRET` (used for digital boarding pass signature generation and verification)
- `MAIL_*`

### 1.2 Multi-Role RBAC & Live Session Checks
- **Role Enforcement:** Administrative privileges are scoped into `super_admin`, `operator`, and `viewer`.
- **Live Database Validation:** Role changes, account deactivations (`is_active = 0`), or password changes invalidate existing sessions immediately.
- **Session Keys:** `admin_id` is segregated from customer session keys (`uid`), preventing token impersonation across contexts.
- **Session Expiration & Background Tab Synchronization:** Idle sessions expire after 1,800 seconds (30 minutes). Background browser tab throttling is mitigated by computing elapsed duration from `Date.now()` on activity ticks and listening to `visibilitychange` events, redirecting expired tabs immediately upon re-focus.
- **Diagnostic Protection:** `admin/diagnostics.php` is restricted to `super_admin` only, strips sensitive credentials from output, and sends `Cache-Control: no-store, private`.

### 1.3 Two-Factor Authentication (RFC 6238 TOTP)
- **High-Assurance MFA:** Compatible with Google Authenticator, Microsoft Authenticator, Authy, and 1Password.
- **Encrypted at Rest:** Base32 secrets are encrypted using AES-256-GCM before storage in `admin.totp_secret`.
- **Replay Protection:** Replay attacks are blocked by checking `last_totp_step` against incoming time-steps.
- **Client-Side QR Rendering:** Generated using HTML5 canvas via `qrcode.js`, complying with CSP and avoiding secret URL leaks to 3rd-party servers.
- **Backup Recovery Codes:** 10 single-use bcrypt-hashed recovery codes stored in `admin_recovery_codes`.

### 1.4 Atomic Seat Allocation & Concurrency Guarantees
- Real-time seat reservations utilize a dedicated `seat_lock` table with `PRIMARY KEY (bus_id, travel_date, seat_no)`.
- Inserting into `booking` and `seat_lock` occurs within a single ACID transaction (`mysqli_begin_transaction`).
- Double-booking collisions throw MySQL error 1062, cleanly rolling back the transaction without leaking raw SQL errors.

### 1.5 Cryptographic Passwords & Rehashing
- Passwords use bcrypt (`PASSWORD_DEFAULT`).
- Stored bcrypt hashes are protected: submittal of matching bcrypt strings does not overwrite existing password hashes (`password_get_info()['algo'] === null`).
- Admin accounts require a minimum password length of 12 characters (maximum 72 bytes).

### 1.6 Pre-Auth Brute-Force Rate Limiting
- Login attempts are throttled before password verification:
  - Account key (`login:user:email` / `login:admin:email`): max 5 failures per 15 minutes.
  - IP key (`login:ip:ip`): max 20 failures per 15 minutes.
- Throttled requests receive an HTTP 429 Too Many Requests response.
- Expired throttle records older than 24 hours are automatically pruned.

### 1.7 PNR Enumeration & Timing Attack Defense
- Replaced sequential integer IDs with random 10-hex uppercase tokens (`random_bytes(5)`).
- Public PNR status checks require the last 4 digits of the passenger phone number.
- Throttled per IP (10 / 10 min) and per target PNR token (5 / 15 min).
- Constant-time password verify dummy execution prevents user enumeration.

### 1.8 Comprehensive Audit Trail
- All administrative mutations (bus create/delete, route schedule create/delete, booking create/cancel, customer modifications) write immutable log entries to `audit_log`.
- Database triggers block `UPDATE` and `DELETE` on `audit_log`.

### 1.9 Enforced HTTP Security Headers
Apache security headers in `docker/apache-security.conf` enforce:
- `Content-Security-Policy`: Strict directives with SRI hashes and script/image whitelisting.
- `Strict-Transport-Security`: `max-age=15552000` (HSTS).
- `X-Frame-Options`: `SAMEORIGIN`.
- `X-Content-Type-Options`: `nosniff`.

---

## 2. Automated Test Harnesses

The system contains five automated test suites verifying security, concurrency, data integrity, and UI resilience:

```bash
# Admin Login Entry Points, Query Whitelist & Modal UI
php tests/test_admin_login.php

# Phase 1: Access Control, TOTP 2FA, Password Policy & Session Invalidation
php tests/test_phase1.php

# Phase 2: Money, Tariff Governance, Hold Segregation, Manifest & Analytics
php tests/test_phase2.php

# Phase 3: Route Conflict Detection, Bus Capacity & Migration Idempotency
php tests/test_phase3.php

# Phase 4: Enforced CSP Headers, SRI Hashes, SQL LIKE Escaping & CLI Guards
php tests/test_phase4.php
```

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
- **Fix:** Archive the vehicle/route (`archived_at`) or wait until scheduled departure dates pass.

### Issue 5: "Unknown column 'role' in 'field list' / Migration skipped"
- **Cause:** A pre-existing database recorded migration 001 as applied before `role` was added, causing subsequent migrations (e.g., 009) to fail.
- **Fix:** `database/db_migrate.php` includes self-healing schema checks on startup that automatically verify and add `admin.role`. Alternatively, add it manually in TiDB Cloud SQL Editor:
  ```sql
  ALTER TABLE `admin` ADD COLUMN `role` ENUM('super_admin','operator','viewer') NOT NULL DEFAULT 'operator';
  ```

### Issue 6: "Two-Factor Authentication required / Redirected to profile.php"
- **Cause:** Mandatory 2FA enforcement was activated for admin accounts when `totp_enabled = 0`.
- **Fix:**
  1. Complete 2FA enrollment on `admin/profile.php` using Google Authenticator or Authy.
  2. Set `ADMIN_MFA_ENFORCE=false` in Render environment variables to make 2FA optional.
  3. If locked out of an account, run the break-glass CLI reset:
     ```bash
     php database/reset-mfa.php "admin@example.com"
     ```

### Issue 7: "The QR code is not loading"
- **Cause:** Browser Content Security Policy (CSP) blocked third-party external QR image generators.
- **Fix:** The application now renders QR codes locally via client-side HTML5 canvas (`qrcode.js`), with a 1-click **Copy** button for manual secret key entry in authenticator apps.

### Issue 8: "TiDB cannot add a STORED generated column via ALTER TABLE (Migration 005)"
- **Cause:** TiDB Cloud Serverless does not support adding `STORED` generated columns via `ALTER TABLE`.
- **Fix:** `database/db_migrate.php` implements an automated fallback to `VIRTUAL` generated columns for `active_seat`, ensuring the unique index `uq_booking_active_seat` is created without failure.
