# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

### Added
- **Performance Overview Charts & Indicators (A12)**:
  - Replaced the basic 14-day bar strip with a full-width quick-read performance dashboard in `admin/dashboard.php`.
  - Added 4 KPI indicator tiles: Reservations, Confirmed Revenue, Cancel Rate, and Average Reservations/Day with previous-period comparison badges and threshold-based indicator coloring.
  - Implemented continuous zero-filled daily series query logic supporting 7d, 30d, and 90d operational windows.
  - Integrated interactive combo chart via Chart.js with SRI hash (stacked active/cancelled booking volume + secondary axis revenue line) and outcome donut chart.
  - Added summary highlights line for busiest day, top revenue day, and quietest booking day.
  - Replaced static table clutter with an accessible collapsible daily breakdown accordion.
  - Added live dark theme synchronization via `MutationObserver` and `prefers-reduced-motion` support.
- **Departure Schedule Carousel (A12)**:
  - Converted static departures table into a moving multi-card departure carousel with 3-second auto-slide, pause guards, touch swipe, and accessibility support.


## [2.3.0] - 2026-10-08

### Added
- **Admin Section Security Remediation Plan**: Fully implemented and verified 32 findings across 4 phases:
  - Phase 1 (Access & Auth): Session hard lifetime cap (8h), password rotation check, fail-closed role check, RFC 6238 TOTP 2FA engine with AES-256-GCM encryption, scoped rate limiting, timing equalization, append-only triggers on `audit_log`, and archival script.
  - Phase 2 (Money & Reporting): RFC 5321 SMTP client with TLS, guarded inquiry email updates, manifest revenue confirmed-only filter with hold segregation, PII masking and export auditing, server-side tariff lookup, locked journey edit parameters, and 30-day dashboard reporting.
  - Phase 3 (Data Integrity): Active route schedule overlap checks, route edit lockdown on active bookings, bus capacity bounds (10–60), container startup migration gating (`MIGRATE_ON_START=1`), safe migration deduping, customer foreign key linkage (`customer_id`), and migration script reconciliation.
  - Phase 4 (Hygiene & Hardening): Diagnostics count reconciliation, double-escaping fixes, SQL `LIKE` wildcard escaping (`escape_like()`), enforced Content-Security-Policy with SRI hashes, read-only GET cleanliness, CLI hold expiration script, and strict manifest date format validation.
- **GitHub Packages Release Automation**: Corrected GHCR metadata tagging with semver, edge, and conditional latest; release bundle archive workflow.

## [2.2.0] - 2026-10-04

### Added
- **Atomic Booking Concurrency & Seat Locking (Issues 2, 4, 5)**: Added transactional `seat_lock` acquisition in `create_booking()`, rejecting uncatalogued fleet vehicles, eliminating `INSERT IGNORE`, and handling MySQL 1062 unique constraint violations with resubmission recovery.
- **Stale Lock Eviction & Lifecycle Management (Issue 2)**: Added `purge_stale_seat_locks()` and upgraded `release_expired_holds()` to purge orphan locks associated with cancelled, expired, or deleted bookings.
- **Atomic Cancellation Helper (Issue 3)**: Introduced `cancel_booking()` ensuring atomic status transition to `Cancelled` and deletion from `seat_lock` in a single transaction across `admin/bookings.php` and `user/my-bookings.php`.
- **Booking Concurrency Diagnostics (Issue 1)**: Added `booking_concurrency_status()` evaluating modern `seat_lock` integrity and active-seat partial constraints without checking deprecated `uq_booking_seat`.
- **Concurrent Test Harness & Suite 14 (Issue 11)**: Created synchronized worker `tests/concurrency_worker.php` and added Suite 14 (Tests A to I) covering same-departure races, day-level lock constraints, hold release, atomic rollback, and 8-worker parallel competition.
- **Password Visibility Toggle (Issue 5)**: Added accessible Show/Hide toggle button in the admin login password group with `aria-label` and `aria-pressed` states, auto-resetting on submit and modal close.
- **Caps Lock Detection & Live Alerts (Issue 6)**: Added real-time Caps Lock state listener with `aria-live="polite"` accessible announcement to minimize mistyped password lockout risks.
- **Admin Password Recovery Guidance (Issue 6)**: Added clear UI recovery guidance directing administrators to super administrators for password reset procedures.

### Changed
- **Migration Conflict Guards (Issues 6, 7, 8)**: Guarded migration 001 against recreating retired `uq_booking_seat`, added pre-migration conflict detection to migration 005 prior to `uq_booking_active_seat` creation, and added conflict checks prior to migration 007 `seat_lock` backfill.
- **Seat Occupancy Visualizer (Issue 10)**: Updated `admin/seats.php` all-departures lock query to join `seat_lock` with `booking` filtering for active statuses (`Confirmed`, `Pending`).
- **Admin Modal Visual Redesign & Palette (Issues 3, 4, 7)**: Redesigned the admin modal header with dedicated slate-900 surface (`#0f172a`), inline brand bus logo, and amber "Restricted access" badge.
- **Accessibility & WCAG AA Contrast Compliance (Issue 7)**: Upgraded helper and label contrast to `#64748b` (4.76:1 ratio against white) and implemented full dark theme support (`html[data-theme="dark"]`).
- **Admin Entry Point Links (Issue 2)**: Converted the header "Admin Portal" button and footer link from JavaScript-only placeholders to accessible, bookmarkable links pointing to `homepage.php?login=admin`, preserving modal trigger attributes for progressive enhancement.

### Fixed
- **Diagnostics False Alarms (Issue 1)**: Replaced retired index check on `uq_booking_seat` with comprehensive table constraint diagnostics in `admin/diagnostics.php`.
- **Session Bootstrap CLI Warning**: Added null coalescing check to `$_SERVER['REQUEST_METHOD']` in `includes/auth/session-bootstrap.php` for seamless CLI test execution.
- **Admin Sign-in Redirects (Issue 1, Issue 8)**: Added GET query parameter handling on `homepage.php` to automatically open the admin login modal when unauthenticated or redirected with `?login=admin`.
- **Session Expiry & Deactivation Notices**: Added whitelist handling for `&error=expired` and `&error=deactivated` so signed-out or deactivated admins receive clear, actionable feedback.
- **Session Guard Expiry Flagging**: Updated `admin-session.php` to distinguish expired active sessions from unauthenticated visits when redirecting.
- **Test Coverage**: Added automated regression checks in `tests/run_tests.php`, standalone test harness in `tests/test_admin_login.php`, and manual test verification checklist in `docs/admin-login-test-checklist.md`.

## [2.1.0] - 2026-10-04

### Security & Hardening
- **SQL Injection Remediation (C-01, C-02)**: Converted all database queries project-wide to parameterized prepared statements (`db_one`, `db_all`, `db_exec`).
- **Authentication & Password Security (C-03, C-04)**: Implemented bcrypt password hashing (`password_hash`/`password_verify`), widened database password columns to 255 chars, and removed hardcoded seed credentials. Added secure CLI admin provisioning script.
- **CSRF Defense (H-03)**: Added CSRF tokens and server-side verification to all forms and actions; converted state-changing GET deletions to POST requests.
- **Role Isolation & Session Security (H-01, H-02)**: Implemented strict role-based session bootstrap with session regeneration, 30-minute idle expiration, and secure cookie flags (`HttpOnly`, `SameSite=Lax`, HTTPS detection).
- **Double-Booking & Race Condition Prevention (H-04, H-08)**: Enforced transactional seat bookings with row-level locks and unique constraints; server-side authoritative fare resolution; randomized 10-char hex PNR tokens.
- **PNR Privacy & Rate Limiting (C-05, H-05)**: Replaced sequential PNR guessing with two-factor rate-limited lookup requiring the last 4 digits of passenger phone.
- **XSS & Output Sanitization (C-06, H-07)**: Sanitized all dynamic outputs with `e()` HTML escaping across admin, user, and public templates.
- **Container Hardening (M-05, M-06)**: Upgraded Dockerfile to PHP 8.4-apache, configured Apache HTTP security headers (CSP, HSTS, X-Frame-Options, etc.), and added healthcheck.
- **CI & Automation (M-08)**: Added GitHub Actions CI pipeline for PHP 8.4 linting, composer validation, and SQL anti-pattern checks; enabled Dependabot.
