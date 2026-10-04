# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 2.x     | :white_check_mark: |
| 1.x     | :x:                |

## Security Architecture & Hardening Measures

This codebase has undergone a comprehensive static and runtime security audit:
- **Prepared Statements Everywhere**: Zero direct variable interpolation in SQL queries. All queries are parameterized via mysqli prepared statements (`db_one`, `db_all`, `db_exec`).
- **Cryptographic Password Hashing**: Passwords stored using `password_hash()` with modern bcrypt algorithms (`$2y$`). Plaintext passwords are automatically converted and prohibited.
- **Strict Role-Based Access Control**: Strict role enforcement (`admin` vs `user`) on every internal page.
- **CSRF Defense**: Every state-changing form and action carries a cryptographic anti-CSRF token verified on submission.
- **Race Condition Prevention**: Ticket booking operations run inside ACID database transactions with row-level locks and unique constraints on `(bus, date, time, seat)`.
- **Brute-Force Rate Limiting**: Multi-factor throttled PNR search and rate-limited authentication endpoints.
- **Output Escaping**: All dynamic outputs are sanitized with `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- **HTTP Security Headers**: HSTS, CSP, X-Frame-Options, X-Content-Type-Options, and Referrer-Policy enforced in container configuration.

## Reporting a Vulnerability

If you discover a security vulnerability in this project, please report it privately:
1. Open a private security advisory on GitHub or email the maintainer.
2. Provide a clear description and reproduction steps.
3. Please do not open public issues for sensitive security vulnerabilities.
