# Changelog

All notable changes to this project are documented in this file.

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
