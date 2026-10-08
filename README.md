<div align="center">

# 🚌 Bus Reservation System

### *A Modern, Cloud-Ready Full-Stack Bus Ticket Booking & Fleet Management Platform*

[![Latest Release](https://img.shields.io/badge/Release-v2.3.0-blue?style=for-the-badge&logo=github)](https://github.com/Jashan-randhawa/Bus-Resrvation/releases/tag/v2.3.0)
[![Docker Package](https://img.shields.io/badge/GitHub%20Package-Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation)
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

The **Bus Reservation System** is an end-to-end digital ticketing and fleet operations management platform. Built using **PHP 8.3 / 8.4** and **MySQL / TiDB Cloud Serverless**, it eliminates manual booking workflows with real-time seat availability maps, instant PNR verification, customer account management, and a centralized administrative control center with multi-role RBAC.

Packaged with **Docker** and configured for one-click deployment on **Render**, the application is production-ready, cloud-native, and responsive across all device form factors.

---

## 📚 Technical Wiki & Documentation

Comprehensive architectural, operational, and development documentation is available in the **[Official Technical Wiki](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki)**:

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
- 🔍 **Interactive Seat Picker:** Visual layout dynamically adapting to bus capacity with live booked/available state indicators.
- 🔒 **Atomic Concurrency:** Concurrency-safe seat reservation prevents double-booking race conditions.
- 🎫 **Instant Ticket Generation:** Auto-generated unique cryptographic PNR with travel details, departure timestamps, and pricing.
- 🔎 **Public PNR Lookup:** Check reservation status right from the homepage without needing to log in.
- 📱 **Customer Dashboard:** Manage profiles, view reservation history, and inspect booked trips.
- 💬 **Inquiry Support:** Direct feedback and query submission form for user support.

### 🛡️ Administrator Operations
- 👥 **Multi-Role RBAC & Live Session Checks:** Granular tiers (`super_admin`, `operator`, `viewer`) with live per-request role/status validation directly against the database; viewer write restrictions block unauthorized modifications across all forms.
- 🛡️ **Account Protection & Self-Service:** Self-service profile updates with current password re-authentication (`admin/profile.php`), self-deactivation/deletion guards, and last-active `super_admin` demotion protection.
- 🗑️ **Soft-Delete Archiving & Restoration:** Safe `archived_at` soft-delete lifecycle with Active vs Archived tab views and one-click restoration across fleet buses, routes, and customer accounts.
- 📥 **Streaming CSV Exports:** Memory-efficient CSV downloads with spreadsheet formula injection protection (`=`, `+`, `-`, `@`) for bookings, customers, trip manifests, and audit trails.
- 🔍 **Multi-Field Search & Date Filters:** Parameterized multi-criteria filtering across bookings, customer accounts, and audit events.
- 📜 **Audit Trail & Visual State Diffs:** Comprehensive event logging with visual before/after state diffing and automated 365-day retention cleanup.
- 💬 **Customer Query Inbox & Email Replies:** Support inquiry lifecycle (`new`, `replied`, `closed`), modal email replies with mail dispatch, and a live sidebar unread query counter.
- 📈 **30-Day Trends & Corridor Analytics:** 30-day booking and revenue breakdowns, cancellation rate KPIs, and top 5 transit corridor utilization metrics.
- 🎟️ **Bulk Booking Operations:** Multi-select checkboxes for batch reservation cancellations and selected-row CSV exports.
- 📋 **Passenger Trip Manifest:** Dedicated printable manifest sheet (`admin/manifest.php`) by bus, date, and corridor with print-ready CSS (`@media print`) and conductor/driver sign-off fields.
- 🚌 **Fleet & Bus Management:** Add, inspect, configure custom seating capacities (10–60), and manage layout configurations (`2+2`, `2+1`, `1+2`, `1+1`).
- 🛣️ **Route Management:** Configure origin/destination hubs, bus assignments, departure schedules, and fare pricing with conflict/collision detection.

---

## 🏗️ System Architecture

```mermaid
flowchart TD
    Client["🌐 Web Browser (Responsive UI)"]
    
    subgraph Render["☁️ Render Cloud Platform"]
        Docker["🐳 Docker Container (PHP 8.3/8.4 + Apache)"]
        App["Bus Reservation App<br/>(Public Area, Admin Dashboard, Customer Portal)"]
    end
    
    subgraph Database["🗄️ TiDB Cloud / MySQL Serverless"]
        Tables[("Database Tables<br/>- admin (RBAC roles)<br/>- costumer<br/>- buses<br/>- route (bus_id ref)<br/>- booking (pnr, bus_id, route_id)<br/>- seat_lock (atomic concurrency)<br/>- audit_log (compliance)<br/>- migration_steps<br/>- query")]
    end

    Client -->|HTTPS / Port 443| Docker
    Docker --> App
    App -->|Secure TLS/SSL Connection (Port 4000)| Tables
```

---

## 🛠️ Tech Stack

| Layer | Technologies |
|---|---|
| **Frontend** | HTML5, CSS3, JavaScript (ES6), Bootstrap 4.6, AOS.js Animations, Font Awesome |
| **Backend** | PHP 8.3 / 8.4 (Apache 2.4 runtime), Session Auth, MySQLi with TLS/SSL |
| **Database** | TiDB Cloud (Serverless MySQL-Compatible) / MySQL 8.0+ |
| **DevOps & Cloud** | Docker, Render Web Services, GitHub Actions CI/CD |
| **Registries & Packages** | GitHub Container Registry (`ghcr.io`), Composer Package Manifest |

---

## 📦 Packages & Distribution Artifacts

| Distribution Package | Target / Registry | Description |
|---|---|---|
| **🐳 Docker App Container** | [`ghcr.io/jashan-randhawa/bus-resrvation`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation) | Production app image (`:latest`, `:2.3.0`, `:edge`) |
| **⚡ Docker Migration Runner** | [`ghcr.io/jashan-randhawa/bus-resrvation-migrate`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation-migrate) | One-off CLI database migration runner (`:latest`, `:1.0.0`) |
| **💺 Seat Picker Widget (npm)** | [`@jashan-randhawa/bus-seat-picker`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/npm/bus-seat-picker) | Accessible seat selection widget with live availability polling |
| **🎨 Design Tokens & UI (npm)** | [`@jashan-randhawa/busres-ui`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/npm/busres-ui) | Design tokens, components, and dark/light theme toggle |
| **🗜️ Release Zip Archive** | [`bus-reservation-v2.3.0.zip`](https://github.com/Jashan-randhawa/Bus-Resrvation/releases/download/v2.3.0/bus-reservation-v2.3.0.zip) | Curated distribution bundle via GitHub Actions |
| **📄 Implementation Plan PDF** | [`Bus_Reservation_GitHub_Packages_Plan.pdf`](https://github.com/Jashan-randhawa/Bus-Resrvation/blob/main/docs/Bus_Reservation_GitHub_Packages_Plan.pdf) | GitHub Packages Release Plan (October 2026) |

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

## ⚡ Quick Start & Deployment

### 🐳 Option A: Deploy to Render with TiDB Cloud (Recommended)

1. **Fork or Clone this repository:**
   ```bash
   git clone https://github.com/Jashan-randhawa/Bus-Resrvation.git
   cd Bus-Resrvation
   ```

2. **Set up a free TiDB Cloud instance:**
   - Create a free cluster on [TiDB Cloud](https://tidb.cloud).
   - In the SQL Editor, execute the schema from [`database/init.sql`](./database/init.sql).

3. **Deploy on Render:**
   - Create a new **Web Service** on [Render](https://dashboard.render.com).
   - Select your repository and choose the **Docker** runtime.
   - Configure your environment variables:

| Variable | Description | Example |
|---|---|---|
| `DB_HOST` | Database Hostname | `gateway01.ap-south-1.prod.aws.tidbcloud.com` |
| `DB_PORT` | Database Port | `4000` (or `3306` for MySQL) |
| `DB_USER` | Database Username | `xxxxxx.root` |
| `DB_PASS` | Database Password | `your_secure_password` |
| `DB_NAME` | Database Name | `test` or `majorproject` |
| `DB_SSL` | Enable TLS/SSL Connection | `true` |

---

### 💻 Option B: Run with GitHub Container Package (Fastest)

```bash
# Pull the pre-built image from GitHub Container Registry
# Latest stable release:
docker pull ghcr.io/jashan-randhawa/bus-resrvation:latest

# Or pull a specific release tag:
docker pull ghcr.io/jashan-randhawa/bus-resrvation:2.3.0

# Or pull the cutting-edge build from main:
docker pull ghcr.io/jashan-randhawa/bus-resrvation:edge

# Run the container connecting to TiDB Cloud or local MySQL using env vars from .env.example:
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

> You can also build locally from source with `docker build -t bus-reservation .`.

---

### 🖥️ Option C: Run Locally with XAMPP / WAMP

1. Place the project folder into your web root (e.g., `C:/xampp/htdocs/Bus-Resrvation`).
2. Start **Apache** and **MySQL** from XAMPP Control Panel.
3. Import [`database/init.sql`](./database/init.sql) into **phpMyAdmin**.
4. Access the application at `http://localhost/Bus-Resrvation/homepage.php`.

> **Sub-folder installs:** links and assets use a `BASE_URL` defined in [`includes/config.php`](./includes/config.php). It is auto-detected from the web root, so no setup is needed. To force a value, set the `BASE_URL` environment variable (empty = web root).

---

## 🔐 Initial Administrator Setup

Default credentials are intentionally **not hardcoded** in the schema for security. To provision your first administrator account, execute the CLI script:

```bash
php database/create-admin.php "Admin Name" "you@example.com" "1234567890"
```

You will be prompted for a secure password (minimum 12 characters). Customers can register directly through the public portal.

---

## 📂 Project Structure

```
Bus-Resrvation/
├── index.php                 # Canonical entry point (redirects to homepage.php)
├── homepage.php              # Landing page, login modals & PNR lookup
├── login.php                 # Standalone login form
├── admin.php                 # Backward-compatibility shim (redirects to admin/index.php)
│
├── admin/                    # 👑 Administrator Panel
│   ├── index.php             # Dashboard shell & navigation sidebar
│   ├── dashboard.php         # Real-time metrics, earnings KPIs & 30-day trends
│   ├── buses.php             # Fleet & bus management (capacity, layouts & soft-delete)
│   ├── routes.php            # Route schedules & tariffs (collision checks & soft-delete)
│   ├── customers.php         # Customer directory, search, CSV export & archiving
│   ├── bookings.php          # Booking management, multi-criteria filters & bulk actions
│   ├── seats.php             # Live seat availability monitor & departure filter
│   ├── manifest.php          # Passenger trip manifest (printable view & CSV export)
│   ├── queries.php           # Customer inquiry inbox & email reply workflow
│   ├── audit-log.php         # Immutable audit trail, visual diffs & 365d retention purge
│   ├── add-admin.php         # Administrator provisioning & account governance
│   ├── profile.php           # Admin profile management & password change
│   ├── diagnostics.php       # Operational integrity & security diagnostics
│   └── edit/                 # ✏️ Inline record editing subsystem
│       ├── edit-booking.php
│       ├── edit-bus.php
│       ├── edit-route.php
│       └── edit-customer.php
│
├── user/                     # 🧑‍💼 Customer Portal
│   ├── index.php             # Customer dashboard & live seat availability
│   ├── booking.php           # Reservation engine & dynamic seat map
│   └── my-bookings.php       # Personal booking history & cancellation
│
├── includes/                 # 🔧 Shared Application Kernels
│   ├── config.php            # Dynamic BASE_URL auto-detector & exception handler
│   ├── db_con.php            # Central environment-driven database connector
│   ├── helpers.php           # Prepared statements, create_booking(), CSRF, rate limits, CSV streaming
│   ├── admin-crud.php        # Shared admin CRUD helpers, soft-delete & UI components
│   ├── auth/
│   │   ├── session-bootstrap.php # Hardened session manager & cookie policy
│   │   ├── admin-session.php # Admin session authentication guard & live role checks
│   │   └── user-session.php  # Customer session authentication guard
│   └── layout/
│       ├── header-public.php # Homepage header
│       ├── header-login.php  # Login page header
│       ├── header-admin.php  # Admin sidebar navigation & unread query counter
│       ├── header-user.php   # Customer navigation
│       ├── header-edit.php   # Edit pages header
│       ├── footer.php        # Public & edit footer scripts
│       └── footer-admin.php  # Admin & customer footer scripts
│
├── tests/                    # 🧪 Automated Regression & Concurrency Tests
│   └── run_tests.php         # Test harness (RBAC, soft-deletes, CSV sanitization, manifests)
│
├── assets/                   # 🎨 Static Client Assets
│   ├── css/                  # public.css, home.css, admin.css, edit.css, design-system.css
│   └── images/               # Vector SVGs, icons, and hero photography
│
├── database/                 # 🗄️ Relational Data Layer
│   ├── init.sql              # Idempotent hardened schema DDL
│   ├── db_migrate.php        # Idempotent database schema migration runner
│   ├── create-admin.php      # CLI administrator provisioning script
│   └── migrations/
│       ├── 001_hardening.sql                         # Security schema migration
│       ├── 002_hash_passwords.php                    # Password migration & rehashing script
│       ├── 003_active_seat_unique_key.sql            # Concurrency active seat index
│       ├── 004_bus_layout.sql                        # Fleet layout column
│       ├── 005_referential_integrity_seat_locks.sql  # Foreign keys & cascade constraints
│       ├── 006_audit_logging.sql                     # Compliance audit log table
│       ├── 007_admin_accounts_and_soft_delete.sql    # Soft delete archived_at & admin roles
│       └── 008_query_inbox_enhancements.sql          # Inquiry status, timestamps & reply fields
│
├── docker/                   # 🐳 Container Configuration
│   ├── php-extra.ini         # Hardened PHP production settings
│   └── apache-security.conf  # HTTP security headers (CSP, HSTS, X-Frame-Options)
│
├── docs/                     # 📄 Project Documents & Reports
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
│   ├── dependabot.yml        # Weekly automated dependency maintenance
│   └── workflows/
│       ├── ci.yml            # Automated linting, composer audit, and SQL checks
│       └── docker-publish.yml # Automated GHCR Docker image build & publish
│
├── Dockerfile                # Production container specification (PHP 8.4 + Apache)
├── .dockerignore             # Docker build context filter
├── .gitignore                # Version control exclusions
├── composer.json             # PHP Composer package definition
├── CHANGELOG.md              # Version and security audit release history
├── SECURITY.md               # Security architecture & vulnerability reporting policy
├── LICENSE                   # MIT License
└── README.md                 # Primary project overview
```

---

## 🛡️ Security & Hardening Architecture

- 🛡️ **Prepared Statements Project-Wide (C-01, C-02):** 100% of SQL queries use parameterized prepared statements (`db_one`, `db_all`, `db_exec`) with zero direct string interpolation.
- 🔑 **Cryptographic Password Hashing (C-03, C-04):** Passwords stored using `password_hash()` with modern bcrypt algorithms (`$2y$`). All demo credentials removed.
- 🛡️ **Cross-Site Request Forgery (CSRF) Defense (H-03):** Every state-changing form and action carries a cryptographic anti-CSRF token verified on submission.
- 🚦 **Strict Role Isolation & Live Session Validation (H-01, H-02):** Live per-request verification against the database ensures role demotions or deactivated accounts take immediate effect. Viewer role is restricted to read-only access across all operations. Session ID regeneration on login and 30-minute idle timeouts enforced.
- 🔒 **Transactional Seat Booking & Anti-Double-Booking (H-04, H-08):** Ticket booking runs inside ACID database transactions with row-level locks and unique constraints on `(bus, date, time, seat)`. Authoritative pricing resolved server-side.
- 🎟️ **Cryptographic PNR Privacy & Rate Limiting (C-05, H-05):** Replaced sequential integer IDs with random 10-character hex tokens. PNR lookup requires a second factor (last 4 digits of phone) and is throttled against brute-force scraping.
- 📊 **Streaming CSV Formula Injection Defense:** All exported CSV records prepend single quotes `'` to formulas starting with `=`, `+`, `-`, or `@` to prevent spreadsheet code execution attacks.
- 🗑️ **Soft-Delete Referential Integrity:** Archiving fleet vehicles, routes, or customer accounts is guarded against orphaned records, verifying no active upcoming bookings exist before archiving.
- 🧼 **XSS Output Sanitization (C-06, H-07):** All dynamic outputs are escaped with `e()` HTML escaping.
- 🐳 **Hardened Container Runtime (M-05, M-06):** Upgraded to PHP 8.4-apache with health check, production error logging, and HTTP security headers (HSTS, CSP, X-Frame-Options, X-Content-Type-Options).
- ⚙️ **Automated CI/CD & Auditing (M-08):** Continuous integration checks PHP syntax, composer configurations, and blocks SQL string anti-patterns. Dependabot enabled for weekly maintenance.

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
