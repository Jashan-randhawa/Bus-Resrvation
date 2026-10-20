# 🚌 Welcome to the Bus Reservation System Wiki

The **Bus Reservation System** is a full-stack, cloud-native web platform engineered with **PHP 8.3 / 8.4**, **Apache 2.4**, and **MySQL / TiDB Cloud Serverless**. It delivers an automated ticketing, fleet scheduling, and reservation lifecycle for transport operators and travelers.

This comprehensive technical wiki provides documentation on architecture, database models, operator manuals, cloud DevOps pipelines, security configurations, and container distributions.

---

## 🧭 Wiki Table of Contents

| Section | Description |
|---|---|
| **[🏗️ Architecture & System Design](Architecture-and-System-Design)** | Monolithic web runtime, Docker containerization, dynamic port binding, and TLS/SSL dataflow. |
| **[📱 Progressive Web App & Android TWA](Progressive-Web-App-and-Android-TWA)** | PWABuilder-compliant PWA, Service Worker offline caching, Background Sync, Windows 11 Widgets, and Android TWA APK. |
| **[🗄️ Database Schema & Models](Database-Schema-and-Models)** | Detailed data dictionaries for core tables (`admin`, `costumer`, `buses`, `route`, `booking`, `seat_lock`, `audit_log`, `query`), and referential keys. |
| **[👑 Administrator Guide](Administrator-Guide)** | Operational guide for multi-role RBAC, fleet management, Option 2 mobile card architectures, route scheduling, seat audits, pure-PHP PDF reporting across all modules, analytics, and audit logging. |
| **[🧑‍💼 Customer Portal & Booking Flow](Customer-Portal-and-Booking-Flow)** | Registration, modern profile pill navbar, mobile carousels, interactive seat picker, digital boarding pass, real-time PNR tracker, and dual-factor verification. |
| **[🐳 Deployment & DevOps](Deployment-and-DevOps)** | Production deployment on Render, Docker container specifications, Android APK package, and GHCR package pipelines. |
| **[🛡️ Security & Troubleshooting](Security-Configuration-and-Troubleshooting)** | Environment variables, HMAC boarding pass seals, test suites, SSL connection enforcement, and solutions to common issues. |

---

## ⚡ System At a Glance

```mermaid
flowchart LR
    A["👤 Customer & Mobile App<br/>(PWA / Android TWA / Web)"] -->|HTTPS / Port 443| C["🌐 Apache 2.4 + PHP 8.4<br/>Docker Container & PWA Service Worker"]
    B["👑 Administrator<br/>(RBAC, 2FA & Audit Trail)"] -->|HTTPS / Port 443| C
    C -->|TLS / SSL (Port 4000)| D[("🗄️ TiDB Cloud Serverless<br/>MySQL 8.0 Compatible")]
```

---

## 🛠️ Technology Stack Reference

- **Backend Runtime:** PHP 8.3 / 8.4 with MySQLi extension, Full-Platform Pure-PHP Native PDF 1.4 Engine (manifests, customer accounts, bookings, and audit logs), and Brevo HTTPS REST API / RFC 5321 SMTP Dual Mailer
- **Progressive Web App & Native Android:** Web App Manifest (PWABuilder 100%), Service Worker (`sw.js` with Cache-First static & Network-First dynamic caching), Background Sync, Periodic Sync, Windows 11 Widgets Board Adaptive Cards, and Android Trusted Web Activity (`BusBook-v2.4.0.apk`, Digital Asset Links)
- **Authentication & Security:** RFC 6238 TOTP 2FA, Google OAuth 2.0 / OpenID Connect (PKCE S256), SHA-256 Hashed Password Resets, Bcrypt, HMAC-SHA256 Boarding Pass Seals, and CSRF Protection
- **Web Server:** Apache 2.4 HTTP Server with `mod_rewrite` and `mod_headers`
- **Database Engine:** TiDB Cloud Serverless (MySQL 8.0 compatible wire protocol)
- **Frontend UI & Assets:** HTML5, CSS3 (Custom Design Tokens with High-Fidelity Light Mode & Scoped Emerald Cyber Theme B Dark Mode), JavaScript (ES6), Self-Hosted Bootstrap 4.6.2 & jQuery 3.5.1 (Zero CDN tracking warnings), Chart.js, QRCode.js, Inline SVG Sprite System, Option 2 Mobile Card Architecture, Precision Horizontal Baseline Grid, Cinematic 3D Transit Preloader
- **Containerization:** Docker (Multi-stage build context, non-root web directory, dynamic PORT support)
- **Cloud Hosting:** Render Web Services
- **CI/CD & Registry:** GitHub Actions, GitHub Container Registry (`ghcr.io`), GitHub Packages (npm)
- **Open Source License:** MIT License
