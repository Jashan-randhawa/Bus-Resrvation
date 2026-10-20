<div align="center">

<img src="assets/images/logo.png" alt="BusBook Logo" width="280">

# 🚌 BusBook — Bus Reservation System

### *A Modern, Cloud-Ready Full-Stack Bus Ticket Booking & Fleet Management Platform*

[![Latest Release](https://img.shields.io/badge/Release-v2.4.0-blue?style=for-the-badge&logo=github)](https://github.com/Jashan-randhawa/Bus-Resrvation/releases/tag/v2.4.0)
[![PWA Ready](https://img.shields.io/badge/PWA-Installable%20%7C%20Offline-5A0FC8?style=for-the-badge&logo=pwa&logoColor=white)](manifest.json)
[![Android Package](https://img.shields.io/badge/Android%20App-APK%20%7C%20AAB-3DDC84?style=for-the-badge&logo=android&logoColor=white)](packages/app)
[![Docker Package](https://img.shields.io/badge/GitHub%20Package-Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation)
[![npm Packages](https://img.shields.io/badge/GitHub%20Packages-npm-CB3837?style=for-the-badge&logo=npm&logoColor=white)](https://github.com/Jashan-randhawa/Bus-Resrvation/packages)
[![Wiki Docs](https://img.shields.io/badge/Documentation-Wiki-green?style=for-the-badge&logo=gitbook&logoColor=white)](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki)
[![PHP](https://img.shields.io/badge/PHP-8.3%20%7C%208.4-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![TiDB Cloud](https://img.shields.io/badge/TiDB%20Cloud-MySQL%20Compatible-E30C34?style=for-the-badge&logo=mysql&logoColor=white)](https://tidb.cloud/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-4.6-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![License](https://img.shields.io/badge/License-MIT-orange?style=for-the-badge)](LICENSE)

<br/>

[📖 Explore Technical Wiki](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki) • [📦 Download v2.4.0 Release Bundle](https://github.com/Jashan-randhawa/Bus-Resrvation/releases/tag/v2.4.0) • [📱 Android Package (APK/AAB)](packages/app) • [🐳 Pull Docker Image](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation)

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
- 🔍 **Interactive Seat Picker:** Dynamic bus seating map adapting to fleet capacity (10–60 seats) with live available/booked status indicators, fluid CSS `clamp()` seat sizing down to 320px viewports, and a sticky mobile seat summary & confirmation footer.
- 🌐 **"Continue with Google" OAuth 2.0 / OpenID Connect:** Frictionless 1-click Google sign-in and registration (`google-login.php`, `google-callback.php`) featuring PKCE (`S256`), cryptographic state/nonce verification, low-latency IPv4 DNS pre-resolution, non-blocking session release, and automatic display name synchronization.
- 🔐 **Sensitive Action Re-Authentication & Profile Credential Controls:** Fresh Google re-authentication challenge (`google-reauth.php` with `max_age=300`) securing high-risk operations (email modifications, account deactivation) for Google-authenticated users, complemented by explicit credential state tracking (`costumer.pwd_set`), credential status badges, and optional permanent password provisioning (`user/profile.php`).
- 🔑 **Secure Password Reset by Email Link:** Zero-enumeration password recovery (`forgot-password.php`, `reset-password.php`) generating cryptographically secure 64-character tokens stored exclusively as SHA-256 hashes (`password_resets`), featuring 10-minute configurable TTL, IP/address rate limiting, single-use invalidation, and transactional email delivery via Brevo HTTPS REST API (Port 443) or RFC 5321 SMTP.
- 🛫 **Mobile-Optimized Digital Boarding Pass & QR Verification:** Airline-grade boarding pass card (`user/ticket.php`) with fluid typography (`clamp()`), authentic perforated stub styling, 1-tap PNR copy to clipboard with floating toast feedback, and an adaptive high-contrast QR code (120px on mobile for fast conductor camera scanning). Includes public verification (`user/verify.php`) and Web Share API (`navigator.share`) integration.
- ✉️ **Emailed Boarding Pass Button with Cryptographic Seal:** Confirmation emails deliver a 1-click "View & Print Boarding Pass" button built with fully qualified public URLs via `get_app_url()` and protected by a 12-character HMAC-SHA256 ticket signature (`&sig=...`), allowing passengers to open, view, and print their pass directly from any mobile mail app without requiring prior login.
- 👤 **Modern User Profile Pill & Dropdown Navbar:** Clean, compact passenger account menu (`header-public.php`, `home.css`) replacing cluttered navbar buttons with a unified Profile Pill, gradient avatar circle with initials, fast links to Dashboard, My Bookings, and Account Settings, isolated CSRF Sign Out, and zero text wrapping (`white-space: nowrap;`).
- 🦶 **Modern 4-Column Responsive Web Footer & Dynamic PWA Bottom Navigation:** Architectural separation between desktop web utility (`includes/footer.php` with dark-slate `#0f172a` design system, brand tagline, dynamic PWA `#installPwaBtn`, session-aware quick links, popular route corridors, 24/7 support helpline, and security badges) and standalone mobile app navigation (`includes/bottom-nav.php` with 4-tab native-style fixed bar for Home, Search, My Tickets, and Profile). Powered by `assets/css/footer.css` with `@media all and (display-mode: standalone)` automatic swaps and iPhone safe-area padding.
- 🎠 **Touch-First Mobile Carousels & 5-Second Autoplay:** Hardware-accelerated swipeable carousels for Features and Popular Routes on mobile viewports with native CSS scroll-snap (`scroll-snap-type: x mandatory`), 44px touch arrows, active dot indicators, and synchronized 5-second auto-scroll with pause-on-touch interaction.
- 🧭 **Custom Search Dropdowns & Interactive City Swap:** Accessible in-DOM custom dropdown menus (`.custom-dropdown-widget`) with Title Case city formatting, dynamic connected-route destination filtering, and 1-click circular origin/destination swap controls (`⇄`) across the homepage and user portal.
- 🔒 **Atomic Concurrency:** Concurrency-safe seat reservation engine with database transactions and row-level locks preventing double-booking race conditions.
- 🎫 **Instant Cryptographic PNR:** Auto-generated unique random 10-character token with departure details, seat assignment, and pricing.
- 🔎 **Real-Time PNR Tracker & Dual-Factor Verification (`#pnr`, `pnr.php`):** Dedicated ticket lookup terminal engineered from custom specification featuring an eyebrow status pill with pulsing animation, privacy/verification badges, and smart dual-factor inputs (10-character hex pattern with live counter badge, 4-digit phone PIN, auto-uppercase, and auto-advance). Renders an airline-grade Option 2 Boarding Pass ticket card preview with route corridor hero, transit bus badge, perforated stub divider with cutout notches, passenger/seat badge, total fare, and 1-click clipboard copy with toast feedback. Protected by rate limiting and includes interactive troubleshooting assistance cards (*Rate Limit Throttled*, *Invalid Format*, *No Matching Reservation*).
- 📐 **Unified Horizontal Baseline Grid:** Precision CSS Grid input row on the PNR lookup terminal locking inputs and the "Search Ticket" button to a unified horizontal baseline across responsive desktop and split tablet viewports without misalignments.
- 📲 **Progressive Web App (PWA) & Offline Resilience:** Fully installable PWA with Service Worker (`sw.js`) implementing `sbtbs-bus-cache-v1` with non-GET mutation protection, offline fallback page (`offline.html`), Web App Manifest (`manifest.json`) with dual quick-actions (`Book Tickets`, `Admin Dashboard`), and path-aware layout head (`assets/partials/_pwa-head.php`). Features Web Push notification listeners, background sync for deferred inquiries, Windows 11 Widgets Board integration, and native Android Trusted Web Activity packaging.
- 📱 **Mobile-First Customer Portal:** Profile & account security settings (`user/profile.php` with password rotation, email change, and account deactivation), 3-column reservation status filter tabs (`user/my-bookings.php`), and self-service cancellation with cutoff window safeguards.
- 💬 **Inquiry Support:** Direct feedback and query submission form for passenger support.

### 🛡️ Administrator Operations
- 👥 **Multi-Role RBAC & Live Session Validation:** Granular tiers (`super_admin`, `operator`, `viewer`) with live per-request role and active status validation against the database. Viewer role is restricted to read-only views with PII masking.
- 🔐 **RFC 6238 TOTP Two-Factor Authentication:** Multi-factor authentication for administrative accounts with client-side HTML5 canvas QR code generation, AES-256-GCM encrypted secrets, 10 single-use recovery codes, and configurable policy enforcement (`ADMIN_MFA_ENFORCE`). Note: Google SSO is strictly customer-scoped; admin access remains password- and TOTP-isolated.
- 🛠️ **CLI Break-Glass Recovery:** Dedicated emergency command-line utilities for administrator creation (`create-admin.php`) and 2FA recovery resets (`reset-mfa.php`).
- 🚨 **Attention-Needed Action Center & Today's Departures:** Real-time priority-ranked operational alert strip (`Critical`, `High Priority`, `Attention`) and Today's Departures compact schedule cards with live seat occupancy progress bars and departure countdown chips (`admin/dashboard.php`).
- 📈 **Privacy-First Performance Overview & Analytics Workspace:** 4 KPI metric cards with period-over-period delta badges, interactive Chart.js dual-axis Booking & Revenue Trends combo chart (`Daily` / `Weekly` toggle), Booking Status donut breakdown, Key Insights strip, and strict PII-free server-side aggregation (`admin/dashboard.php`, `assets/js/perf-chart.js`).
- 📄 **Full-Platform Pure-PHP Native PDF 1.4 Reporting Engine:** Zero-dependency, memory-efficient vector PDF 1.4 generator (`generate_manifest_pdf_doc()`) built with pure PHP without external binaries (wkhtmltopdf/dompdf). Generates downloadable, print-ready branded documents with conductor/driver sign-off blocks and pagination across:
  - **Passenger Trip Manifest** (`admin/manifest.php?export=pdf`) with passenger roll call, seat allocations, and mobile download card (`.manifest-mobile-card`).
  - **Customer Directory** (`admin/customers.php?export=pdf`) with registered user profiles, security status, and contact details.
  - **Bookings & Reservations** (`admin/bookings.php?export=pdf`) with corridor routes, seat allocations, payment status, and tariffs.
  - **Compliance Audit Trail** (`admin/audit-log.php?export=pdf`) with IP addresses, timestamps, and state diffs.
  Complemented by streaming CSV downloads with formula injection defense (`=`, `+`, `-`, `@`).
- 📱 **Option 2 Mobile Card Architecture for Admin Tables (≤ 768px):** Eliminates cramped, horizontally overflowing tables on mobile viewports by transforming rows into specialized elevated cards with 24px vertical separation and borderless wrapper:
  - **Active Fleet Vehicles Table** (`admin/buses.php`): Authentic Indian HSRP Commercial License Plate Hero card with `[IND]` tag, bold yellow license plate, ID bar, and 2-column capacity & layout footer. Enforced desktop table integrity (`@media (min-width: 769px)`) ensuring standard table rows with zero CSS collisions.
  - **Bookings Table** (`admin/bookings.php`): Airline-style Boarding Pass card with status-colored left accents (Confirmed emerald, Pending amber, Cancelled rose), PNR badge, route corridor, bus details, passenger stub, total fare, and touch-friendly actions.
  - **Customer Queries Table** (`admin/queries.php`): Support Ticket card architecture with status accents, sender profile block, line-clamped inquiry message preview, and modal email response workflow. Clean Light-themed modal header with high-contrast text and subtle divider.
  - **Customer Accounts Table** (`admin/customers.php`): Passenger Profile card with status badges, contact details, email links, and administrative actions.
  - **Route Schedules** (`admin/routes.php`): Emerald-accented mobile route cards with corridor hero, stats strip, and 44px touch targets.
- 🛣️ **Modernized Transit Route Schedule Management:** 4 operational KPI cards, smart multi-criteria filter toolbar (search, status pills, sort order), visual route corridor cards, 1-click manifest links, bidirectional city swap controls, and schedule collision detection (`admin/routes.php`).
- 🚌 **Fleet & Bus Management:** Configure custom seating capacities (10–60) and seating layout geometries (`2+2`, `2+1`, `1+2`, `1+1`).
- 🗑️ **Soft-Delete Archiving & Restoration:** Safe `archived_at` lifecycle with Active vs Archived tab views and one-click restoration across fleet buses, routes, and customer accounts.
- 📥 **Streaming CSV Exports:** Memory-efficient CSV downloads with spreadsheet formula injection protection (`=`, `+`, `-`, `@`) for bookings, customers, trip manifests, and audit trails.
- 🔍 **Multi-Field Search & Date Filters:** Parameterized multi-criteria filtering across bookings, customer accounts, and audit events.
- 📜 **Immutable Audit Trail:** Append-only event logging with visual before/after state diffing, database trigger protection, and automated retention cleanup.
- 💬 **Customer Query Inbox & Modal Email Reply Workflow:** Support inquiry lifecycle (`new`, `replied`, `closed`), multi-line inquiry message preview clamping with "Read More →" modal inspection, clean Light-themed modal dialog with high-contrast headers, smooth 1-click transition to email reply modal (`#singleReplyModal`), and RFC 5321 SMTP email delivery.
- 🎟️ **Bulk Booking Operations:** Multi-select checkboxes for batch reservation cancellations and selected-row CSV exports.
- 🎨 **High-Fidelity Light Mode & Emerald Cyber (Theme B) Dark Mode System:** Unified design token architecture (`assets/css/design-system.css`, `assets/css/theme-emerald.css`) delivering a crisp pure-white Light Mode surface (`#ffffff`) and a cybernetic Dark Mode (`data-theme="dark"` / `body.dark-mode`) featuring Deep Obsidian canvas (`#050C0F`), Dark Pine surfaces (`#081216`), Elevated Teal accents (`#0F222A`), Mint Emerald primary CTA (`#10B981`), and Sky Cyan interactive highlights (`#06B6D4`). Enforces strict CSS scoping to ensure dark mode tokens never pollute the crisp light theme, complete with a custom SVG vector icon sprite system, high-definition bus brand emblem (`assets/images/bus.svg`), glassmorphic sticky topbar with semantic section eyebrow pills, automatic CSS cache-busting, and an accessible touch-first mobile navigation drawer (`<992px`).
- ⚡ **Animated BusBook Splash & Loading Experience:** Dedicated standalone loading page (`loading.html`) and responsive app preloader overlay (`assets/partials/_preloader.php`) built around the luminous BusBook speed-ticket logo, featuring perspective road travel animations, gradient progress indicator, journey status updates, and auto-dismiss logic.

---

## 🏗️ System Architecture

```mermaid
flowchart TD
    Client["🌐 Web Browser (Responsive UI)"]
    GoogleAuth["🔑 Google Identity Provider<br/>(OAuth 2.0 & OpenID Connect)"]
    EmailGateway["✉️ Brevo REST API / SMTP Relay<br/>(Password Resets & Notifications)"]
    
    subgraph Render["☁️ Render Cloud Platform"]
        Docker["🐳 Docker Container (PHP 8.4 + Apache 2.4)"]
        Entrypoint["🚀 entrypoint.sh (Auto Migrations)"]
        App["Bus Reservation App<br/>(Public Area, Admin Dashboard, Customer Portal)"]
        Entrypoint --> App
        Docker --> Entrypoint
    end
    
    subgraph Database["🗄️ TiDB Cloud Serverless / MySQL 8.0+"]
        Tables[("Database Layer<br/>- admin (RBAC & TOTP MFA)<br/>- costumer (Accounts, google_id & pwd_set)<br/>- buses (Capacity & Layouts)<br/>- route (Schedules & Tariffs)<br/>- booking (PNR & Concurrency Keys)<br/>- seat_lock (Atomic Holds & Claims)<br/>- password_resets (Hashed Tokens)<br/>- audit_log (Immutable Compliance)<br/>- admin_recovery_codes (Backup TOTP)<br/>- schema_migrations & migration_steps<br/>- query (Support Inbox)")]
    end

    Client -->|HTTPS / TLS 1.3 (Port 443)| Docker
    Docker <-->|OAuth 2.0 / PKCE (Port 443)| GoogleAuth
    Docker -->|Transactional Emails (Port 443 / 587)| EmailGateway
    App -->|Secure TLS/SSL Connection (Port 4000/3306)| Tables
```

---

## 🛠️ Tech Stack

| Layer | Technologies |
|---|---|
| **Frontend** | HTML5, CSS3 (Custom Design Tokens, Light Mode & Emerald Cyber Dark Theme), JavaScript (ES6), Bootstrap 4.6, Chart.js, Font Awesome, QRCode.js, Inline SVG Sprite System, Progressive Web App (Service Worker, Web App Manifest) |
| **Backend** | PHP 8.3 / 8.4 (Apache 2.4 runtime), Session Auth, Pure-PHP Native PDF 1.4 Engine, Brevo REST API / SMTP Mailer, MySQLi with TLS/SSL |
| **Database** | TiDB Cloud Serverless (MySQL Compatible) / MySQL 8.0+ |
| **Security & Auth** | RFC 6238 TOTP 2FA, Google OAuth 2.0 / OpenID Connect (PKCE S256), AES-256-GCM, Bcrypt Hashing, Anti-CSRF, Enforced CSP, Rate Limiting |
| **DevOps & Cloud** | Docker, Render Web Services, GitHub Actions CI/CD, GHCR Container Registry |
| **Registries & Packages** | GitHub Container Registry (`ghcr.io`), GitHub Packages (npm), Composer, Android TWA (APK/AAB) |

---

## 📦 Packages & Distribution Artifacts

| Distribution Package | Target / Registry | Description |
|---|---|---|
| **🐳 Docker App Container** | [`ghcr.io/jashan-randhawa/bus-resrvation`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation) | Production app image (`:latest`, `:2.4.0`, `:edge`) |
| **⚡ Docker Migration Runner** | [`ghcr.io/jashan-randhawa/bus-resrvation-migrate`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation-migrate) | One-off CLI database migration runner (`:latest`, `:1.0.0`) |
| **💺 Seat Picker Widget (npm)** | [`@jashan-randhawa/bus-seat-picker`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/npm/bus-seat-picker) | Accessible seat selection widget with live availability polling |
| **🎨 Design Tokens & UI (npm)** | [`@jashan-randhawa/busres-ui`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/npm/busres-ui) | Design tokens, components, and dark/light theme toggle |
| **📱 Android Native App (TWA)** | [`packages/app`](packages/app) | Native Android APK (~999 KB) & Play Store AAB Bundle (~1.08 MB) |
| **🗜️ Release Zip Archive** | [`bus-reservation-v2.4.0.zip`](https://github.com/Jashan-randhawa/Bus-Resrvation/releases/download/v2.4.0/bus-reservation-v2.4.0.zip) | Curated distribution bundle via GitHub Actions |
| **📄 Implementation Plan PDF** | [`Bus_Reservation_GitHub_Packages_Plan.pdf`](https://github.com/Jashan-randhawa/Bus-Resrvation/blob/main/docs/Bus_Reservation_GitHub_Packages_Plan.pdf) | GitHub Packages Release Plan |

### 📱 Android Native Package (Trusted Web Activity)

The application is packaged as a production native Android package via Trusted Web Activity (TWA) under package name `com.onrender.bus_resrvation.twa`:

- **Direct Android Sideload (`.apk`)**: Download [`packages/app/BusBooking.apk`](packages/app/BusBooking.apk) and install directly or run:
  ```bash
  adb install -r packages/app/BusBooking.apk
  ```
- **Google Play Store Publishing (`.aab`)**: Production Android App Bundle [`packages/app/BusBooking.aab`](packages/app/BusBooking.aab) signed with production release keystore [`packages/app/signing.keystore`](packages/app/signing.keystore). Keystore credentials are documented in [`packages/app/signing-key-info.txt`](packages/app/signing-key-info.txt).
- **Digital Asset Links Verification**: Hosted live at [`/.well-known/assetlinks.json`](.well-known/assetlinks.json), establishing cryptographic domain ownership and unlocking full-screen standalone Android execution without URL bars.

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
| `BREVO_API_KEY` | *(empty)* | Brevo HTTPS REST API Key for cloud transactional emails (port 443) | `xkeysib-your_brevo_api_key` |
| `EMAIL_FROM` | *(empty)* | Brevo sender identity formatted as `"Name <email>"` | `"Bus Reservation System <reservations@example.com>"` |
| `MAIL_ENABLED` | `false` | Enable outbound RFC 5321 SMTP email delivery | `true` |
| `MAIL_HOST` | *(empty)* | SMTP Relay Host | `smtp.example.com` |
| `MAIL_PORT` | `587` | SMTP Relay Port | `587` |
| `MAIL_USER` | *(empty)* | SMTP Username / API Key | `apikey` |
| `MAIL_PASS` | *(empty)* | SMTP Password / Secret Key | `your_smtp_secret` |
| `MAIL_FROM` | *(empty)* | Outbound From Address | `reservations@example.com` |
| `MAIL_FROM_NAME` | `"Bus Reservation System"` | Outbound Sender Name | `"Bus Reservation System"` |
| `APP_URL` | *(auto via Render)* | Trusted public base URL for password resets & OAuth callbacks | `https://bus-resrvation.onrender.com` |
| `PASSWORD_RESET_TTL_MIN` | `10` | Password reset link lifetime in minutes (`5` to `60`) | `10` |
| `GOOGLE_CLIENT_ID` | *(empty)* | Google Cloud OAuth 2.0 Web Client ID | `xxxxxx.apps.googleusercontent.com` |
| `GOOGLE_CLIENT_SECRET` | *(empty)* | Google Cloud OAuth 2.0 Client Secret | `GOCSPX-xxxxxx` |
| `GOOGLE_REDIRECT_URI` | *(auto)* | Explicit OAuth callback URI (defaults to `<APP_URL>/google-callback.php`) | `https://bus-resrvation.onrender.com/google-callback.php` |

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

The codebase includes comprehensive test harnesses covering authentication, concurrency, RBAC, input sanitization, UI responsiveness, and regression checks:

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

# Admin mobile drawer, topbar eyebrow pills, Option 2 card architectures & desktop table integrity (60 assertions)
php tests/test_admin_layout.php

# Customer portal mobile responsiveness, custom dropdowns & sticky booking bar
php tests/test_user_layout.php

# Modernized route schedule UI, KPI cards, smart filters & swap controls
php tests/test_route_ui.php

# Pure-PHP native PDF manifest generator & mobile manifest card verification
php tests/test_pdf_manifest.php

# Today's Departures compact schedule cards, occupancy bars & status chips
php tests/test_departure_carousel.php

# Privacy-first Performance Overview analytics & dashboard audit verification
php tests/test_perf_overview.php
php tests/test_dashboard_audit.php

# Email password reset link flow, SHA-256 token hashing & anti-enumeration suite (39 assertions)
php tests/test_password_reset.php

# Google OAuth 2.0, OpenID Connect, PKCE, re-auth, session sync & profile guards (120 assertions)
php tests/test_google_auth.php

# Real-Time PNR Tracker, dual-factor inputs, auto-advance & boarding pass card suite (33 assertions)
php tests/test_pnr_section.php

# Customer inquiries message clamping, modal triggers & email reply transitions (20 assertions)
php tests/test_queries_modal.php

# Emerald Cyber (Theme B) design system tokens, components & dark layout integration (49 assertions)
php tests/test_theme_emerald.php

# Light theme fidelity & strict Theme B dark mode isolation (22 assertions)
php tests/test_light_theme_fidelity.php

# Dark mode elevation, card contrast & surface tokens (30 assertions)
php tests/test_dark_mode_elevation.php

# Modern 4-column responsive web footer, PWA bottom nav & admin utility bar (36 assertions)
php tests/test_modern_footer.php

# Mobile features & routes touch swipe carousels & 5-second auto-scroll (28 assertions)
php tests/test_features_carousel.php

# Standalone loading page & brand preloader overlay test suite (26 assertions)
php tests/test_loading_page.php

# PWABuilder web app manifest compliance test suite (30 assertions)
php tests/test_pwabuilder_manifest.php

# Boarding pass email button & HMAC signature authorization test suite
php tests/test_email_ticket_link.php
```

---

## 📂 Project Structure

```
Bus-Reservation/
├── index.php                 # Canonical entry point (redirects to homepage.php)
├── homepage.php              # Public landing page, login modal, Google sign-in & PNR lookup
├── login.php                 # Standalone customer login endpoint (password & Google SSO)
├── forgot-password.php       # Customer password reset link request endpoint
├── reset-password.php        # Single-use SHA-256 hashed token password reset completion
├── google-login.php          # Google OAuth 2.0 / OpenID Connect authorization redirector
├── google-callback.php       # Google OAuth 2.0 PKCE token exchange & customer login handler
├── google-reauth.php         # Fresh Google re-authentication challenge for sensitive actions
├── pnr.php                   # Standalone PNR lookup endpoint (Web Share & protocol handler)
├── loading.html              # Standalone animated splash & loading screen
├── offline.html              # Offline fallback screen for Service Worker
├── manifest.json             # Progressive Web App (PWA) manifest
├── sw.js                     # Root Service Worker (sbtbs-bus-cache-v1)
├── .env.example              # Reference environment configuration template
├── admin.php                 # Backward-compatibility shim (redirects to admin/index.php)
│
├── admin/                    # 👑 Administrator Management Subsystem
│   ├── index.php             # Dashboard entry point
│   ├── dashboard.php         # Real-time KPIs, Attention Center, Today's Departures & Analytics
│   ├── buses.php             # Fleet management (capacity 10–60, layouts & soft-delete)
│   ├── routes.php            # Route schedules, KPI cards, smart filters & collision checks
│   ├── customers.php         # Customer directory, search, CSV export & archiving
│   ├── bookings.php          # Booking management, multi-criteria filters & bulk actions
│   ├── seats.php             # Live seat occupancy monitor with departure filters
│   ├── manifest.php          # Passenger trip manifest (PDF generator, mobile card, CSV & print)
│   ├── queries.php           # Customer inquiry inbox & modal email reply workflow
│   ├── audit-log.php         # Immutable audit trail, visual diffs & compliance logs
│   ├── add-admin.php         # Administrator provisioning & account governance
│   ├── profile.php           # Profile management, password rotation & TOTP 2FA setup
│   ├── mfa.php               # Two-Factor Authentication login challenge controller
│   ├── diagnostics.php       # Operational integrity & security diagnostics
│   ├── includes/             # 🛠️ Admin View Partials
│   │   └── footer.php        # Minimalist admin utility bar (version, server status, copyright)
│   └── edit/                 # ✏️ Inline Record Editing Subsystem
│       ├── edit-booking.php
│       ├── edit-bus.php
│       ├── edit-route.php
│       └── edit-customer.php
│
├── user/                     # 🧑‍💼 Customer Passenger Portal
│   ├── index.php             # Customer dashboard, upcoming journey card & custom city dropdowns
│   ├── booking.php           # Reservation engine, fluid seat map & sticky mobile summary bar
│   ├── my-bookings.php       # Personal booking history, status tabs & cancellation
│   ├── profile.php           # Account security, Google re-auth, credential badges & deactivation
│   ├── ticket.php            # Digital boarding pass, QR code & Native Web Share integration
│   └── verify.php            # Public QR boarding pass verification endpoint
│
├── includes/                 # 🔧 Core Framework & Security Libraries
│   ├── config.php            # Dynamic BASE_URL detector, global error handling & env defaults
│   ├── db_con.php            # Environment-driven database connector with TLS/SSL support
│   ├── helpers.php           # Prepared statements, create_booking(), PDF generator, CSRF, CSV streaming
│   ├── footer.php            # Modern 4-column responsive web footer & PWA install action
│   ├── bottom-nav.php        # Native-style fixed bottom navigation bar (standalone PWA & mobile)
│   ├── google-auth.php       # Google OAuth 2.0/OIDC engine, PKCE, token verification & re-auth
│   ├── password-reset.php    # Email reset-link flow, SHA-256 token hashing & anti-enumeration engine
│   ├── smtp.php              # Dual-transport email delivery (Brevo HTTPS REST API & RFC 5321 SMTP)
│   ├── admin-crud.php        # Shared admin CRUD helpers, soft-delete & UI components
│   ├── auth/
│   │   ├── session-bootstrap.php # Hardened session manager & secure cookie policy
│   │   ├── admin-session.php # Admin session authentication guard & live role checks
│   │   ├── user-session.php  # Customer session authentication guard
│   │   └── totp.php          # RFC 6238 TOTP engine, AES-256-GCM encryption & recovery codes
│   └── layout/
│       ├── header-public.php # Homepage header
│       ├── header-login.php  # Login page header
│       ├── header-admin.php  # Admin sidebar, SVG sprite system, topbar & cache-busting
│       ├── header-user.php   # Customer sidebar, SVG sprite system, topbar & cache-busting
│       ├── header-edit.php   # Edit pages header
│       ├── google-button.php # Accessible "Continue with Google" button component
│       ├── footer.php        # Public & edit footer scripts
│       ├── footer-public.php # Backward-compatible public footer proxy
│       ├── footer-admin.php  # Admin footer scripts & drawer controller
│       └── footer-user.php   # Customer footer scripts & idle timeout integration
│
├── assets/                   # 🎨 Stylesheets, Scripts & Vector Brand Assets
│   ├── css/
│   │   ├── design-system.css # Global Light (White) & Dark (Slate) design tokens
│   │   ├── footer.css        # Scoped 4-column footer, dark-slate tokens & PWA media queries
│   │   ├── admin.css         # Admin workspace, topbar, sidebar & table styles
│   │   ├── admin-mobile-responsive.css # Responsive mobile drawer & card adaptations
│   │   ├── dashboard.css     # Attention Center, Today's Departures & Analytics styles
│   │   ├── user.css          # Customer portal, boarding pass & custom dropdown styles
│   │   ├── seat-map.css      # Fluid seat map grid & sticky mobile booking bar
│   │   ├── home.css          # Public landing page styles
│   │   ├── theme-emerald.css # Emerald Cyber (Theme B) Dark Mode design system
│   │   └── preloader.css     # Brand splash preloader stylesheet
│   ├── js/
│   │   ├── admin-mobile.js   # Accessible off-canvas drawer & swipe gesture controller
│   │   ├── perf-chart.js     # Chart.js dual-axis booking/revenue & status donut charts
│   │   ├── theme-toggle.js   # Persistent Light/Dark theme switcher
│   │   ├── user-idle.js      # Customer session idle warning & auto-logout monitor
│   │   └── preloader.js      # Brand preloader controller API (window.BusBookLoader)
│   └── images/
│       ├── logo.png          # High-resolution BusBook brand master logo
│       ├── bus-emblem.png    # Transparent speed-ticket transit coach emblem
│       └── bus.svg           # Scalable vector bus brand emblem
│   └── partials/
│       ├── _preloader.php    # Path-aware brand preloader overlay partial
│       └── _pwa-head.php     # Path-aware PWA head tags & Service Worker register
│
├── database/                 # 🗄️ Relational Schema & Migration Engine
│   ├── init.sql              # Idempotent hardened schema DDL
│   ├── db_migrate.php        # Robust database migration runner with advisory locking
│   ├── create-admin.php      # CLI administrator provisioning script
│   ├── reset-mfa.php         # CLI emergency break-glass 2FA reset script
│   ├── expire-holds.php      # CLI expired seat hold release utility
│   ├── purge-audit-log.php   # CLI audit log retention cleanup utility
│   └── migrations/           # 🔄 Sequential Schema Migrations (001 to 013)
│       ├── 001_hardening.sql                         # Base hardening & indexes
│       ├── 002_hash_passwords.php                    # Password migration & rehashing
│       ├── 003_bus_capacity.sql                      # Fleet bus capacity bounds
│       ├── 004_seat_hold_and_payment_states.sql      # Seat holds & payment states
│       ├── 005_referential_integrity_seat_locks.sql  # Referential integrity & seat_lock table
│       ├── 006_audit_logging.sql                     # Compliance audit log table
│       ├── 007_admin_accounts_and_soft_delete.sql    # Soft delete archived_at columns
│       ├── 008_query_inbox_enhancements.sql          # Inquiry status & reply tracking
│       ├── 009_admin_accounts_and_soft_delete.sql    # Admin security columns & recovery codes
│       ├── 010_query_inbox_enhancements.sql          # Inbox timestamps & enhancements
│       ├── 011_password_reset_hashed_tokens.sql      # SHA-256 hashed password reset tokens
│       ├── 012_customer_google_signin.sql            # Customer google_id unique identifier
│       └── 013_google_pwd_set.sql                    # Customer pwd_set credential state flag
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
│   ├── test_phase3_pwa.php   # Dynamic PWA root resolution, service worker & print guard test suite
│   ├── test_phase4.php       # Phase 4: Hygiene & hardening test suite
│   ├── test_google_auth.php  # Google OAuth 2.0, OpenID Connect & re-auth test suite
│   ├── test_admin_layout.php # Admin mobile drawer, Option 2 card architectures & desktop table integrity
│   ├── test_pnr_section.php  # Real-Time PNR Tracker, dual-factor inputs & boarding pass card suite
│   ├── test_queries_modal.php # Customer inquiries message clamping & descriptive modal dialog suite
│   ├── test_theme_emerald.php # Emerald Cyber (Theme B) design system tokens & layout integration
│   ├── test_light_theme_fidelity.php # Light theme fidelity & strict Theme B dark mode isolation
│   ├── test_dark_mode_elevation.php # Dark mode elevation, card contrast & surface tokens
│   ├── test_user_layout.php  # Customer portal mobile & custom dropdown test suite
│   ├── test_email_ticket_link.php # Boarding pass email button & HMAC signature authorization test suite
│   ├── test_features_carousel.php # Mobile features & routes swipe carousels & 5s auto-scroll test suite
│   ├── test_modern_footer.php # Modern 4-column footer, PWA bottom nav & admin utility bar test suite
│   ├── test_loading_page.php # Standalone loading page & preloader overlay test suite
│   ├── test_pwabuilder_manifest.php # PWABuilder web app manifest compliance test suite
│   ├── test_route_ui.php     # Route schedule KPI, filter & corridor UI test suite
│   ├── test_pdf_manifest.php # Native PDF manifest generator & mobile view test suite
│   ├── test_departure_carousel.php # Today's Departures schedule list test suite
│   ├── test_perf_overview.php # Performance Overview analytics test suite
│   ├── test_dashboard_audit.php # Dashboard operational audit test suite
│   ├── test_password_reset.php # Email reset-link flow & SHA-256 token hashing test suite
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
- 🔐 **RFC 6238 TOTP 2FA & Encrypted Secrets:** High-assurance two-factor authentication for administrative accounts with AES-256-GCM secret encryption at rest, 10 single-use recovery codes, and client-side canvas QR code generation complying with strict CSP.
- 🌐 **Customer-Scoped Google OAuth 2.0 & OpenID Connect:** Server-side authorization-code exchange with RFC 7636 PKCE (`S256`), cryptographic CSRF state validation, and cryptographic nonce verification. Scoped strictly to customers (`costumer` table) to preserve absolute isolation from administrative credentials. Features automatic pre-hijack defense (invalidating legacy unverified passwords on linking) and Google re-authentication (`max_age=300`) verification for high-risk account modifications.
- ✉️ **Zero-Enumeration Password Recovery & Resilient Dual Mail Transport:** Cryptographically secure 64-character reset tokens stored strictly as SHA-256 hashes with 10-minute expiry and uniform timing responses. Delivered via Brevo HTTPS REST API (Port 443) to guarantee deliverability in cloud environments restricting SMTP port 587/25, with automatic fallback to standard RFC 5321 SMTP.
- 🛡️ **Cross-Site Request Forgery (CSRF) Tokens (H-03):** Cryptographic anti-CSRF token verified across all state-modifying requests.
- 🚦 **Strict Role Isolation & Live Session Checks (H-01, H-02):** Immediate enforcement of role demotions and deactivations against the database; viewer role restricted to read-only views with PII phone/email masking.
- 🔒 **Transactional Seat Locking:** Concurrency-safe seat reservation using dedicated `seat_lock` table and row-level locks preventing race conditions.
- 🎟️ **Cryptographic PNR Privacy & HMAC-SHA256 Boarding Pass Seals:** Random 10-character hexadecimal tokens replacing sequential IDs, with rate-limited two-factor status lookup and tamper-proof 12-character HMAC-SHA256 digital seals securing direct mobile email boarding pass access without authentication bottlenecks.
- 📊 **Streaming CSV Formula Injection Defense:** Prepends `'` to cells beginning with `=`, `+`, `-`, or `@` to neutralize spreadsheet execution exploits.
- 📄 **Pure-PHP Vector PDF Security & Memory Safety:** Manifests and administrative exports are compiled in-memory via pure-PHP PDF primitives (`generate_manifest_pdf_doc()`) without invoking external system binaries or shell wrappers, eliminating command injection and remote code execution vulnerabilities commonly associated with headless browser or Ghostscript pipelines.
- 🎨 **Strict Theme Boundary Isolation:** CSS architecture guarantees clean isolation between high-fidelity Light Mode and Emerald Cyber (Theme B) Dark Mode, preventing theme leakage, layout shifts, or selector collisions across desktop and mobile cards.
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
