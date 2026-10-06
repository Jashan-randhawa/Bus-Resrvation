# 👑 Administrator Guide

The **Administrator Control Center** (`admin/`) allows transport operators and executive staff to monitor live business metrics, manage fleet vehicles and capacities, configure route schedules, supervise ticket reservations, review immutable audit trails, and execute system diagnostics.

---

## 1. Authentication, Sessions & RBAC

### 1.1 Secure Login Portal
- **URL:** Navigate to the public homepage and click **Administrator Login** (`homepage.php`).
- **Session Isolation (P2):** Administrator sessions are strictly isolated via `admin_id` session keys to prevent privilege escalation or session collision with customer portals.
- **Idle Timeout (P2):** Admin sessions expire automatically after **1,800 seconds (30 minutes)** of inactivity. On expiration, the session is cleared, destroyed, and redirected safely via `BASE_URL`.
- **Pre-Auth Rate Limiting:** Brute-force attacks are throttled (5 failures per 15 minutes per account; 20 per IP). Throttled requests receive an HTTP 429 Too Many Requests response.

### 1.2 Multi-Role Role-Based Access Control (RBAC) (P2, P9)
The system supports three distinct administrator roles:

| Role | Scope & Description | Permissions & Access |
|---|---|---|
| `super_admin` | Full executive privileges | All operational pages, staff provisioning (`add-admin.php`), system diagnostics (`diagnostics.php`), and compliance audit trail (`audit-log.php`). Can delete buses, routes, and bookings. |
| `operator` | Daily dispatch & scheduling | Can create/edit buses, routes, and bookings. Cannot delete core entities or manage staff/system internals. |
| `viewer` | Read-only reporting & audit | Can inspect dashboards, seat maps, customer lists, and fleet catalogs without edit or delete capabilities. |

All protected routes and mutation handlers enforce permissions via `require_role(...)`. Unauthorized access attempts return an HTTP 403 Forbidden error.

### 1.3 Administrator Provisioning
Super administrators can provision new administrators directly in the web UI via **System > Administrators** (`admin/add-admin.php`) or via the secure CLI runner:
```bash
php database/create-admin.php "Staff Name" "admin@example.com" "9876543210" "operator"
```
Passwords must satisfy the 12-character minimum security requirement and are hashed using bcrypt (`PASSWORD_DEFAULT`).

---

## 2. Executive Dashboard KPIs (`admin/dashboard.php`) (P10)

The executive dashboard consolidates all real-time operational and revenue KPIs in a **single database query**, eliminating latency and multiple network round-trips:

| Metric Card | Badge / Context | Aggregation | Description |
|---|---|---|---|
| **Reservations** | Total | `COUNT(*) FROM booking` | Total lifetime passenger bookings |
| **Fleet** | Active | `COUNT(*) FROM buses` | Operational transit vehicles registered |
| **Routes** | Timetable | `COUNT(*) FROM route` | Active route connections |
| **Fleet Seats** | Capacity | `SUM(capacity) FROM buses` | Real passenger seats across all vehicles |
| **Passenger Accounts** | Directory | `COUNT(*) FROM costumer` | Registered customer profiles |
| **System Staff** | RBAC | `COUNT(*) FROM admin` | Active administrator accounts |
| **Passenger Inquiries** | Inbox | `COUNT(*) FROM query` | Customer feedback submissions |
| **Total Revenue** | Net Confirmed | `SUM(price) FROM booking` | Gross earnings from confirmed bookings |

---

## 3. Fleet & Bus Operations (`admin/buses.php`)

- **Register New Bus:** Click **+ Register New Bus** to open the creation modal. Enter the vehicle identifier (e.g., `PB-02-1044`), passenger seat capacity (`10` to `60`), and layout configuration (`2+2`, `2+1`, `1+2`, `1+1`).
- **Fleet Catalog:** Displays vehicle license, capacity, layout, and edit/delete actions. Paginated at 25 vehicles per page.
- **Referential Integrity on Deletion (P4):** Buses with active routes or active upcoming bookings cannot be deleted. All attempts are blocked with clear feedback.
- **Audit Logging (P9):** Vehicle creation and deletion events are automatically recorded in the audit trail.

---

## 4. Route Scheduling (`admin/routes.php`)

- **Create Route Schedule:** Specify origin (`city1`), destination (`city2`), assigned bus (`busno`), departure time (`time`), and ticket fare (`price`).
- **Referential Integrity (P4):** The route automatically links to `bus_id` in the `buses` table.
- **Schedule Conflict Prevention:** Assigning the same vehicle to overlapping departures is blocked.
- **Safe Route Deletion:** Deletion is blocked if active upcoming bookings are booked on the departure schedule.
- **List Pagination:** Paginated at 25 route schedules per page.

---

## 5. Booking Supervision (`admin/bookings.php`)

- **Create Reservation:** Dispatchers can create bookings for walk-in passengers directly through the modal dialog.
- **Concurrency & Atomic Seat Locks (P3):** Every booking atomically reserves the seat in the dedicated `seat_lock` table (`PRIMARY KEY (bus_id, travel_date, seat_no)`). Concurrent duplicate requests are rolled back safely and return a clean error message.
- **Cryptographic PNR Generation:** Generates a secure, unguessable 10-character hex PNR (`random_bytes(5)`).
- **Status Filter & 25-Item Pagination:** Filter by `Confirmed`, `Pending`, `Expired`, `Cancelled`, or `All`. Paginated for fast browsing.
- **Cancellation & Seat Liberation:** Cancelling a booking updates status to `Cancelled` and immediately frees the lock from `seat_lock`.

---

## 6. Live Seat Availability Visualizer (`admin/seats.php`) (P8)

- **Trip Selection:** Select a vehicle, journey date, and optional departure time.
- **Dynamic Seating Matrix:** Automatically adapts to the vehicle's true capacity (`buses.capacity`) using `render_seat_grid()`.
- **Occupancy Badge:** Accurately displays booked count vs. available seats (`X of Y Booked (Z Available)`).
- **Color Legend:** Reserved seats appear in red, and available seats appear in clean bordered white.

---

## 7. Administrative Audit Log (`admin/audit-log.php`) (P9)

Restricted exclusively to `super_admin`:
- **Immutable Log:** Records admin actor ID and name, action (`CREATE`, `UPDATE`, `DELETE`, `CANCEL`), target entity (`bus`, `route`, `booking`, `customer`, `admin`), entity ID, state changes (JSON old/new values), client IP, and user-agent string.
- **Filters & Pagination:** Filter by action or entity type with 25-item pagination.

---

## 8. System Diagnostics (`admin/diagnostics.php`) (P6)

Restricted exclusively to `super_admin`:
- **Security Headers:** Enforces `Cache-Control: no-store, private` to prevent caching of diagnostic data.
- **Credential Masking:** Masks database credentials and hostnames.
- **Integrity Matrix:**
  - PHP version and required extensions (`mysqli`, `openssl`, `mbstring`, `curl`).
  - Database connectivity, ping latency, and UTF-8 collation.
  - Core tables, columns (`pnr`, `status`, `capacity`, `bus_id`, `route_id`), and unique constraints.
  - Dedicated `seat_lock`, `schema_migrations`, and `migration_steps` verification.
  - Advisory-locked migration runner trigger.
