<div align="center">

# 🚌 Bus Reservation System

### *A Modern, Cloud-Ready Full-Stack Bus Ticket Booking & Fleet Management Platform*

[![Latest Release](https://img.shields.io/badge/Release-v2.3.0-blue?style=for-the-badge&logo=github)](https://github.com/Jashan-randhawa/Bus-Resrvation/releases/tag/v2.3.0)
[![Docker Package](https://img.shields.io/badge/GitHub%20Package-Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation)
[![npm Packages](https://img.shields.io/badge/GitHub%20Packages-npm-CB3837?style=for-the-badge&logo=npm&logoColor=white)](https://github.com/Jashan-randhawa/Bus-Resrvation/packages)
[![Wiki Docs](https://img.shields.io/badge/Documentation-Wiki-green?style=for-the-badge&logo=gitbook&logoColor=white)](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki)
[![PHP](https://img.shields.io/badge/PHP-8.3%20%7C%208.4-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![TiDB Cloud](https://img.shields.io/badge/TiDB%20Cloud-MySQL%20Compatible-E30C34?style=for-the-badge&logo=mysql&logoColor=white)](https://tidb.cloud/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-4.6-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![License](https://img.shields.io/badge/License-MIT-orange?style=for-the-badge)](LICENSE)

<br/>

[📖 Explore Technical Wiki](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki) • [📦 Download v2.3.0 Release Bundle](https://github.com/Jashan-randhawa/Bus-Resrvation/releases/tag/v2.3.0) • [🐳 Pull Docker Image](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation)

</div>

---

## 📖 Overview

The **Bus Reservation System** is an end-to-end digital ticketing and fleet operations management platform. Built using **PHP 8.3 / 8.4** and **MySQL / TiDB Cloud Serverless**, it eliminates manual booking workflows with real-time seat availability maps, instant cryptographic PNR verification, customer account management, and a centralized administrative control center with multi-role RBAC and RFC 6238 Two-Factor Authentication.

Packaged with **Docker** and pre-configured for one-click deployment on **Render**, the application is production-hardened, cloud-native, and fully responsive across mobile, tablet, and desktop viewports.

---

## 📚 Technical Wiki & Documentation

Comprehensive architectural, operational, and security documentation is available in the **[Official Technical Wiki](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki)**:

| Document | Topic | Description |
|---|---|---|
| **[🏠 Wiki Home](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Home)** | Overview | Central hub and quick navigation directory |
| **[🏗️ Architecture & System Design](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Architecture-and-System-Design)** | System Topology | Monolithic runtime, Docker containerization, dynamic port binding, and request flow |
| **[🗄️ Database Schema & Models](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Database-Schema-and-Models)** | Data Layer | ERD, data dictionaries for all tables, column constraints, referential integrity |
| **[👑 Administrator Guide](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Administrator-Guide)** | Operator Manual | RBAC roles, live KPI cards, fleet management, route scheduling, seat auditor, audit logging |
| **[🧑‍💼 Customer Portal & Booking Flow](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Customer-Portal-and-Booking-Flow)** | Passenger UX | Interactive seat map picker, PNR lifecycle, and trip history |
| **[🐳 Deployment & DevOps](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Deployment-and-DevOps)** | Cloud & CI/CD | Render Web Service deployment, TiDB Cloud setup, environment catalog, and GHCR builds |
| **[🛡️ Security & Troubleshooting](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Security-Configuration-and-Troubleshooting)** | Hardening & Fixes | TLS/SSL enforcement, session protection, rate limiting, and error runbooks |

---

## ✨ Key Features

### 👤 Customer Experience
- 🔍 **Interactive Seat Picker:** Dynamic bus seating map adapting to fleet capacity (10–60 seats) with live available/booked status indicators.
- 🔒 **Atomic Concurrency:** Concurrency-safe seat reservation engine with database transactions and row-level locks preventing double-booking race conditions.
- 🎫 **Instant Cryptographic PNR:** Auto-generated unique random 10-character token with departure details, seat assignment, and pricing.
- 🔎 **Two-Factor Public PNR Lookup:** Check reservation status directly from the homepage without logging in, protected by rate limiting and phone number verification.
- 📱 **Customer Portal:** Profile management, reservation history, and self-service cancellation with cutoff window safeguards.
- 💬 **Inquiry Support:** Direct feedback and query submission form for passenger support.

### 🛡️ Administrator Operations
- 👥 **Multi-Role RBAC & Live Session Validation:** Granular tiers (`super_admin`, `operator`, `viewer`) with live per-request role and active status validation against the database. Viewer role is restricted to read-only views with PII masking.
- 🔐 **RFC 6238 TOTP Two-Factor Authentication:** Multi-factor authentication for administrative accounts with client-side HTML5 canvas QR code generation, AES-256-GCM encrypted secrets, 10 single-use recovery codes, and configurable policy enforcement (`ADMIN_MFA_ENFORCE`).
- 🛠️ **CLI Break-Glass Recovery:** Dedicated emergency command-line utilities for administrator creation (`create-admin.php`) and 2FA recovery resets (`reset-mfa.php`).
- 🗑️ **Soft-Delete Archiving & Restoration:** Safe `archived_at` lifecycle with Active vs Archived tab views and one-click restoration across fleet buses, routes, and customer accounts.
- 📥 **Streaming CSV Exports:** Memory-efficient CSV downloads with spreadsheet formula injection protection (`=`, `+`, `-`, `@`) for bookings, customers, trip manifests, and audit trails.
- 🔍 **Multi-Field Search & Date Filters:** Parameterized multi-criteria filtering across bookings, customer accounts, and audit events.
- 📜 **Immutable Audit Trail:** Append-only event logging with visual before/after state diffing, database trigger protection, and automated retention cleanup.
- 💬 **Customer Query Inbox & Email Replies:** Support inquiry lifecycle (`new`, `replied`, `closed`), modal email replies with RFC 5321 SMTP transport, and a live sidebar unread query counter.
- 📈 **30-Day Trends & Corridor Analytics:** 30-day booking and revenue breakdowns, cancellation rate KPIs, and top 5 transit corridor utilization metrics.
- 🎟️ **Bulk Booking Operations:** Multi-select checkboxes for batch reservation cancellations and selected-row CSV exports.
- 📋 **Passenger Trip Manifest:** Dedicated printable manifest sheet (`admin/manifest.php`) by bus, date, and corridor with print-ready CSS (`@media print`) and conductor/driver sign-off fields.
- 🚌 **Fleet & Bus Management:** Configure custom seating capacities (10–60) and seating layout geometries (`2+2`, `2+1`, `1+2`, `1+1`).
- 🛣️ **Route Management:** Configure origin/destination hubs, bus assignments, departure schedules, and fare pricing with conflict/collision detection.

---

## 🏗️ System Architecture

```mermaid
flowchart TD
    Client["🌐 Web Browser (Responsive UI)"]
    
    subgraph Render["☁️ Render Cloud Platform"]
        Docker["🐳 Docker Container (PHP 8.4 + Apache 2.4)"]
        Entrypoint["🚀 entrypoint.sh (Auto Migrations)"]
        App["Bus Reservation App<br/>(Public Area, Admin Dashboard, Customer Portal)"]
        Entrypoint --> App
        Docker --> Entrypoint
    end
    
    subgraph Database["🗄️ TiDB Cloud Serverless / MySQL 8.0+"]
        Tables[("Database Layer<br/>- admin (RBAC & TOTP MFA)<br/>- costumer (Accounts & Passwords)<br/>- buses (Capacity & Layouts)<br/>- route (Schedules & Tariffs)<br/>- booking (PNR & Concurrency Keys)<br/>- seat_lock (Atomic Holds & Claims)<br/>- audit_log (Immutable Compliance)<br/>- admin_recovery_codes (Backup TOTP)<br/>- schema_migrations & migration_steps<br/>- query (Support Inbox)")]
    end

    Client -->|HTTPS / TLS 1.3 (Port 443)| Docker
    App -->|Secure TLS/SSL Connection (Port 4000/3306)| Tables
```

---

## 🛠️ Tech Stack

| Layer | Technologies |
|---|---|
| **Frontend** | HTML5, CSS3, JavaScript (ES6), Bootstrap 4.6, AOS.js Animations, Font Awesome, QRCode.js |
| **Backend** | PHP 8.3 / 8.4 (Apache 2.4 runtime), Session Auth, MySQLi with TLS/SSL |
| **Database** | TiDB Cloud Serverless (MySQL Compatible) / MySQL 8.0+ |
| **Security & Auth** | RFC 6238 TOTP 2FA, AES-256-GCM, Bcrypt Hashing, Anti-CSRF, Enforced CSP, Rate Limiting |
| **DevOps & Cloud** | Docker, Render Web Services, GitHub Actions CI/CD, GHCR Container Registry |
| **Registries & Packages** | GitHub Container Registry (`ghcr.io`), GitHub Packages (npm), Composer |

---

## 📦 Packages & Distribution Artifacts

| Distribution Package | Target / Registry | Description |
|---|---|---|
| **🐳 Docker App Container** | [`ghcr.io/jashan-randhawa/bus-resrvation`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation) | Production app image (`:latest`, `:2.3.0`, `:edge`) |
| **⚡ Docker Migration Runner** | [`ghcr.io/jashan-randhawa/bus-resrvation-migrate`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation-migrate) | One-off CLI database migration runner (`:latest`, `:1.0.0`) |
| **💺 Seat Picker Widget (npm)** | [`@jashan-randhawa/bus-seat-picker`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/npm/bus-seat-picker) | Accessible seat selection widget with live availability polling |
| **🎨 Design Tokens & UI (npm)** | [`@jashan-randhawa/busres-ui`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/npm/busres-ui) | Design tokens, components, and dark/light theme toggle |
| **🗜️ Release Zip Archive** | [`bus-reservation-v2.3.0.zip`](https://github.com/Jashan-randhawa/Bus-Resrvation/releases/download/v2.3.0/bus-reservation-v2.3.0.zip) | Curated distribution bundle via GitHub Actions |
| **📄 Implementation Plan PDF** | [`Bus_Reservation_GitHub_Packages_Plan.pdf`](https://github.com/Jashan-randhawa/Bus-Resrvation/blob/main/docs/Bus_Reservation_GitHub_Packages_Plan.pdf) | GitHub Packages Release Plan |

### 📦 Installing npm Packages from GitHub Packages

Configure your local `.npmrc`:
```ini
@jashan-randhawa:registry=https://npm.pkg.github.com
//npm.pkg.github.com/:_authToken=${GITHUB_TOKEN}
```

Install via npm:
```bash
npm install @jashan-randhawa/bus-seat-picker
npm install @jashan-randhawa/busres-ui
```

### ⚡ Running Standalone Migration Runner

```bash
docker pull ghcr.io/jashan-randhawa/bus-resrvation-migrate:latest

docker run --rm \
  -e DB_HOST=your_host \
  -e DB_PORT=4000 \
  -e DB_USER=your_user \
  -e DB_PASS=your_pass \
  -e DB_NAME=test \
  -e DB_SSL=true \
  ghcr.io/jashan-randhawa/bus-resrvation-migrate:latest
```

---

## ⚙️ Environment Configuration

All application configuration is driven by environment variables. Configure them in Render, Docker, or your local `.env` file:

| Variable | Default | Description | Example |
|---|---|---|---|
| `DB_HOST` | `localhost` | Database Hostname / Gateway | `gateway01.ap-south-1.prod.aws.tidbcloud.com` |
| `DB_PORT` | `3306` | Database Port | `4000` (TiDB) or `3306` (MySQL) |
| `DB_USER` | `root` | Database Username | `xxxxxx.root` |
| `DB_PASS` | *(empty)* | Database Password | `your_secure_password` |
| `DB_NAME` | `bus_reservation` | Database Catalog Name | `test` or `bus_reservation` |
| `DB_SSL` | `false` | Enable TLS/SSL Connection | `true` |
| `MIGRATE_ON_START` | `1` | Automatically run migrations on container boot | `1` |
| `ADMIN_MFA_ENFORCE` | `false` | Strictly enforce 2FA on admin dashboard access | `false` (optional) or `true` (mandatory) |
| `APP_BOOKING_CUTOFF_MIN` | `30` | Minimum minutes before departure to book tickets | `30` |
| `APP_CANCEL_CUTOFF_MIN` | `120` | Minimum minutes before departure to cancel bookings | `120` |
| `APP_TZ` | `Asia/Kolkata` | Application Timezone | `Asia/Kolkata` |
| `APP_CURRENCY` | `₹` | Currency symbol | `₹` or `$` |
| `APP_DEBUG` | `0` | Expose detailed error stack traces to super admins | `0` (off) or `1` (on) |
| `BASE_URL` | *(auto)* | Root URL path (auto-detected when blank) | `""` (web root) or `"/bus-reservation"` |
| `MAIL_ENABLED` | `false` | Enable outbound RFC 5321 SMTP email delivery | `true` |
| `MAIL_HOST` | *(empty)* | SMTP Relay Host | `smtp.example.com` |
| `MAIL_PORT` | `587` | SMTP Relay Port | `587` |
| `MAIL_USER` | *(empty)* | SMTP Username / API Key | `apikey` |
| `MAIL_PASS` | *(empty)* | SMTP Password / Secret Key | `your_smtp_secret` |
| `MAIL_FROM` | *(empty)* | Outbound From Address | `reservations@example.com` |
| `MAIL_FROM_NAME` | `"Bus Reservation System"` | Outbound Sender Name | `"Bus Reservation System"` |

---

## ⚡ Quick Start & Deployment

### 🐳 Option A: Deploy to Render with TiDB Cloud (Recommended)

1. **Fork or Clone this repository:**
   ```bash
   git clone https://github.com/Jashan-randhawa/Bus-Resrvation.git
   cd Bus-Reservation
   ```

2. **Set up a free TiDB Cloud Serverless instance:**
   - Create a free cluster on [TiDB Cloud](https://tidb.cloud).
   - In the SQL Editor, execute the schema from [`database/init.sql`](./database/init.sql).

3. **Deploy on Render:**
   - Create a new **Web Service** on [Render](https://dashboard.render.com).
   - Connect your GitHub repository and select the **Docker** runtime.
   - Configure the environment variables listed in the catalog above (`DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_SSL=true`).
   - Click **Deploy Web Service**. On container startup, `entrypoint.sh` automatically runs all pending migrations idempotently.

---

### 💻 Option B: Run with GitHub Container Package (Fastest)

```bash
# Pull the pre-built image from GitHub Container Registry
docker pull ghcr.io/jashan-randhawa/bus-resrvation:latest

# Run container connected to TiDB Cloud or local MySQL
docker run -d -p 8080:80 \
  -e DB_HOST=your_host \
  -e DB_PORT=4000 \
  -e DB_USER=your_user \
  -e DB_PASS=your_pass \
  -e DB_NAME=test \
  -e DB_SSL=true \
  --name bus-reservation-app \
  ghcr.io/jashan-randhawa/bus-resrvation:latest
```
Visit `http://localhost:8080` in your browser.

---

### 🖥️ Option C: Run Locally with XAMPP / WAMP

1. Place the project folder into your web root (e.g., `C:/xampp/htdocs/Bus-Reservation`).
2. Start **Apache** and **MySQL** from XAMPP Control Panel.
3. Import [`database/init.sql`](./database/init.sql) into **phpMyAdmin**.
4. Run migrations via CLI: `php database/db_migrate.php`.
5. Access the application at `http://localhost/Bus-Reservation/homepage.php`.

---

## 🔐 Administrative Operations & CLI Tooling

Default credentials are intentionally **not hardcoded** in the schema for security. Use the provided CLI utilities:

### 1. Provision First Administrator Account
```bash
php database/create-admin.php "Super Administrator" "admin@example.com" "9876543210"
```
*Prompts for a secure password (minimum 12 characters, bcrypt hashed).*

### 2. Two-Factor Authentication & Break-Glass CLI Reset
- **Setup:** Log into the admin portal and navigate to **Profile & Security** (`admin/profile.php`). Scan the client-side QR code with Google Authenticator or copy the 16-character Secret Key.
- **Enforcement:** By default, 2FA is optional. To mandate 2FA across all admin accounts, set `ADMIN_MFA_ENFORCE=true` in Render.
- **Emergency CLI Reset:** If an admin is locked out or loses their authenticator device:
  ```bash
  php database/reset-mfa.php "admin@example.com"
  ```

### 3. Background Maintenance & Housekeeping
- **Execute Pending Migrations:**
  ```bash
  php database/db_migrate.php
  ```
- **Release Stale Seat Holds:**
  ```bash
  php database/expire-holds.php
  ```
- **Purge Compliance Audit Logs (>365 days):**
  ```bash
  php database/purge-audit-log.php 365
  ```

---

## 🧪 Automated Testing & Verification

The codebase includes comprehensive test harnesses covering authentication, concurrency, RBAC, input sanitization, and regression checks:

```bash
# Admin login entry points, parameter whitelist, and modal UI tests
php tests/test_admin_login.php

# Phase 1: Access control, TOTP 2FA engine, password policy & session invalidation
php tests/test_phase1.php

# Phase 2: Money, tariff governance, hold segregation, manifest & 30-day analytics
php tests/test_phase2.php

# Phase 3: Route conflict detection, bus capacity bounds & migration idempotency
php tests/test_phase3.php

# Phase 4: Enforced CSP headers, SRI hashes, SQL LIKE escaping & CLI guards
php tests/test_phase4.php
```

---

## 📂 Project Structure

```
Bus-Reservation/
├── index.php                 # Canonical entry point (redirects to homepage.php)
├── homepage.php              # Public landing page, login modal & PNR status check
├── login.php                 # Standalone login endpoint
├── admin.php                 # Backward-compatibility shim (redirects to admin/index.php)
│
├── admin/                    # 👑 Administrator Management Subsystem
│   ├── index.php             # Dashboard entry point
│   ├── dashboard.php         # Real-time metrics, earnings KPIs & 30-day trends
│   ├── buses.php             # Fleet management (capacity 10–60, layouts & soft-delete)
│   ├── routes.php            # Route schedules & tariffs (collision checks & soft-delete)
│   ├── customers.php         # Customer directory, search, CSV export & archiving
│   ├── bookings.php          # Booking management, multi-criteria filters & bulk actions
│   ├── seats.php             # Live seat occupancy monitor with departure filters
│   ├── manifest.php          # Passenger trip manifest (printable view & CSV export)
│   ├── queries.php           # Customer inquiry inbox & modal email reply workflow
│   ├── audit-log.php         # Immutable audit trail, visual diffs & compliance logs
│   ├── add-admin.php         # Administrator provisioning & account governance
│   ├── profile.php           # Profile management, password rotation & TOTP 2FA setup
│   ├── mfa.php               # Two-Factor Authentication login challenge controller
│   ├── diagnostics.php       # Operational integrity & security diagnostics
│   └── edit/                 # ✏️ Inline Record Editing Subsystem
│       ├── edit-booking.php
│       ├── edit-bus.php
│       ├── edit-route.php
│       └── edit-customer.php
│
├── user/                     # 🧑‍💼 Customer Passenger Portal
│   ├── index.php             # Customer dashboard & live seat search
│   ├── booking.php           # Reservation engine & dynamic seat layout picker
│   └── my-bookings.php       # Personal booking history & cancellation
│
├── includes/                 # 🔧 Core Framework & Security Libraries
│   ├── config.php            # Dynamic BASE_URL detector, global error handling & env defaults
│   ├── db_con.php            # Environment-driven database connector with TLS/SSL support
│   ├── helpers.php           # Prepared statements, create_booking(), CSRF, rate limits, CSV streaming
│   ├── admin-crud.php        # Shared admin CRUD helpers, soft-delete & UI components
│   ├── auth/
│   │   ├── session-bootstrap.php # Hardened session manager & secure cookie policy
│   │   ├── admin-session.php # Admin session authentication guard & live role checks
│   │   ├── user-session.php  # Customer session authentication guard
│   │   └── totp.php          # RFC 6238 TOTP engine, AES-256-GCM encryption & recovery codes
│   └── layout/
│       ├── header-public.php # Homepage header
│       ├── header-login.php  # Login page header
│       ├── header-admin.php  # Admin sidebar navigation & unread query counter
│       ├── header-user.php   # Customer navigation
│       ├── header-edit.php   # Edit pages header
│       ├── footer.php        # Public & edit footer scripts
│       └── footer-admin.php  # Admin & customer footer scripts
│
├── database/                 # 🗄️ Relational Schema & Migration Engine
│   ├── init.sql              # Idempotent hardened schema DDL
│   ├── db_migrate.php        # Robust database migration runner with advisory locking
│   ├── create-admin.php      # CLI administrator provisioning script
│   ├── reset-mfa.php         # CLI emergency break-glass 2FA reset script
│   ├── expire-holds.php      # CLI expired seat hold release utility
│   ├── purge-audit-log.php   # CLI audit log retention cleanup utility
│   └── migrations/           # 🔄 Sequential Schema Migrations (001 to 010)
│       ├── 001_hardening.sql                         # Base hardening & indexes
│       ├── 002_hash_passwords.php                    # Password migration & rehashing
│       ├── 003_bus_capacity.sql                      # Fleet bus capacity bounds
│       ├── 004_seat_hold_and_payment_states.sql      # Seat holds & payment states
│       ├── 005_referential_integrity_seat_locks.sql  # Referential integrity & seat_lock table
│       ├── 006_audit_logging.sql                     # Compliance audit log table
│       ├── 007_admin_accounts_and_soft_delete.sql    # Soft delete archived_at columns
│       ├── 008_query_inbox_enhancements.sql          # Inquiry status & reply tracking
│       ├── 009_admin_accounts_and_soft_delete.sql    # Admin security columns & recovery codes
│       └── 010_query_inbox_enhancements.sql          # Inbox timestamps & enhancements
│
├── packages/                 # 📦 npm UI Packages
│   ├── bus-seat-picker/      # @jashan-randhawa/bus-seat-picker package
│   └── busres-ui/            # @jashan-randhawa/busres-ui package
│
├── docker/                   # 🐳 Container Configuration
│   ├── entrypoint.sh         # Container entrypoint with startup migration gating
│   ├── php-extra.ini         # Hardened PHP production settings
│   └── apache-security.conf  # HTTP security headers (CSP, HSTS, X-Frame-Options)
│
├── tests/                    # 🧪 Automated Test Harnesses
│   ├── test_admin_login.php  # Admin login & redirect test suite
│   ├── test_phase1.php       # Phase 1: Access & auth test suite
│   ├── test_phase2.php       # Phase 2: Money & reporting test suite
│   ├── test_phase3.php       # Phase 3: Data integrity test suite
│   ├── test_phase4.php       # Phase 4: Hygiene & hardening test suite
│   ├── concurrency_worker.php # Concurrency test worker
│   └── run_tests.php         # Master test harness
│
├── docs/                     # 📄 Project Documents & Release Plans
│   └── Bus_Reservation_GitHub_Packages_Plan.pdf
│
├── wiki/                     # 📚 Complete Offline Technical Wiki
│   ├── Home.md
│   ├── Architecture-and-System-Design.md
│   ├── Database-Schema-and-Models.md
│   ├── Administrator-Guide.md
│   ├── Customer-Portal-and-Booking-Flow.md
│   ├── Deployment-and-DevOps.md
│   └── Security-Configuration-and-Troubleshooting.md
│
├── .github/                  # 🤖 GitHub Automation Workflows
│   ├── dependabot.yml        # Automated dependency maintenance
│   └── workflows/
│       ├── ci.yml            # Automated syntax, composer audit & SQL checks
│       ├── docker-publish.yml # Automated GHCR Docker image build & publish
│       └── release-bundle.yml # Release archive generation workflow
│
├── Dockerfile                # Production container specification (PHP 8.4 + Apache)
├── .dockerignore             # Docker build context exclusions
├── .gitignore                # Version control exclusions
├── composer.json             # PHP Composer package definition
├── CHANGELOG.md              # Version and security audit release history
├── SECURITY.md               # Security architecture & vulnerability reporting policy
├── LICENSE                   # MIT License
└── README.md                 # Primary project documentation
```

---

## 🛡️ Security & Hardening Highlights

- 🛡️ **100% Prepared Statements (C-01, C-02):** Parameterized queries across all database operations with zero string concatenation.
- 🔑 **Modern Bcrypt Password Hashing (C-03, C-04):** Passwords hashed using `password_hash()` with auto-rehashing on login.
- 🔐 **RFC 6238 TOTP 2FA & Encrypted Secrets:** High-assurance two-factor authentication with AES-256-GCM secret encryption at rest and client-side canvas QR code generation complying with strict CSP.
- 🛡️ **Cross-Site Request Forgery (CSRF) Tokens (H-03):** Cryptographic anti-CSRF token verified across all state-modifying requests.
- 🚦 **Strict Role Isolation & Live Session Checks (H-01, H-02):** Immediate enforcement of role demotions and deactivations against the database; viewer role restricted to read-only views with PII phone/email masking.
- 🔒 **Transactional Seat Locking:** Concurrency-safe seat reservation using dedicated `seat_lock` table and row-level locks preventing race conditions.
- 🎟️ **Cryptographic PNR Privacy:** Random 10-character hexadecimal tokens replacing sequential IDs, with rate-limited two-factor status lookup.
- 📊 **Streaming CSV Formula Injection Defense:** Prepends `'` to cells beginning with `=`, `+`, `-`, or `@` to neutralize spreadsheet execution exploits.
- 🧼 **XSS Output Sanitization:** Context-aware `e()` HTML escaping on all dynamic data.
- 🐳 **Hardened Container Runtime:** PHP 8.4-apache with HSTS, CSP (`script-src`, `img-src data:`, SRI hashes), X-Frame-Options, and X-Content-Type-Options headers.

---

## 🤝 Contributing

Contributions, issues, and feature requests are welcome! Feel free to check the [issues page](https://github.com/Jashan-randhawa/Bus-Resrvation/issues).

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📄 License

Distributed under the **MIT License**. See [`LICENSE`](LICENSE) for more information.

<div align="center">
  <sub>Crafted with ❤️ by <a href="https://github.com/Jashan-randhawa">Jashan Randhawa</a></sub>
</div>
