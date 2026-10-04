# 👑 Administrator Guide

The **Administrator Control Center** (`admin/`) allows transport operators to monitor live business metrics, manage buses and capacities, configure routes, supervise ticket sales, execute diagnostics, and provision staff.

---

## 1. Authentication & Access

- **Login Portal:** Navigate to the public homepage and click **Administrator Login**.
- **Credentials:** Protected with bcrypt hashing. Passwords must be at least 12 characters. New admins can be provisioned using CLI:
  ```bash
  php database/create-admin.php "Staff Name" "admin@example.com" "1234567890"
  ```
- **Brute-Force Rate Limiting:** Account login is throttled to 5 failures per 15 minutes per account and 20 failures per 15 minutes per IP.
- **Session Protection:** All administrative pages verify authenticated admin session tokens with HttpOnly, SameSite, and strict timeout parameters.

---

## 2. Dashboard KPIs (`admin/dashboard.php`)

The administrative landing screen displays 7 real-time KPI metrics aggregated directly from the database:

| Metric Card | Color Theme | Aggregation Query | Description |
|---|---|---|---|
| **Bookings** | Info (Blue) | `SELECT COUNT(*) FROM booking WHERE status != 'Cancelled'` | Active tickets reserved across all journeys |
| **Buses** | Success (Green) | `SELECT COUNT(*) FROM buses` | Total operational fleet vehicles registered |
| **Routes** | Danger (Red) | `SELECT COUNT(*) FROM route` | Number of active routes |
| **Seats** | Warning (Yellow) | `SELECT SUM(capacity) FROM buses` | Real fleet passenger seat capacity |
| **Customers** | Primary (Dark Blue) | `SELECT COUNT(*) FROM costumer` | Total registered passenger profiles |
| **Admins** | Secondary (Gray) | `SELECT COUNT(*) FROM admin` | Number of authorized admin staff accounts |
| **Earnings** | Dark (Black) | `SELECT SUM(price) FROM booking WHERE status != 'Cancelled'` | Total gross revenue collected |

---

## 3. Fleet & Bus Operations (`admin/buses.php`)

- **Add New Bus:** Click **Add Bus Details** to trigger the modal dialog. Enter the vehicle identifier (e.g., `PB-02-1044`) and passenger seat capacity (`10` to `60`).
- **Inspect Fleet:** View all vehicles, registered capacity, and edit or delete links.
- **Referential Integrity on Deletion (O10):** A bus **cannot** be deleted if it is currently assigned to active routes or has active passenger reservations.
- **Edit Bus (`admin/edit/edit-bus.php`):** Modify vehicle number or capacity. Renaming a bus automatically synchronizes assigned routes and bookings.

---

## 4. Route Management (`admin/routes.php`)

Routes establish the operational timetable and pricing:

- **Add Route Modal:**
  - **Origin & Destination:** Origin and destination cities must be distinct (`city1 != city2`).
  - **Bus Number:** Dropdown menu populated from `buses` table.
  - **Departure Time:** 24-hour time schedule (`HH:MM`).
  - **Collision Prevention (O11):** The system prevents assigning the same bus to multiple routes departing at the exact same time.
  - **Ticket Price:** Journey fare in currency units.
- **Edit Route (`admin/edit/edit-route.php`):** Updates route attributes with duplicate collision validation.
- **Safe Route Deletion (O10):** Prevents deletion if upcoming active passenger bookings exist for that departure schedule.

---

## 5. Booking Supervision (`admin/bookings.php`)

Allows operators to create bookings on behalf of walk-in passengers:

1. **Canonical Booking Function (`create_booking()`):** Shared with customer portal for unified validation and error handling.
2. **Auto-Generated Cryptographic PNR:** Uses `random_bytes(5)` to generate a 10-character hex token (e.g., `A9F1C84B20`).
3. **Trip Definition:** Checks seat collision per `bus + date + time`.
4. **Soft Cancellations (O4):** Cancelling a booking marks `status = 'Cancelled'` preserving audit trails while instantly freeing the seat.

---

## 6. Live Seat Availability Monitor (`admin/seats.php`)

Enables dispatchers to check seat occupancy before a bus departs:

1. Select a **Bus Number**, **Travel Date**, and optional **Departure Time**.
2. Dynamically pulls actual bus capacity (`get_bus_capacity()`) and displays a responsive seat map.
3. Reserved seats appear in **Red** (disabled), while available seats appear in **Blue**. Cancelled or expired hold seats are automatically shown as available.

---

## 7. System Diagnostics (`admin/diagnostics.php`) (O15)

The live diagnostic matrix verifies infrastructure and database integrity:
- PHP version and required extensions (`mysqli`, `openssl`).
- Database connectivity and ping latency.
- Hardening columns (`pnr`, `status`, `capacity`, `hold_expires_at`).
- Unique constraints (`uq_booking_seat`, `uq_booking_pnr`).
- Ephemeral rate limit storage (`login_attempts`).
- Applied migration log from `schema_migrations`.
- Session cookie flags (`HttpOnly`, `SameSite`).
- System timezone synchronization.

---

## 8. Customer Inquiries Inbox (`admin/queries.php`)

Displays customer inquiries submitted via the public contact form. Shows sender name, email address, topic, and message text.
