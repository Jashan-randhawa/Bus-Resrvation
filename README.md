# 🚌 Bus Reservation System

A full-stack **Bus Ticket Booking & Management System** built with PHP and MySQL. Provides two separate portals — an **Admin Panel** for complete system management, and a **Customer Portal** for searching routes, selecting seats, and booking tickets.

🌐 **Live Demo:** [https://busrevervation.gamer.gd/Homepage.php](https://busrevervation.gamer.gd/Homepage.php)
🖥️ **Hosting Panel:** [InfinityFree Dashboard](https://dash.infinityfree.com/accounts/if0_41583501)

---

## 📋 Table of Contents

- [Overview](#overview)
- [System Architecture](#system-architecture)
- [Project Structure](#project-structure)
- [Tech Stack](#tech-stack)
- [Features](#features)
- [Database Schema](#database-schema)
- [Prerequisites](#prerequisites)
- [Getting Started — Local Setup](#getting-started--local-setup)
- [Default Login Credentials](#default-login-credentials)
- [Page-by-Page Breakdown](#page-by-page-breakdown)
- [Authentication and Session Flow](#authentication-and-session-flow)
- [Seat Availability System](#seat-availability-system)
- [Deployment](#deployment)
- [Known Issues and Security Notes](#known-issues-and-security-notes)
- [Improvement Roadmap](#improvement-roadmap)

---

## Overview

The Bus Reservation System digitalises the entire ticket booking workflow for a bus service operator. Customers can self-register, browse routes, pick seats on a live interactive seat map, and check their PNR status without admin involvement. Administrators get a single dashboard to manage every entity in the system: buses, routes, bookings, customers, seats, and admin accounts.

The project is a classic **monolithic PHP application** with no framework, no ORM, and no build step. Every page is a self-contained `.php` file that handles its own form processing, database queries, and HTML output. Shared concerns (DB connection, session guard, header/footer) are split into reusable `include_once` partials.

---

## System Architecture

```
Browser (Bootstrap 4 + AOS.js + Vanilla JS)
        |
        |  HTTP POST / GET
        v
+---------------------------------------------------------------+
|                     Apache + PHP 7.4+                        |
|                                                               |
|  +----------------+  +------------------+  +-------------+   |
|  |  Public Area   |  |   Admin Panel    |  |  Customer   |   |
|  |                |  |                  |  |   Portal    |   |
|  | index.php      |  | admin.php        |  |             |   |
|  | Homepage.php   |  | dashboard.php    |  | userinter-  |   |
|  | header*.php    |  | buses.php        |  | face/       |   |
|  | footer.php     |  | route.php        |  | admin.php   |   |
|  |                |  | booking.php      |  | booking.php |   |
|  |                |  | costumer.php     |  | userbooking |   |
|  |                |  | seat.php         |  | .php        |   |
|  |                |  | addadmin.php     |  |             |   |
|  |                |  | query.php        |  |             |   |
|  +----------------+  +------------------+  +-------------+   |
|                                                               |
|  Shared: db_con.php  adminsession.php  adminhead.php         |
|  editpage/ (routeedit, bookingedit, costumeredit, busesedit) |
+----------------------------+----------------------------------+
                             |  mysqli_connect / mysqli_query
                             v
                  +----------------------+
                  |    MySQL Database    |
                  |     majorproject     |
                  |                      |
                  |  admin               |
                  |  costumer            |
                  |  buses               |
                  |  route               |
                  |  booking             |
                  |  query               |
                  +----------------------+
```

---

## Project Structure

```
Bus-Resrvation-main/
|
+-- index.php                    # Entry point — single-line redirect to Homepage.php
+-- Homepage.php                 # Landing page: hero, login modals, PNR lookup
|
+-- -- Admin Panel ----------------------------------------------------------
+-- admin.php                    # Admin layout shell (sidebar + content area)
+-- dashboard.php                # Live KPI cards: bookings, buses, routes,
|                                #   seats, customers, admins, total earnings
+-- buses.php                    # Bus CRUD: Add modal + table view + edit/delete
+-- busesedit.php                # Edit a single bus record
+-- route.php                    # Route CRUD: Add modal + table view + edit/delete
+-- routeedit.php                # Edit a single route record
+-- booking.php                  # Booking CRUD: Add modal with 36-seat picker +
|                                #   full table view + edit/delete
+-- bookingedit.php              # Edit a single booking record
+-- costumer.php                 # Customer CRUD: Add modal + table view + edit/delete
+-- costumeredit.php             # Edit a single customer record
+-- seat.php                     # Seat availability viewer (bus + date -> seat map)
+-- addadmin.php                 # Create new administrator accounts
+-- query.php                    # View all customer contact/query messages
|
+-- -- Shared Admin Components -----------------------------------------------
+-- adminhead.php                # Admin sidebar nav
+-- adminfooter.php              # Admin footer (Bootstrap JS imports)
+-- adminsession.php             # Session guard — redirects if not logged in
|
+-- -- Customer Portal -------------------------------------------------------
+-- userinterface/
|   +-- admin.php                # Customer dashboard shell (sidebar + layout)
|   +-- booking.php              # Full booking form with interactive seat map
|   +-- userbooking.php          # Customer's own booking history
|   +-- usersession.php          # Customer session guard
|   +-- db_con.php               # DB connection (own copy for path resolution)
|   +-- adminhead.php            # Customer panel header/navbar
|   +-- adminfooter.php          # Customer panel footer
|
+-- -- Edit Pages (Standalone) -----------------------------------------------
+-- editpage/
|   +-- routeedit.php            # Edit route: pre-fills from DB, updates on submit
|   +-- bookingedit.php          # Edit booking: pre-fills + re-renders seat map
|   +-- costumeredit.php         # Edit customer: pre-fills name/email/phone/pwd
|   +-- busesedit.php            # Edit bus number
|   +-- adminsession.php         # Session guard copy for this directory
|   +-- db_con.php               # DB connection copy for this directory
|   +-- header1.php              # Edit page header
|   +-- footer.php               # Edit page footer
|   +-- edit.css                 # Edit page custom styles
|
+-- -- Public Components -----------------------------------------------------
+-- header.php                   # Full Bootstrap navbar for public pages
+-- header1.php                  # Header variant (minimal nav)
+-- header2.php                  # Header variant used by Homepage.php
+-- header3.php                  # Header variant (bare head tags only)
+-- footer.php                   # Public page footer
+-- login.php                    # Standalone customer login page
+-- logincopy.php                # Login + registration modal content component
+-- db_con.php                   # Root-level database connection
+-- db_con1.php                  # Legacy/backup DB connection
|
+-- -- Stylesheets -----------------------------------------------------------
+-- admin.css                    # Admin panel custom styles
+-- project.css                  # Public page custom styles
+-- project1.css                 # Alternate public styles
+-- edit.css                     # Edit-page form styles
|
+-- images/
    +-- bus.svg                  # Bus icon (admin sidebar navbar)
    +-- route.svg                # Route section icon
    +-- ticket.svg               # Ticket section icon
    +-- customer.svg             # Customer section icon
    +-- userav-min.png           # Admin avatar image
    +-- user-profile-min.png     # Customer avatar image
    +-- buscv.jpg                # Hero section bus photograph
    +-- tranbus.png              # Transparent bus graphic
    +-- rhn-about-map.jpg        # About section map image
    +-- *.jpg / *.jpeg           # Additional hero/section images
```

---

## Tech Stack

| Technology | Version | Role |
|---|---|---|
| PHP | 7.4+ | Server-side scripting, session management, business logic |
| MySQL | 5.7+ | Relational database |
| MySQLi | Bundled | PHP database driver (mysqli_connect, mysqli_query, mysqli_fetch_assoc) |
| Bootstrap | 4.6.2 | Responsive grid, modals, navbar, forms, tables, buttons |
| AOS.js | 2.3.1 | Scroll-triggered CSS animations on the public landing page |
| HTML5 / CSS3 | — | Page markup and custom styling |
| JavaScript | ES5 | Seat selection interactivity, modal toggling, dynamic UI |
| Apache | 2.4+ | Web server (via XAMPP / WAMP / LAMP / cPanel) |

---

## Features

### Customer Portal

| Feature | Details |
|---|---|
| Register | Create an account with first name, last name, email, phone, and password |
| Login | Session-based authentication verified against the costumer table |
| Browse Routes | View all routes with From/To cities, bus number, departure time, and price |
| Seat Selection | Interactive 36-seat visual bus map — booked seats in red, available in blue |
| Book a Ticket | Pick bus, route, date, time, and seat; PNR auto-generated via rand(1,10000000) |
| View My Bookings | Personal booking history filtered by the logged-in customer's session name |
| PNR Lookup | Enter a PNR on the public homepage to retrieve ticket details without login |
| Submit Query | Contact form that stores a message in the query table for admin review |
| Logout | Destroys session, redirects to Homepage |

### Admin Panel

| Feature | Details |
|---|---|
| Dashboard | Live stats: total bookings, buses, routes, seats (buses x 36), customers, admins, total earnings |
| Bus Management | Add buses by bus number, view all, edit, delete |
| Route Management | Add routes (From, To, Bus, Departure Time, Price), view all, edit, delete |
| Booking Management | Manually add bookings with the 36-seat picker, view all records, edit, delete |
| Customer Management | View all customers, add manually, edit details, delete accounts |
| Seat Availability | Select any bus + date to see a live seat map with booked seats highlighted red |
| Query Management | Read all customer-submitted messages in a table view |
| Add Admin | Create new administrator accounts (name, email, phone, password) |
| Logout | POST-based session destruction and redirect to Homepage |

---

## Database Schema

Database name: **majorproject**

### admin

| Column | Type | Notes |
|---|---|---|
| id | INT, PK, AUTO_INCREMENT | Admin record ID |
| name | VARCHAR | Administrator full name |
| Email_id | VARCHAR | Login email address |
| Password | VARCHAR | Stored as plain text (security issue) |
| phone | VARCHAR | Contact phone number |

### costumer

| Column | Type | Notes |
|---|---|---|
| id | INT, PK, AUTO_INCREMENT | Customer record ID |
| name | VARCHAR | Customer full name |
| email | VARCHAR | Login email address |
| pwd | VARCHAR | Stored as plain text (security issue) |
| phone | VARCHAR | Contact phone number |

### buses

| Column | Type | Notes |
|---|---|---|
| id | INT, PK, AUTO_INCREMENT | Bus record ID |
| bus_number | VARCHAR | Unique bus identifier (e.g. HR-001) |

> The system assumes 36 seats per bus, hardcoded throughout (dashboard uses count(buses) x 36).

### route

| Column | Type | Notes |
|---|---|---|
| sno | INT, PK, AUTO_INCREMENT | Route ID |
| city1 | VARCHAR | Departure city (From) |
| city2 | VARCHAR | Destination city (To) |
| busno | VARCHAR | Assigned bus — references buses.bus_number |
| time | TIME | Scheduled departure time |
| price | DECIMAL | Ticket price for this route |

### booking

| Column | Type | Notes |
|---|---|---|
| id | INT, PK | PNR number — generated via rand(1, 10000000) |
| bus | VARCHAR | Bus number |
| unm | VARCHAR | Customer name |
| num | VARCHAR | Customer contact number |
| from | VARCHAR | Departure city |
| to | VARCHAR | Destination city |
| date | DATE | Travel date |
| time | TIME | Departure time |
| seat | INT | Seat number (1–36) |
| price | DECIMAL | Ticket price paid |

### query

| Column | Type | Notes |
|---|---|---|
| id | INT, PK, AUTO_INCREMENT | Query record ID |
| name | VARCHAR | Sender name |
| email | VARCHAR | Sender email |
| message | TEXT | Message content |

---

## Prerequisites

| Requirement | Notes |
|---|---|
| PHP 7.4+ | Bundled with XAMPP / WAMP |
| MySQL 5.7+ | Bundled with XAMPP / WAMP |
| Apache 2.4+ | Bundled with XAMPP / WAMP |
| XAMPP / WAMP / LAMP | Any local PHP server stack |
| phpMyAdmin | Included with XAMPP / WAMP — used to import the database |

---

## Getting Started — Local Setup

### Step 1 — Clone the repository

```bash
git clone https://github.com/your-username/bus-reservation-system.git
```

### Step 2 — Move files to your server root

```bash
# XAMPP on Windows:
mv bus-reservation-system/  C:/xampp/htdocs/bus-reservation/

# XAMPP on macOS:
mv bus-reservation-system/  /Applications/XAMPP/htdocs/bus-reservation/

# WAMP on Windows:
mv bus-reservation-system/  C:/wamp64/www/bus-reservation/

# LAMP on Linux:
mv bus-reservation-system/  /var/www/html/bus-reservation/
```

### Step 3 — Start Apache and MySQL

Open the XAMPP / WAMP control panel and start both Apache and MySQL.

### Step 4 — Create the database

1. Open phpMyAdmin at `http://localhost/phpmyadmin`
2. Click New in the left sidebar
3. Create a database named exactly: `majorproject`
4. Select that database, click the Import tab
5. Upload and import the provided `.sql` dump file

### Step 5 — Configure the database connection

Three copies of `db_con.php` exist across subdirectories to handle different relative paths. Update all three:

- `db_con.php` (project root)
- `editpage/db_con.php`
- `userinterface/db_con.php`

Replace the contents of each with:

```php
<?php
$hostname = "localhost";
$username = "root";
$password = "";             // XAMPP / WAMP default is empty
$db_name  = "majorproject";
$link = mysqli_connect($hostname, $username, $password, $db_name);
?>
```

> The committed db_con.php contains live InfinityFree production credentials.
> Replace all three files and add db_con.php to .gitignore before pushing to Git.

### Step 6 — Open in your browser

```
http://localhost/bus-reservation/
```

index.php immediately redirects to Homepage.php.

---

## Default Login Credentials

| Role | Email | Password |
|---|---|---|
| Admin | admin@example.com | admin123 |
| Customer | user@example.com | user123 |

> Change these after first login. Passwords are currently stored in plain text.

---

## Page-by-Page Breakdown

### Public Pages

**index.php** — Single line: `header("location:Homepage.php")`. Canonical entry point.

**Homepage.php** — Main public landing page containing:
- Hero section with AOS.js scroll animations
- Administrator Login button — opens a modal; on submit queries the admin table, creates session, redirects to admin.php
- Customer Login / Register modal with two Bootstrap tabs: login (checks costumer table, creates session, redirects to userinterface/admin.php) and register (inserts new costumer row)
- PNR Status Lookup — public form to check booking details by PNR without logging in

---

### Admin Panel

Every admin page starts with `include_once("adminsession.php")` which calls `session_start()` and redirects to Homepage.php if the session is absent.

**dashboard.php — Stats Overview**

Renders 7 live KPI cards with individual database queries:

| Card | Colour | Query |
|---|---|---|
| Bookings | Info (blue) | SELECT * FROM booking → row count |
| Buses | Success (green) | SELECT * FROM buses → row count |
| Routes | Danger (red) | SELECT * FROM route → row count |
| Seats | Warning (yellow) | SELECT * FROM buses → count x 36 |
| Customers | Primary | SELECT * FROM costumer → row count |
| Admins | Secondary | SELECT * FROM admin → row count |
| Earnings | Dark | SELECT SUM(price) AS cost FROM booking |

**buses.php — Bus Management**

Add Bus modal accepts a bus_number. On submit: `INSERT INTO buses VALUES(null,'$bus_no')`. Show Bus Details renders all buses in a table with Edit (editpage/busesedit.php?id=X) and Delete (DELETE FROM buses WHERE id=X) controls.

**route.php — Route Management**

Add Route modal fields: From city, To city, Bus Number (dropdown from buses), Departure Time, Ticket Price. On submit: `INSERT INTO route VALUES(null,From,To,bus,time,price)`. Show Route Details table has Edit and Delete columns.

**booking.php — Booking Management**

Add Booking modal contains:
- Auto-generated PNR via `rand(1, 10000000)` in a read-only field
- Bus Number dropdown (from buses table)
- Customer Name and Contact Number text inputs
- From / To dropdowns (populated from route.city1 and route.city2)
- Date picker with min set to today
- Time picker
- Interactive 36-seat map — clicking a seat calls fun(N) which writes the seat number into a hidden seat_no input

On submit: `INSERT INTO booking VALUES(id, bus, unm, num, from, to, date, time, seat_no, price)`

**costumer.php — Customer Management**

Add Customer modal: Name, Email, Phone, Password. On submit: `INSERT INTO costumer VALUES(null,name,email,num,pwd)`. Table view with Edit and Delete controls.

**seat.php — Seat Availability Viewer**

Accepts a bus number and date. Queries booked seats, generates JavaScript functions fun{N}() for each, then fires them all via `<body onload="call()">` to paint booked buttons red.

**addadmin.php** — Form for Name, Email, Phone, Password. On submit: `INSERT INTO admin VALUES(null,name,email,phone,pwd)`.

**query.php** — Runs `SELECT * FROM query` and renders all customer messages in a table.

---

### Customer Portal (userinterface/)

All pages include usersession.php, which redirects to ../Homepage.php if not logged in.

**admin.php** — Customer dashboard shell with sidebar links to Booking and My Bookings. Displays `$_SESSION['name']`.

**booking.php** — Full booking form with interactive 36-seat map, identical to the admin version. On submit, inserts into the booking table.

**userbooking.php** — Shows bookings filtered by the current user:
```sql
SELECT * FROM booking WHERE unm = '$_SESSION[name]'
```

---

### Edit Pages (editpage/)

Each page receives a record ID via $_GET['id'], pre-fills a form from the DB, and runs an UPDATE on submit.

**routeedit.php**
```sql
SELECT * FROM route WHERE sno = $_GET['id']          -- pre-fill
UPDATE route SET city1,city2,busno,time,price WHERE sno=X
```

**bookingedit.php** — Pre-fills all booking fields and re-renders the seat map with the existing seat pre-selected.

**costumeredit.php**
```sql
SELECT * FROM costumer WHERE id = $_GET['id']        -- pre-fill
UPDATE costumer SET name,email,phone,pwd WHERE id=X
```

**busesedit.php**
```sql
SELECT * FROM buses WHERE id = $_GET['id']           -- pre-fill
UPDATE buses SET bus_number=X WHERE id=X
```

---

## Authentication and Session Flow

```
Homepage.php
     |
     +-- Admin Login (POST: name="admin")
     |      SELECT * FROM admin
     |      WHERE Email_id='...' AND Password='...'
     |              |
     |              +-- Match found:
     |              |     session_start()
     |              |     $_SESSION["name"]  = admin name
     |              |     $_SESSION["pwd"]   = admin password
     |              |     $_SESSION["phone"] = admin phone
     |              |     redirect --> admin.php
     |              |
     |              +-- No match: show alert warning
     |
     +-- User Login (POST: name="user")
            SELECT * FROM costumer
            WHERE email='...' AND pwd='...'
                    |
                    +-- Match found:
                    |     session_start()
                    |     $_SESSION["name"] = customer name
                    |     $_SESSION["pwd"]  = customer pwd
                    |     redirect --> userinterface/admin.php
                    |
                    +-- No match: show alert warning


Every admin page top:
  include adminsession.php
    --> session_start()
    --> if (!$_SESSION["name"] && !$_SESSION["pwd"])
              redirect --> ./Homepage.php

Every customer page top:
  include usersession.php
    --> session_start()
    --> if (!$_SESSION["name"] && !$_SESSION["pwd"])
              redirect --> ../Homepage.php

Logout (POST: name="logout"):
  session_unset()
  session_destroy()
  redirect --> Homepage.php
```

---

## Seat Availability System

Each bus has 36 seats in a 2+3 layout across 9 rows. Booked seats are rendered dynamically via server-generated JavaScript:

```
1. User/Admin submits: bus number + date

2. PHP queries:
      SELECT seat FROM booking
      WHERE date = '$date' AND bus = '$bus'

3. For each booked seat (e.g. seat 7), PHP echoes:
      <script>
        function fun7() {
          document.getElementById("7").style.background = "red";
        }
      </script>

4. PHP echoes a master call() function containing all fun{N}() calls:
      <script>
        function call() { fun7(); fun14(); fun22(); }
      </script>

5. <body onload="call()"> fires on page load
   --> all booked seats turn red instantly

6. Unbooked seats remain Bootstrap btn-info (blue)
   --> customer clicks any blue seat to select it
```

Seat map layout:

```
Row 1:  [ 1 ][ 2 ]  |  [ 3 ][ 4 ][ 5 ]
Row 2:  [ 6 ][ 7 ]  |  [ 8 ][ 9 ][10 ]
Row 3:  [11 ][12 ]  |  [13 ][14 ][15 ]
Row 4:  [16 ][17 ]  |  [18 ][19 ][20 ]
Row 5:  [21 ][22 ]  |  [23 ][24 ][25 ]
Row 6:  [26 ][27 ]  |  [28 ][29 ][30 ]
Row 7:  [31 ][32 ]  |  [33 ][34 ][35 ]
Row 8:  [36 ]
```

---

## Deployment

### InfinityFree (Current Live Host)

1. Log in at infinityfree.com
2. Go to File Manager or connect via FTP (FileZilla)
3. Upload all project files to htdocs/
4. Create a MySQL database in the InfinityFree control panel
5. Import the .sql dump via built-in phpMyAdmin
6. Update all three db_con.php files with the new host/user/pass/db values

### XAMPP / WAMP — Local Development

See the full Getting Started section above.

### cPanel Shared Hosting

1. Upload files to public_html/bus-reservation/ via File Manager or FTP
2. In cPanel > MySQL Databases, create a database and user
3. Import the SQL dump via phpMyAdmin
4. Update all three db_con.php files with the new credentials
5. Visit: https://yourdomain.com/bus-reservation/

---

## Known Issues and Security Notes

### Critical

| Issue | Location | Fix |
|---|---|---|
| SQL Injection | Every page using $_REQUEST/$_POST/$_GET in raw queries | Use mysqli_prepare() with bound parameters throughout |
| Plain-text passwords | admin.Password and costumer.pwd columns | Use password_hash() on save, password_verify() on login |
| Production credentials in repository | db_con.php (root) | Move to environment variables; add db_con.php to .gitignore |

### Medium

| Issue | Fix |
|---|---|
| No CSRF protection on any form | Add a CSRF token to every form and verify server-side |
| No server-side validation beyond empty-check | Validate data types, lengths, and formats before every query |
| Duplicate PNR risk from rand() | Use AUTO_INCREMENT or UUID() for guaranteed uniqueness |
| Debug output (echo/var_dump) left in production code | Remove all debug statements |
| No DB-level seat uniqueness constraint | Add UNIQUE on (bus, date, seat) in booking table |

### Minor / UX

| Issue | Fix |
|---|---|
| No pagination on data tables | Add LIMIT/OFFSET with page controls |
| No email confirmation after booking | Integrate PHPMailer for booking confirmation |
| costumer typo throughout codebase | Rename column/table to customer consistently |

---

## Improvement Roadmap

- [ ] Migrate all raw SQL to prepared statements (MySQLi or PDO)
- [ ] Implement password hashing with password_hash() / password_verify()
- [ ] Move credentials to environment variables, excluded from Git via .gitignore
- [ ] Add UNIQUE constraint on (bus, date, seat) in the booking table
- [ ] Add CSRF tokens to every form
- [ ] Add full server-side form validation with error messages
- [ ] Add pagination for all admin data tables
- [ ] Send email confirmation on booking using PHPMailer
- [ ] Replace rand() PNR generation with AUTO_INCREMENT or UUID()
- [ ] Add a cancel booking feature for customers
- [ ] Add search and date-range filters to admin booking and customer tables
- [ ] Rename costumer to customer throughout the entire codebase
- [ ] Add PHPUnit tests for core booking and authentication logic

---

## License

Developed as a **Major Academic Project**.
Free to use for learning and educational purposes.

---

> Made with love using PHP and MySQL
