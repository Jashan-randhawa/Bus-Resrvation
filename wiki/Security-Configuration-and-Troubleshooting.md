# 🛡️ Security, Configuration & Troubleshooting

This document explains security hardening measures, multi-role RBAC, concurrency guarantees, regression test verification, and diagnostic troubleshooting runbooks.

---

## 1. Security Architecture & Hardening

### 1.1 Zero Hardcoded Secrets
All production credentials and API keys are injected dynamically via environment variables (`includes/db_con.php`, `includes/config.php`):
- `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_SSL`
- `ADMIN_MFA_ENFORCE`
- `BREVO_API_KEY`, `EMAIL_FROM` (transactional email dispatch over HTTPS port 443)
- `MAIL_ENABLED`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM`, `MAIL_FROM_NAME` (RFC 5321 SMTP relay)
- `APP_URL`, `PASSWORD_RESET_TTL_MIN`
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- `TICKET_HMAC_SECRET` (used for digital boarding pass signature generation and verification)

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

### 1.9 Enforced HTTP Security Headers & PII Zero-Leakage Analytics
Apache security headers in `docker/apache-security.conf` enforce:
- `Content-Security-Policy`: Strict directives with SRI hashes and script/image whitelisting.
- `Strict-Transport-Security`: `max-age=15552000` (HSTS).
- `X-Frame-Options`: `SAMEORIGIN`.
- `X-Content-Type-Options`: `nosniff`.
- **Privacy-First Dashboard Analytics (`admin/dashboard.php`):** All Chart.js datasets (`Booking & Revenue Trends`, `Booking Status`) are aggregated strictly on the server over numeric/date columns so zero passenger PII (names, phone numbers, emails, or PNRs) is ever exposed in browser DOM payloads.

### 1.10 Customer Google OAuth 2.0 / OpenID Connect Security
- **Strict Customer Isolation:** Google sign-in is restricted exclusively to the customer portal (`login_user('user', ...)`). The administrator section (`admin/`) does not accept Google credentials.
- **PKCE (S256) & Nonce Replay Defense:** Generates high-entropy cryptographic `state`, `nonce`, and S256 PKCE `code_verifier` on each initiation, stored in session and consumed on single use.
- **Server-to-Server Code Exchange:** Google authorization code is exchanged directly over TLS with Google's token endpoint (`https://oauth2.googleapis.com/token`); client secrets never touch the browser.
- **Token Verification:** Strict verification of JWT claims (`iss`, `aud`, `exp`, `nonce`, `sub`, and `email_verified`).
- **Pre-Hijack Account Protection:** Linking Google to an existing email clears previous unverified passwords, preventing pre-hijacking attacks.
- **Sensitive Action Re-Authentication Window (`max_age=300`):** Customers with Google-only accounts (`pwd_set = 0`) must complete Google re-authentication (`google-reauth.php`) to unlock sensitive actions (change email, change/set password, deactivate account). Google mandates `prompt=login`, `max_age=300`, and verifies the `auth_time` claim within 300 seconds to ensure the user is actively present.
- **Session Replay & Hijacking Defense:** Re-authentication state is single use, stored securely in `$_SESSION['reauth_at']`, and automatically invalidates after `GOOGLE_REAUTH_TTL` (300 s).
- **Zero Callback Latency & Lock Contention Elimination:** Configures `CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4` on token exchange (`oauth2.googleapis.com`) to eliminate dual-stack IPv6 DNS resolution stalls. Calls explicit `session_write_close()` before issuing 302 redirect headers in `google-callback.php` and `pr_after_response()` so customer portal pages load instantaneously without PHP session lock contention.

### 1.11 Cryptographic HMAC-SHA256 Boarding Pass Seals
- **Tamper-Proof Link Authorization:** Confirmation emails include a 1-click boarding pass URL with a 12-character HMAC-SHA256 seal (`&sig=...`) computed via `hash_hmac('sha256', "{$pnr}|{$date}|{$time}|{$seat}", $secret)`.
- **Session Login Bypass:** Validating incoming signatures with `hash_equals()` allows passengers to view and print their boarding pass immediately from mobile mail clients (Gmail, Apple Mail, Outlook) without logging in or risking CSRF vulnerabilities.
- **Privilege Separation:** Signature authorization is strictly read-only for ticket viewing and printing. It grants zero access to passenger profile mutations, ticket cancellations, or account settings.

### 1.12 Self-Hosted Vendor Assets & Tracking Prevention
- Self-hosting Bootstrap 4.6.2 and jQuery 3.5.1 in `assets/vendor/` guarantees that strict browser privacy engines (Microsoft Edge Tracking Prevention, Brave Shields, Safari ITP) never block asset loading or report storage access warnings.
- Assures 100% offline service worker caching without external network dependencies.

---

## 2. Automated Test Harnesses

The system contains 18 automated test suites (480+ assertions) verifying security, concurrency, data integrity, PDF generation, PWA compliance, and UI responsiveness:

```bash
# Admin Login Entry Points, Query Whitelist & Modal UI
php tests/test_admin_login.php

# Phase 1: Access Control, TOTP 2FA, Password Policy & Session Invalidation
php tests/test_phase1.php

# Phase 2: Money, Tariff Governance, Hold Segregation, Manifest & Analytics
php tests/test_phase2.php

# Phase 3: Route Conflict Detection, Bus Capacity & Migration Idempotency
php tests/test_phase3.php

# Phase 3 PWA: Dynamic Root Resolution, Service Worker & Print Guards
php tests/test_phase3_pwa.php

# Phase 4: Enforced CSP Headers, SRI Hashes, SQL LIKE Escaping & CLI Guards
php tests/test_phase4.php

# Admin Mobile Drawer, Topbar Eyebrow Pills, SVG Sprites & Global Theme Tokens
php tests/test_admin_layout.php

# Customer Portal Mobile Responsiveness, Custom Dropdowns & Sticky Booking Bar
php tests/test_user_layout.php

# Boarding Pass Email Button & HMAC-SHA256 Signature Authorization
php tests/test_email_ticket_link.php

# Mobile Features & Popular Routes Swipe Carousels & 5s Auto-Scroll
php tests/test_features_carousel.php

# Modern 4-Column Web Footer, PWA Standalone Bottom Nav & Admin Utility Bar
php tests/test_modern_footer.php

# Standalone Cinematic Loading Page & Preloader Transition
php tests/test_loading_page.php

# PWABuilder Web App Manifest Compliance Suite
php tests/test_pwabuilder_manifest.php

# Modernized Route Schedule UI, KPI Cards, Smart Filters & Swap Controls
php tests/test_route_ui.php

# Pure-PHP Native PDF Manifest Generator & Mobile Manifest Card Verification
php tests/test_pdf_manifest.php

# Today's Departures Compact Schedule Cards, Occupancy Bars & Status Chips
php tests/test_departure_carousel.php

# Privacy-First Performance Overview Analytics & Dashboard Audit Verification
php tests/test_perf_overview.php
php tests/test_dashboard_audit.php

# Email Password Reset Link Flow, SHA-256 Token Hashing & Anti-Enumeration Suite
php tests/test_password_reset.php

# Customer Google OAuth 2.0 / OpenID Connect PKCE & Token Validation Suite
php tests/test_google_auth.php
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

### Issue 9: "SMTP Connection Timed Out / Outbound Port 587 Blocked on Cloud Host"
- **Cause:** Cloud hosting environments such as Render restrict direct outbound TCP connections on SMTP ports (`25`, `465`, `587`).
- **Fix:** Use the built-in Brevo HTTPS REST API transport over standard HTTPS port 443. Set `BREVO_API_KEY` and `EMAIL_FROM` in your Render dashboard environment variables; outbound emails are dispatched via direct HTTPS REST requests without port restrictions.

### Issue 10: "Boarding Pass Button in Email Leads to 404 Error"
- **Cause:** Webmail clients (such as Gmail or Outlook) resolve relative URLs (e.g. `/user/ticket.php?pnr=...`) against their own webmail origin (`https://mail.google.com/user/...`).
- **Fix:** `includes/helpers.php` provides `get_app_url()`, which dynamically resolves the fully qualified public domain name (`APP_URL`, `RENDER_EXTERNAL_URL`, or `RENDER_EXTERNAL_HOSTNAME`), ensuring all confirmation email links are generated with complete absolute paths (`https://bus-resrvation.onrender.com/user/ticket.php?...`).

### Issue 11: "Cannot Open Boarding Pass from Mobile Email Without Logging In"
- **Cause:** Passenger is redirected to the login page when opening a ticket link from their mobile mail app.
- **Fix:** Email ticket URLs now contain a cryptographic HMAC-SHA256 signature (`&sig=...`). `user/ticket.php` verifies this signature using constant-time `hash_equals()`. If the signature matches, the ticket renders immediately on the mobile browser without requiring login credentials.

### Issue 12: "Tracking Prevention Blocked Access to Storage for cdn.jsdelivr.net"
- **Cause:** Modern privacy-focused browsers (Microsoft Edge, Brave, Safari) block third-party CDNs from accessing browser cookies and local storage.
- **Fix:** Self-host Bootstrap and jQuery in `assets/vendor/`. The application no longer loads external script or stylesheet assets from third-party CDN origins.

### Issue 13: "Banner Not Shown: beforeinstallpromptevent.preventDefault() Called"
- **Cause:** The browser's native PWA installation mini-infobar was suppressed by `e.preventDefault()` without an active button triggering `e.prompt()`.
- **Fix:** The install controller in `assets/js/pwa-install.js` stores the `beforeinstallprompt` event without suppression, activating the custom `#pwa-install-btn` in the navbar and triggering native prompts smoothly upon user tap.
