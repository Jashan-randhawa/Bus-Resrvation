# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

### Added
- **Password Visibility Toggle (Issue 5)**: Added accessible Show/Hide toggle button in the admin login password group with `aria-label` and `aria-pressed` states, auto-resetting on submit and modal close.
- **Caps Lock Detection & Live Alerts (Issue 6)**: Added real-time Caps Lock state listener with `aria-live="polite"` accessible announcement to minimize mistyped password lockout risks.
- **Admin Password Recovery Guidance (Issue 6)**: Added clear UI recovery guidance directing administrators to super administrators for password reset procedures.

### Changed
- **Admin Modal Visual Redesign & Palette (Issues 3, 4, 7)**: Redesigned the admin modal header with dedicated slate-900 surface (`#0f172a`), inline brand bus logo, and amber "Restricted access" badge.
- **Accessibility & WCAG AA Contrast Compliance (Issue 7)**: Upgraded helper and label contrast to `#64748b` (4.76:1 ratio against white) and implemented full dark theme support (`html[data-theme="dark"]`).
- **Admin Entry Point Links (Issue 2)**: Converted the header "Admin Portal" button and footer link from JavaScript-only placeholders to accessible, bookmarkable links pointing to `homepage.php?login=admin`, preserving modal trigger attributes for progressive enhancement.

### Fixed
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
