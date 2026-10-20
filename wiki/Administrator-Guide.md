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

### 2.3 Operational Alerting, Today's Departures & Performance Overview (`admin/dashboard.php`)

The dashboard surfaces real-time dispatch alerts, live departures, and privacy-first interactive analytics:
- **Attention-Needed Action Center:** Priority-ranked operational alert strip (`Critical`, `High Priority`, `Attention`) highlighting unanswered customer inquiries, full-capacity departures (`100%`), low-occupancy departures (`<30%`), and trips boarding within 60 minutes.
- **Today's Departures Compact Schedule List:** Scannable operational list showing route corridor (`Origin → Destination`), bus badge, departure time, relative countdown chip (`Departed`, `In Xm`, `In Xh Ym`), progress bar occupancy (`booked/capacity`), and 1-click manifest shortcut.
- **Privacy-First Performance Overview (`assets/js/perf-chart.js`):**
  - **4 KPI Metric Cards:** Confirmed Revenue, Confirmed Bookings, Cancellation Rate, and Average Booking Value with period-over-period delta badges (`▲` / `▼`).
  - **Interactive Chart.js Visuals:** Dual-axis **Booking & Revenue Trends** combo chart (bar + line with `Daily` / `Weekly` toggle) and **Booking Status** donut chart (`Confirmed`, `Pending`, `Cancelled`, `Expired`).
  - **Key Insights Strip:** Automatically computes the Peak Revenue Day, Top Performing Route, and Average Lead Time.
  - **Strict PII Zero-Leakage Guarantee:** All chart datasets and insights are aggregated server-side from numerical/date metrics only; zero passenger names, emails, phones, or PNRs are ever serialized to client scripts.

### 2.4 Data Export & Breakdown Table

- **Daily Breakdown Table:** Continuous daily sequence showing reservations, cancellations, and daily revenue with previous-period delta context.
- **Safe CSV Export (`?export=daily_csv`):** Memory-efficient streaming export with formula-injection mitigation (`escape_csv_formula`) and administrative audit logging.

---

## 3. Fleet & Bus Operations (`admin/buses.php`)

- **Register New Bus:** Click **+ Register New Bus** to specify vehicle identifier (e.g., `PB-02-1044`), passenger seat capacity (`10` to `60`), and layout configuration (`2+2`, `2+1`, `1+2`, `1+1`).
- **Soft-Delete Archiving:** Vehicles can be archived safely (`archived_at`). Active and Archived tabs allow one-click archiving and instant restoration.
- **Referential Integrity on Deletion:** Vehicles with active routes or active upcoming bookings cannot be deleted or archived.
- **Audit Logging:** Vehicle creation, update, archive, and restoration events are automatically recorded in the audit trail.
- **Option 2 Indian License Plate Card Architecture (Mobile, ≤ 768px):** On mobile viewports, fleet vehicle rows transform into authentic Indian HSRP Commercial License Plate Hero cards (`.buses-table tr`):
  - Top utility bar with vehicle ID (`#1`) on the left and quick action buttons (`Edit`, `Archive`) on the right.
  - High-contrast yellow HSRP license plate hero badge (`[IND] HR55D5576`) styled with authentic blue IND tag and monospace registration numerals.
  - 2-column specification footer separating Seating Capacity on the left from Seating Layout on the right.
- **Desktop Table Layout Integrity (≥ 769px):** Enforced via `@media (min-width: 769px)` rules in `assets/css/admin.css` ensuring standard `display: table-row` and `display: table-cell` alignment across all 5 columns (`#`, `Bus Identifier / License`, `Seating Capacity`, `Seating Layout`, `Actions`). Uses dedicated `fleet-bus-row` classes to eliminate selector collisions with passenger cabin seat grids.

---

## 4. Route Scheduling (`admin/routes.php`)

- **Operational KPI Summary Cards:** Displays Active Schedules, Connected Cities, Assigned Fleet Vehicles, and Average Ticket Fare at the top of the workspace.
- **Smart Multi-Criteria Filter Toolbar:** Instant client-side filtering by city or bus number, status filter pills (`All Routes`, `With Upcoming Bookings`, `No Bookings`), and multi-field sorting (`Route ID`, `Fare: Low → High`, `Fare: High → Low`, `Departure Time`).
- **Create Route Schedule & Swap Controls:** Specify origin (`city1`), destination (`city2`) with a 1-click bidirectional swap button (`⇄`), assigned bus (`busno`), departure time (`time`), and ticket fare (`price`).
- **Corridor Cards & Quick Manifest Access:** Route rows render visual corridor badges (`Origin → Destination`), bus layout tags, booking status pills, and a direct 1-click **Manifest** shortcut button (`admin/manifest.php`).
- **Conflict Prevention & Active Booking Lockdown:** Assigning the same vehicle to overlapping departures is blocked, and routes with active upcoming bookings are locked from destructive schedule edits.
- **Soft-Delete Archiving:** Routes can be archived and restored without losing historical trip linkages.

---

## 5. Booking Operations & Management (`admin/bookings.php`)

- **Dispatch Booking:** Dispatchers can create bookings for walk-in passengers directly through the modal dialog.
- **Concurrency & Atomic Seat Locks:** Every booking atomically claims the seat in `seat_lock` (`PRIMARY KEY (bus_id, travel_date, seat_no)`). Concurrent duplicate requests are rolled back safely.
- **Cryptographic PNR Generation:** Generates an unguessable 10-character hex PNR (`random_bytes(5)`).
- **Multi-Criteria Search & Filters:** Search by PNR, passenger name, contact phone, or route with date range filtering.
- **Bulk Actions:** Multi-select checkboxes for batch cancellations and selected-row CSV exports.
- **Streaming CSV Exports:** Memory-efficient CSV exports with spreadsheet formula injection protection (`=`, `+`, `-`, `@`).
- **Option 2 Boarding Pass Card Architecture (Mobile, ≤ 768px):** Mobile booking rows transform into airline-style digital boarding pass cards with 24px vertical separation, unboxed borderless wrapper, status-coded colored left borders (Confirmed emerald, Pending amber, Cancelled rose), monospace PNR badge, route corridor hero, and touch-friendly actions.
- **Pure-PHP PDF Bookings Export (`?export=pdf`):** In addition to streaming CSV downloads, administrators can export print-ready PDF reports with full passenger roll call, route information, and collected tariffs.

---

## 6. Passenger Trip Manifest & Full-Platform Pure-PHP PDF Generator (`admin/manifest.php`)

A dedicated operational workspace for bus conductors and dispatchers:
- **Corridor & Departure Filter:** Filter passenger manifests by vehicle, departure date, and route.
- **Pure-PHP Native PDF 1.4 Generator (`?export=pdf`):** Powered by `generate_manifest_pdf_doc()` in `includes/helpers.php` with zero external libraries required. Generates a structured A4 portrait PDF featuring:
  - Branded navy header bar with trip date, bus number, and route corridor.
  - 4-column summary KPI strip (Vehicle, Capacity, Confirmed Passengers, Collected Revenue).
  - Complete seat-by-seat manifest table (Seat #, Status Badge, Passenger Name, Contact Phone, PNR, Fare, Conductor Checkoff Box) with automatic multi-page pagination and driver/conductor signature blocks.
- **Mobile-First PDF Experience (`.manifest-mobile-card`):** On mobile viewports (`<768px`), displays a focused trip summary card with a prominent 1-tap **Download Manifest PDF** button instead of cramped horizontal tables.
- **Hold Segregation:** Passenger manifest displays confirmed travelers; pending holds are segregated into an alert banner.
- **Print-Ready Layout & CSV Export:** Full `@media print` styling and streaming CSV export (`?export=csv`) for desktop dispatchers.

---

## 7. Customer Inquiry Inbox (`admin/queries.php`)

- **Inquiry Lifecycle:** Manage customer messages across three statuses: `new`, `replied`, and `closed`.
- **Modal Email Replies:** Dispatch email responses directly from the dashboard using RFC 5321 SMTP relay.
- **Audit Integration:** Inquiry status updates and sent replies are logged to the audit trail.
- **Unread Counter:** A live badge in the navigation sidebar alerts administrators to new inquiries.
- **Option 2 Support Ticket Card Architecture (Mobile, ≤ 768px):** Mobile inquiry rows transform into executive support ticket cards with status-coded left borders (`new` red, `replied` emerald, `closed` slate), sender profile badge, line-clamped inquiry message preview, and stacked touch-friendly actions.
- **Inquiry Message Clamping & Modal Inspection:** Long message previews are clamped to 3 lines on desktop and mobile, with a "Read More →" trigger that launches the comprehensive `#viewQueryModal` dialog.
- **Clean Light-Themed Modal Header:** Modal dialog features a clean, high-contrast light header with dark text (`#1e293b`) and subtle bottom divider, with a 1-click transition button to the `#singleReplyModal` for drafting email replies.

---

## 8. Customer Accounts Management (`admin/customers.php`)

- **Customer Directory & Security Status:** Comprehensive directory of registered passenger profiles with full name, email, phone number, security status, and residential address.
- **Option 2 Passenger Profile Card Architecture (Mobile, ≤ 768px):** Mobile customer rows transform into clean passenger profile cards with active/archived status badges, direct email links, and touch-friendly administrative actions.
- **Pure-PHP PDF Customer Directory Export (`?export=pdf`):** Generates structured, branded PDF reports of registered travelers alongside streaming CSV export.
- **Soft-Delete Archiving & Restoration:** Customer accounts can be safely archived and restored without losing historical booking linkages.

---

## 9. Live Seat Availability Visualizer (`admin/seats.php`)

- **Trip Selection:** Select vehicle, journey date, and departure time.
- **Dynamic Seating Matrix:** Automatically adapts to vehicle capacity (`buses.capacity`) and geometry (`buses.layout`).
- **Occupancy Badge:** Real-time counter of booked vs. available seats (`X of Y Booked (Z Available)`).
- **Seat Lock Joins:** Joins active reservations from `seat_lock` and `booking` to accurately display reserved seats in red and free seats in white.

---

## 10. Administrative Audit Trail (`admin/audit-log.php`)

Restricted exclusively to `super_admin`:
- **Immutable Log:** Records admin actor ID, action (`CREATE`, `UPDATE`, `DELETE`, `CANCEL`, `LOGIN`), target entity, entity ID, JSON state diffs (old vs new values), client IP, and user-agent string.
- **Database Trigger Protection:** MySQL / TiDB database triggers block inline `UPDATE` or `DELETE` statements on `audit_log`.
- **Retention Archiving:** Automated CLI retention script cleans up records older than 365 days:
  ```bash
  php database/purge-audit-log.php 365
  ```

---

## 11. System Diagnostics (`admin/diagnostics.php`)

Restricted exclusively to `super_admin`:
- **Security Headers:** Enforces `Cache-Control: no-store, private` to prevent caching of diagnostic data.
- **Credential Masking:** Masks database credentials and hostnames.
- **Integrity Matrix:**
  - PHP version and required extensions (`mysqli`, `openssl`, `mbstring`, `curl`).
  - Database connectivity, ping latency, and UTF-8 collation.
  - Core tables, columns, foreign keys, and unique indexes (`uq_booking_active_seat`, `uq_admin_email`).
  - Dedicated `seat_lock`, `schema_migrations`, and `migration_steps` verification.
  - Advisory-locked migration runner execution trigger.
