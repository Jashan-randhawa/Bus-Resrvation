# 🚌 Welcome to the Bus Reservation System Wiki

The **Bus Reservation System** is a full-stack, cloud-native web platform engineered with **PHP 8.1**, **Apache 2.4**, and **MySQL / TiDB Cloud Serverless**. It delivers an automated ticketing, fleet scheduling, and reservation lifecycle for transport operators and travelers.

This comprehensive technical wiki provides documentation on architecture, database models, operator manuals, cloud DevOps pipelines, security configurations, and container distributions.

---

## 🧭 Wiki Table of Contents

| Section | Description |
|---|---|
| **[🏗️ Architecture & System Design](Architecture-and-System-Design)** | Monolithic web runtime, Docker containerization, dynamic port binding, and TLS/SSL dataflow. |
| **[🗄️ Database Schema & Models](Database-Schema-and-Models)** | Detailed data dictionaries for all 6 tables, primary/foreign relationships, and seed accounts. |
| **[👑 Administrator Guide](Administrator-Guide)** | Operational guide for fleet management, route scheduling, seat audits, and analytics. |
| **[🧑‍💼 Customer Portal & Booking Flow](Customer-Portal-and-Booking-Flow)** | Registration, interactive 36-seat visual map picker, instant PNR generation, and lookup engine. |
| **[🐳 Deployment & DevOps](Deployment-and-DevOps)** | Production deployment on Render, Docker container specifications, and GHCR package pipelines. |
| **[🛡️ Security & Troubleshooting](Security-Configuration-and-Troubleshooting)** | Environment variables, output buffering, SSL connection enforcement, and solutions to common issues. |

---

## ⚡ System At a Glance

```mermaid
flowchart LR
    A["👤 Customer<br/>(Book & Track PNR)"] -->|Web Browser| C["🌐 Apache 2.4 + PHP 8.1<br/>Docker Container"]
    B["👑 Administrator<br/>(Manage Fleet & Routes)"] -->|Web Browser| C
    C -->|TLS / SSL (Port 4000)| D[("🗄️ TiDB Cloud Serverless<br/>MySQL 8.0 Compatible")]
```

---

## 🛠️ Technology Stack Reference

- **Backend Runtime:** PHP 8.1 with MySQLi extension
- **Web Server:** Apache 2.4 HTTP Server with `mod_rewrite`
- **Database Engine:** TiDB Cloud Serverless (MySQL 8.0 compatible wire protocol)
- **Frontend UI:** HTML5, CSS3, JavaScript (ES6), Bootstrap 4.6, AOS.js
- **Containerization:** Docker (Multi-stage build context, non-root web directory, dynamic PORT support)
- **Cloud Hosting:** Render Web Services
- **CI/CD & Registry:** GitHub Actions & GitHub Container Registry (`ghcr.io`)
- **Open Source License:** MIT License
