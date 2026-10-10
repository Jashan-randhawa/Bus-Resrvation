# 👑 Administrator Guide

The **Administrator Control Center** (`admin/`) allows transport operators and executive staff to monitor live business metrics, manage fleet vehicles and seating geometries, configure route schedules, supervise ticket reservations, manage passenger manifests, review immutable audit trails, and execute system diagnostics.

---

## 1. Authentication, Sessions & RBAC

### 1.1 Secure Login Portal
- **URL:** Navigate to the public homepage and click **Administrator Login** (`homepage.php?login=admin`).
- **Session Isolation (P2):** Administrator sessions are strictly isolated via `admin_id` session keys to prevent privilege escalation or session collision with customer portals.
- **Idle & Absolute Lifetime:** Admin sessions expire automatically after **1,800 seconds (30 minutes)** of inactivity or an **8-hour absolute maximum lifetime**.
- **Live Database Session Checks:** Role changes, account deactivation (`is_active = 0`), or password rotations take effect immediately across all active sessions.
- **Pre-Auth Rate Limiting:** Brute-force attacks are throttled (5 failures per 15 minutes per account; 20 per IP). Throttled requests receive an HTTP 429 Too Many Requests response.

### 1.2 Multi-Role Role-Based Access Control (RBAC)
The system supports three distinct administrator roles:

| Role | Scope & Description | Permissions & Access |
|---|---|---|
| `super_admin` | Full executive privileges | All operational pages, staff provisioning (`add-admin.php`), system diagnostics (`diagnostics.php`), pricing overrides, and compliance audit trail (`audit-log.php`). Can delete and restore buses, routes, and bookings. |
| `operator` | Daily dispatch & scheduling | Can create/edit buses, routes, and bookings. Cannot delete core entities or manage staff/system internals. |
| `viewer` | Read-only reporting & audit | Can inspect dashboards, seat maps, customer lists, and fleet catalogs with automatic PII phone/email masking. No write, edit, or delete capabilities. |

All protected routes and mutation handlers enforce permissions via `require_role(...)`. Unauthorized access attempts return an HTTP 403 Forbidden error.

### 1.3 Administrator Provisioning
Super administrators can provision new administrators directly in the web UI via **System > Administrators** (`admin/add-admin.php`) or via the secure CLI runner:
```bash
php database/create-admin.php "Staff Name" "admin@example.com" "9876543210" "operator"
```
Passwords must satisfy the 12-character minimum security requirement and are hashed using bcrypt (`PASSWORD_DEFAULT`).

### 1.4 Two-Factor Authentication (RFC 6238 TOTP)
Administrative accounts can be protected using industry-standard Time-Based One-Time Passwords (TOTP):
- **Setup Flow:** Navigate to **Profile & Security** (`admin/profile.php`). The page generates a secure 16-character base32 secret key.
- **Client-Side QR Code:** An HTML5 canvas QR code is rendered locally in the browser via `qrcode.js` (complying with strict Content Security Policies and preventing OTP URL exposure to external servers). A 1-click **Copy** button is available for manual entry.
- **Mobile Authenticator Apps:** Compatible with Google Authenticator, Microsoft Authenticator, Authy, and 1Password.
- **Secret Encryption:** The TOTP secret key is encrypted at rest using AES-256-GCM.
- **Backup Recovery Codes:** Activating 2FA generates **10 single-use recovery codes** (`XXXX-XXXX`) for emergency access.
- **Policy Enforcement:** Controlled by the `ADMIN_MFA_ENFORCE` environment variable:
  - `false` (default): 2FA is optional. Admins can access the dashboard directly without mandatory setup.
  - `true`: 2FA is strictly enforced. Admins must activate 2FA before accessing dashboard pages.
- **Break-Glass Emergency Reset (CLI):** If an administrator loses access to their authenticator device:
  ```bash
  php database/reset-mfa.php "admin@example.com"
  ```

---

## 2. Executive Dashboard KPIs (`admin/dashboard.php`)

The executive analytics workspace consolidates operational, dispatch, and revenue KPIs with configurable reporting windows (7d, 30d, 90d), dual date filtering modes, route intelligence, and attention-needed alerts:

### 2.1 Authoritative Metric Definitions

| Metric | Source & Aggregation | Scope & Semantics | Description |
|---|---|---|---|
| **Confirmed Revenue** | `SUM(price) FROM booking WHERE status = 'Confirmed' OR status IS NULL` | Selected Window (or All-time) | Net gross receipts from completed reservations. Excludes Pending holds, Cancelled, and Expired tickets. |
| **Reservations** | `COUNT(*) FROM booking` | Selected Window (or All-time) | Gross volume of all booking attempts (Confirmed, Pending, Cancelled, Expired) within the reporting window. |
| **Trip Occupancy** | `SUM(active_seats) / SUM(capacity) * 100` | Today's Departures | Real-time percentage of today's scheduled departure seats reserved by active passengers (`Confirmed` and `Pending`). |
| **Cancellation Rate** | `(Cancelled / Total Window Reservations) * 100` | Selected Window | Percentage of reservations cancelled. Changes versus equal-length previous period are measured strictly in **percentage points (pts)**. |
| **Confirmed Bookings** | `COUNT(*) FROM booking WHERE status = 'Confirmed'` | Selected Window | Successfully issued tickets that completed checkout without subsequent cancellation. |
| **Average Booking Value** | `Confirmed Revenue / Confirmed Bookings` | Selected Window | Average transaction revenue generated per confirmed ticket. |
| **Average Lead Time** | `AVG(DATEDIFF(date, DATE(created_at)))` | Selected Window (Confirmed) | Mean number of days between customer reservation creation and scheduled travel departure. |
| **Active Customers** | `COUNT(*) FROM costumer WHERE archived_at IS NULL` | All-Time Directory | Registered, active customer passenger profiles. |
| **Active Fleet** | `COUNT(*) FROM buses WHERE archived_at IS NULL` | Real-time Fleet | Operational buses currently in service. |
| **Active Schedules** | `COUNT(*) FROM route WHERE archived_at IS NULL` | Real-time Timetable | Active scheduled transit corridors. |
| **Total Fleet Capacity** | `SUM(capacity) FROM buses WHERE archived_at IS NULL` | Real-time Fleet | Total physical seating capacity across all active fleet vehicles. |
| **Unanswered Inquiries** | `COUNT(*) FROM query WHERE status = 'new'` | Actionable Support | Customer contact submissions awaiting administrative response. |

### 2.2 Reporting Date Modes

The dashboard provides a dedicated mode toggle allowing administrators to view data from two distinct operational perspectives:
- **By Journey Date (`mode=journey`, default):** Analyzes reservations by physical travel departure date (`booking.date`). This view is essential for fleet dispatchers, depot managers, and capacity planners monitoring on-the-road volume.
- **By Booking Date (`mode=created`):** Analyzes reservations by transaction timestamp (`DATE(booking.created_at)`). This view is essential for finance officers and revenue managers tracking daily cash inflow and marketing conversion.

### 2.3 Operational Alerting & Attention-Needed Panel

The Attention-Needed panel surfaces real-time dispatch and governance items requiring immediate action:
- **Actionable Inquiries:** Direct link and count of open customer queries requiring response.
- **Full Capacity Departures:** Today's trips at 100% capacity where walk-ins must be diverted to alternative departures.
- **Low Occupancy Departures:** Upcoming departures with <30% seat occupancy departing within the operational window.
- **Boarding Soon:** Trips scheduled to depart within 60 minutes.
- **System Integrity:** Automated verification of `seat_lock` table constraints and generated `active_seat` uniqueness to guarantee double-booking prevention.

### 2.4 Data Export & Breakdown Table

- **Daily Breakdown Table:** Continuous daily sequence showing reservations, cancellations, and daily revenue with previous-period delta context.
- **Safe CSV Export (`?export=daily_csv`):** Memory-efficient streaming export with formula-injection mitigation (`escape_csv_formula`) and administrative audit logging.

---

## 3. Fleet & Bus Operations (`admin/buses.php`)

- **Register New Bus:** Click **+ Register New Bus** to specify vehicle identifier (e.g., `PB-02-1044`), passenger seat capacity (`10` to `60`), and layout configuration (`2+2`, `2+1`, `1+2`, `1+1`).
- **Soft-Delete Archiving:** Vehicles can be archived safely (`archived_at`). Active and Archived tabs allow one-click archiving and instant restoration.
- **Referential Integrity on Deletion:** Vehicles with active routes or active upcoming bookings cannot be deleted or archived.
- **Audit Logging:** Vehicle creation, update, archive, and restoration events are automatically recorded in the audit trail.

---

## 4. Route Scheduling (`admin/routes.php`)

- **Create Route Schedule:** Specify origin (`city1`), destination (`city2`), assigned bus (`busno`), departure time (`time`), and ticket fare (`price`).
- **Referential Integrity:** The route links directly to `bus_id` in the `buses` table.
- **Conflict Prevention:** Assigning the same vehicle to overlapping departures is blocked.
- **Active Booking Lockdown:** If a route has active upcoming bookings, journey parameters (cities, vehicle, time) are locked from destructive modifications.
- **Soft-Delete Archiving:** Routes can be archived and restored without losing historical trip linkages.

---

## 5. Booking Operations & Management (`admin/bookings.php`)

- **Dispatch Booking:** Dispatchers can create bookings for walk-in passengers directly through the modal dialog.
- **Concurrency & Atomic Seat Locks:** Every booking atomically claims the seat in `seat_lock` (`PRIMARY KEY (bus_id, travel_date, seat_no)`). Concurrent duplicate requests are rolled back safely.
- **Cryptographic PNR Generation:** Generates an unguessable 10-character hex PNR (`random_bytes(5)`).
- **Multi-Criteria Search & Filters:** Search by PNR, passenger name, contact phone, or route with date range filtering.
- **Bulk Actions:** Multi-select checkboxes for batch cancellations and selected-row CSV exports.
- **Streaming CSV Exports:** Memory-efficient CSV exports with spreadsheet formula injection protection (`=`, `+`, `-`, `@`).

---

## 6. Passenger Trip Manifest (`admin/manifest.php`)

A dedicated operational tool for bus conductors and dispatchers:
- **Corridor & Departure Filter:** Filter passenger manifests by vehicle, departure date, and route.
- **Hold Segregation:** Passenger manifest displays confirmed travelers; pending holds are segregated into an alert banner.
- **Print-Ready Layout:** Full `@media print` styling with clean typography and conductor/driver signature sign-off lines.
- **Manifest Export:** Download filtered passenger rosters as CSV for offline dispatch operations.

---

## 7. Customer Inquiry Inbox (`admin/queries.php`)

- **Inquiry Lifecycle:** Manage customer messages across three statuses: `new`, `replied`, and `closed`.
- **Modal Email Replies:** Dispatch email responses directly from the dashboard using RFC 5321 SMTP relay.
- **Audit Integration:** Inquiry status updates and sent replies are logged to the audit trail.
- **Unread Counter:** A live badge in the navigation sidebar alerts administrators to new inquiries.

---

## 8. Live Seat Availability Visualizer (`admin/seats.php`)

- **Trip Selection:** Select vehicle, journey date, and departure time.
- **Dynamic Seating Matrix:** Automatically adapts to vehicle capacity (`buses.capacity`) and geometry (`buses.layout`).
- **Occupancy Badge:** Real-time counter of booked vs. available seats (`X of Y Booked (Z Available)`).
- **Seat Lock Joins:** Joins active reservations from `seat_lock` and `booking` to accurately display reserved seats in red and free seats in white.

---

## 9. Administrative Audit Trail (`admin/audit-log.php`)

Restricted exclusively to `super_admin`:
- **Immutable Log:** Records admin actor ID, action (`CREATE`, `UPDATE`, `DELETE`, `CANCEL`, `LOGIN`), target entity, entity ID, JSON state diffs (old vs new values), client IP, and user-agent string.
- **Database Trigger Protection:** MySQL / TiDB database triggers block inline `UPDATE` or `DELETE` statements on `audit_log`.
- **Retention Archiving:** Automated CLI retention script cleans up records older than 365 days:
  ```bash
  php database/purge-audit-log.php 365
  ```

---

## 10. System Diagnostics (`admin/diagnostics.php`)

Restricted exclusively to `super_admin`:
- **Security Headers:** Enforces `Cache-Control: no-store, private` to prevent caching of diagnostic data.
- **Credential Masking:** Masks database credentials and hostnames.
- **Integrity Matrix:**
  - PHP version and required extensions (`mysqli`, `openssl`, `mbstring`, `curl`).
  - Database connectivity, ping latency, and UTF-8 collation.
  - Core tables, columns, foreign keys, and unique indexes (`uq_booking_active_seat`, `uq_admin_email`).
  - Dedicated `seat_lock`, `schema_migrations`, and `migration_steps` verification.
  - Advisory-locked migration runner execution trigger.
