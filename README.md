<div align="center">

# 🚌 Bus Reservation System

### *A Modern, Cloud-Ready Full-Stack Bus Ticket Booking & Fleet Management Platform*

[![Render](https://img.shields.io/badge/Render-Deployed-46E3B7?style=for-the-badge&logo=render&logoColor=white)](https://bus-resrvation.onrender.com)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://www.docker.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![TiDB Cloud](https://img.shields.io/badge/TiDB%20Cloud-MySQL%20Compatible-E30C34?style=for-the-badge&logo=mysql&logoColor=white)](https://tidb.cloud/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-4.6-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![License](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)](LICENSE)

<br/>

🌐 **Live Demo:** [https://bus-resrvation.onrender.com](https://bus-resrvation.onrender.com)

</div>

---

## 📖 Overview

The **Bus Reservation System** is an end-to-end digital ticketing and fleet operations management platform. Built using **PHP** and **MySQL / TiDB Cloud**, it eliminates manual booking workflows with real-time seat availability maps, instant PNR verification, customer account management, and a centralized administrative control center.

Packaged with **Docker** and pre-configured for one-click deployment on **Render**, the application is production-ready, cloud-native, and accessible from any modern device.

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
| **DevOps & Cloud** | Docker, Render Web Services, GitHub CI/CD |

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

### 💻 Option B: Run Locally with Docker

```bash
# Build the Docker image
docker build -t bus-reservation .

# Run the container
docker run -p 8080:80 \
  -e DB_HOST=your_host \
  -e DB_PORT=4000 \
  -e DB_USER=your_user \
  -e DB_PASS=your_pass \
  -e DB_NAME=majorproject \
  -e DB_SSL=true \
  bus-reservation
```
Visit `http://localhost:8080` in your browser.

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
bus-reservation/
├── index.php                 # Entry point (redirects to homepage.php)
├── homepage.php              # Landing page, login modals & PNR lookup
├── login.php                 # Standalone login form
├── admin.php                 # Shortcut: /admin.php → admin/index.php
│
├── admin/                    # 👑 Administrator Panel
│   ├── index.php             # Dashboard shell & sidebar
│   ├── dashboard.php         # Real-time metrics & earnings KPIs
│   ├── buses.php             # Fleet & bus management
│   ├── routes.php            # Route schedule & fare controls
│   ├── customers.php         # Customer records management
│   ├── bookings.php          # Booking management & seat picker
│   ├── seats.php             # Live seat availability monitor
│   ├── queries.php           # Customer inquiry viewer
│   ├── add-admin.php         # Administrator provisioning
│   └── edit/                 # ✏️ Edit-record pages
│       ├── edit-booking.php
│       ├── edit-bus.php
│       ├── edit-route.php
│       └── edit-customer.php
│
├── user/                     # 🧑‍💼 Customer Portal
│   ├── index.php             # Customer dashboard (route search)
│   ├── booking.php           # Reservation engine
│   └── my-bookings.php       # Personal booking history
│
├── includes/                 # 🔧 Shared PHP — one copy of everything
│   ├── config.php            # BASE_URL (auto-detected or via env)
│   ├── db_con.php            # Single database connection (env-driven)
│   ├── auth/
│   │   ├── admin-session.php # Admin login guard
│   │   └── user-session.php  # Customer login guard
│   └── layout/
│       ├── header-public.php # Homepage header
│       ├── header-login.php  # Login page header
│       ├── header-admin.php  # Admin sidebar/nav
│       ├── header-user.php   # Customer sidebar/nav
│       ├── header-edit.php   # Edit pages header
│       ├── footer.php        # Public / edit footer scripts
│       └── footer-admin.php  # Admin / customer footer scripts
│
├── assets/                   # 🎨 Everything the browser downloads
│   ├── css/                  # public.css, home.css, admin.css, edit.css
│   └── images/               # bus.svg, userav-min.png, backgrounds…
│       └── _unused/          # Unreferenced pictures — review, then delete
│
├── database/
│   └── init.sql              # Complete schema & seed accounts
│
├── Dockerfile                # Production container build (PHP 8.1 + Apache + MySQLi)
├── .dockerignore
├── .gitignore
├── LICENSE                   # MIT License
└── README.md
```

---

## 🛡️ Security & Best Practices Implemented

- ✅ **Zero Hardcoded Credentials:** 100% of database configurations rely on environment variables.
- ✅ **Encrypted In-Transit:** Enforced SSL/TLS connections for cloud databases like TiDB.
- ✅ **Output Buffering & Clean Sessions:** Robust header and session state management across container restarts.
- ✅ **Dynamic Port Binding:** Seamless execution on cloud container orchestrators like Render.

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

Distributed under the **MIT License**. See `LICENSE` for more information.

<div align="center">
  <sub>Crafted with ❤️ by <a href="https://github.com/Jashan-randhawa">Jashan Randhawa</a></sub>
</div>
