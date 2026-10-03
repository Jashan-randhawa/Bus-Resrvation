# 👑 Administrator Guide

The **Administrator Control Center** (`admin/`) allows transport operators to monitor live business metrics, manage buses, configure routes, supervise ticket sales, and provision staff.

---

## 1. Authentication & Access

- **Login Portal:** Navigate to the public homepage and click **Administrator Login**.
- **Default Credentials:**
  - **Email:** `admin@example.com`
  - **Password:** `admin123`
- **Session Protection:** All administrative pages verify `$_SESSION["name"]` and `$_SESSION["pwd"]`. If a session is missing or expired, the user is redirected to `../Homepage.php`.
- **Logout:** Submitting the sidebar logout button invokes `session_unset()` and `session_destroy()`, terminating the session.

---

## 2. Dashboard KPIs (`admin/dashboard.php`)

The administrative landing screen displays 7 real-time KPI metrics aggregated directly from the database:

| Metric Card | Color Theme | Aggregation Query | Description |
|---|---|---|---|
| **Bookings** | Info (Blue) | `SELECT * FROM booking` | Total tickets reserved across all journeys |
| **Buses** | Success (Green) | `SELECT * FROM buses` | Total operational fleet vehicles registered |
| **Routes** | Danger (Red) | `SELECT * FROM route` | Number of active routes |
| **Seats** | Warning (Yellow) | `SELECT COUNT(buses) * 36` | Theoretical total passenger seat capacity |
| **Customers** | Primary (Dark Blue) | `SELECT * FROM costumer` | Total registered passenger profiles |
| **Admins** | Secondary (Gray) | `SELECT * FROM admin` | Number of authorized admin staff accounts |
| **Earnings** | Dark (Black) | `SELECT SUM(price) FROM booking` | Total gross revenue collected |

---

## 3. Fleet & Bus Operations (`admin/buses.php`)

- **Add New Bus:** Click **Add Bus Details** to trigger the modal dialog. Enter the vehicle identifier (e.g., `PB-02-1044`) and submit.
- **Inspect Fleet:** The data table displays all buses with unique primary keys and vehicle numbers.
- **Edit Bus (`editpage/busesedit.php`):** Modify vehicle codes; saving redirects back to `../admin/buses.php`.
- **Delete Bus:** Deletes the record from the `buses` table.

---

## 4. Route Management (`admin/route.php`)

Routes establish the operational timetable and pricing:

- **Add Route Modal:**
  - **City 1 (Origin):** Departure station
  - **City 2 (Destination):** Arrival station
  - **Bus Number:** Dropdown menu dynamically populated from `buses` table
  - **Departure Time:** 24-hour time schedule (`HH:MM`)
  - **Ticket Price:** Journey fare in currency units
- **Edit Route (`editpage/routeedit.php`):** Updates cities, vehicle assignment, schedule, or fare.
- **Delete Route:** Removes the journey schedule.

---

## 5. Booking Supervision (`admin/booking.php`)

Allows operators to create bookings on behalf of walk-in passengers:

1. **Auto-Generated PNR:** Uses PHP `rand(1, 10000000)` to assign a unique identifier.
2. **Passenger Details:** Passenger name and contact number.
3. **Trip Details:** Origin, destination, journey date, and departure time.
4. **Interactive 36-Seat Bus Map:**
   - Displays 9 rows of seats (2 left, 3 right layout).
   - Clicking any seat button populates the hidden `seat_no` form field.
5. **Supervision Table:** Complete ledger showing all bookings, passenger contacts, assigned seats, and amounts collected with Edit and Delete options.

---

## 6. Live Seat Availability Monitor (`admin/seat.php`)

Enables dispatchers to check seat occupancy before a bus departs:

1. Select a **Bus Number** and **Travel Date**.
2. The server queries:
   ```sql
   SELECT seat FROM booking WHERE date = '$date' AND bus = '$bus'
   ```
3. Dynamically paints reserved seats **Red** and keeps available seats **Blue** via JavaScript.

---

## 7. Customer Inquiries Inbox (`admin/query.php`)

Displays all customer inquiries submitted via the public contact form. Shows sender name, email address, topic, and message text.
