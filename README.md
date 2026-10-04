<div align="center">

# 🚌 Bus Reservation System

### *A Modern, Cloud-Ready Full-Stack Bus Ticket Booking & Fleet Management Platform*

[![Latest Release](https://img.shields.io/badge/Release-v2.0.0-blue?style=for-the-badge&logo=github)](https://github.com/Jashan-randhawa/Bus-Resrvation/releases/tag/v2.0.0)
[![Docker Package](https://img.shields.io/badge/GitHub%20Package-Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation)
[![Wiki Docs](https://img.shields.io/badge/Documentation-Wiki-green?style=for-the-badge&logo=gitbook&logoColor=white)](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki)
[![PHP](https://img.shields.io/badge/PHP-8.1-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![TiDB Cloud](https://img.shields.io/badge/TiDB%20Cloud-MySQL%20Compatible-E30C34?style=for-the-badge&logo=mysql&logoColor=white)](https://tidb.cloud/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-4.6-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![License](https://img.shields.io/badge/License-MIT-orange?style=for-the-badge)](LICENSE)

<br/>

[📖 Explore Technical Wiki](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki) • [📦 Download v2.0.0 Release Bundle](https://github.com/Jashan-randhawa/Bus-Resrvation/releases/tag/v2.0.0) • [🐳 Pull Docker Image](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation)

</div>

---

## 📖 Overview

The **Bus Reservation System** is an end-to-end digital ticketing and fleet operations management platform. Built using **PHP 8.1** and **MySQL / TiDB Cloud Serverless**, it eliminates manual booking workflows with real-time seat availability maps, instant PNR verification, customer account management, and a centralized administrative control center.

Packaged with **Docker** and configured for one-click deployment on **Render**, the application is production-ready, cloud-native, and responsive across all device form factors.

---

## 📚 Technical Wiki & Documentation

Comprehensive architectural, operational, and development documentation is available in the **[Official Technical Wiki](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki)**:

| Document | Topic | Description |
|---|---|---|
| **[🏠 Wiki Home](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Home)** | Overview | Central hub and quick navigation directory |
| **[🏗️ Architecture & System Design](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Architecture-and-System-Design)** | System Topology | Monolithic runtime, Docker containerization, dynamic port binding, and request flow |
| **[🗄️ Database Schema & Models](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Database-Schema-and-Models)** | Data Layer | ERD, data dictionaries for all 6 tables, column constraints, and seed accounts |
| **[👑 Administrator Guide](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Administrator-Guide)** | Operator Manual | 7 live KPI cards, fleet management, route scheduling, seat auditor, and inquiries |
| **[🧑‍💼 Customer Portal & Booking Flow](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Customer-Portal-and-Booking-Flow)** | Passenger UX | Interactive 36-seat bus map engine (`fun(N)`), PNR lifecycle, and trip history |
| **[🐳 Deployment & DevOps](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Deployment-and-DevOps)** | Cloud & CI/CD | Render Web Service deployment, TiDB Cloud setup, environment catalog, and GHCR builds |
| **[🛡️ Security & Troubleshooting](https://github.com/Jashan-randhawa/Bus-Resrvation/wiki/Security-Configuration-and-Troubleshooting)** | Hardening & Fixes | TLS/SSL enforcement, output buffering, session protection, and error runbooks |

---

## ✨ Key Features

### 👤 Customer Experience
- 🔍 **Interactive Seat Picker:** Visual 36-seat layout with live booked/available state indicators.
- 🎫 **Instant Ticket Generation:** Auto-generated unique PNR with travel details, departure timestamps, and pricing.
- 🔎 **Public PNR Lookup:** Check reservation status right from the homepage without needing to log in.
- 📱 **Customer Dashboard:** Manage profiles, view reservation history, and inspect booked trips.
- 💬 **Inquiry Support:** Direct feedback and query submission form for user support.

### 🛡️ Administrator Operations
- 📊 **Real-Time Analytics Dashboard:** Instant KPI cards displaying total bookings, bus counts, active routes, seat utilization, and total revenue.
- 🚌 **Fleet & Bus Management:** Add, edit, and organize buses with custom bus IDs.
- 🛣️ **Route Management:** Configure origin and destination hubs, bus assignment, departure schedules, and fare pricing.
- 🎟️ **Booking Supervision:** Create reservations manually or modify/cancel existing ticket orders.
- 👥 **Customer & Admin Access Controls:** Manage customer registries and provision secure admin credentials.

---

## 🏗️ System Architecture

```mermaid
flowchart TD
    Client["🌐 Web Browser (Responsive UI)"]
    
    subgraph Render["☁️ Render Cloud Platform"]
        Docker["🐳 Docker Container (PHP 8.1 + Apache)"]
        App["Bus Reservation App<br/>(Public Area, Admin Dashboard, Customer Portal)"]
    end
    
    subgraph Database["🗄️ TiDB Cloud / MySQL Serverless"]
        Tables[("majorproject / test<br/>- admin<br/>- costumer<br/>- buses<br/>- route<br/>- booking<br/>- query")]
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
| **Backend** | PHP 8.1 (Apache 2.4 runtime), Session Auth, MySQLi with TLS/SSL |
| **Database** | TiDB Cloud (Serverless MySQL-Compatible) / MySQL 8.0+ |
| **DevOps & Cloud** | Docker, Render Web Services, GitHub Actions CI/CD |
| **Registries & Packages** | GitHub Container Registry (`ghcr.io`), Composer Package Manifest |

---

## 📦 Packages & Distribution Artifacts

| Distribution Package | Target / Type | Description |
|---|---|---|
| **🐳 Docker Container** | [`ghcr.io/jashan-randhawa/bus-resrvation`](https://github.com/Jashan-randhawa/Bus-Resrvation/pkgs/container/bus-resrvation) | Production image tags: `:latest` & `:v2.0.0` |
| **🗜️ Release Zip Archive** | [`bus-reservation-v2.0.0.zip`](https://github.com/Jashan-randhawa/Bus-Resrvation/releases/download/v2.0.0/bus-reservation-v2.0.0.zip) | Standalone distribution bundle (878 KB) |
| **📄 Implementation Plan PDF** | [`Bus_Reservation_GitHub_Packages_Plan.pdf`](https://github.com/Jashan-randhawa/Bus-Resrvation/blob/main/docs/Bus_Reservation_GitHub_Packages_Plan.pdf) | Technical feasibility report & roadmap (287 KB) |
| **🐘 Composer Manifest** | `composer.json` | Project package definition for PHP environments |

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
docker pull ghcr.io/jashan-randhawa/bus-resrvation:latest

# Run the container connecting to TiDB Cloud or local MySQL
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

## 🔑 Demo Credentials

| Role | Email | Password | Access Level |
|---|---|---|---|
| 👑 **Administrator** | `admin@example.com` | `admin123` | Full control: fleet, routes, analytics |
| 🧑‍💼 **Customer** | `user@example.com` | `user123` | Ticket booking & booking history |

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
│   ├── dashboard.php         # Real-time metrics & earnings KPIs
│   ├── buses.php             # Fleet & bus management
│   ├── routes.php            # Route schedule & fare controls
│   ├── customers.php         # Customer records management
│   ├── bookings.php          # Booking management & seat picker
│   ├── seats.php             # Live seat availability monitor
│   ├── queries.php           # Customer inquiry viewer
│   ├── add-admin.php         # Administrator provisioning
│   └── edit/                 # ✏️ Inline record editing subsystem
│       ├── edit-booking.php
│       ├── edit-bus.php
│       ├── edit-route.php
│       └── edit-customer.php
│
├── user/                     # 🧑‍💼 Customer Portal
│   ├── index.php             # Customer dashboard
│   ├── booking.php           # Reservation engine & interactive seat map
│   └── my-bookings.php       # Personal booking history
│
├── includes/                 # 🔧 Shared Application Kernels
│   ├── config.php            # Dynamic BASE_URL auto-detector
│   ├── db_con.php            # Central environment-driven database connector
│   ├── auth/
│   │   ├── admin-session.php # Admin session authentication guard
│   │   └── user-session.php  # Customer session authentication guard
│   └── layout/
│       ├── header-public.php # Homepage header
│       ├── header-login.php  # Login page header
│       ├── header-admin.php  # Admin sidebar navigation
│       ├── header-user.php   # Customer navigation
│       ├── header-edit.php   # Edit pages header
│       ├── footer.php        # Public & edit footer scripts
│       └── footer-admin.php  # Admin & customer footer scripts
│
├── assets/                   # 🎨 Static Client Assets
│   ├── css/                  # public.css, home.css, admin.css, edit.css
│   └── images/               # Vector SVGs, icons, and hero photography
│
├── database/                 # 🗄️ Relational Data Layer
│   └── init.sql              # Complete schema DDL & initial seed accounts
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
│   └── workflows/
│       └── docker-publish.yml # Automated GHCR Docker image build & publish
│
├── Dockerfile                # Production container specification (PHP 8.1 + Apache)
├── .dockerignore             # Docker build context filter
├── .gitignore                # Version control exclusions
├── composer.json             # PHP Composer package definition
├── LICENSE                   # MIT License
└── README.md                 # Primary project overview
```

---

## 🛡️ Security & Best Practices Implemented

- ✅ **Zero Hardcoded Credentials:** 100% of database configurations rely on environment variables (`DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_SSL`).
- ✅ **Encrypted In-Transit:** Enforced SSL/TLS connections for cloud databases like TiDB (`MYSQLI_CLIENT_SSL`).
- ✅ **Output Buffering & Clean Sessions:** Robust header and session state management across container restarts.
- ✅ **Dynamic Port Binding:** Seamless execution on cloud container orchestrators like Render (`${PORT}`).
- ✅ **Automated CI/CD:** GitHub Actions pipeline automatically builds and publishes container images to GitHub Packages.

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
