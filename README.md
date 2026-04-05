# 🚌 Bus Reservation System

A full-stack **Bus Booking Management System** built with PHP and MySQL. It provides separate portals for administrators and customers to manage bus routes, bookings, seats, and more.

🌐 **Live Demo:** [https://busrevervation.gamer.gd/Homepage.php](https://busrevervation.gamer.gd/Homepage.php)
🖥️ **Deployment Panel:** [InfinityFree Dashboard](https://dash.infinityfree.com/accounts/if0_41583501)

---

## 📸 Screenshots

> Homepage | Admin Dashboard | Booking Page

---

## ✨ Features

### 👤 Customer Portal
- Register and log in securely
- Search available bus routes
- Book seats on available buses
- View booking history and PNR details

### 🛠️ Admin Panel
- **Dashboard** — View total bookings, buses, routes, customers, seats, and earnings at a glance
- **Bus Management** — Add, edit, and delete buses
- **Route Management** — Add, edit, and delete routes with departure times
- **Booking Management** — Add, edit, and delete booking records
- **Customer Management** — Add, edit, and delete customer accounts
- **Seat Management** — View seat availability per bus per date
- **Query Management** — View customer queries/comments
- **Add Admin** — Create new administrator accounts

---

## 🗂️ Project Structure

```
📦 Bus-Reservation-System
├── index.php               # Entry point — redirects to Homepage
├── Homepage.php            # Landing page with User & Admin login
├── login.php               # Customer login page
├── db_con.php              # Database connection
├── adminsession.php        # Admin session guard
├── admin.php               # Admin panel layout & sidebar
├── dashboard.php           # Admin dashboard stats
├── buses.php               # Bus management (CRUD)
├── busesedit.php           # Edit bus record
├── route.php               # Route management (CRUD)
├── routeedit.php           # Edit route record
├── booking.php             # Booking management (CRUD)
├── bookingedit.php         # Edit booking record
├── costumer.php            # Customer management (CRUD)
├── costumeredit.php        # Edit customer record
├── seat.php                # Seat availability viewer
├── addadmin.php            # Add new admin account
├── query.php               # View customer queries
├── adminhead.php           # Admin panel header component
├── adminfooter.php         # Admin panel footer component
├── header.php / header1-3  # Public page header variants
├── footer.php              # Public page footer
├── admin.css / project.css # Stylesheets
├── images/                 # Project images & icons
├── editpage/               # Edit pages (route, customer, booking, bus)
└── userinterface/          # Customer-facing booking interface
    ├── admin.php           # User dashboard
    ├── booking.php         # User booking form
    ├── userbooking.php     # User booking view
    └── usersession.php     # User session guard
```

---

## 🛢️ Database Tables

| Table      | Description                        |
|------------|------------------------------------|
| `admin`    | Administrator accounts             |
| `costumer` | Registered customer accounts       |
| `buses`    | Bus records (bus numbers)          |
| `route`    | Routes (from, to, bus, time)       |
| `booking`  | Booking records with PNR & price   |
| `query`    | Customer queries/contact messages  |

---

## 🚀 Getting Started (Local Setup)

### Prerequisites
- PHP 7.4+
- MySQL / phpMyAdmin
- Apache (XAMPP / WAMP / LAMP)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/bus-reservation-system.git
   ```

2. **Move to your server's root directory**
   ```bash
   # For XAMPP:
   mv bus-reservation-system/ C:/xampp/htdocs/
   ```

3. **Import the database**
   - Open `phpMyAdmin`
   - Create a database named `majorproject`
   - Import the provided SQL file

4. **Configure the database connection**
   - Open `db_con.php`
   - Update the credentials:
     ```php
     $hostname = "localhost";
     $username = "root";
     $password = "";
     $db_name  = "majorproject";
     ```

5. **Run the project**
   - Visit: `http://localhost/bus-reservation-system/`

---

## 🔐 Default Login Credentials

| Role     | Email              | Password  |
|----------|--------------------|-----------|
| Admin    | admin@example.com  | admin123  |
| Customer | user@example.com   | user123   |

> ⚠️ Change these credentials after first login.

---

## 🛠️ Tech Stack

| Technology   | Usage                    |
|--------------|--------------------------|
| PHP          | Server-side scripting    |
| MySQL        | Database                 |
| Bootstrap 4  | Responsive UI framework  |
| HTML5 / CSS3 | Markup & Styling         |
| JavaScript   | Client-side interactions |
| AOS.js       | Scroll animations        |

---

## ⚠️ Known Issues / TODOs

- [ ] SQL queries are vulnerable to **SQL Injection** — migrate to prepared statements
- [ ] Passwords are stored in **plain text** — implement `password_hash()` and `password_verify()`
- [ ] Add **pagination** for large data tables
- [ ] Add **email confirmation** for bookings
- [ ] Improve **form validation** on the client side

---

## 🤝 Contributing

Pull requests are welcome! For major changes, please open an issue first to discuss what you would like to change.

---

## 📄 License

This project is developed as a **Major Academic Project**.  
Feel free to use it for learning and educational purposes.

---

> Made with ❤️ using PHP & MySQL
